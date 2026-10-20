<?php

namespace Tests\Feature;

use App\Mail\OrderFilesMail;
use App\Mail\OrderPlacedMail;
use App\Mail\OrderUpdateMail;
use App\Models\Admin;
use App\Models\Book;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Paper;
use App\Models\StockAdjustment;
use App\Support\Library;
use Database\Seeders\CatalogueSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Module 8: admin sales (orders, payments, deliveries, customers). */
class AdminSalesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CatalogueSeeder::class);
        Storage::fake('local');
        Mail::fake();
    }

    private function admin(string $role = 'super_admin'): self
    {
        $a = Admin::where('email', $role . '@example.com')->first()
            ?? Admin::create(['name' => 'Test ' . $role, 'email' => $role . '@example.com', 'password' => 'secret-pass-1', 'role' => $role, 'status' => 'active']);

        return $this->withSession(['LoggedAdmin' => $a->id]);
    }

    private function order(array $over = [], ?Book $book = null, int $qty = 2, string $fmt = null): Order
    {
        $book ??= Book::first();
        $o = Order::create(array_merge([
            'customer_name' => 'Green Hill School', 'customer_email' => 'bursar@greenhill.test', 'customer_phone' => '+256700111222',
            'delivery_method' => 'kampala', 'delivery_address' => 'Plot 1', 'payment_method' => 'mtn', 'payment_status' => 'pending', 'status' => 'pending',
            'subtotal' => $book->price * $qty, 'delivery_fee' => 8000, 'total' => $book->price * $qty + 8000,
        ], $over));
        $o->forceFill(['number' => 'WK-' . (Order::NUMBER_BASE + $o->id)])->save();
        $o->items()->create(['item_type' => 'book', 'item_id' => $book->id, 'item_slug' => $book->slug, 'title' => $book->title, 'level' => 'uce',
            'unit_price' => $book->price, 'quantity' => $qty, 'line_total' => $book->price * $qty, 'is_physical' => true]);

        return $o;
    }

    private function pdfOrder(array $over = []): Order
    {
        $p = Paper::first();
        $p->forceFill(['pdf_on' => true, 'pdf_price' => 2000])->save();
        $o = Order::create(array_merge([
            'customer_name' => 'Agnes', 'customer_email' => 'agnes@test.test', 'customer_phone' => '0700000000',
            'delivery_method' => 'email', 'payment_method' => 'mtn', 'payment_status' => 'pending', 'status' => 'pending',
            'subtotal' => 2000, 'delivery_fee' => 0, 'total' => 2000,
        ], $over));
        $o->forceFill(['number' => 'WK-' . (Order::NUMBER_BASE + $o->id)])->save();
        $o->items()->create(['item_type' => 'paper', 'item_id' => $p->id, 'item_slug' => $p->slug, 'format' => 'pdf', 'title' => $p->title . ' ' . $p->year,
            'level' => 'uce', 'subject' => 'Biology', 'year' => $p->year, 'unit_price' => 2000, 'quantity' => 1, 'line_total' => 2000, 'is_physical' => false]);

        return $o;
    }

    public function test_sales_pages_need_sign_in_and_carry_the_real_data(): void
    {
        foreach (['orders', 'order', 'payments', 'deliveries', 'customers'] as $r) {
            $this->get(route('admin.' . $r))->assertRedirect(route('admin.login'));
        }

        $o = $this->order();
        $this->admin('editor')->get(route('admin.orders'))->assertOk()->assertSee('WK.sales', false)->assertSee($o->number);
        foreach (['order', 'payments', 'deliveries', 'customers'] as $r) {
            $this->admin()->get(route('admin.' . $r))->assertOk()->assertSee('WK.sales', false);
        }
    }

    public function test_guests_are_grouped_and_registered_customers_listed(): void
    {
        Customer::create(['name' => 'Mrs Teacher', 'email' => 't@test.test', 'password' => 'x', 'type' => 'Teacher']);
        $this->order(); $this->order(); $this->order(['customer_email' => 'other@x.test', 'customer_name' => 'Other']);

        $payload = \App\Support\AdminSales::payload(Admin::create(['name' => 'Boss', 'email' => 'boss@example.com', 'password' => 'secret-pass-1', 'role' => 'super_admin', 'status' => 'active']));
        $guests  = array_filter($payload['customers'], fn ($c) => $c['type'] === 'Guest');

        $this->assertCount(2, $guests);
        $this->assertCount(3, $payload['orders']);
        $this->assertContains('Mrs Teacher', array_column($payload['customers'], 'name'));
    }

    public function test_manual_order_is_priced_by_the_server_and_emailed(): void
    {
        $book = Book::first();
        $r = $this->admin()->postJson(route('admin.orders.store'), [
            'name' => 'Walk In', 'phone' => '0700123456', 'email' => 'walk@in.test', 'type' => 'book', 'item' => $book->slug, 'qty' => 10,
            'delivery' => 'kampala', 'address' => 'Ntinda', 'pay' => 'mtn', 'total' => 1,
        ])->assertCreated();

        $o = Order::where('number', $r->json('order.id'))->first();
        $this->assertSame($book->price * 10 + 8000, $o->total);
        $this->assertSame('manual', $o->source);
        $this->assertSame('pending', $o->status);
        $this->assertSame('Order placed', $o->events->first()->label);
        Mail::assertSent(OrderPlacedMail::class);
    }

    public function test_manual_order_rules(): void
    {
        $book = Book::first();
        $base = ['name' => 'A', 'phone' => '1', 'type' => 'book', 'item' => $book->slug, 'qty' => 1, 'delivery' => 'kampala', 'address' => 'X', 'pay' => 'mtn'];

        $this->admin()->postJson(route('admin.orders.store'), array_merge($base, ['pay' => 'cash']))->assertStatus(422)->assertJsonPath('message', 'Cash is only for orders collected from the shop.');
        $this->admin()->postJson(route('admin.orders.store'), array_merge($base, ['address' => '']))->assertStatus(422);
        $this->admin()->postJson(route('admin.orders.store'), array_merge($base, ['item' => 'nope']))->assertStatus(422);
        $this->admin()->postJson(route('admin.orders.store'), array_merge($base, ['qty' => 0]))->assertStatus(422);
        $this->admin()->postJson(route('admin.orders.store'), array_merge($base, ['delivery' => 'pickup', 'pay' => 'cash', 'email' => null]))->assertCreated();
        Mail::assertNotSent(OrderPlacedMail::class);
        $this->assertSame(0, Order::latest('id')->first()->delivery_fee);
    }

    public function test_confirming_takes_stock_once_and_cancelling_gives_it_back(): void
    {
        $book = Book::first(); $book->forceFill(['stock' => 50])->save();
        $o = $this->order([], $book, 4);

        $this->admin()->postJson(route('admin.orders.status', $o->number), ['status' => 'confirmed'])->assertOk();
        $this->admin()->postJson(route('admin.orders.status', $o->number), ['status' => 'processing'])->assertOk();
        $this->assertSame(46, $book->fresh()->stock);
        $this->assertSame(1, StockAdjustment::where('reason', 'sale')->count());
        $this->assertSame('Being prepared', $o->fresh()->events->last()->label);

        $this->admin()->postJson(route('admin.orders.status', $o->number), ['status' => 'cancelled'])->assertOk();
        $this->assertSame(50, $book->fresh()->stock);
        $this->assertSame('cancelled', $o->fresh()->status);
    }

    public function test_closed_orders_and_paid_orders_follow_the_rules(): void
    {
        $o = $this->order();
        $this->admin()->postJson(route('admin.payments.verify', $o->number))->assertOk()->assertJsonPath('order.pay', 'paid')->assertJsonPath('order.status', 'confirmed');
        $this->admin()->postJson(route('admin.orders.status', $o->number), ['status' => 'cancelled'])->assertStatus(422);
        $this->admin()->postJson(route('admin.payments.verify', $o->number))->assertStatus(422);

        $this->admin()->postJson(route('admin.payments.refund', $o->number), ['reason' => 'Wrong item'])->assertOk()->assertJsonPath('order.status', 'refunded')->assertJsonPath('order.pay', 'refunded');
        $this->admin()->postJson(route('admin.orders.status', $o->number), ['status' => 'confirmed'])->assertStatus(422);
        $this->admin()->postJson(route('admin.payments.refund', $o->number))->assertStatus(422);
    }

    public function test_completing_needs_payment_except_cash_which_is_paid_by_collecting(): void
    {
        $mtn = $this->order();
        $this->admin()->postJson(route('admin.orders.status', $mtn->number), ['status' => 'completed'])->assertStatus(422)->assertJsonPath('message', 'Confirm the payment before completing the order.');
        $this->assertSame('pending', $mtn->fresh()->status);

        $cash = $this->order(['payment_method' => 'cash', 'delivery_method' => 'pickup', 'delivery_fee' => 0]);
        $this->admin()->postJson(route('admin.orders.status', $cash->number), ['status' => 'completed'])->assertOk();
        $this->assertSame('paid', $cash->fresh()->payment_status);
        $this->assertNotNull($cash->fresh()->paid_at);
    }

    public function test_pdf_only_orders_complete_on_payment_and_unlock_the_library(): void
    {
        $c = Customer::create(['name' => 'Agnes', 'email' => 'agnes@test.test', 'password' => 'x', 'type' => 'Teacher']);
        $o = $this->pdfOrder(['customer_id' => $c->id]);

        $this->admin()->postJson(route('admin.orders.status', $o->number), ['status' => 'processing'])->assertStatus(422);
        $this->assertCount(0, Library::forCustomer($c));

        $this->admin()->postJson(route('admin.payments.verify', $o->number), ['reference' => 'MTN123'])->assertOk()->assertJsonPath('order.status', 'completed');
        $this->assertSame('MTN123', $o->fresh()->transaction_ref);
        $this->assertCount(1, Library::forCustomer($c));
    }

    public function test_customer_email_follows_the_notify_switch(): void
    {
        $o = $this->order();
        $this->admin()->postJson(route('admin.orders.status', $o->number), ['status' => 'confirmed', 'notify' => false])->assertOk();
        Mail::assertNotSent(OrderUpdateMail::class);

        $this->admin()->postJson(route('admin.orders.status', $o->number), ['status' => 'processing', 'notify' => true])->assertOk();
        Mail::assertSent(OrderUpdateMail::class, fn ($m) => $m->hasTo('bursar@greenhill.test'));

        $this->admin()->postJson(route('admin.orders.email', $o->number))->assertOk();
        Mail::assertSent(OrderUpdateMail::class, 2);
    }

    public function test_bulk_changes_skip_orders_that_cannot_take_them(): void
    {
        $a = $this->order(); $b = $this->order(); $this->admin()->postJson(route('admin.orders.status', $b->number), ['status' => 'cancelled']);

        $r = $this->admin()->postJson(route('admin.orders.bulk'), ['ids' => [$a->number, $b->number], 'action' => 'status', 'status' => 'confirmed'])->assertOk();
        $this->assertCount(1, $r->json('skipped'));
        $this->assertSame('confirmed', $a->fresh()->status);

        $this->admin()->postJson(route('admin.orders.bulk'), ['ids' => [$b->number], 'action' => 'status', 'status' => 'confirmed'])->assertStatus(422);
        $this->admin()->postJson(route('admin.orders.bulk'), ['ids' => [$a->number], 'action' => 'email'])->assertOk();
    }

    public function test_notes_courier_and_customers(): void
    {
        $o = $this->order();
        $this->admin()->postJson(route('admin.orders.note', $o->number), ['admin_note' => 'Call bursar'])->assertOk();
        $this->admin()->postJson(route('admin.orders.courier', $o->number), ['courier_note' => 'Boda Ssebo 0700'])->assertOk();
        $this->assertSame('Call bursar', $o->fresh()->admin_note);
        $this->assertSame('Boda Ssebo 0700', $o->fresh()->courier_note);

        $r = $this->admin()->postJson(route('admin.customers.store'), ['name' => 'Lakeview Primary', 'type' => 'School', 'email' => 'Info@Lakeview.test', 'district' => 'Wakiso', 'contact' => 'Bursar'])->assertCreated();
        $c = Customer::where('email', 'info@lakeview.test')->first();
        $this->assertSame('Wakiso', $c->district);
        $this->assertSame('Lakeview Primary', $c->school);
        $this->admin()->postJson(route('admin.customers.store'), ['name' => 'Dup', 'type' => 'School', 'email' => 'info@lakeview.test'])->assertStatus(422);
        $this->admin()->postJson(route('admin.customers.note', $c->id), ['admin_note' => 'Prefers Fridays'])->assertOk();
        $this->assertSame('Prefers Fridays', $c->fresh()->admin_note);
    }

    public function test_roles(): void
    {
        $o = $this->order();
        $this->admin('editor')->postJson(route('admin.orders.status', $o->number), ['status' => 'confirmed'])->assertForbidden();
        $this->admin('editor')->postJson(route('admin.customers.store'), ['name' => 'X', 'type' => 'School', 'email' => 'x@x.test'])->assertForbidden();
        $this->admin('support')->postJson(route('admin.orders.status', $o->number), ['status' => 'confirmed'])->assertOk();
        $this->admin('support')->postJson(route('admin.payments.verify', $o->number))->assertForbidden();
        $this->admin('support')->putJson(route('admin.payments.methods'), ['mtn' => true, 'airtel' => true, 'card' => true, 'cash' => true])->assertForbidden();
        $this->admin('manager')->postJson(route('admin.payments.verify', $o->number))->assertOk();
    }

    public function test_switched_off_payment_methods_leave_the_shop_and_checkout(): void
    {
        $this->admin()->putJson(route('admin.payments.methods'), ['mtn' => true, 'airtel' => false, 'card' => true, 'cash' => true])->assertOk();

        $this->get(route('site.cart'))->assertOk()->assertDontSee('value="airtel"', false)->assertSee('value="mtn"', false);

        $book = Book::first();
        $this->postJson(route('cart.add'), ['items' => [['type' => 'book', 'id' => $book->slug, 'qty' => 1]]])->assertOk();
        $this->postJson(route('checkout.store'), ['name' => 'A', 'phone' => '0700000000', 'email' => 'a@a.test', 'delivery' => 'pickup', 'pay' => 'airtel'])->assertStatus(422)->assertJsonValidationErrors(['pay']);
        $this->postJson(route('checkout.store'), ['name' => 'A', 'phone' => '0700000000', 'email' => 'a@a.test', 'delivery' => 'pickup', 'pay' => 'mtn'])->assertCreated();

        $old = $this->order(['payment_method' => 'airtel']);
        $this->assertSame('Airtel Money', $old->payment_label);

        $this->admin()->putJson(route('admin.payments.methods'), ['mtn' => false, 'airtel' => false, 'card' => false, 'cash' => false])->assertStatus(422);
        $this->admin()->putJson(route('admin.payments.methods'), ['mtn' => true, 'airtel' => true, 'card' => true, 'cash' => true])->assertOk();
        $this->get(route('site.cart'))->assertSee('value="airtel"', false);
    }

    public function test_delivery_options_are_editable_and_used_by_checkout(): void
    {
        $rows = fn (array $over = []) => array_replace_recursive([
            'pickup'    => ['label' => 'Pick up', 'eta' => 'Same day', 'fee' => 0, 'enabled' => true],
            'kampala'   => ['label' => 'Kampala', 'eta' => '1 day', 'fee' => 10000, 'enabled' => true],
            'upcountry' => ['label' => 'Upcountry', 'eta' => '3 days', 'fee' => 20000, 'enabled' => false],
        ], $over);

        $this->admin()->putJson(route('admin.deliveries.options'), $rows())->assertOk();

        $this->get(route('site.cart'))->assertSee('Same day')->assertDontSee('Upcountry courier');
        $book = Book::first();
        $this->postJson(route('cart.add'), ['items' => [['type' => 'book', 'id' => $book->slug, 'qty' => 1]]])->assertOk();
        $r = $this->postJson(route('checkout.store'), ['name' => 'A', 'phone' => '0700000000', 'email' => 'a@a.test', 'delivery' => 'kampala', 'address' => 'X', 'pay' => 'mtn'])->assertCreated();
        $this->assertSame($book->price + 10000, $r->json('total'));

        $this->postJson(route('cart.add'), ['items' => [['type' => 'book', 'id' => $book->slug, 'qty' => 1]]])->assertOk();
        $this->postJson(route('checkout.store'), ['name' => 'A', 'phone' => '0700000000', 'email' => 'a@a.test', 'delivery' => 'upcountry', 'address' => 'X', 'pay' => 'mtn'])->assertStatus(422)->assertJsonValidationErrors(['delivery']);

        $this->admin()->putJson(route('admin.deliveries.options'), $rows(['pickup' => ['enabled' => false], 'kampala' => ['enabled' => false]]))->assertStatus(422);
        $this->admin()->putJson(route('admin.deliveries.options'), $rows(['kampala' => ['fee' => -5]]))->assertStatus(422);
        $this->admin()->putJson(route('admin.deliveries.options'), $rows(['kampala' => ['label' => '']]))->assertStatus(422);
    }

    public function test_resending_pdf_files(): void
    {
        config(['wakata.placeholder_pdfs' => false]);
        $o = $this->pdfOrder();

        $this->admin()->postJson(route('admin.orders.files', $o->number))->assertStatus(422);   // unpaid
        $this->admin()->postJson(route('admin.payments.verify', $o->number))->assertOk();
        $this->admin()->postJson(route('admin.orders.files', $o->number))->assertStatus(422)->assertJsonPath('message', 'There is no file to send yet. Upload the PDF for this paper first.');

        Storage::disk('local')->put('papers/test.pdf', '%PDF-1.4 test');
        Paper::first()->forceFill(['pdf_path' => 'papers/test.pdf'])->save();
        $this->admin()->postJson(route('admin.orders.files', $o->number))->assertOk();
        Mail::assertSent(OrderFilesMail::class, fn ($m) => $m->hasTo('agnes@test.test') && count($m->files) === 1);
        $this->assertSame('PDF files emailed', $o->fresh()->events->last()->label);
    }

    public function test_reconcile_counts_waiting_payments_and_returned_stock_is_not_received(): void
    {
        $this->order(); $this->order(['payment_method' => 'cash']);
        $this->admin()->postJson(route('admin.payments.reconcile'))->assertOk()->assertJsonPath('count', 1);

        $book = Book::first(); $book->forceFill(['stock' => 10])->save();
        $o = $this->order([], $book, 3);
        $this->admin()->postJson(route('admin.orders.status', $o->number), ['status' => 'confirmed']);
        $this->admin()->postJson(route('admin.orders.status', $o->number), ['status' => 'cancelled']);
        $this->assertSame(0, array_sum(\App\Support\AdminCatalogue::movement()['received']));
    }
}
