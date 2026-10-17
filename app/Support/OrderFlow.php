<?php

namespace App\Support;

use App\Mail\OrderFilesMail;
use App\Mail\OrderPlacedMail;
use App\Mail\OrderUpdateMail;
use App\Models\Admin;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

/**
 * What staff can do to an order (module 8). Every rule lives here so the order page, the delivery board, the payments
 * table and the bulk actions all behave the same way:
 *
 *  - A cancelled or refunded order is closed: it cannot be changed again.
 *  - A paid order cannot just be cancelled: it is refunded (the payment goes back and the order closes as Refunded).
 *  - An order is completed only once it is paid. Cash on pickup is paid by the act of collecting it.
 *  - Confirming a payment confirms the order (PDF-only orders complete at once: the files are in the customer's library).
 *  - Printed stock is taken once, when the order is first confirmed, and put back if the order is cancelled or refunded.
 *  - Every step writes a line to the order history (the same history the customer sees in their portal).
 */
class OrderFlow
{
    /** Statuses that count as "being worked": printed stock has been taken for them. */
    private const ACTIVE = ['confirmed', 'processing', 'ready', 'shipped', 'completed'];

    private const HISTORY = [
        'pending'    => 'Back to pending',
        'confirmed'  => 'Order confirmed',
        'processing' => 'Being prepared',
        'ready'      => 'Ready for pickup',
        'shipped'    => 'Handed to courier',
        'cancelled'  => 'Order cancelled',
    ];

    public static function isClosed(Order $order): bool
    {
        return in_array($order->status, ['cancelled', 'refunded'], true);
    }

    /** Move an order to a new status. Returns true when something changed. */
    public static function changeStatus(Order $order, string $status, ?Admin $admin, bool $notify = true): bool
    {
        if (! in_array($status, Order::STATUSES, true)) {
            throw new OrderRuleException('That is not an order status.');
        }
        if ($status === $order->status) {
            return false;
        }
        if (self::isClosed($order)) {
            throw new OrderRuleException('This order is ' . $order->status . ', so it can no longer be changed.');
        }
        if ($status === 'refunded') {
            return self::refund($order, $admin, null, $notify);
        }

        $order->loadMissing('items');

        if ($status === 'cancelled' && $order->payment_status === 'paid') {
            throw new OrderRuleException('This order is paid. Refund the payment first (choose Refunded).');
        }
        if (in_array($status, ['processing', 'ready', 'shipped'], true) && ! $order->hasPhysicalItems()) {
            throw new OrderRuleException('This order only has PDF files, so there is nothing to prepare or deliver.');
        }

        $by = $admin ? 'By ' . $admin->name : null;

        DB::transaction(function () use ($order, $status, $admin, $by) {
            if ($status === 'completed' && $order->payment_status !== 'paid') {
                if ($order->payment_method === 'cash' && $order->payment_status === 'pending') {
                    self::receivePayment($order, 'Cash collected', $admin);
                } else {
                    throw new OrderRuleException('Confirm the payment before completing the order.');
                }
            }

            if (in_array($status, self::ACTIVE, true)) {
                self::takeStock($order, $admin);
            }

            $order->forceFill(['status' => $status])->save();

            if ($status === 'cancelled') {
                self::restoreStock($order, $admin, 'Order cancelled');
            }

            $order->record(self::historyLabel($order, $status), $order->courier_note && $status === 'shipped' ? $order->courier_note : $by);
        });

        if ($notify) {
            [$headline, $body] = self::message($order, $status);
            self::email($order, $headline, $body);
        }

        return true;
    }

    /** Payment arrived (mobile money checked, card paid, cash handed over). */
    public static function markPaid(Order $order, ?Admin $admin, ?string $reference = null, bool $notify = true): bool
    {
        if (self::isClosed($order)) {
            throw new OrderRuleException('This order is ' . $order->status . ', so its payment can no longer be changed.');
        }
        if ($order->payment_status === 'paid') {
            throw new OrderRuleException('This payment is already confirmed.');
        }

        $order->loadMissing('items');

        DB::transaction(function () use ($order, $admin, $reference) {
            if ($reference && ! $order->transaction_ref) {
                $order->transaction_ref = trim($reference);
            }
            self::receivePayment($order, null, $admin);

            if ($order->status === 'pending') {
                $done = ! $order->hasPhysicalItems();
                self::takeStock($order, $admin);
                $order->forceFill(['status' => $done ? 'completed' : 'confirmed'])->save();
                $order->record($done ? 'PDF files added to your library' : 'Order confirmed', $admin ? 'By ' . $admin->name : null);
            }
        });

        if ($notify) {
            $done = $order->status === 'completed' && ! $order->hasPhysicalItems();
            self::email(
                $order,
                'We received your payment',
                $done
                    ? 'Thank you. Your PDF files are ready: sign in and open Downloads and library in your account.'
                    : 'Thank you. Your ' . $order->payment_label . ' payment of UGX ' . number_format($order->total) . ' is confirmed and we are preparing your order.'
            );
        }

        return true;
    }

