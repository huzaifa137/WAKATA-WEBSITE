<?php

namespace Tests\Feature;

use App\Support\OrderRuleException;
use App\Mail\GeneralMail;
use App\Mail\OrderPlacedMail;
use App\Models\Admin;
use App\Models\AdminActivity;
use App\Models\Book;
use App\Models\Customer;
use App\Models\Newsletter;
use App\Models\Order;
use App\Models\Paper;
use App\Models\Subscriber;
use App\Support\Activity;
use App\Support\AdminInsights;
use App\Support\AdminSales;
use App\Support\Cart;
use App\Support\OrderFlow;
use App\Support\SiteSettings;
use Database\Seeders\CatalogueSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/** Module 11: stock rule, order locking, private customer details, background mail, headers, passwords, profile, log upkeep. */
class Patch11Test extends TestCase
{
    use RefreshDatabase;

    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CatalogueSeeder::class);
        Mail::fake();
        Cache::flush();
    }

    protected function tearDown(): void
    {
        File::deleteDirectory(public_path('uploads/avatars'));
        parent::tearDown();
    }

    private function me(string $role = 'super_admin'): Admin
    {
        return Admin::where('email', $role . '@example.com')->first()
            ?? Admin::create(['name' => 'Test ' . $role, 'email' => $role . '@example.com', 'password' => 'secret-pass-1', 'role' => $role, 'status' => 'active']);
    }

    private function admin(string $role = 'super_admin'): self
    {
        return $this->withSession(['LoggedAdmin' => $this->me($role)->id]);
    }

    private function order(array $over = [], ?Book $book = null, int $qty = 2): Order
    {
        $book ??= Book::first();
        $o = Order::create(array_merge([
            'customer_name' => 'Green Hill School', 'customer_email' => 'bursar@greenhill.test', 'customer_phone' => '+256700111222',
            'delivery_method' => 'kampala', 'delivery_address' => 'Plot 1 Secret Road', 'payment_method' => 'mtn', 'payment_status' => 'pending', 'status' => 'pending',
            'subtotal' => $book->price * $qty, 'delivery_fee' => 8000, 'total' => $book->price * $qty + 8000, 'note' => 'Gate code 4411',
        ], $over));
        $o->forceFill(['number' => 'WK-' . (Order::NUMBER_BASE + $o->id)])->save();
        $o->items()->create(['item_type' => 'book', 'item_id' => $book->id, 'item_slug' => $book->slug, 'title' => $book->title, 'level' => 'uce',
            'unit_price' => $book->price, 'quantity' => $qty, 'line_total' => $book->price * $qty, 'is_physical' => true]);

        return $o;
    }

    private function add(string $slug, int $qty = 1, string $type = 'book', ?string $fmt = null)
    {
        return $this->postJson(route('cart.add'), ['items' => [['type' => $type, 'id' => $slug, 'qty' => $qty, 'fmt' => $fmt]]]);
    }

    private function details(array $over = []): array
    {
        return array_merge(['name' => 'Agnes Nabirye', 'phone' => '+256 772 123 456', 'email' => 'Agnes@School.ac.ug', 'delivery' => 'pickup', 'pay' => 'mtn'], $over);
    }

    // ------------------------------------------------------------ stock rule

    public function test_the_stock_rule_is_off_until_it_is_switched_on(): void
    {
        $book = Book::first();
        $book->forceFill(['stock' => 0])->save();

        $this->add($book->slug, 3)->assertOk()->assertJsonPath('count', 3);
        $this->postJson(route('checkout.store'), $this->details())->assertCreated();
    }

    public function test_with_the_rule_on_an_empty_shelf_cannot_be_bought(): void
    {
        SiteSettings::save(['stock_block' => true]);
        $book = Book::first();
        $book->forceFill(['stock' => 0])->save();

        $this->add($book->slug)->assertStatus(422)->assertJsonPath('message', 'Sorry, that item is out of stock right now.');
        $this->assertSame(0, Cart::current()->count());
    }

    public function test_with_the_rule_on_the_cart_holds_no_more_than_the_shelf(): void
    {
        SiteSettings::save(['stock_block' => true]);
        $book = Book::first();
        $book->forceFill(['stock' => 3])->save();

        $this->add($book->slug, 2)->assertOk()->assertJsonPath('limited', 0);
        $this->add($book->slug, 5)->assertOk()->assertJsonPath('count', 3)->assertJsonPath('limited', 1);

        // the shelf runs down while the customer is still shopping
        $book->forceFill(['stock' => 1])->save();
        $this->postJson(route('checkout.store'), $this->details())->assertStatus(409)->assertJsonPath('cart.lines.0.qty', 1);
        $this->postJson(route('checkout.store'), $this->details())->assertCreated();

        $book->forceFill(['stock' => 0])->save();
        $this->add($book->slug)->assertStatus(422);
    }

    public function test_a_line_that_sold_out_is_dropped_and_checkout_asks_again(): void
    {
        $book = Book::first();
        $book->forceFill(['stock' => 5])->save();
        SiteSettings::save(['stock_block' => true]);
        $this->add($book->slug, 2)->assertOk();

        $book->forceFill(['stock' => 0])->save();

        $this->postJson(route('checkout.store'), $this->details())->assertStatus(409)->assertJsonPath('cart.count', 0);
        $this->assertSame(0, Order::count());
    }

    public function test_pdf_papers_and_staff_phone_orders_ignore_the_stock_rule(): void
    {
        SiteSettings::save(['stock_block' => true]);
        Book::query()->update(['stock' => 0]);
        $paper = Paper::where('pdf_on', true)->first();

        $this->add($paper->slug, 1, 'paper', 'pdf')->assertOk();

        $lines = Cart::priceLines([['type' => 'book', 'id' => Book::first()->slug, 'fmt' => null, 'qty' => 4]]);
        $this->assertSame(4, $lines[0]['qty']);
    }

    public function test_the_rule_is_a_switch_in_settings(): void
    {
        $this->admin()->post(route('admin.settings.general'), ['email' => 'info@wakata.test', 'goal' => 1000, 'stock_block' => '1'])->assertSessionHas('status');
        $this->assertTrue((bool) SiteSettings::get('stock_block'));

        $this->admin()->post(route('admin.settings.general'), ['email' => 'info@wakata.test', 'goal' => 1000])->assertSessionHas('status');
        $this->assertFalse((bool) SiteSettings::get('stock_block'));
    }

    // -------------------------------------------------------- order locking

    public function test_two_people_confirming_the_same_order_take_the_stock_once(): void
    {
        $book = Book::first();
        $book->forceFill(['stock' => 50])->save();
        $order = $this->order([], $book, 4);

        // two requests loaded the order while it was still pending
        $first  = Order::find($order->id);
        $second = Order::find($order->id);

        $this->assertTrue(OrderFlow::changeStatus($first, 'confirmed', $this->me(), false));
        $this->assertFalse(OrderFlow::changeStatus($second, 'confirmed', $this->me(), false));

        $this->assertSame(46, $book->fresh()->stock);
        $this->assertSame(1, $order->fresh()->events()->where('label', 'Order confirmed')->count());
    }

    public function test_a_payment_cannot_be_confirmed_twice_from_stale_pages(): void
    {
        $book = Book::first();
        $book->forceFill(['stock' => 50])->save();
        $order = $this->order([], $book, 4);
        $a = Order::find($order->id);
        $b = Order::find($order->id);

        OrderFlow::markPaid($a, $this->me(), null, false);

        $this->expectException(OrderRuleException::class);
        try {
            OrderFlow::markPaid($b, $this->me(), null, false);
        } finally {
            $this->assertSame(46, $book->fresh()->stock);
        }
    }

    public function test_a_second_refund_from_a_stale_page_is_refused(): void
    {
        $order = $this->order(['payment_status' => 'paid', 'status' => 'confirmed']);
        $a = Order::find($order->id);
        $b = Order::find($order->id);

        OrderFlow::refund($a, $this->me(), 'test', false);

        $this->expectException(OrderRuleException::class);
        OrderFlow::refund($b, $this->me(), 'again', false);
    }

    // ------------------------------------------------- private customer data

    public function test_the_content_editor_sees_the_numbers_but_not_the_customers(): void
    {
        $this->order(['payment_status' => 'paid', 'status' => 'completed', 'paid_at' => now(), 'transaction_ref' => 'MP123456']);

        $p = AdminSales::payload($this->me('editor'));

        $this->assertTrue($p['redacted']);
        $this->assertSame('Green H.', $p['orders'][0]['name']);
        $this->assertSame(['', '', '', '', ''], [$p['orders'][0]['email'], $p['orders'][0]['phone'], $p['orders'][0]['address'], $p['orders'][0]['note'], $p['orders'][0]['txn']]);
        $this->assertSame(['', ''], [$p['customers'][0]['email'], $p['customers'][0]['phone']]);
        $this->assertGreaterThan(0, $p['orders'][0]['total']);

        foreach (['support', 'manager', 'super_admin'] as $role) {
            $full = AdminSales::payload($this->me($role));
            $this->assertFalse($full['redacted'], $role);
            $this->assertSame('bursar@greenhill.test', $full['orders'][0]['email'], $role);
        }
    }

    public function test_no_customer_detail_reaches_the_editors_pages(): void
    {
        $this->order();

        foreach (['admin.dashboard', 'admin.orders', 'admin.customers', 'admin.reports'] as $page) {
            $html = $this->admin('editor')->get(route($page))->assertOk()->getContent();
            foreach (['bursar@greenhill.test', '+256700111222', 'Plot 1 Secret Road', 'Gate code 4411', 'Green Hill School'] as $secret) {
                $this->assertStringNotContainsString($secret, $html, "$page leaks $secret");
            }
        }

        $this->assertStringContainsString('bursar@greenhill.test', $this->admin('support')->get(route('admin.orders'))->getContent());
    }

    public function test_the_shell_search_and_bell_do_not_name_customers_to_the_editor(): void
    {
        Customer::create(['name' => 'Mrs Teacher', 'email' => 't@test.test', 'password' => 'x', 'type' => 'Teacher']);
        $this->order();

        $editor = AdminInsights::shell($this->me('editor'));
        $this->assertSame([], $editor['search']['customers']);
        $this->assertStringNotContainsString('Green Hill', json_encode($editor));

        $support = AdminInsights::shell($this->me('support'));
        $this->assertNotEmpty($support['search']['customers']);
        $this->assertStringContainsString('Green Hill', json_encode($support));
    }

    // ------------------------------------------------------------------ mail

    public function test_order_emails_still_go_out_with_the_order(): void
    {
        $book = Book::first();
        $this->add($book->slug)->assertOk();
        $this->postJson(route('checkout.store'), $this->details())->assertCreated();

        Mail::assertSent(OrderPlacedMail::class, fn ($m) => $m->hasTo('agnes@school.ac.ug'));
    }

    public function test_a_small_newsletter_goes_out_at_once_with_counts(): void
    {
        foreach (range(1, 3) as $i) {
            Subscriber::create(['email' => "p$i@school.test", 'status' => 'active', 'source' => 'Test']);
        }

        $this->admin()->post(route('admin.subscribers.send'), ['subject' => 'News', 'body' => 'Hello everyone', 'audience' => 'all'])
            ->assertSessionHas('status', 'Newsletter sent to 3 people.');

        $n = Newsletter::first();
        $this->assertSame(['sent', 3, 3, 0], [$n->status, $n->total, $n->sent_count, $n->failed_count]);
    }

    public function test_a_big_newsletter_is_sent_after_the_page_and_counted_when_done(): void
    {
        foreach (range(1, 30) as $i) {
            Subscriber::create(['email' => "p$i@school.test", 'status' => 'active', 'source' => 'Test']);
        }

        $this->admin()->post(route('admin.subscribers.send'), ['subject' => 'News', 'body' => 'Hello everyone', 'audience' => 'all'])
            ->assertSessionHas('status', 'The newsletter is being sent to 30 people. The history shows the final count in a few minutes.');

        Mail::assertSent(GeneralMail::class, 30);
        $n = Newsletter::first();
        $this->assertSame(['sent', 30, 30], [$n->status, $n->total, $n->sent_count]);
    }

    public function test_team_members_can_opt_in_to_the_order_alert(): void
    {
        SiteSettings::save(['alert_recipients' => 'shop@wakata.test']);
        $this->assertSame(['shop@wakata.test'], SiteSettings::alertRecipients('orders'));

        $this->admin('manager')->post(route('admin.profile.notifications'), ['alert_orders' => '1'])->assertSessionHas('status');

        $this->assertEqualsCanonicalizing(['shop@wakata.test', 'manager@example.com'], SiteSettings::alertRecipients('orders'));
        $this->assertSame(['shop@wakata.test'], SiteSettings::alertRecipients('messages'));

        SiteSettings::save(['alert_orders' => false]);
        $this->assertSame([], SiteSettings::alertRecipients('orders'));
    }

    // -------------------------------------------------------- headers & errors

    public function test_every_page_carries_the_browser_protections(): void
    {
        $r = $this->get(route('site.home'))->assertOk();

        $r->assertHeader('X-Frame-Options', 'DENY')->assertHeader('X-Content-Type-Options', 'nosniff')->assertHeader('Referrer-Policy');
        $csp = $r->headers->get('Content-Security-Policy');
        $this->assertStringContainsString("frame-ancestors 'none'", $csp);
        $this->assertStringContainsString("object-src 'none'", $csp);
        $this->assertStringContainsString('https://fonts.googleapis.com', $csp);
        $this->assertNull($r->headers->get('Strict-Transport-Security'));

        $this->get('https://localhost/')->assertHeader('Strict-Transport-Security');
    }

    public function test_the_policy_can_be_set_to_report_only_or_off(): void
    {
        config(['security.csp' => 'report']);
        $r = $this->get(route('site.home'));
        $this->assertNull($r->headers->get('Content-Security-Policy'));
        $this->assertNotNull($r->headers->get('Content-Security-Policy-Report-Only'));

        config(['security.csp' => 'off']);
        $r = $this->get(route('site.home'));
        $this->assertNull($r->headers->get('Content-Security-Policy'));
        $this->assertNull($r->headers->get('Content-Security-Policy-Report-Only'));
    }

    public function test_admin_and_account_pages_are_never_cached(): void
    {
        $this->assertStringContainsString('no-store', $this->admin()->get(route('admin.dashboard'))->headers->get('Cache-Control'));
        $this->assertStringContainsString('no-store', $this->get(route('account.login'))->headers->get('Cache-Control'));
        $this->assertStringNotContainsString('no-store', (string) $this->get(route('site.home'))->headers->get('Cache-Control'));
    }

    public function test_error_pages_are_branded(): void
    {
        foreach ([403 => 'do not have access', 429 => 'Too many requests', 500 => 'Something went wrong', 503 => 'right back'] as $code => $text) {
            Route::get('/_fail/' . $code, fn () => abort($code));
            $this->get('/_fail/' . $code)->assertStatus($code)->assertSee($text)->assertSee('WAKATA');
        }
    }

    // -------------------------------------------------------------- passwords

    public function test_team_passwords_need_ten_characters_with_a_letter_and_a_number(): void
    {
        $me = $this->me();
        $post = fn (string $pw) => $this->admin()->post(route('admin.profile.password'), ['current_password' => 'secret-pass-1', 'new_password' => $pw, 'new_password_confirmation' => $pw]);

        $post('short1')->assertSessionHasErrors('new_password');
        $post('onlylettersonly')->assertSessionHasErrors('new_password');
        $post('1234567890123')->assertSessionHasErrors('new_password');
        $post('long-enough-9')->assertSessionHas('status');
        $this->assertTrue(Hash::check('long-enough-9', $me->fresh()->password));
    }

    // --------------------------------------------------------- double checkout

    public function test_a_double_click_on_place_order_makes_one_order(): void
    {
        $this->add(Book::first()->slug, 2)->assertOk();

        $first  = $this->postJson(route('checkout.store'), $this->details())->assertCreated();
        $second = $this->postJson(route('checkout.store'), $this->details())->assertOk();

        $this->assertSame($first->json('ref'), $second->json('ref'));
        $this->assertSame(1, Order::count());
        Mail::assertSent(OrderPlacedMail::class, 1);

        // somebody else with an empty cart still gets the normal answer
        $this->postJson(route('checkout.store'), $this->details(['email' => 'other@school.test']))->assertStatus(422);
    }

    public function test_an_order_being_placed_blocks_a_second_at_the_same_moment(): void
    {
        $this->add(Book::first()->slug)->assertOk();

        // the first request still holds this browser's checkout lock
        $busy = \Mockery::mock(\Illuminate\Contracts\Cache\Lock::class);
        $busy->shouldReceive('get')->once()->andReturnFalse();
        $busy->shouldReceive('release')->never();
        Cache::partialMock()->shouldReceive('lock')->once()->andReturn($busy);

        $this->postJson(route('checkout.store'), $this->details())->assertStatus(409)->assertJsonPath('message', 'Your order is already being placed. Please wait a moment.');
        $this->assertSame(0, Order::count());
    }

    // -------------------------------------------------------------- the log

    public function test_failed_and_blocked_sign_ins_are_logged_without_flooding(): void
    {
        $me = $this->me();

        $this->post(route('admin.login.submit'), ['email' => $me->email, 'password' => 'wrong'])->assertSessionHasErrors('email');
        $this->post(route('admin.login.submit'), ['email' => 'nobody@example.com', 'password' => 'wrong'])->assertSessionHasErrors('email');

        $rows = AdminActivity::orderBy('id')->get();
        $this->assertSame(['Failed sign-in', 'Failed sign-in'], $rows->pluck('verb')->all());
        $this->assertSame([$me->name, 'Unknown visitor'], $rows->pluck('admin_name')->all());
        $this->assertSame('nobody@example.com', $rows[1]->object);

        foreach (range(1, 40) as $i) {
            $this->post(route('admin.login.submit'), ['email' => "x$i@example.com", 'password' => 'wrong']);
        }
        $this->assertLessThanOrEqual(31, AdminActivity::where('area', 'auth')->count());   // 30 an hour per address, plus the first which share the counter
    }

    public function test_old_log_lines_are_pruned(): void
    {
        $me = $this->me();
        Activity::log($me, 'book', 'edit', 'Updated book', 'Old');
        Activity::log($me, 'book', 'edit', 'Updated book', 'New');
        AdminActivity::where('object', 'Old')->update(['created_at' => now()->subDays(400)]);

        $this->artisan('wakata:prune-activity')->assertSuccessful();
        $this->assertSame(['New'], AdminActivity::pluck('object')->all());

        $this->artisan('wakata:prune-activity', ['--days' => 5])->assertFailed();
        $this->assertSame(1, AdminActivity::count());
    }

    // ------------------------------------------------------------ my profile

    public function test_everyone_on_the_team_can_open_their_profile(): void
    {
        foreach (['super_admin', 'manager', 'editor', 'support'] as $role) {
            $this->admin($role)->get(route('admin.profile'))->assertOk()->assertSee('Test ' . $role, false)->assertDontSee('Design preview');
        }
    }

    public function test_name_and_phone_are_saved_but_the_email_is_not_editable(): void
    {
        $me = $this->me('editor');

        $this->admin('editor')->post(route('admin.profile.update'), ['name' => 'Grace N.', 'phone' => '+256 700 123 456', 'email' => 'hacker@example.com'])->assertSessionHas('status');
        $me->refresh();
        $this->assertSame(['Grace N.', '+256 700 123 456', 'editor@example.com'], [$me->name, $me->phone, $me->email]);

        $this->admin('editor')->post(route('admin.profile.update'), ['name' => '', 'phone' => 'abc'])->assertSessionHasErrors(['name', 'phone']);
    }

    public function test_a_profile_photo_can_be_added_changed_and_removed(): void
    {
        $me = $this->me();
        $png = fn () => UploadedFile::fake()->createWithContent('me.png', base64_decode(self::PNG));

        $this->admin()->post(route('admin.profile.photo'), ['photo' => $png()])->assertSessionHas('status');
        $first = $me->fresh()->avatar_path;
        $this->assertFileExists(public_path($first));
        $this->admin()->get(route('admin.dashboard'))->assertSee($first, false);

        $this->admin()->post(route('admin.profile.photo'), ['photo' => $png()]);
        $this->assertFileDoesNotExist(public_path($first));            // the old picture is deleted
        $second = $me->fresh()->avatar_path;
        $this->assertFileExists(public_path($second));

        $this->admin()->delete(route('admin.profile.photo.remove'))->assertSessionHas('status');
        $this->assertNull($me->fresh()->avatar_path);
        $this->assertFileDoesNotExist(public_path($second));
    }

    public function test_only_pictures_can_be_uploaded_as_a_photo(): void
    {
        // Real files (not the test fakes, which trust the file name): the check must look at what is inside
        $real = function (string $name, string $content) {
            $path = tempnam(sys_get_temp_dir(), 'wk');
            file_put_contents($path, $content);

            return new UploadedFile($path, $name, null, null, true);
        };
        $php  = $real('shell.png', '<?php echo "owned"; ?>');
        $big  = $real('big.png', base64_decode(self::PNG) . str_repeat('0', 1200 * 1024));
        $text = $real('notes.txt', 'hello');

        foreach (['php' => $php, 'big' => $big, 'text' => $text] as $name => $bad) {
            $r = $this->admin()->post(route('admin.profile.photo'), ['photo' => $bad]);
            $this->assertTrue($r->getSession()->has('errors'), "$name was accepted");
        }
        $this->assertNull($this->me()->avatar_path);
    }

    public function test_changing_the_password_needs_the_current_one_and_signs_out_other_devices(): void
    {
        $me = $this->me();
        $me->forceFill(['remember_token' => 'device-token'])->save();

        $this->admin()->post(route('admin.profile.password'), ['current_password' => 'wrong-one', 'new_password' => 'brand-new-pass-9', 'new_password_confirmation' => 'brand-new-pass-9'])
            ->assertSessionHasErrors('current_password');
        $this->assertTrue(Hash::check('secret-pass-1', $me->fresh()->password));

        $this->admin()->post(route('admin.profile.password'), ['current_password' => 'secret-pass-1', 'new_password' => 'brand-new-pass-9', 'new_password_confirmation' => 'different-9-pass'])
            ->assertSessionHasErrors('new_password');

        $this->admin()->post(route('admin.profile.password'), ['current_password' => 'secret-pass-1', 'new_password' => 'brand-new-pass-9', 'new_password_confirmation' => 'brand-new-pass-9'])
            ->assertSessionHas('status');

        $me->refresh();
        $this->assertTrue(Hash::check('brand-new-pass-9', $me->password));
        $this->assertNull($me->remember_token);
        $this->assertTrue(AdminActivity::where('verb', 'Changed their password')->exists());
    }

    public function test_guessing_the_current_password_is_rate_limited(): void
    {
        foreach (range(1, 5) as $i) {
            $this->admin()->post(route('admin.profile.password'), ['current_password' => 'nope-' . $i, 'new_password' => 'brand-new-pass-9', 'new_password_confirmation' => 'brand-new-pass-9'])
                ->assertSessionHasErrors('current_password');
        }

        $this->admin()->post(route('admin.profile.password'), ['current_password' => 'secret-pass-1', 'new_password' => 'brand-new-pass-9', 'new_password_confirmation' => 'brand-new-pass-9'])
            ->assertSessionHas('fail');
        $this->assertTrue(Hash::check('secret-pass-1', $this->me()->password));
    }
}
