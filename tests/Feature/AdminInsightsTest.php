<?php

namespace Tests\Feature;

use App\Mail\GeneralMail;
use App\Models\Admin;
use App\Models\AdminActivity;
use App\Models\Book;
use App\Models\ContactMessage;
use App\Models\Order;
use App\Support\Activity;
use App\Support\AdminInsights;
use App\Support\ReportExport;
use App\Support\ReportPdf;
use Database\Seeders\CatalogueSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/** Module 10: dashboard, reports, team and roles, activity log, and the live notifications of the console shell. */
class AdminInsightsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CatalogueSeeder::class);
        Mail::fake();
        Cache::flush();
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

    private function order(array $over = []): Order
    {
        $book = Book::first();
        $o = Order::create(array_merge([
            'customer_name' => 'Green Hill School', 'customer_email' => 'bursar@greenhill.test', 'customer_phone' => '+256700111222',
            'delivery_method' => 'kampala', 'delivery_address' => 'Plot 1', 'payment_method' => 'mtn', 'payment_status' => 'paid', 'status' => 'completed',
            'subtotal' => $book->price * 2, 'delivery_fee' => 8000, 'total' => $book->price * 2 + 8000, 'paid_at' => now(),
        ], $over));
        $o->forceFill(['number' => 'WK-' . (Order::NUMBER_BASE + $o->id)])->save();
        $o->items()->create(['item_type' => 'book', 'item_id' => $book->id, 'item_slug' => $book->slug, 'title' => $book->title, 'level' => 'uce',
            'unit_price' => $book->price, 'quantity' => 2, 'line_total' => $book->price * 2, 'is_physical' => true]);

        return $o;
    }

    // ------------------------------------------------------------ the pages

    public function test_the_four_pages_need_sign_in_and_open_for_every_role(): void
    {
        foreach (['admin.dashboard', 'admin.reports', 'admin.team', 'admin.activity'] as $name) {
            $this->get(route($name))->assertRedirect(route('admin.login'));
        }

        foreach (['super_admin', 'manager', 'editor', 'support'] as $role) {
            $this->admin($role)->get(route('admin.dashboard'))->assertOk()->assertSee('WK.shell', false)->assertSee('WK.ins', false)->assertSee('WK.sales', false);
            $this->admin($role)->get(route('admin.reports'))->assertOk()->assertSee('WK.ins', false);
            $this->admin($role)->get(route('admin.team'))->assertOk();
            $this->admin($role)->get(route('admin.activity'))->assertOk();
        }
    }

    public function test_the_shell_carries_real_badges_notifications_and_search(): void
    {
        $this->order(['status' => 'pending', 'payment_status' => 'pending']);
        ContactMessage::create(['name' => 'Agnes', 'email' => 'agnes@school.test', 'phone' => '0772123456', 'topic' => 'Order or delivery', 'body' => 'Hello', 'status' => 'new']);

        $shell = AdminInsights::shell($this->me());

        $this->assertSame(1, $shell['badges']['orders']);
        $this->assertSame(1, $shell['badges']['messages']);
        $this->assertContains('Payments waiting to be confirmed', array_column($shell['notifications'], 't'));
        $this->assertNotEmpty($shell['search']['books']);
        $this->assertStringContainsString('WK-', $shell['search']['orders'][0]['t']);

        // the page shows it
        $this->admin()->get(route('admin.orders'))->assertSee('"orders":1', false);
    }

    public function test_the_dashboard_counts_what_needs_attention(): void
    {
        $this->order(['status' => 'pending', 'payment_status' => 'pending']);
        Book::query()->update(['stock' => 0]);

        $a = AdminInsights::dashboard()['attention'];

        $this->assertSame(1, $a['pending']);
        $this->assertSame(1, $a['unpaid']);
        $this->assertGreaterThan(0, $a['low']);
        $this->assertSame(40000000, AdminInsights::dashboard()['goal']);
    }

    // ---------------------------------------------------------- activity log

    public function test_a_change_is_logged_but_looking_and_failures_are_not(): void
    {
        $this->admin()->get(route('admin.subscribers'))->assertOk();
        $this->assertSame(0, AdminActivity::count());

        // refused: not a valid email
        $this->admin()->post(route('admin.subscribers.store'), ['email' => 'nope'])->assertSessionHasErrors('email');
        $this->assertSame(0, AdminActivity::count());

        $this->admin()->post(route('admin.subscribers.store'), ['email' => 'new@school.test']);
        $row = AdminActivity::first();

        $this->assertNotNull($row);
        $this->assertSame('Test super_admin', $row->admin_name);
        $this->assertSame('subscriber', $row->area);
        $this->assertSame('Added subscriber', $row->verb);
        $this->assertSame('new@school.test', $row->object);
    }

    public function test_a_role_without_the_right_leaves_nothing_in_the_log(): void
    {
        $this->admin('editor')->post(route('admin.subscribers.store'), ['email' => 'x@school.test']);

        $this->assertSame(0, AdminActivity::count());
    }

    public function test_signing_in_and_out_are_logged(): void
    {
        $a = $this->me();

        $this->post(route('admin.login.submit'), ['email' => $a->email, 'password' => 'secret-pass-1'])->assertRedirect(route('admin.dashboard'));
        $this->post(route('admin.logout'));

        $this->assertSame(['Signed in', 'Signed out'], AdminActivity::orderBy('id')->pluck('verb')->all());
        $this->assertSame('auth', AdminActivity::first()->area);
    }

    public function test_the_log_page_carries_the_lines_and_the_areas(): void
    {
        Activity::log($this->me(), 'book', 'edit', 'Updated book', 'UCE Biology');

        $this->admin()->get(route('admin.activity'))->assertOk()->assertSee('Updated book', false)->assertSee('UCE Biology', false)->assertSee('Past papers', false);
        $this->assertSame('UCE Biology', AdminInsights::activity()[0]['obj']);
    }

    public function test_the_log_keeps_the_name_after_a_team_member_is_removed(): void
    {
        $gone = Admin::create(['name' => 'Brenda Achieng', 'email' => 'brenda@example.com', 'password' => 'secret-pass-1', 'role' => 'support', 'status' => 'active']);
        Activity::log($gone, 'order', 'check', 'Confirmed payment for order', 'WK-10001');

        $this->admin()->delete(route('admin.team.destroy', $gone))->assertSessionHas('status');

        $this->assertNull(Admin::find($gone->id));
        $this->assertSame('Brenda Achieng', AdminActivity::where('area', 'order')->first()->admin_name);
    }

    // ------------------------------------------------------------------ team

    public function test_the_role_table_matches_what_the_roles_can_do(): void
    {
        $rows = collect(Admin::permissionMatrix())->keyBy('area');

        $this->assertSame(['super_admin' => true, 'manager' => false, 'editor' => false, 'support' => false], $rows['Team and roles']['roles']);
        $this->assertSame(['super_admin' => true, 'manager' => true, 'editor' => false, 'support' => false], $rows['Settings']['roles']);
        $this->assertTrue($rows['Orders, deliveries and customers']['roles']['support']);
        $this->assertFalse($rows['Orders, deliveries and customers']['roles']['editor']);
    }

    public function test_only_a_super_admin_can_invite(): void
    {
        $data = ['name' => 'Sarah Nakato', 'email' => 'sarah@example.com', 'role' => 'support'];

        $this->admin('manager')->post(route('admin.team.store'), $data)->assertSessionHas('fail');
        $this->assertNull(Admin::where('email', 'sarah@example.com')->first());
    }

    public function test_an_invitation_creates_an_invited_account_and_the_link_lets_them_join(): void
    {
        $this->admin()->post(route('admin.team.store'), ['name' => 'Sarah Nakato', 'email' => 'Sarah@Example.com', 'role' => 'support', 'two_factor' => '1'])->assertSessionHas('status');

        $sarah = Admin::where('email', 'sarah@example.com')->first();
        $this->assertSame('invited', $sarah->status);
        $this->assertSame('support', $sarah->role);
        $this->assertTrue($sarah->two_factor_enabled);
        $this->assertSame($this->me()->id, $sarah->invited_by);

        $url = null;
        Mail::assertSent(GeneralMail::class, function (GeneralMail $m) use (&$url) {
            preg_match('#https?://\S+#', $m->body, $found);
            $url = $found[0] ?? null;

            return $m->hasTo('sarah@example.com');
        });
        $this->assertNotNull($url);

        // an invited account cannot sign in before it has a password
        $this->flushSession();
        $this->post(route('admin.login.submit'), ['email' => 'sarah@example.com', 'password' => 'anything'])->assertSessionHasErrors('email');

        parse_str((string) parse_url($url, PHP_URL_QUERY), $q);
        $this->get($url)->assertOk();
        $this->post(route('admin.reset-password.update'), ['token' => $q['token'], 'email' => $q['email'], 'password' => 'brand-new-pass-9', 'confirm' => 'brand-new-pass-9'])
            ->assertRedirect(route('admin.login'));

        $this->assertSame('active', $sarah->fresh()->status);
        $this->assertTrue(AdminActivity::where('admin_name', 'Sarah Nakato')->where('verb', 'like', 'Accepted the invitation%')->exists());
    }

    public function test_the_invitation_link_lasts_days_not_an_hour(): void
    {
        $this->admin()->post(route('admin.team.store'), ['name' => 'Sarah Nakato', 'email' => 'sarah@example.com', 'role' => 'editor']);
        $created = \Illuminate\Support\Facades\DB::table('wk_password_resets')->where('email', 'sarah@example.com')->value('created_at');

        $this->assertTrue(\Illuminate\Support\Carbon::parse($created)->isFuture());
    }

    public function test_an_invitation_needs_a_new_email_and_a_real_role(): void
    {
        $this->admin()->post(route('admin.team.store'), ['name' => 'Copy', 'email' => $this->me()->email, 'role' => 'support'])->assertSessionHasErrors('email');
        $this->admin()->post(route('admin.team.store'), ['name' => 'Odd', 'email' => 'odd@example.com', 'role' => 'accountant'])->assertSessionHasErrors('role');
    }

    public function test_a_team_member_can_be_changed_suspended_and_removed(): void
    {
        $m = $this->me('manager');

        $this->admin()->put(route('admin.team.update', $m), ['name' => 'Ronald S.', 'role' => 'editor', 'status' => 'suspended'])->assertSessionHas('status');
        $m->refresh();
        $this->assertSame(['Ronald S.', 'editor', 'suspended'], [$m->name, $m->role, $m->status]);

        // a suspended person is out at once, even with a live session
        $this->withSession(['LoggedAdmin' => $m->id])->get(route('admin.dashboard'))->assertRedirect(route('admin.login'));

        $this->admin()->delete(route('admin.team.destroy', $m))->assertSessionHas('status');
        $this->assertNull(Admin::find($m->id));
    }

    public function test_nobody_can_lock_themselves_out(): void
    {
        $me = $this->me();

        $this->admin()->put(route('admin.team.update', $me), ['name' => $me->name, 'role' => 'editor', 'status' => 'active'])->assertSessionHas('fail');
        $this->admin()->put(route('admin.team.update', $me), ['name' => $me->name, 'role' => 'super_admin', 'status' => 'suspended'])->assertSessionHas('fail');
        $this->admin()->delete(route('admin.team.destroy', $me))->assertSessionHas('fail');

        $this->assertSame(['super_admin', 'active'], [$me->fresh()->role, $me->fresh()->status]);
    }

    public function test_the_invitation_can_be_sent_again_only_while_invited(): void
    {
        $this->admin()->post(route('admin.team.store'), ['name' => 'Sarah Nakato', 'email' => 'sarah@example.com', 'role' => 'editor']);
        $sarah = Admin::where('email', 'sarah@example.com')->first();

        $this->admin()->post(route('admin.team.invite', $sarah))->assertSessionHas('status');
        Mail::assertSent(GeneralMail::class, 2);

        $sarah->forceFill(['status' => 'active'])->save();
        $this->admin()->post(route('admin.team.invite', $sarah))->assertSessionHas('fail');
    }

    // --------------------------------------------------------------- reports

    public function test_reports_download_as_csv_for_a_manager_but_not_an_editor(): void
    {
        $this->order();

        $csv = $this->admin('manager')->get(route('admin.reports.export', ['report' => 'sales', 'format' => 'csv', 'days' => 30]))->assertOk()->streamedContent();
        $this->assertStringContainsString('Date,Orders', $csv);
        $this->assertStringContainsString(now()->format('Y-m-d'), $csv);

        $this->admin('editor')->get(route('admin.reports.export', ['report' => 'sales', 'format' => 'csv']))->assertRedirect()->assertSessionHas('fail');
        $this->admin('support')->post(route('admin.reports.schedule'), ['frequency' => 'off'])->assertSessionHas('fail');
    }

    public function test_every_report_builds_in_both_formats(): void
    {
        $this->order();

        foreach (ReportExport::KEYS as $key) {
            $csv = $this->admin()->get(route('admin.reports.export', ['report' => $key, 'format' => 'csv', 'days' => 365]))->assertOk()->streamedContent();
            $this->assertNotSame('', trim($csv), $key);

            $pdf = $this->admin()->get(route('admin.reports.export', ['report' => $key, 'format' => 'pdf', 'days' => 365]))->assertOk();
            $this->assertStringStartsWith('%PDF-1.4', $pdf->getContent());
            $this->assertStringEndsWith('%%EOF', $pdf->getContent());
        }
    }

    public function test_an_unknown_report_or_format_is_a_404(): void
    {
        $this->admin()->get('/admin/reports/export/secrets.csv')->assertNotFound();
        $this->admin()->get('/admin/reports/export/sales.xlsx')->assertNotFound();
    }

    public function test_the_downloads_leave_a_line_in_the_log(): void
    {
        $this->admin()->get(route('admin.reports.export', ['report' => 'customers', 'format' => 'csv']))->streamedContent();

        $row = AdminActivity::first();
        $this->assertSame('report', $row->area);
        $this->assertSame('Downloaded report', $row->verb);
        $this->assertSame('customers (csv)', $row->object);
    }

    public function test_a_customer_name_that_looks_like_a_formula_is_neutralised(): void
    {
        $this->order(['customer_name' => '=HYPERLINK("http://evil.test","click")']);

        $csv = $this->admin()->get(route('admin.reports.export', ['report' => 'customers', 'format' => 'csv', 'days' => 30]))->streamedContent();

        $this->assertStringContainsString("'=HYPERLINK", $csv);
        $this->assertStringNotContainsString('"=HYPERLINK', $csv);
    }

    public function test_the_pdf_writer_makes_a_page_per_screenful(): void
    {
        $rows = array_map(fn ($i) => ['Row ' . $i, 'Naïve (text) \\ ok', $i * 1000], range(1, 120));
        $pdf  = ReportPdf::table('WAKATA', 'Test', 'sub', ['Name', 'Note', 'Amount'], $rows, [2 => true]);

        $this->assertSame(3, preg_match_all('#/Type /Page /Parent#', $pdf));
        $this->assertStringContainsString('Page 1 of 3', $pdf);
        $this->assertStringContainsString('Row 120', $pdf);
        $this->assertStringContainsString('Nave \\(text\\)', $pdf);   // non-ASCII dropped, brackets escaped for the PDF string
    }

    public function test_the_emailed_report_is_saved_and_sent(): void
    {
        $this->order();

        $this->admin()->post(route('admin.reports.schedule'), ['frequency' => 'weekly', 'recipients' => ''])->assertSessionHasErrors('recipients');
        $this->admin()->post(route('admin.reports.schedule'), ['frequency' => 'weekly', 'recipients' => 'boss@example.com, not-an-email'])->assertSessionHas('status');

        $this->assertSame(['frequency' => 'weekly', 'recipients' => 'boss@example.com', 'last_sent' => null], ReportExport::schedule());

        $this->artisan('wakata:send-report', ['--force' => true])->assertSuccessful();
        Mail::assertSent(GeneralMail::class, fn (GeneralMail $m) => $m->hasTo('boss@example.com') && str_contains($m->subjectLine, 'weekly report'));
        $this->assertSame(now()->toDateString(), ReportExport::schedule()['last_sent']);
    }

    public function test_the_scheduler_only_sends_when_it_is_due(): void
    {
        ReportExport::saveSchedule('off', 'boss@example.com');
        $this->artisan('wakata:send-report')->assertSuccessful();
        Mail::assertNothingSent();

        // weekly: only on a Monday, and only once a day
        ReportExport::saveSchedule('weekly', 'boss@example.com');
        $this->travelTo(now()->next('Tuesday')->setTime(7, 0));
        $this->artisan('wakata:send-report')->assertSuccessful();
        Mail::assertNothingSent();

        $this->travelTo(now()->next('Monday')->setTime(7, 0));
        $this->artisan('wakata:send-report')->assertSuccessful();
        $this->artisan('wakata:send-report')->assertSuccessful();
        Mail::assertSent(GeneralMail::class, 1);
    }

    public function test_the_summary_adds_up_paid_orders_only(): void
    {
        $a = $this->order();
        $this->order(['status' => 'pending', 'payment_status' => 'pending']);
        $this->order(['status' => 'cancelled', 'payment_status' => 'paid']);

        $s = ReportExport::summary(30);

        $this->assertSame(1, $s['orders']);
        $this->assertSame($a->total, $s['revenue']);
        $this->assertSame($a->total, $s['average']);
        $this->assertCount(1, $s['top']);
    }
}