    /** Give the money back and close the order as Refunded. */
    public static function refund(Order $order, ?Admin $admin, ?string $reason = null, bool $notify = true): bool
    {
        if ($order->status === 'refunded' || $order->payment_status === 'refunded') {
            throw new OrderRuleException('This order has already been refunded.');
        }
        if ($order->payment_status !== 'paid') {
            throw new OrderRuleException('Only a paid order can be refunded.');
        }

        $order->loadMissing('items');

        DB::transaction(function () use ($order, $admin, $reason) {
            $order->forceFill(['payment_status' => 'refunded', 'status' => 'refunded'])->save();
            self::restoreStock($order, $admin, 'Order refunded');
            $order->record('Refund issued', trim('Returned via ' . $order->payment_label . ($reason ? '. ' . $reason : '')));
        });

        if ($notify) {
            self::email($order, 'Your refund is on its way', 'We have refunded UGX ' . number_format($order->total) . ' to you via ' . $order->payment_label . '.');
        }

        return true;
    }

    /**
     * Phone and walk-in orders. $data: name, phone, email?, delivery, address?, pay, note?, lines[] (type, id, fmt, qty).
     * Priced by the same code as the shop, so a price the admin changed is the price on the order.
     */
    public static function createManual(array $data, ?Admin $admin): Order
    {
        $lines = Cart::priceLines($data['lines'] ?? []);
        if (! $lines) {
            throw new OrderRuleException('That item is not on sale. Publish it first, or pick another item.');
        }

        $physical = collect($lines)->contains(fn ($l) => $l['phys']);
        $options  = config('wakata.delivery_all');
        $delivery = $physical ? (string) ($data['delivery'] ?? '') : 'email';

        if ($physical && ! in_array($delivery, ShopSettings::EDITABLE_DELIVERY, true)) {
            throw new OrderRuleException('Choose how the order is delivered.');
        }
        if ($physical && $delivery !== 'pickup' && trim((string) ($data['address'] ?? '')) === '') {
            throw new OrderRuleException('Enter where the order should be delivered.');
        }
        if (! array_key_exists($data['pay'], config('wakata.payments_all'))) {
            throw new OrderRuleException('Choose a payment method.');
        }
        if ($data['pay'] === 'cash' && ! ($physical && $delivery === 'pickup')) {
            throw new OrderRuleException('Cash is only for orders collected from the shop.');
        }

        $subtotal = (int) array_sum(array_column($lines, 'line'));
        $fee      = (int) ($options[$delivery]['fee'] ?? 0);
        $email    = strtolower(trim((string) ($data['email'] ?? '')));
        $customer = $email !== '' ? Customer::where('email', $email)->first() : null;

        $order = DB::transaction(function () use ($data, $lines, $physical, $delivery, $subtotal, $fee, $email, $customer, $admin) {
            $order = Order::create([
                'customer_id'      => $customer?->id,
                'customer_name'    => trim($data['name']),
                'customer_email'   => $email,
                'customer_phone'   => trim($data['phone']),
                'delivery_method'  => $delivery,
                'delivery_address' => $physical && $delivery !== 'pickup' ? trim((string) $data['address']) : null,
                'payment_method'   => $data['pay'],
                'payment_status'   => 'pending',
                'status'           => 'pending',
                'subtotal'         => $subtotal,
                'delivery_fee'     => $fee,
                'total'            => $subtotal + $fee,
                'note'             => ($data['note'] ?? null) ?: null,
                'source'           => 'manual',
            ]);

            $order->forceFill(['number' => 'WK-' . (Order::NUMBER_BASE + $order->id)])->save();
            $order->record('Order placed', 'Phone or walk-in order' . ($admin ? ', entered by ' . $admin->name : ''));

            foreach ($lines as $l) {
                $order->items()->create([
                    'item_type' => $l['type'], 'item_id' => $l['item_id'], 'item_slug' => $l['id'], 'format' => $l['fmt'],
                    'title' => $l['title'], 'level' => $l['level'], 'subject' => $l['subject'], 'year' => $l['year'],
                    'paper_no' => $l['paperNo'], 'unit_price' => $l['price'], 'quantity' => $l['qty'],
                    'line_total' => $l['line'], 'is_physical' => $l['phys'],
                ]);
            }

            return $order;
        });

        if (self::canEmail($order)) {
            try {
                Mail::to($order->customer_email)->send(new OrderPlacedMail($order->load('items')));
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return $order;
    }

    /** "Email customer": a short update with the order's current state. */
    public static function emailUpdate(Order $order): bool
    {
        if (! self::canEmail($order)) {
            throw new OrderRuleException('This order has no email address.');
        }

        [$headline, $body] = self::message($order, $order->status);
        $order->loadMissing('items');
        self::email($order, $headline, $body);

        return true;
    }

    /**
     * Email the order's PDF files again (attached). Only for a paid order that was not cancelled or refunded.
     * Returns how many files were sent. A file that has not been uploaded yet is sent as the sample sheet only when
     * placeholder files are on (the same rule as the customer's own downloads).
     */
    public static function resendFiles(Order $order, ?Admin $admin): int
    {
        if (! self::canEmail($order)) {
            throw new OrderRuleException('This order has no email address.');
        }
        if (! $order->isCounted()) {
            throw new OrderRuleException('Files are sent once the order is paid.');
        }

        $guest = new Customer(['name' => $order->customer_name, 'email' => $order->customer_email]);
        $files = [];

        foreach (Library::forOrder($order->loadMissing('items')) as $file) {
            if (! $file['ready']) {
                continue;
            }
            $path  = Library::storedPath($file);
            $bytes = $path ? Storage::disk('local')->get($path) : Library::placeholder($file, $guest);
            if ($bytes) {
                $files[$file['name']] = $bytes;
            }
        }

        if (! $files) {
            throw new OrderRuleException('There is no file to send yet. Upload the PDF for this paper first.');
        }

        Mail::to($order->customer_email)->send(new OrderFilesMail($order, $files));
        $order->record('PDF files emailed', count($files) . ' file(s)' . ($admin ? ', sent by ' . $admin->name : ''));

        return count($files);
    }

    /** Printed stock for the order's physical lines, taken once. */
    public static function takeStock(Order $order, ?Admin $admin): void
    {
        if ($order->stock_deducted_at) {
            return;
        }

        foreach (self::physicalLines($order) as [$item, $kind, $model]) {
            Inventory::adjust($model, $kind, 'remove', $item->quantity, 'sale', 'Order ' . $order->number, $admin);
        }

        $order->forceFill(['stock_deducted_at' => now()])->save();
    }

    /** Put the printed stock back (cancelled or refunded order), once. */
    public static function restoreStock(Order $order, ?Admin $admin, string $why): void
    {
        if (! $order->stock_deducted_at) {
            return;
        }

        foreach (self::physicalLines($order) as [$item, $kind, $model]) {
            Inventory::adjust($model, $kind, 'add', $item->quantity, 'return', $why . ': ' . $order->number, $admin);
        }

        $order->forceFill(['stock_deducted_at' => null])->save();
    }

    /** @return array<int, array{0:OrderItem,1:string,2:\Illuminate\Database\Eloquent\Model}> */
    private static function physicalLines(Order $order): array
    {
        $out = [];

        foreach ($order->items as $item) {
            if (! $item->is_physical || ! in_array($item->item_type, Inventory::KINDS, true) || ! $item->item_id) {
                continue;
            }
            $model = Inventory::model($item->item_type)::find($item->item_id);
            if ($model) {
                $out[] = [$item, $item->item_type, $model];
            }
        }

        return $out;
    }

    private static function receivePayment(Order $order, ?string $note, ?Admin $admin): void
    {
        $order->forceFill(['payment_status' => 'paid', 'paid_at' => now()])->save();
        $order->record('Payment received', $note ?: $order->payment_label . ($admin ? ', confirmed by ' . $admin->name : ''));
    }

    private static function historyLabel(Order $order, string $status): string
    {
        if ($status === 'completed') {
            return $order->hasPhysicalItems() ? 'Order completed' : 'PDF files added to your library';
        }
        if ($status === 'ready' && $order->delivery_method !== 'pickup') {
            return 'Packed and ready to dispatch';
        }

        return self::HISTORY[$status] ?? ucfirst($status);
    }

    /** @return array{0:string,1:string} headline and body of the customer email */
    private static function message(Order $order, string $status): array
    {
        $physical = $order->hasPhysicalItems();

        return match ($status) {
            'pending'    => ['Your order is waiting', 'We have your order and are waiting for the payment to be confirmed.'],
            'confirmed'  => ['Your order is confirmed', 'We have confirmed your order and will start preparing it.'],
            'processing' => ['Your order is being prepared', 'We are printing and packing your order.'],
            'ready'      => $order->delivery_method === 'pickup'
                ? ['Your order is ready for pickup', 'You can collect it from our shop. Please bring your order number.']
                : ['Your order is packed', 'Your order is packed and will be handed to the courier shortly.'],
            'shipped'    => ['Your order is on its way', 'Your order has been handed to our courier.' . ($order->courier_note ? ' Note: ' . $order->courier_note : '')],
            'completed'  => $physical
                ? ['Your order is complete', 'Thank you for choosing WAKATA.']
                : ['Your PDF files are ready', 'Sign in to your account and open Downloads and library.'],
            'cancelled'  => ['Your order was cancelled', 'Your order has been cancelled. If you did not ask for this, please contact us.'],
            'refunded'   => ['Your refund is on its way', 'We have refunded UGX ' . number_format($order->total) . ' to you via ' . $order->payment_label . '.'],
            default      => ['Update on your order', 'Your order is now ' . $status . '.'],
        };
    }

    private static function canEmail(Order $order): bool
    {
        return Validator::make(['e' => $order->customer_email], ['e' => 'required|email'])->passes();
    }

    /** Emails never block a staff action: a mail outage is logged and the change stands. */
    private static function email(Order $order, string $headline, string $body): void
    {
        if (! self::canEmail($order)) {
            return;
        }

        try {
            Mail::to($order->customer_email)->send(new OrderUpdateMail($order->loadMissing('items'), $headline, $body));
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
