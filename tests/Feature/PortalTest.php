<?php

namespace Tests\Feature;

use App\Mail\CustomerOtpMail;
use App\Mail\LibraryFileMail;
use App\Models\Customer;
use App\Models\FileDownload;
use App\Models\Order;
use App\Models\Paper;
use App\Models\SavedItem;
use App\Models\SupportThread;
use Database\Seeders\CatalogueSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Module 6: the customer portal (orders, library and downloads, invoices, saved items, addresses, support, profile). */
class PortalTest extends TestCase
{
    use RefreshDatabase;

    private Customer $me;
    private Customer $other;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CatalogueSeeder::class);
        Mail::fake();
        config(['wakata.placeholder_pdfs' => true]);

        $this->me    = Customer::create(['name' => 'Joseph Ssemakula', 'email' => 'joseph@example.com', 'phone' => '0772123456', 'password' => 'secret-pass-1']);
        $this->other = Customer::create(['name' => 'Agnes Nabirye', 'email' => 'agnes@example.com', 'phone' => '0772000000', 'password' => 'secret-pass-2']);
    }

    private function as(?Customer $c = null): static
    {
        return $this->withSession(['LoggedCustomer' => ($c ?? $this->me)->id]);
    }

    /** An order with one PDF paper (and optionally more), owned by $owner (null = guest). */
    private function order(?Customer $owner, array $over = [], string $fmt = 'pdf'): Order
    {
        $paper = Paper::published()->first();
        $o = Order::create($over + [
            'customer_id' => $owner?->id, 'customer_name' => 'Joseph', 'customer_email' => 'joseph@example.com', 'customer_phone' => '0772123456',
            'delivery_method' => 'email', 'payment_method' => 'mtn', 'payment_status' => 'paid', 'status' => 'completed',
            'subtotal' => 2500, 'delivery_fee' => 0, 'total' => 2500,
        ]);
        $o->forceFill(['number' => 'WK-' . (Order::NUMBER_BASE + $o->id)])->save();
        $o->items()->create([
            'item_type' => 'paper', 'item_id' => $paper->id, 'item_slug' => $paper->slug, 'format' => $fmt, 'title' => $paper->title . ' ' . $paper->year,
            'level' => $paper->level->code, 'subject' => $paper->subject->name, 'year' => $paper->year, 'paper_no' => '1',
            'unit_price' => 2500, 'quantity' => 1, 'line_total' => 2500, 'is_physical' => $fmt !== 'pdf',
        ]);

        return $o->load('items');
    }

    private function key(Order $o): string
    {
        return 'i' . $o->items->first()->id;
    }

    private function dl(Order $o, ?Customer $c = null)
    {
        return $this->as($c)->post(route('account.library.download', [$o->number, $this->key($o)]));
    }

    /* ------------------------------------------------------------ pages and data */

    public function test_the_portal_only_carries_the_signed_in_customers_own_data(): void
    {
        $mine = $this->order($this->me);
        $this->order($this->other);

        $html = $this->as()->get(route('account.orders'))->assertOk()->getContent();

        $this->assertStringContainsString($mine->number, $html);
        $this->assertStringNotContainsString('agnes@example.com', $html);
        $this->assertSame(1, substr_count($html, '"iid"'));
    }

    public function test_an_order_page_is_private_and_unknown_numbers_look_the_same(): void
    {
        $theirs = $this->order($this->other);

        $this->as()->get(route('account.order', $theirs->number))->assertNotFound();
        $this->as()->get(route('account.order', 'WK-99999'))->assertNotFound();
        $this->as($this->other)->get(route('account.order', $theirs->number))->assertOk();
    }

    public function test_an_invoice_exists_only_for_paid_orders(): void
    {
        $paid    = $this->order($this->me);
        $pending = $this->order($this->me, ['payment_status' => 'pending', 'status' => 'pending']);

        $this->as()->get(route('account.invoice', $paid->number))->assertOk();
        $this->as()->get(route('account.invoice', $pending->number))->assertNotFound();
    }

    public function test_old_order_links_land_on_the_order(): void
    {
        $this->as()->get('/account/order.html?id=WK-10005')->assertRedirect(route('account.orders') . '/WK-10005');
        $this->as()->get('/account/invoice?id=wk-10005&print=1')->assertRedirect(route('account.invoices') . '/WK-10005?print=1');
        $this->as()->get('/account/order.html')->assertRedirect(route('account.orders'));
    }

    public function test_orders_without_history_still_show_their_first_line(): void
    {
        $o = $this->order($this->me);

        $this->as()->get(route('account.orders'))->assertSee('Order placed', false)->assertSee((string) $o->number, false);
    }

    /* ------------------------------------------------------------ library and downloads */

    public function test_a_paid_pdf_can_be_downloaded_five_times_and_no_more(): void
    {
        $o = $this->order($this->me);

        foreach (range(1, 5) as $n) {
            $this->dl($o)->assertOk()->assertHeader('X-Downloads-Used', (string) $n)->assertHeader('Content-Type', 'application/pdf');
        }

        $this->dl($o)->assertStatus(429)->assertJson(['limit' => true]);
        $this->assertSame(5, FileDownload::first()->used);
    }

    public function test_the_limit_is_taken_atomically_and_can_be_raised_for_one_file(): void
    {
        $o = $this->order($this->me);
        $row = null;
        foreach (range(1, 5) as $n) {
            $row = FileDownload::take($o, $this->key($o));
        }
        $this->assertNotNull($row);
        $this->assertNull(FileDownload::take($o, $this->key($o)));          // the 6th is refused by the statement itself

        FileDownload::where('order_id', $o->id)->update(['max_downloads' => 7]);
        $this->assertNotNull(FileDownload::take($o, $this->key($o)));
    }

    public function test_the_downloaded_file_is_a_pdf_licensed_to_the_customer(): void
    {
        $o = $this->order($this->me);

        $body = $this->dl($o)->assertOk()->getContent();

        $this->assertStringStartsWith('%PDF-1.4', $body);
        $this->assertStringContainsString('Licensed to: Joseph Ssemakula', $body);
        $this->assertStringContainsString('%%EOF', $body);
    }

    public function test_a_stored_file_is_served_instead_of_the_sample(): void
    {
        Storage::fake('local');
        $o = $this->order($this->me);
        $paper = Paper::find($o->items->first()->item_id);
        Storage::disk('local')->put('papers/real.pdf', '%PDF-1.4 REAL FILE');
        $paper->update(['pdf_path' => 'papers/real.pdf']);

        $this->assertStringContainsString('REAL FILE', $this->dl($o)->assertOk()->streamedContent());
    }

    public function test_nobody_else_can_download_it_and_unpaid_or_printed_items_have_no_file(): void
    {
        $o = $this->order($this->me);
        $this->dl($o, $this->other)->assertNotFound();

        $unpaid  = $this->order($this->me, ['payment_status' => 'pending', 'status' => 'pending']);
        $printed = $this->order($this->me, [], 'print');
        $this->dl($unpaid)->assertNotFound();
        $this->dl($printed)->assertNotFound();
        $this->assertSame(0, FileDownload::count());
    }

    public function test_downloading_needs_a_post_so_a_link_preview_cannot_use_a_download(): void
    {
        $o = $this->order($this->me);

        $this->as()->get('/account/library/' . $o->number . '/' . $this->key($o) . '/download')->assertNotFound();
        $this->assertSame(0, FileDownload::count());
    }

    public function test_in_production_a_missing_file_is_still_being_prepared_and_costs_no_download(): void
    {
        config(['wakata.placeholder_pdfs' => false]);
        $o = $this->order($this->me);

        $this->dl($o)->assertStatus(409)->assertJsonFragment(['ok' => false]);
        $this->assertSame(0, FileDownload::count());
    }

    public function test_emailing_a_file_attaches_it_and_counts_as_a_download(): void
    {
        $o = $this->order($this->me);

        $this->as()->post(route('account.library.email', [$o->number, $this->key($o)]))->assertOk()->assertJson(['ok' => true, 'used' => 1]);

        Mail::assertSent(LibraryFileMail::class, fn ($m) => $m->hasTo('joseph@example.com') && count($m->attachments()) === 1);
    }

    public function test_a_failed_email_gives_the_download_back(): void
    {
        $o = $this->order($this->me);
        Mail::shouldReceive('to')->andThrow(new \RuntimeException('smtp down'));

        $this->as()->post(route('account.library.email', [$o->number, $this->key($o)]))->assertStatus(502);

        $this->assertSame(0, FileDownload::first()->used);
    }

    public function test_the_library_lists_files_with_their_download_counts(): void
    {
        $o = $this->order($this->me);
        $this->dl($o);

        $files = $this->portal(route('account.library'));

        $this->assertCount(1, $files['files']);
        $this->assertSame(1, $files['files'][0]['used']);
        $this->assertSame(5, $files['files'][0]['max']);
    }

    /* ------------------------------------------------------------ finding guest orders */

    public function test_a_guest_order_is_added_only_with_the_code_sent_to_its_email(): void
    {
        $guest  = $this->order(null, ['customer_email' => 'buyer@school.ac.ug']);
        $second = $this->order(null, ['customer_email' => 'buyer@school.ac.ug']);
        $stray  = $this->order(null, ['customer_email' => 'someone.else@example.com']);

        $this->as()->postJson(route('account.orders.claim'), ['number' => $guest->number])->assertOk();

        $code = null;
        Mail::assertSent(CustomerOtpMail::class, function ($m) use (&$code) { $code = $m->code; return $m->hasTo('buyer@school.ac.ug') && $m->purpose === 'order'; });

        $this->as()->postJson(route('account.orders.claim.verify'), ['number' => $guest->number, 'code' => '000000'])->assertStatus(422);
        $this->assertNull($guest->fresh()->customer_id);

        $this->as()->postJson(route('account.orders.claim.verify'), ['number' => $guest->number, 'code' => $code])->assertOk()->assertJson(['count' => 2]);
        $this->assertSame($this->me->id, $guest->fresh()->customer_id);
        $this->assertSame($this->me->id, $second->fresh()->customer_id);
        $this->assertNull($stray->fresh()->customer_id);
    }

    public function test_asking_for_a_code_does_not_reveal_whether_an_order_exists(): void
    {
        $guest = $this->order(null);
        $owned = $this->order($this->other);

        $real = $this->as()->postJson(route('account.orders.claim'), ['number' => $guest->number])->json('message');
        $none = $this->as()->postJson(route('account.orders.claim'), ['number' => 'WK-99999'])->json('message');
        $taken = $this->as()->postJson(route('account.orders.claim'), ['number' => $owned->number])->json('message');

        $this->assertSame($real, $none);
        $this->assertSame($real, $taken);
        Mail::assertSent(CustomerOtpMail::class, 1);
    }

    public function test_someone_elses_order_can_never_be_taken(): void
    {
        $owned = $this->order($this->other);
        Cache::put('claim-order|' . $this->me->id . '|' . $owned->number, hash('sha256', '123456'), now()->addMinutes(5));

        $this->as()->postJson(route('account.orders.claim.verify'), ['number' => $owned->number, 'code' => '123456'])->assertStatus(422);
        $this->assertSame($this->other->id, $owned->fresh()->customer_id);
    }

    public function test_too_many_wrong_codes_lock_the_code(): void
    {
        $guest = $this->order(null);
        $this->as()->postJson(route('account.orders.claim'), ['number' => $guest->number]);

        foreach (range(1, 5) as $i) {
            $this->as()->postJson(route('account.orders.claim.verify'), ['number' => $guest->number, 'code' => '111111'])->assertStatus(422);
        }
        $this->as()->postJson(route('account.orders.claim.verify'), ['number' => $guest->number, 'code' => '111111'])->assertStatus(429);
    }

    /* ------------------------------------------------------------ saved items */

    public function test_saving_items_keeps_one_row_per_product_and_format(): void
    {
        $paper = Paper::published()->first();
        $item  = ['type' => 'paper', 'id' => $paper->slug, 'fmt' => 'pdf'];

        $this->as()->postJson(route('account.saved.store'), ['items' => [$item, $item, ['type' => 'book', 'id' => 'does-not-exist']]])->assertCreated();
        $this->as()->postJson(route('account.saved.store'), ['items' => [$item]])->assertOk()->assertJson(['added' => 0]);

        $this->assertSame(1, SavedItem::count());
    }

    public function test_a_saved_paper_can_switch_format_and_only_its_owner_can_change_it(): void
    {
        $paper = Paper::published()->first();
        $this->as()->postJson(route('account.saved.store'), ['items' => [['type' => 'paper', 'id' => $paper->slug, 'fmt' => 'print']]]);
        $item = SavedItem::first();

        $this->as($this->other)->patchJson(route('account.saved.update', $item), ['fmt' => 'pdf'])->assertNotFound();
        $this->as($this->other)->deleteJson(route('account.saved.destroy', $item))->assertNotFound();
        $this->as()->patchJson(route('account.saved.update', $item), ['fmt' => 'pdf'])->assertOk();
        $this->assertSame('pdf', $item->fresh()->format);
        $this->as()->deleteJson(route('account.saved.destroy', $item))->assertOk();
        $this->assertSame(0, SavedItem::count());
    }

    /* ------------------------------------------------------------ addresses */

    public function test_addresses_the_first_is_default_and_a_new_default_replaces_it(): void
    {
        $a = ['recipient' => 'Joseph', 'line' => 'Green Hill SS, Mukono Road', 'district' => 'Mukono'];

        $this->as()->postJson(route('account.addresses.store'), $a + ['label' => 'School'])->assertCreated();
        $this->as()->postJson(route('account.addresses.store'), $a + ['label' => 'Home', 'is_default' => true])->assertCreated();

        $this->assertSame(['Home'], $this->me->addresses()->where('is_default', true)->pluck('label')->all());

        $this->as()->deleteJson(route('account.addresses.destroy', $this->me->addresses()->where('label', 'Home')->first()))->assertOk();
        $this->assertSame(['School'], $this->me->addresses()->where('is_default', true)->pluck('label')->all());
    }

    public function test_addresses_are_private_and_validated(): void
    {
        $this->as()->postJson(route('account.addresses.store'), ['line' => 'x'])->assertStatus(422);
        $this->as()->postJson(route('account.addresses.store'), ['recipient' => 'J', 'line' => 'Somewhere']);
        $addr = $this->me->addresses()->first();

        $this->as($this->other)->putJson(route('account.addresses.update', $addr), ['recipient' => 'X', 'line' => 'Y'])->assertNotFound();
        $this->as($this->other)->deleteJson(route('account.addresses.destroy', $addr))->assertNotFound();
    }

    public function test_the_default_address_pre_fills_checkout(): void
    {
        $this->as()->postJson(route('account.addresses.store'), ['recipient' => 'J', 'line' => 'Plot 14, Ntinda', 'district' => 'Kampala']);

        $this->as()->get(route('site.cart'))->assertSee('Plot 14, Ntinda, Kampala', false);
    }

    /* ------------------------------------------------------------ support */

    public function test_a_customer_can_start_and_continue_a_conversation(): void
    {
        $o = $this->order($this->me);

        $this->as()->postJson(route('account.support.store'), ['topic' => 'Order or delivery', 'order' => $o->number, 'subject' => 'Stamp please', 'body' => 'Can you stamp it?'])
            ->assertCreated()->assertJsonPath('messages.0.order', $o->number);

        $thread = SupportThread::first();
        $this->assertSame('T-' . (SupportThread::NUMBER_BASE + $thread->id), $thread->ref);

        $this->as()->postJson(route('account.support.reply', $thread->ref), ['body' => 'Thank you'])->assertOk()->assertJsonCount(2, 'messages.0.msgs');
    }

    public function test_conversations_are_private_and_orders_must_be_the_customers_own(): void
    {
        $theirs = $this->order($this->other);

        $this->as()->postJson(route('account.support.store'), ['topic' => 'Something else', 'order' => $theirs->number, 'subject' => 'S', 'body' => 'B'])->assertStatus(422);

        $this->as($this->other)->postJson(route('account.support.store'), ['topic' => 'Something else', 'subject' => 'S', 'body' => 'B'])->assertCreated();
        $ref = SupportThread::first()->ref;

        $this->as()->postJson(route('account.support.reply', $ref), ['body' => 'hi'])->assertNotFound();
        $this->as()->postJson(route('account.support.read', $ref))->assertNotFound();
    }

    public function test_a_staff_reply_is_new_until_the_customer_opens_it(): void
    {
        $this->as()->postJson(route('account.support.store'), ['topic' => 'Something else', 'subject' => 'S', 'body' => 'B']);
        $t = SupportThread::first();
        $t->messages()->create(['sender' => 'staff', 'body' => 'Hello']);
        $t->forceFill(['last_message_at' => now(), 'customer_read_at' => now()->subMinute()])->save();

        $status = fn () => $this->portal(route('account.support'))['messages'][0]['status'];

        $this->assertSame('new', $status());
        $this->as()->postJson(route('account.support.read', $t->ref))->assertOk();
        $this->assertSame('answered', $status());
    }

    public function test_staff_are_emailed_when_an_address_is_configured(): void
    {
        config(['wakata.support_notify' => 'staff@wakata.test']);

        $this->as()->postJson(route('account.support.store'), ['topic' => 'Something else', 'subject' => 'S', 'body' => 'B'])->assertCreated();

        Mail::assertSent(\App\Mail\SupportMessageStaffMail::class, fn ($m) => $m->hasTo('staff@wakata.test'));
    }

    /* ------------------------------------------------------------ profile and security */

    public function test_the_profile_saves_and_an_email_change_needs_the_password(): void
    {
        $base = ['name' => 'Joseph S', 'email' => 'joseph@example.com', 'phone' => '0700', 'type' => 'School'];

        $this->as()->post(route('account.profile.update'), $base)->assertRedirect();
        $this->assertSame('Joseph S', $this->me->fresh()->name);

        $this->as()->post(route('account.profile.update'), ['email' => 'new@example.com'] + $base)->assertSessionHasErrors('email_password');
        $this->as()->post(route('account.profile.update'), ['email' => 'new@example.com', 'email_password' => 'wrong'] + $base)->assertSessionHasErrors('email_password');
        $this->assertSame('joseph@example.com', $this->me->fresh()->email);

        $this->as()->post(route('account.profile.update'), ['email' => 'new@example.com', 'email_password' => 'secret-pass-1'] + $base)->assertSessionHasNoErrors();
        $this->assertSame('new@example.com', $this->me->fresh()->email);

        $this->as()->post(route('account.profile.update'), ['email' => 'agnes@example.com', 'email_password' => 'secret-pass-1'] + $base)->assertSessionHasErrors('email');
    }

    public function test_the_school_details_feed_the_invoice(): void
    {
        $this->as()->post(route('account.profile.organisation'), ['school' => 'Green Hill SS', 'invoice_name' => 'Green Hill Bursar', 'org_address' => 'Mukono', 'accounts_email' => 'Bursar@Greenhill.ac.ug']);
        $o = $this->order($this->me);

        $html = $this->as()->get(route('account.invoice', $o->number))->getContent();

        $this->assertStringContainsString('Green Hill Bursar', $html);
        $this->assertStringContainsString('"stamp":false', $html);   // the stamp box was left unticked
        $this->assertSame('bursar@greenhill.ac.ug', $this->me->fresh()->accounts_email);
    }

    public function test_changing_the_password_signs_out_other_devices_but_not_this_one(): void
    {
        $this->as()->post(route('account.profile.password'), ['current_password' => 'nope', 'new_password' => 'brand-new-pass', 'new_password_confirmation' => 'brand-new-pass'])->assertSessionHasErrors('current_password');

        $this->as()->post(route('account.profile.password'), ['current_password' => 'secret-pass-1', 'new_password' => 'brand-new-pass', 'new_password_confirmation' => 'brand-new-pass'])->assertSessionHasNoErrors();

        $this->assertSame(1, $this->me->fresh()->session_epoch);
        $this->assertSame(1, session('LoggedCustomerEpoch'));
        $this->get(route('account.dashboard'))->assertOk();                                      // this browser carries on
        $this->flushSession();
        $this->withSession(['LoggedCustomer' => $this->me->id])->get(route('account.dashboard'))->assertRedirect(route('account.login'));   // an older session does not
    }

    public function test_sign_out_everywhere_else_also_ends_remembered_logins(): void
    {
        $this->me->forceFill(['remember_token' => hash('sha256', 'tok')])->save();

        $this->as()->post(route('account.profile.sign-out-others'))->assertRedirect();

        $this->assertNull($this->me->fresh()->remember_token);
    }

    public function test_two_step_sign_in_asks_for_the_emailed_code(): void
    {
        $this->as()->post(route('account.profile.two-step'), ['enabled' => 1, 'two_step_password' => 'wrong'])->assertSessionHasErrors('two_step_password');
        $this->as()->post(route('account.profile.two-step'), ['enabled' => 1, 'two_step_password' => 'secret-pass-1']);
        $this->assertTrue($this->me->fresh()->two_step_enabled);

        $this->flushSession();
        $this->post(route('account.login.submit'), ['email' => 'joseph@example.com', 'password' => 'secret-pass-1'])->assertRedirect(route('account.verify'));
        $this->assertNull(session('LoggedCustomer'));
        $this->get(route('account.dashboard'))->assertRedirect(route('account.login'));

        $code = null;
        Mail::assertSent(CustomerOtpMail::class, function ($m) use (&$code) { $code = $m->code; return $m->purpose === 'sign-in'; });

        $this->post(route('account.verify.submit'), ['code' => $code === '000000' ? '111111' : '000000'])->assertSessionHasErrors('code');
        $this->post(route('account.verify.submit'), ['code' => $code])->assertRedirect(route('account.dashboard'));
        $this->assertSame($this->me->id, session('LoggedCustomer'));
    }

    public function test_the_two_step_code_expires_and_locks_after_five_wrong_tries(): void
    {
        $this->me->update(['two_step_enabled' => true]);
        $this->post(route('account.login.submit'), ['email' => 'joseph@example.com', 'password' => 'secret-pass-1']);

        foreach (range(1, 5) as $i) {
            $this->post(route('account.verify.submit'), ['code' => '999999'])->assertSessionHasErrors('code');
        }
        $this->post(route('account.verify.submit'), ['code' => '999999'])->assertRedirect(route('account.login'));
        $this->assertNull(session('CustomerPending'));
    }

    public function test_notifications_and_payment_numbers_are_saved(): void
    {
        $this->as()->post(route('account.profile.notifications'), ['notify_news' => 1]);
        $p = $this->me->fresh();
        $this->assertTrue($p->pref('notify_news'));
        $this->assertFalse($p->pref('notify_orders'));

        $this->as()->post(route('account.profile.payment-numbers'), ['mtn_number' => 'abc'])->assertSessionHasErrors('mtn_number');
        $this->as()->post(route('account.profile.payment-numbers'), ['mtn_number' => '+256 772 123 456', 'prefill_payment' => 1]);
        $this->assertSame('+256 772 123 456', $this->me->fresh()->pref('mtn_number'));
    }

    public function test_the_data_export_has_only_this_customers_data(): void
    {
        $this->order($this->me);
        $this->order($this->other);

        $json = $this->as()->get(route('account.profile.export'))->assertOk()->assertHeader('Content-Disposition', 'attachment; filename="wakata-my-data.json"')->getContent();

        $this->assertStringContainsString('joseph@example.com', $json);
        $this->assertStringNotContainsString('agnes@example.com', $json);
        $this->assertStringNotContainsString('secret', $json);
        $this->assertCount(1, json_decode($json, true)['orders']);
    }

    public function test_deleting_the_account_keeps_the_orders_but_unlinks_them(): void
    {
        $o = $this->order($this->me);
        $this->as()->postJson(route('account.addresses.store'), ['recipient' => 'J', 'line' => 'Somewhere']);

        $this->as()->post(route('account.profile.delete'), ['delete_password' => 'wrong'])->assertSessionHasErrors('delete_password');
        $this->assertNotNull(Customer::find($this->me->id));

        $this->as()->post(route('account.profile.delete'), ['delete_password' => 'secret-pass-1'])->assertRedirect(route('site.home'));

        $this->assertNull(Customer::find($this->me->id));
        $this->assertNull($o->fresh()->customer_id);
        $this->assertSame(0, \App\Models\CustomerAddress::count());
    }

    public function test_checkout_records_the_first_line_of_the_order_history(): void
    {
        $paper = Paper::published()->first();
        $this->postJson(route('cart.add'), ['items' => [['type' => 'paper', 'id' => $paper->slug, 'fmt' => 'pdf']]]);
        $this->postJson(route('checkout.store'), ['name' => 'A B', 'phone' => '0772123456', 'email' => 'a@b.co', 'pay' => 'mtn'])->assertCreated();

        $this->assertSame(['Order placed'], Order::first()->events->pluck('label')->all());
    }

    public function test_the_mark_paid_command_makes_the_library_and_invoice_available(): void
    {
        $o = $this->order($this->me, ['payment_status' => 'pending', 'status' => 'pending']);

        $this->artisan('wakata:mark-paid', ['number' => $o->number, '--status' => 'completed'])->assertSuccessful();

        $this->assertTrue($o->fresh()->isCounted());
        $this->dl($o)->assertOk();
        $this->artisan('wakata:mark-paid', ['number' => 'WK-1'])->assertFailed();
    }

    public function test_the_demo_seeder_fills_the_portal_with_real_rows(): void
    {
        $this->seed(\Database\Seeders\CustomerSeeder::class);
        $this->seed(\Database\Seeders\DemoPortalSeeder::class);
        $this->seed(\Database\Seeders\DemoPortalSeeder::class);   // twice: no duplicates

        $demo = Customer::where('email', 'joseph.ssemakula@greenhill.ac.ug')->first();
        $this->assertGreaterThan(5, $demo->orders()->count());
        $this->assertSame(2, $demo->addresses()->count());
        $this->assertSame(1, $demo->supportThreads()->count());
        $this->as($demo)->get(route('account.dashboard'))->assertOk();
    }

    public function test_the_sign_out_everywhere_epoch_is_ignored_for_sessions_that_never_had_one(): void
    {
        // Sessions created before this patch have no epoch and must keep working
        $this->get(route('account.dashboard'))->assertRedirect();
        $this->withSession(['LoggedCustomer' => $this->me->id])->get(route('account.dashboard'))->assertOk();
    }

    private function portal(string $url): array
    {
        $html = $this->as()->get($url)->getContent();
        preg_match('/^WK\.portal = (.*);$/m', $html, $m);
        return json_decode($m[1] ?? 'null', true) ?? [];
    }
}
