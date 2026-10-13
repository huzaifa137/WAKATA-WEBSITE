<?php

namespace Database\Seeders;

use App\Models\Book;
use App\Models\Customer;
use App\Models\ExamSet;
use App\Models\HolidayPackage;
use App\Models\Order;
use App\Models\Paper;
use App\Models\SupportThread;
use Illuminate\Database\Seeder;

/**
 * Gives the demo customer (Joseph Ssemakula) real rows to look at in the portal: nine orders, two addresses and a
 * support thread with a reply. Local and test use only, never production, and it does nothing if he already has orders.
 */
class DemoPortalSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            return;
        }

        $c = Customer::where('email', 'joseph.ssemakula@greenhill.ac.ug')->first();
        if (! $c || $c->orders()->exists() || ! Book::exists()) {
            return;
        }

        // [days ago, status, payment status, method, delivery, [items]]  item = [type, slug-or-[subject,no,year], format, qty]
        $specs = [
            [0.3, 'processing', 'paid', 'mtn', 'kampala', [['book', 'chem-uce-practical', null, 2], ['paper', ['Chemistry', 2, 2026], 'print', 12]]],
            [2, 'completed', 'paid', 'airtel', 'email', [['paper', ['Biology', 1, 2026], 'pdf', 1], ['paper', ['Biology', 2, 2026], 'pdf', 1], ['paper', ['Physics', 1, 2026], 'pdf', 1]]],
            [6, 'shipped', 'paid', 'mtn', 'kampala', [['set', 'set-uce-2026', null, 1]]],
            [19, 'completed', 'paid', 'cash', 'pickup', [['pack', 'hp-s4', null, 1]]],
            [41, 'completed', 'paid', 'mtn', 'email', [['paper', ['Mathematics', 1, 2025], 'pdf', 1], ['paper', ['Mathematics', 2, 2025], 'pdf', 1]]],
            [75, 'completed', 'paid', 'card', 'upcountry', [['book', 'math-uce-revision', null, 3]]],
            [120, 'completed', 'paid', 'airtel', 'pickup', [['paper', ['English', 1, 2026], 'print', 30]]],
            [210, 'cancelled', 'failed', 'mtn', 'kampala', [['set', 'set-uce-maths-5y', null, 1]]],
            [300, 'completed', 'paid', 'mtn', 'email', [['book', 'ent-uce', null, 1]]],
        ];

        foreach (array_reverse($specs) as [$days, $status, $pay, $method, $delivery, $items]) {
            $lines = collect($items)->map(fn ($i) => $this->line($i))->filter()->values();
            if ($lines->isEmpty()) {
                continue;
            }

            $sub = $lines->sum('line_total');
            $fee = (int) config("wakata.delivery.$delivery.fee", 0);
            $at  = now()->subMinutes((int) ($days * 1440));

            $order = Order::create([
                'customer_id' => $c->id, 'customer_name' => $c->name, 'customer_email' => $c->email, 'customer_phone' => (string) $c->phone,
                'delivery_method' => $delivery, 'delivery_address' => in_array($delivery, ['kampala', 'upcountry'], true) ? 'Green Hill Secondary School, Mukono Road, Mukono' : null,
                'payment_method' => $method, 'payment_status' => $pay, 'status' => $status,
                'subtotal' => $sub, 'delivery_fee' => $fee, 'total' => $sub + $fee,
            ]);
            $order->forceFill(['number' => 'WK-' . (Order::NUMBER_BASE + $order->id), 'created_at' => $at, 'updated_at' => $at])->save();
            $order->items()->createMany($lines->all());

            $order->record('Order placed', 'Online checkout', $at);
            if ($pay === 'paid') {
                $order->record('Payment received', config("wakata.payments.$method"), $at->copy()->addMinutes(4));
            }
            if (in_array($status, ['processing', 'shipped', 'completed'], true)) {
                $order->record($status === 'processing' ? 'Being prepared' : ($delivery === 'email' ? 'PDF files added to your library' : 'Order completed'), null, $at->copy()->addHours(5));
            }
            if ($status === 'cancelled') {
                $order->record('Order cancelled', 'Payment not completed', $at->copy()->addHours(8));
            }
        }

        $c->addresses()->createMany([
            ['label' => 'School', 'recipient' => $c->name, 'line' => 'Green Hill Secondary School, Mukono Road', 'district' => 'Mukono', 'phone' => $c->phone, 'is_default' => true],
            ['label' => 'Home', 'recipient' => $c->name, 'line' => 'Plot 14, Ntinda Estate', 'district' => 'Kampala', 'phone' => $c->phone, 'is_default' => false],
        ]);

        $thread = SupportThread::create([
            'customer_id' => $c->id, 'order_id' => Order::where('customer_id', $c->id)->orderBy('id')->skip(2)->value('id'),
            'topic' => 'Order or delivery', 'subject' => 'Can the invoice carry our school stamp?', 'status' => 'open',
            'customer_read_at' => now()->subHours(30), 'last_message_at' => now()->subHours(5),
        ]);
        $thread->forceFill(['ref' => 'T-' . (SupportThread::NUMBER_BASE + $thread->id)])->save();
        $thread->messages()->forceCreate(['support_thread_id' => $thread->id, 'updated_at' => now()->subHours(30), 'sender' => 'customer', 'body' => 'Good afternoon. Please can the invoice carry our school stamp? Our bursar needs it for accounts.', 'created_at' => now()->subHours(30)]);
        $thread->messages()->forceCreate(['support_thread_id' => $thread->id, 'updated_at' => now()->subHours(5), 'sender' => 'staff', 'body' => 'Hello, yes. Every invoice now has a stamp area. Open Invoices and receipts, choose the order and print it, then stamp it at school.', 'created_at' => now()->subHours(5)]);
    }

    /** One order line from a catalogue product (prices and titles as they are today). */
    private function line(array $i): ?array
    {
        [$type, $ref, $fmt, $qty] = $i;

        if ($type === 'paper') {
            [$subject, $no, $year] = $ref;
            $p = Paper::published()->with(['level', 'subject'])
                ->whereHas('subject', fn ($q) => $q->where('name', $subject))->where('paper', 'Paper ' . $no)->where('year', $year)
                ->orderBy('level_id')->first();
            if (! $p) {
                return null;
            }
            $price = $fmt === 'pdf' ? $p->pdf_price : $p->print_price;

            return ['item_type' => 'paper', 'item_id' => $p->id, 'item_slug' => $p->slug, 'format' => $fmt, 'title' => $p->title . ' ' . $p->year,
                'level' => $p->level->code, 'subject' => $p->subject->name, 'year' => $p->year, 'paper_no' => (string) $no,
                'unit_price' => $price, 'quantity' => $qty, 'line_total' => $price * $qty, 'is_physical' => $fmt !== 'pdf'];
        }

        $m = match ($type) {
            'book' => Book::published()->with(['level', 'subject'])->where('slug', $ref)->first(),
            'set'  => ExamSet::published()->with('level')->where('slug', $ref)->first(),
            'pack' => HolidayPackage::published()->with('level')->where('slug', $ref)->first(),
        };
        if (! $m) {
            return null;
        }

        return ['item_type' => $type, 'item_id' => $m->id, 'item_slug' => $m->slug, 'format' => null, 'title' => $m->title ?? $m->name,
            'level' => $m->level->code, 'subject' => $type === 'book' ? $m->subject->name : null, 'year' => null, 'paper_no' => null,
            'unit_price' => $m->price, 'quantity' => $qty, 'line_total' => $m->price * $qty, 'is_physical' => true];
    }
}
