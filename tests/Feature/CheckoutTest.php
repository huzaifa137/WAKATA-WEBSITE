<?php

namespace Tests\Feature;

use App\Mail\OrderPlacedMail;
use App\Mail\OrderReceivedStaffMail;
use App\Models\Book;
use App\Models\Customer;
use App\Models\Order;
use Database\Seeders\CatalogueSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class CheckoutTest extends TestCase
{
    use RefreshDatabase;

    private const BOOK = 'bio-uce-practical';               // UGX 28,000
    private const PAPER = 'pp-uce-agriculture-paper-1-2026'; // print 4,000 / pdf 2,500

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CatalogueSeeder::class);
        Mail::fake();
    }

    private function add(array $items): void
    {
        $this->postJson(route('cart.add'), ['items' => $items])->assertOk();
    }

    private function details(array $over = []): array
    {
        return array_merge([
            'name'     => 'Agnes Nabirye',
            'phone'    => '+256 772 123 456',
            'email'    => 'Agnes@School.ac.ug',
            'delivery' => 'pickup',
            'pay'      => 'mtn',
        ], $over);
    }

    private function checkout(array $over = [])
    {
        return $this->postJson(route('checkout.store'), $this->details($over));
    }

    private function customer(): Customer
    {
        return Customer::create(['name' => 'Joseph Ssemakula', 'email' => 'joseph@example.com', 'phone' => '0772123456', 'password' => 'secret-pass-1']);
    }

    public function test_a_guest_can_place_an_order_for_pickup(): void
    {
        $this->add([['type' => 'book', 'id' => self::BOOK, 'qty' => 2]]);

        $this->checkout()
            ->assertCreated()
            ->assertJson(['ok' => true, 'ref' => 'WK-10001', 'total' => 56000, 'payName' => 'MTN Mobile Money'])
            ->assertJsonPath('cart.count', 0);

        $order = Order::with('items')->firstOrFail();
        $this->assertSame('WK-10001', $order->number);
        $this->assertNull($order->customer_id);
        $this->assertSame('agnes@school.ac.ug', $order->customer_email);
        $this->assertSame(['pending', 'pending', 'pickup', 'mtn'], [$order->status, $order->payment_status, $order->delivery_method, $order->payment_method]);
        $this->assertSame([56000, 0, 56000], [$order->subtotal, $order->delivery_fee, $order->total]);
        $this->assertNull($order->delivery_address);

        $item = $order->items->first();
        $this->assertSame(['book', self::BOOK, 28000, 2, 56000, true, 'uce', 'Biology'], [
            $item->item_type, $item->item_slug, $item->unit_price, $item->quantity, $item->line_total, $item->is_physical, $item->level, $item->subject,
        ]);
        $this->assertNotNull($item->item_id);
    }

    public function test_placing_the_order_empties_the_cart_and_a_second_try_is_refused(): void
    {
        $this->add([['type' => 'book', 'id' => self::BOOK]]);
        $this->checkout()->assertCreated();

        // the same person pressing the button again gets the same order back (a double click), never a second order
        $this->checkout()->assertOk()->assertJson(['ok' => true, 'ref' => 'WK-10001']);
        // anybody else with an empty cart is told so
        $this->checkout(['email' => 'someone.else@school.ac.ug'])->assertStatus(422)->assertJson(['ok' => false, 'message' => 'Your cart is empty.']);
        $this->assertSame(1, Order::count());
    }

    public function test_order_numbers_are_sequential(): void
    {
        foreach ([1, 2, 3] as $n) {
            $this->add([['type' => 'book', 'id' => self::BOOK]]);
            $this->checkout()->assertCreated()->assertJson(['ref' => 'WK-' . (10000 + $n)]);
        }
    }

    public function test_the_delivery_fee_is_set_by_the_server(): void
    {
        $this->add([['type' => 'book', 'id' => self::BOOK]]);

        $this->checkout(['delivery' => 'kampala', 'address' => 'Green Hill SS, Mukono Road', 'fee' => 0, 'total' => 1])
            ->assertCreated()->assertJson(['total' => 36000]);

        $order = Order::first();
        $this->assertSame([28000, 8000, 36000, 'kampala', 'Green Hill SS, Mukono Road'], [$order->subtotal, $order->delivery_fee, $order->total, $order->delivery_method, $order->delivery_address]);

        $this->add([['type' => 'book', 'id' => self::BOOK]]);
        $this->checkout(['delivery' => 'upcountry', 'address' => 'Gulu', 'pay' => 'airtel'])->assertCreated()->assertJson(['total' => 43000]);
    }

    public function test_a_delivery_address_is_required_unless_collecting(): void
    {
        $this->add([['type' => 'book', 'id' => self::BOOK]]);

        $this->checkout(['delivery' => 'kampala'])->assertStatus(422)->assertJsonValidationErrors(['address']);
        $this->checkout(['delivery' => 'kampala', 'address' => '  '])->assertStatus(422)->assertJsonValidationErrors(['address']);
        $this->assertSame(0, Order::count());
    }

    public function test_a_pickup_order_ignores_any_address_sent(): void
    {
        $this->add([['type' => 'book', 'id' => self::BOOK]]);
        $this->checkout(['delivery' => 'pickup', 'address' => 'somewhere'])->assertCreated();

        $this->assertNull(Order::first()->delivery_address);
    }

    public function test_pdf_only_carts_are_emailed_and_need_no_delivery(): void
    {
        $this->add([['type' => 'paper', 'id' => self::PAPER, 'fmt' => 'pdf', 'qty' => 2]]);

        // delivery and address are not even sent
        $this->postJson(route('checkout.store'), ['name' => 'Agnes', 'phone' => '0772123456', 'email' => 'a@b.co', 'pay' => 'card', 'delivery' => 'upcountry'])
            ->assertCreated()->assertJson(['total' => 5000]);

        $order = Order::with('items')->first();
        $this->assertSame(['email', 0, null], [$order->delivery_method, $order->delivery_fee, $order->delivery_address]);
        $this->assertFalse($order->items->first()->is_physical);
        $this->assertSame('pdf', $order->items->first()->format);
        $this->assertSame(['2026', '1'], [(string) $order->items->first()->year, $order->items->first()->paper_no]);
        $this->assertFalse($order->hasPhysicalItems());
    }

    public function test_a_mixed_cart_follows_the_delivery_choice(): void
    {
        $this->add([['type' => 'paper', 'id' => self::PAPER, 'fmt' => 'pdf'], ['type' => 'book', 'id' => self::BOOK]]);

        $this->checkout(['delivery' => 'kampala', 'address' => 'Ntinda'])->assertCreated()->assertJson(['total' => 2500 + 28000 + 8000]);
        $this->assertTrue(Order::first()->hasPhysicalItems());
    }

    public function test_cash_is_only_for_pickup_orders(): void
    {
        $this->add([['type' => 'book', 'id' => self::BOOK]]);

        $this->checkout(['delivery' => 'kampala', 'address' => 'Ntinda', 'pay' => 'cash'])
            ->assertStatus(422)->assertJsonPath('errors.pay.0', 'Cash is only available when you collect from our shop.');
        $this->checkout(['delivery' => 'pickup', 'pay' => 'cash'])->assertCreated();

        $this->add([['type' => 'paper', 'id' => self::PAPER, 'fmt' => 'pdf']]);
        $this->checkout(['pay' => 'cash'])->assertStatus(422)->assertJsonPath('errors.pay.0', 'Cash is only available when you collect from our shop.');
    }

    public function test_details_are_validated(): void
    {
        $this->add([['type' => 'book', 'id' => self::BOOK]]);

        $this->checkout(['name' => '', 'phone' => '12345', 'email' => 'not-an-email', 'pay' => 'bitcoin', 'delivery' => 'drone'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'phone', 'email', 'pay', 'delivery'])
            ->assertJsonPath('errors.phone.0', 'Enter a valid phone number.');

        $this->postJson(route('checkout.store'), [])->assertStatus(422)->assertJsonValidationErrors(['name', 'phone', 'email', 'pay', 'delivery']);
        $this->assertSame(0, Order::count());
    }

    public function test_an_empty_cart_cannot_check_out(): void
    {
        $this->checkout()->assertStatus(422)->assertJson(['message' => 'Your cart is empty.']);
        $this->assertSame(0, Order::count());
    }

    public function test_an_item_unpublished_after_adding_stops_the_order_and_updates_the_cart(): void
    {
        $this->add([['type' => 'book', 'id' => self::BOOK], ['type' => 'paper', 'id' => self::PAPER, 'fmt' => 'pdf']]);
        Book::where('slug', self::BOOK)->update(['status' => 'draft']);

        $this->checkout()->assertStatus(409)->assertJsonPath('cart.removed', 1)->assertJsonPath('cart.count', 1);
        $this->assertSame(0, Order::count());
    }

    public function test_a_signed_in_customer_gets_the_order_on_their_account(): void
    {
        $customer = $this->customer();
        $this->add([['type' => 'book', 'id' => self::BOOK]]);

        $this->withSession(['LoggedCustomer' => $customer->id])->checkout()->assertCreated();

        $this->assertSame($customer->id, Order::first()->customer_id);
        $this->assertTrue($customer->is(Order::first()->customer));
    }

    public function test_a_suspended_customer_is_treated_as_a_guest(): void
    {
        $customer = $this->customer();
        $customer->update(['status' => 'suspended']);
        $this->add([['type' => 'book', 'id' => self::BOOK]]);

        $this->withSession(['LoggedCustomer' => $customer->id])->checkout()->assertCreated();

        $this->assertNull(Order::first()->customer_id);
    }

    public function test_typing_someone_elses_email_does_not_attach_the_order_to_their_account(): void
    {
        $customer = $this->customer();
        $this->add([['type' => 'book', 'id' => self::BOOK]]);

        $this->checkout(['email' => $customer->email])->assertCreated();

        $this->assertNull(Order::first()->customer_id);
    }

    public function test_the_order_keeps_the_prices_and_titles_of_the_day(): void
    {
        $this->add([['type' => 'book', 'id' => self::BOOK]]);
        $this->checkout()->assertCreated();

        Book::where('slug', self::BOOK)->update(['price' => 99999, 'title' => 'Renamed']);

        $item = Order::first()->items->first();
        $this->assertSame(28000, $item->unit_price);
        $this->assertSame('UCE Biology 553/2 & 553/3 Practical Workbook', $item->title);
    }

    public function test_old_orders_survive_a_catalogue_clean_up(): void
    {
        $this->add([['type' => 'book', 'id' => self::BOOK]]);
        $this->checkout()->assertCreated();

        $this->assertDatabaseCount('order_items', 1);
        Book::where('slug', self::BOOK)->delete();
        $this->assertDatabaseCount('order_items', 1);
    }

    public function test_deleting_an_order_removes_its_items_and_deleting_a_customer_keeps_the_order(): void
    {
        $customer = $this->customer();
        $this->add([['type' => 'book', 'id' => self::BOOK]]);
        $this->withSession(['LoggedCustomer' => $customer->id])->checkout()->assertCreated();

        $customer->delete();
        $this->assertNull(Order::first()->customer_id);

        Order::first()->delete();
        $this->assertDatabaseCount('order_items', 0);
    }

    public function test_the_customer_gets_a_confirmation_email(): void
    {
        $this->add([['type' => 'book', 'id' => self::BOOK]]);
        $this->checkout()->assertCreated();

        Mail::assertSent(OrderPlacedMail::class, function (OrderPlacedMail $m) {
            return $m->hasTo('agnes@school.ac.ug')
                && str_contains($m->render(), 'WK-10001')
                && str_contains($m->render(), 'UGX 28,000');
        });
        Mail::assertNotSent(OrderReceivedStaffMail::class);
    }

    public function test_staff_get_a_copy_when_an_address_is_configured(): void
    {
        config(['wakata.order_notify' => 'orders@wakatastationers.com']);
        $this->add([['type' => 'book', 'id' => self::BOOK]]);
        $this->checkout()->assertCreated();

        Mail::assertSent(OrderReceivedStaffMail::class, fn ($m) => $m->hasTo('orders@wakatastationers.com'));
    }

    public function test_a_mail_failure_never_loses_the_order(): void
    {
        Mail::shouldReceive('to')->andThrow(new \RuntimeException('SMTP down'));
        $this->add([['type' => 'book', 'id' => self::BOOK]]);

        $this->checkout()->assertCreated();
        $this->assertSame(1, Order::count());
    }

    public function test_checkout_is_rate_limited(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->checkout()->assertStatus(422); // empty cart, but each try counts
        }
        $this->checkout()->assertStatus(429);
    }

    public function test_the_session_survives_sign_in_so_a_guest_cart_is_kept(): void
    {
        $this->add([['type' => 'book', 'id' => self::BOOK, 'qty' => 2]]);
        $this->customer();

        $this->post(route('account.login.submit'), ['email' => 'joseph@example.com', 'password' => 'secret-pass-1'])->assertRedirect();

        $this->assertSame(2, $this->get(route('site.cart'))->viewData('cart')['count']);
    }
}
