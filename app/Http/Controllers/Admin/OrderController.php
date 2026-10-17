<?php

namespace App\Http\Controllers\Admin;

use App\Models\Order;
use App\Support\AdminSales;
use App\Support\OrderFlow;
use App\Support\OrderRuleException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Orders and the order page (module 8). Looking is open to every signed-in admin; changing needs admin.can:sales. */
class OrderController extends SalesController
{
    public function index()
    {
        return view('admin.orders');
    }

    public function show()
    {
        return view('admin.order');
    }

    /** "Create an order manually": phone and walk-in orders, priced like online ones. */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'     => ['required', 'string', 'max:120'],
            'phone'    => ['required', 'string', 'max:30'],
            'email'    => ['nullable', 'email', 'max:190'],
            'type'     => ['required', Rule::in(['book', 'paper', 'set', 'pack'])],
            'item'     => ['required', 'string', 'max:120'],
            'fmt'      => ['nullable', Rule::in(['print', 'pdf'])],
            'qty'      => ['required', 'integer', 'min:1', 'max:5000'],
            'delivery' => ['nullable', Rule::in(['pickup', 'kampala', 'upcountry'])],
            'address'  => ['nullable', 'string', 'max:500'],
            'pay'      => ['required', Rule::in(array_keys(config('wakata.payments_all')))],
            'note'     => ['nullable', 'string', 'max:500'],
        ], [
            'name.required'  => 'Enter the customer name.',
            'phone.required' => 'Enter a phone number.',
            'email.email'    => 'Enter a valid email address, or leave it empty.',
            'item.required'  => 'Choose an item.',
            'qty.min'        => 'The quantity must be at least 1.',
            'qty.integer'    => 'The quantity must be a whole number.',
        ]);

        try {
            $order = OrderFlow::createManual([
                'name' => $data['name'], 'phone' => $data['phone'], 'email' => $data['email'] ?? null,
                'delivery' => $data['delivery'] ?? null, 'address' => $data['address'] ?? null, 'pay' => $data['pay'], 'note' => $data['note'] ?? null,
                'lines' => [['type' => $data['type'], 'id' => $data['item'], 'fmt' => $data['fmt'] ?? null, 'qty' => $data['qty']]],
            ], $this->admin($request));
        } catch (OrderRuleException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json(['ok' => true, 'message' => 'Order ' . $order->number . ' created.', 'order' => AdminSales::order($order)], 201);
    }

    public function status(Request $request, Order $order): JsonResponse
    {
        $data = $request->validate([
            'status'       => ['required', Rule::in(Order::STATUSES)],
            'notify'       => ['nullable', 'boolean'],
            'courier_note' => ['nullable', 'string', 'max:255'],
        ], ['status.in' => 'That is not an order status.']);

        if (array_key_exists('courier_note', $data) && $data['courier_note'] !== null) {
            $order->forceFill(['courier_note' => trim($data['courier_note']) ?: null])->save();
        }

        return $this->act($order, 'Status changed.', fn () => OrderFlow::changeStatus($order, $data['status'], $this->admin($request), (bool) ($data['notify'] ?? true)));
    }

    /** Several orders at once from the table: change the status or email each customer. Orders that cannot take it are skipped and listed. */
    public function bulk(Request $request): JsonResponse
    {
        $data = $request->validate([
            'ids'    => ['required', 'array', 'min:1', 'max:200'],
            'ids.*'  => ['string', 'max:20'],
            'action' => ['required', Rule::in(['status', 'email'])],
            'status' => ['required_if:action,status', 'nullable', Rule::in(Order::STATUSES)],
        ], ['ids.required' => 'Select at least one order.']);

        $admin   = $this->admin($request);
        $changed = [];
        $skipped = [];

        foreach (Order::whereIn('number', $data['ids'])->get() as $order) {
            try {
                $done = $data['action'] === 'status'
                    ? OrderFlow::changeStatus($order, $data['status'], $admin)
                    : OrderFlow::emailUpdate($order);

                $changed[] = AdminSales::order($order->fresh());
            } catch (OrderRuleException $e) {
                $skipped[] = ['id' => $order->number, 'reason' => $e->getMessage()];
            }
        }

        $verb    = $data['action'] === 'status' ? 'updated' : 'emailed';
        $message = count($changed) . ' order(s) ' . $verb . ($skipped ? ', ' . count($skipped) . ' skipped (' . $skipped[0]['id'] . ': ' . $skipped[0]['reason'] . ')' : '') . '.';

        return response()->json(['ok' => (bool) $changed, 'message' => $message, 'orders' => $changed, 'skipped' => $skipped], $changed ? 200 : 422);
    }

    public function note(Request $request, Order $order): JsonResponse
    {
        $data = $request->validate(['admin_note' => ['nullable', 'string', 'max:2000']]);
        $order->forceFill(['admin_note' => trim((string) ($data['admin_note'] ?? '')) ?: null])->save();

        return response()->json(['ok' => true, 'message' => 'Note saved.', 'order' => AdminSales::order($order->fresh())]);
    }

    /** Courier, rider or tracking note on the order page. */
    public function courier(Request $request, Order $order): JsonResponse
    {
        $data = $request->validate(['courier_note' => ['nullable', 'string', 'max:255']]);
        $order->forceFill(['courier_note' => trim((string) ($data['courier_note'] ?? '')) ?: null])->save();

        return response()->json(['ok' => true, 'message' => 'Delivery note saved.', 'order' => AdminSales::order($order->fresh())]);
    }

    public function email(Order $order): JsonResponse
    {
        return $this->act($order, 'Email sent to ' . $order->customer_email . '.', fn () => OrderFlow::emailUpdate($order));
    }

    /** Resend the PDF files of a paid order by email (attached). */
    public function files(Request $request, Order $order): JsonResponse
    {
        return $this->act($order, 'PDF files sent to ' . $order->customer_email . '.', fn () => OrderFlow::resendFiles($order, $this->admin($request)));
    }
}
