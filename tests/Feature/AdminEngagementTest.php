<?php

namespace Tests\Feature;

use App\Mail\GeneralMail;
use App\Mail\OrderReceivedStaffMail;
use App\Models\Admin;
use App\Models\ContactMessage;
use App\Models\Customer;
use App\Models\Faq;
use App\Models\Newsletter;
use App\Models\Order;
use App\Models\Subscriber;
use App\Models\SupportMessage;
use App\Models\SupportThread;
use App\Models\Testimonial;
use App\Support\AdminEngagement;
use App\Support\SiteContent;
use App\Support\SiteSettings;
use Database\Seeders\CatalogueSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/** Module 9: the Contact form and newsletter, Messages, Subscribers, Testimonials, Questions, Site content and Settings. */
class AdminEngagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CatalogueSeeder::class);
        Mail::fake();
        Cache::flush();
    }

    private function admin(string $role = 'super_admin'): self
    {
        $a = Admin::where('email', $role . '@example.com')->first()
            ?? Admin::create(['name' => 'Test ' . $role, 'email' => $role . '@example.com', 'password' => 'secret-pass-1', 'role' => $role, 'status' => 'active']);

        return $this->withSession(['LoggedAdmin' => $a->id]);
    }

    private function contact(array $over = []): array
    {
        return array_merge(['name' => 'Agnes Nabirye', 'phone' => '0772123456', 'email' => 'agnes@school.test', 'topic' => 'Bulk or school quotation', 'message' => 'We need 200 workbooks for term two.'], $over);
    }

    private function message(array $over = []): ContactMessage
    {
        $m = ContactMessage::create(array_merge(['name' => 'Agnes', 'email' => 'agnes@school.test', 'phone' => '0772123456', 'topic' => 'Bulk or school quotation', 'body' => 'We need 200 workbooks.', 'status' => 'new'], $over));
        $m->forceFill(['ref' => 'M-' . (ContactMessage::REF_BASE + $m->id)])->save();

        return $m;
    }

    private function thread(): SupportThread
    {
        $c = Customer::create(['name' => 'Joseph', 'email' => 'joseph@example.com', 'phone' => '0772000111', 'password' => 'secret-pass-1']);
        $t = SupportThread::create(['ref' => 'T-1001', 'customer_id' => $c->id, 'topic' => 'Order or delivery', 'subject' => 'Where is my order?', 'status' => 'open', 'last_message_at' => now()]);
        SupportMessage::create(['support_thread_id' => $t->id, 'sender' => 'customer', 'body' => 'It has been a week.']);

        return $t;
    }

    // ------------------------------------------------------------ the public Contact form

    public function test_the_contact_form_saves_a_message_and_links_a_known_customer(): void
    {
        $c = Customer::create(['name' => 'Agnes', 'email' => 'agnes@school.test', 'phone' => '0772123456', 'password' => 'secret-pass-1']);

        $this->postJson(route('contact.store'), $this->contact())->assertOk()->assertJson(['ok' => true, 'ref' => 'M-1001']);

        $m = ContactMessage::firstOrFail();
        $this->assertSame('new', $m->status);
        $this->assertSame('Bulk or school quotation', $m->topic);
        $this->assertSame($c->id, $m->customer_id);
        $this->assertSame('M-' . (1000 + $m->id), $m->ref);
    }

    public function test_the_contact_form_is_checked_and_robots_are_ignored(): void
    {
        $this->postJson(route('contact.store'), $this->contact(['email' => 'nope']))->assertStatus(422)->assertJsonValidationErrors('email');
        $this->postJson(route('contact.store'), $this->contact(['phone' => '123']))->assertStatus(422)->assertJsonValidationErrors('phone');
        $this->postJson(route('contact.store'), $this->contact(['topic' => 'Spam']))->assertStatus(422)->assertJsonValidationErrors('topic');
        $this->postJson(route('contact.store'), $this->contact(['message' => '']))->assertStatus(422)->assertJsonValidationErrors('message');
        $this->postJson(route('contact.store'), $this->contact(['website' => 'http://spam.test']))->assertOk();      // the hidden field is for robots

        $this->assertSame(0, ContactMessage::count());
    }

    public function test_the_team_is_alerted_about_a_message_when_alerts_are_on(): void
    {
        config(['wakata.order_notify' => 'team@wakatastationers.test']);
        $this->postJson(route('contact.store'), $this->contact())->assertOk();
        Mail::assertSent(GeneralMail::class, fn ($m) => $m->hasTo('team@wakatastationers.test') && str_contains($m->subjectLine, 'Agnes'));

        Mail::fake();
        SiteSettings::save(['alert_messages' => false]);
        $this->postJson(route('contact.store'), $this->contact())->assertOk();
        Mail::assertNothingSent();
        $this->assertSame(2, ContactMessage::count());           // the message is kept either way
    }

    public function test_a_mail_problem_never_loses_a_message(): void
    {
        config(['wakata.order_notify' => 'team@wakatastationers.test']);
        Mail::shouldReceive('to')->andThrow(new \RuntimeException('smtp down'));

        $this->postJson(route('contact.store'), $this->contact())->assertOk();
        $this->assertSame(1, ContactMessage::count());
    }

    // ------------------------------------------------------------ newsletter sign-up

    public function test_signing_up_for_the_newsletter(): void
    {
        $this->postJson(route('newsletter.subscribe'), ['email' => 'Teacher@School.test', 'source' => 'Home page'])->assertOk()->assertJsonPath('ok', true);

        $s = Subscriber::firstOrFail();
        $this->assertSame('teacher@school.test', $s->email);
        $this->assertSame('Home page', $s->source);
        $this->assertSame(40, strlen($s->token));

        // again: no duplicate
        $this->postJson(route('newsletter.subscribe'), ['email' => 'teacher@school.test'])->assertOk()->assertJsonFragment(['message' => 'You are already on the list. Thank you.']);
        $this->assertSame(1, Subscriber::count());

        // an unknown source becomes Footer, a bad address and robots are refused
        $this->postJson(route('newsletter.subscribe'), ['email' => 'b@school.test', 'source' => 'Hacked'])->assertOk();
        $this->assertSame('Footer', Subscriber::where('email', 'b@school.test')->value('source'));
        $this->postJson(route('newsletter.subscribe'), ['email' => 'nope'])->assertStatus(422);
        $this->postJson(route('newsletter.subscribe'), ['email' => 'bot@school.test', 'website' => 'x'])->assertOk();
        $this->assertNull(Subscriber::where('email', 'bot@school.test')->first());
    }

    public function test_the_unsubscribe_link_works_and_coming_back_resubscribes(): void
    {
        $s = Subscriber::create(['email' => 'a@school.test']);

        $this->get(route('newsletter.unsubscribe', $s->token))->assertOk()->assertSee('You are unsubscribed');
        $this->assertSame('unsubscribed', $s->fresh()->status);

        $this->get(route('newsletter.unsubscribe', str_repeat('z', 40)))->assertOk()->assertSee('Link not recognised');

        $this->postJson(route('newsletter.subscribe'), ['email' => 'a@school.test', 'source' => 'Checkout'])->assertOk();
        $this->assertSame('active', $s->fresh()->status);
        $this->assertSame('Checkout', $s->fresh()->source);
        $this->assertSame(1, Subscriber::count());
    }

    // ------------------------------------------------------------ the public pages read the database

    public function test_the_home_page_shows_the_visible_testimonials_in_order(): void
    {
        $this->get(route('site.home'))->assertOk()->assertSee('What teachers say');
        $this->assertSame(10, Testimonial::count());

        Testimonial::query()->update(['visible' => false]);
        Testimonial::create(['stars' => 4, 'quote' => 'A very useful set of papers.', 'initials' => 'XY', 'role' => 'Head teacher', 'place' => 'Entebbe', 'sort_order' => 1]);
        Testimonial::create(['stars' => 5, 'quote' => 'First on the list.', 'initials' => 'AB', 'role' => 'Bursar', 'place' => 'Jinja', 'sort_order' => 0]);

        $html = $this->get(route('site.home'))->assertOk()->getContent();
        $this->assertLessThan(strpos($html, 'A very useful set'), strpos($html, 'First on the list.'));
        $this->assertStringContainsString('aria-label="4 out of 5"', $html);
        $this->assertStringContainsString('&#9733;&#9733;&#9733;&#9733;&#9734;', $html);

        // nothing visible: the whole section goes
        Testimonial::query()->update(['visible' => false]);
        $this->get(route('site.home'))->assertOk()->assertDontSee('What teachers say');
    }

    public function test_the_questions_on_the_contact_and_holiday_pages_come_from_the_database(): void
    {
        $this->get(route('site.contact'))->assertOk()->assertSee('How do I pay?')->assertSee('When are you open?');
        $this->get(route('site.packages'))->assertOk()->assertSee('When should I order a holiday package?');

        Faq::where('question', 'How do I pay?')->update(['visible' => false]);
        Faq::create(['page' => 'contact', 'question' => 'Do you give receipts?', 'answer' => 'Yes, every order has an invoice.', 'icon' => 'check', 'sort_order' => 0]);

        $html = $this->get(route('site.contact'))->assertOk()->assertDontSee('How do I pay?')->getContent();
        $this->assertLessThan(strpos($html, 'Do you deliver outside Kampala?'), strpos($html, 'Do you give receipts?'));
        $this->get(route('site.packages'))->assertDontSee('Do you give receipts?');          // another page's list
    }

    // ------------------------------------------------------------ Site content

    public function test_unedited_content_is_the_original_wording(): void
    {
        $this->get(route('site.home'))->assertSee('Quality assessment, priced for every school.')->assertSee('Shop books');
        $this->get(route('site.about'))->assertSee('Written by teachers, for teachers');
        $this->get(route('site.contact'))->assertSee('+256 700 000 000')->assertSee('info@wakatastationers.com')->assertSee('Monday to Saturday, 8am to 6pm');
        $this->get(route('site.cart'))->assertSee('Your cart is empty');
        $this->assertSame('WAKATA Stationers | Books, past papers and mock examinations', SiteContent::seo('home', 'title'));
    }

    public function test_content_edits_reach_the_pages_and_blank_goes_back(): void
    {
        $this->admin()->post(route('admin.content.save'), ['tab' => 'home', 'h1' => 'Papers your school can trust.', 'b1' => 'Browse books', 'b1l' => 'exam-sets', 'tag' => 'Print or PDF'])->assertSessionHas('status');

        $html = $this->get(route('site.home'))->assertOk()->assertSee('Papers your school can trust.')->assertSee('Browse books')->assertSee('Print or PDF')->getContent();
        $this->assertStringContainsString('href="' . route('site.sets') . '">Browse books', $html);

        // empty -> the built-in wording again; text equal to the default is not kept
        $this->admin()->post(route('admin.content.save'), ['tab' => 'home', 'h1' => '', 'b1' => 'Shop books', 'b1l' => 'books', 'tag' => 'Printed or PDF']);
        $this->get(route('site.home'))->assertSee('Quality assessment, priced for every school.');
        $this->assertSame([], \App\Models\CatalogueOption::read('site_content'));
    }

    public function test_content_is_checked(): void
    {
        $this->admin()->post(route('admin.content.save'), ['tab' => 'contact', 'cem' => 'not-an-email'])->assertSessionHasErrors('cem');
        $this->admin()->post(route('admin.content.save'), ['tab' => 'contact', 'cph' => 'call me'])->assertSessionHasErrors('cph');
        $this->admin()->post(route('admin.content.save'), ['tab' => 'contact', 'cmp' => 'somewhere'])->assertSessionHasErrors('cmp');
        $this->admin()->post(route('admin.content.save'), ['tab' => 'home', 'b1l' => 'evil.html'])->assertSessionHasErrors('b1l');
        $this->admin()->post(route('admin.content.save'), ['tab' => 'home', 'h1' => str_repeat('x', 300)])->assertSessionHasErrors('h1');
        $this->admin()->post(route('admin.content.save'), ['tab' => 'nonsense', 'h1' => 'x'])->assertStatus(422);
        $this->assertSame([], \App\Models\CatalogueOption::read('site_content'));
    }

    public function test_contact_details_feed_the_whole_site(): void
    {
        $this->admin()->post(route('admin.content.save'), ['tab' => 'contact', 'cph' => '+256 772 123 456', 'cem' => 'hello@wakata.test', 'coh' => 'Monday to Friday, 9am to 5pm', 'cmp' => '0.31, 32.58'])->assertSessionHas('status');

        $contact = $this->get(route('site.contact'))->assertOk()->assertSee('+256 772 123 456')->assertSee('hello@wakata.test')->getContent();
        $this->assertStringContainsString('href="tel:+256772123456"', $contact);
        $this->assertStringContainsString('maps?q=0.31,32.58', $contact);
        $this->assertSame('hello@wakata.test', config('wakata.email'));

        // the top strip follows the contact details until it has its own text
        $this->get(route('site.home'))->assertSee('Monday to Friday, 9am to 5pm');
        $this->admin()->post(route('admin.content.save'), ['tab' => 'chrome', 'st2' => '0800 100 200']);
        $this->get(route('site.home'))->assertSee('0800 100 200');
    }

    public function test_search_engine_text_and_the_footer_are_editable(): void
    {
        $this->admin()->post(route('admin.content.save'), ['tab' => 'seo', 'seo_about_title' => 'About our teachers | WAKATA', 'seo_about_desc' => 'Who we are.'])->assertSessionHas('status');
        $this->get(route('site.about'))->assertSee('<title>About our teachers | WAKATA</title>', false)->assertSee('content="Who we are."', false);

        $this->admin()->post(route('admin.content.save'), ['tab' => 'seo', 'seo_about_title' => str_repeat('t', 71)])->assertSessionHasErrors('seo_about_title');

        $this->admin()->post(route('admin.content.save'), ['tab' => 'chrome', 'fc' => '© {year} WAKATA. Made in Uganda.', 'ft' => 'Fair prices for schools']);
        $this->get(route('site.home'))->assertSee('© ' . date('Y') . ' WAKATA. Made in Uganda.', false)->assertSee('Fair prices for schools');
    }

    public function test_the_order_received_text_keeps_its_two_places(): void
    {
        $this->admin()->post(route('admin.content.save'), ['tab' => 'cart', 'oct' => 'Thanks!', 'ocx' => 'Reference [reference]. We will confirm your [payment method] payment <b>soon</b>.']);

        $html = $this->get(route('site.cart'))->assertOk()->assertSee('Thanks!')->getContent();
        $this->assertStringContainsString('Reference <b id="ref"></b>. We will confirm your <span id="pay-name"></span> payment &lt;b&gt;soon&lt;/b&gt;.', $html);
    }

    public function test_the_content_page_shows_the_saved_text(): void
    {
        $this->admin()->post(route('admin.content.save'), ['tab' => 'about', 'at' => 'About our association']);
        $this->admin()->get(route('admin.content'))->assertOk()->assertSee('About our association')->assertSee('name="tab" value="home"', false)->assertSee('seo_home_title', false);
    }

    // ------------------------------------------------------------ Settings

    public function test_general_settings_save_the_public_email_and_the_goal(): void
    {
        $this->admin()->post(route('admin.settings.general'), ['email' => 'shop@wakata.test', 'goal' => 55000000])->assertSessionHas('status');
        $this->assertSame(55000000, SiteSettings::get('goal'));
        $this->get(route('site.contact'))->assertSee('shop@wakata.test');

        $this->admin()->get(route('admin.settings'))->assertOk()->assertSee('value="55000000"', false)->assertSee('shop@wakata.test');
        $this->admin()->post(route('admin.settings.general'), ['email' => 'bad', 'goal' => 'lots'])->assertSessionHasErrors(['email', 'goal']);
        $this->assertSame(55000000, SiteSettings::get('goal'));
    }

    public function test_the_download_limit_is_applied(): void
    {
        $this->assertSame(5, config('wakata.download_limit'));
        $this->admin()->post(route('admin.settings.downloads'), ['downloads' => 3])->assertSessionHas('status');
        $this->assertSame(3, config('wakata.download_limit'));
        $this->admin()->post(route('admin.settings.downloads'), ['downloads' => 0]);
        $this->assertGreaterThan(1000, config('wakata.download_limit'));
        $this->admin()->post(route('admin.settings.downloads'), ['downloads' => 7])->assertSessionHasErrors('downloads');
    }

    public function test_alert_recipients_and_switches(): void
    {
        config(['wakata.order_notify' => 'env@wakata.test']);
        $this->assertSame(['env@wakata.test'], SiteSettings::alertRecipients('orders'));

        $this->admin()->post(route('admin.settings.notifications'), ['recipients' => 'a@wakata.test, b@wakata.test;a@wakata.test', 'alert_orders' => '1'])->assertSessionHas('status');
        $this->assertSame(['a@wakata.test', 'b@wakata.test'], SiteSettings::alertRecipients('orders'));
        $this->assertSame([], SiteSettings::alertRecipients('messages'));           // that switch was not sent: off

        $this->admin()->post(route('admin.settings.notifications'), ['recipients' => 'a@wakata.test, nope'])->assertSessionHasErrors('recipients');
        $this->assertSame('a@wakata.test, b@wakata.test', SiteSettings::get('alert_recipients'));

        // a new order alerts exactly that list
        Mail::fake();
        $book = \App\Models\Book::first();
        $this->postJson(route('cart.add'), ['items' => [['type' => 'book', 'id' => $book->slug, 'qty' => 1]]]);
        $this->postJson(route('checkout.store'), ['name' => 'Agnes', 'email' => 'agnes@school.test', 'phone' => '0772123456', 'delivery' => 'pickup', 'pay' => 'cash']);
        Mail::assertSent(OrderReceivedStaffMail::class, fn ($m) => $m->hasTo('a@wakata.test') && ! $m->hasTo('env@wakata.test'));
    }

    public function test_analytics_is_added_only_when_an_id_is_saved(): void
    {
        $this->get(route('site.home'))->assertDontSee('googletagmanager');

        $this->admin()->post(route('admin.settings.integrations'), ['ga4' => 'g-abc123xyz'])->assertSessionHasErrors();           // lower case is not an ID
        $this->admin()->post(route('admin.settings.integrations'), ['ga4' => 'G-ABC123XYZ'])->assertSessionHas('status');
        $this->get(route('site.home'))->assertSee('googletagmanager.com/gtag/js?id=G-ABC123XYZ', false);

        $this->admin()->post(route('admin.settings.integrations'), ['ga4' => '']);
        $this->get(route('site.home'))->assertDontSee('googletagmanager');
    }

    public function test_data_can_be_exported_as_safe_csv(): void
    {
        Customer::create(['name' => '=HYPERLINK("http://evil.test")', 'email' => 'x@school.test', 'phone' => '0772000000', 'password' => 'secret-pass-1']);
        Subscriber::create(['email' => 'sub@school.test']);

        $res = $this->admin()->get(route('admin.settings.export', 'customers'))->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $csv = $res->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertStringContainsString("'=HYPERLINK", $csv);                      // not a live formula
        $this->assertStringContainsString('sub@school.test', $this->admin()->get(route('admin.settings.export', 'subscribers'))->streamedContent());
        $this->assertStringContainsString('SKU,Title', $this->admin()->get(route('admin.settings.export', 'books'))->streamedContent());
        $this->admin()->get(route('admin.settings.export', 'orders'))->assertOk();
        $this->admin()->get('/admin/settings/export/secrets')->assertNotFound();
    }

    // ------------------------------------------------------------ Messages

    public function test_messages_carry_contact_messages_and_customer_questions(): void
    {
        $m = $this->message();
        $t = $this->thread();

        $payload = AdminEngagement::payload()['messages'];
        $this->assertCount(2, $payload);
        $row = collect($payload)->firstWhere('id', $m->ref);
        $this->assertSame(['contact', 'new', 'Bulk or school quotation'], [$row['kind'], $row['status'], $row['topic']]);
        $this->assertSame(['support', 'new'], [collect($payload)->firstWhere('id', 'T-1001')['kind'], collect($payload)->firstWhere('id', 'T-1001')['status']]);

        $this->admin()->get(route('admin.messages'))->assertOk()->assertSee('WK.eng = ', false);
    }

    public function test_a_reply_is_emailed_and_kept(): void
    {
        $m = $this->message();

        $this->admin()->post(route('admin.messages.reply', $m->ref), ['body' => 'We can supply 200 copies by Friday.'])->assertSessionHas('status');

        Mail::assertSent(GeneralMail::class, fn ($g) => $g->hasTo('agnes@school.test') && $g->subjectLine === 'Re: Bulk or school quotation' && str_contains($g->body, 'by Friday'));
        $m->refresh();
        $this->assertSame('replied', $m->status);
        $this->assertNotNull($m->replied_at);
        $this->assertSame('We can supply 200 copies by Friday.', $m->replies->first()->body);
        $this->assertSame('Test super_admin', $m->replies->first()->admin_name);

        $row = collect(AdminEngagement::payload()['messages'])->firstWhere('id', $m->ref);
        $this->assertSame('We can supply 200 copies by Friday.', $row['thread'][0]['body']);

        $this->admin()->post(route('admin.messages.reply', $m->ref), ['body' => ''])->assertSessionHasErrors('body');
    }

    public function test_a_failed_email_does_not_mark_a_message_as_answered(): void
    {
        $m = $this->message();
        Mail::shouldReceive('to')->andThrow(new \RuntimeException('smtp down'));

        $this->admin()->post(route('admin.messages.reply', $m->ref), ['body' => 'Hello there'])->assertSessionHas('fail');

        $this->assertSame('new', $m->fresh()->status);
        $this->assertSame(0, $m->replies()->count());
    }

    public function test_messages_can_be_read_marked_archived_and_deleted(): void
    {
        $m = $this->message();

        $this->admin()->postJson(route('admin.messages.read', $m->ref))->assertOk();
        $this->assertSame('read', $m->fresh()->status);
        $this->assertNotNull($m->fresh()->read_at);

        $this->admin()->post(route('admin.messages.status', $m->ref), ['status' => 'new']);
        $this->assertSame(['new', null], [$m->fresh()->status, $m->fresh()->read_at]);
        $this->admin()->post(route('admin.messages.status', $m->ref), ['status' => 'archived']);
        $this->assertSame('archived', $m->fresh()->status);
        $this->admin()->post(route('admin.messages.status', $m->ref), ['status' => 'replied'])->assertSessionHasErrors('status');

        $this->admin()->delete(route('admin.messages.destroy', $m->ref))->assertSessionHas('status');
        $this->assertNull(ContactMessage::find($m->id));
    }

    public function test_a_quotation_is_totalled_by_the_server_and_emailed(): void
    {
        $m = $this->message();

        $this->admin()->post(route('admin.messages.quote', $m->ref), [
            'prepared' => 'Green Hill School', 'valid_until' => now()->addDays(14)->toDateString(), 'delivery' => 8000, 'terms' => 'Pay within 14 days.',
            'lines' => [['label' => 'UCE Biology Workbook', 'qty' => 120, 'unit' => 26000], ['label' => 'UCE Chemistry Workbook', 'qty' => 60, 'unit' => 26000]],
        ])->assertSessionHas('status');

        Mail::assertSent(GeneralMail::class, function ($g) {
            return $g->hasTo('agnes@school.test') && $g->subjectLine === 'Your WAKATA quotation'
                && $g->rows[0] === ['UCE Biology Workbook', '120', 'UGX 26,000', 'UGX 3,120,000']
                && end($g->totals) === ['Total', 'UGX 4,688,000'] && $g->footerNote === 'Pay within 14 days.';
        });
        $m->refresh();
        $this->assertSame('quoted', $m->status);
        $this->assertSame(4688000, $m->replies->first()->meta['total']);
        $this->assertSame('quotation', $m->replies->first()->kind);
    }

    public function test_a_quotation_needs_lines_and_sensible_numbers(): void
    {
        $m = $this->message();
        $bad = fn ($d) => $this->admin()->post(route('admin.messages.quote', $m->ref), $d);

        $bad(['lines' => []])->assertSessionHasErrors('lines');
        $bad(['lines' => [['label' => 'X', 'qty' => 0, 'unit' => 10]]])->assertSessionHasErrors('lines.0.qty');
        $bad(['lines' => [['label' => 'X', 'qty' => 1, 'unit' => -5]]])->assertSessionHasErrors('lines.0.unit');
        $bad(['lines' => [['label' => '', 'qty' => 1, 'unit' => 5]]])->assertSessionHasErrors('lines.0.label');
        $bad(['valid_until' => '2001-01-01', 'lines' => [['label' => 'X', 'qty' => 1, 'unit' => 5]]])->assertSessionHasErrors('valid_until');

        Mail::assertNothingSent();
        $this->assertSame('new', $m->fresh()->status);
    }

    public function test_customer_questions_are_answered_from_the_inbox(): void
    {
        $t = $this->thread();

        $this->admin()->post(route('admin.messages.reply', 'T-1001'), ['body' => 'It ships tomorrow.'])->assertSessionHas('status');

        $t->refresh();
        $this->assertSame('answered', $t->status);
        $last = SupportMessage::where('support_thread_id', $t->id)->latest('id')->first();
        $this->assertSame(['staff', 'It ships tomorrow.'], [$last->sender, $last->body]);
        $this->assertTrue($t->fresh('messages')->hasUnreadReply());               // new for the customer until they open it
        Mail::assertSent(GeneralMail::class, fn ($g) => $g->hasTo('joseph@example.com'));

        $row = collect(AdminEngagement::payload()['messages'])->firstWhere('id', 'T-1001');
        $this->assertSame('replied', $row['status']);

        $this->admin()->post(route('admin.messages.status', 'T-1001'), ['status' => 'archived']);
        $this->assertSame('closed', $t->fresh()->status);
        $this->admin()->post(route('admin.messages.status', 'T-1001'), ['status' => 'new']);
        $this->assertSame('open', $t->fresh()->status);

        $this->admin()->delete(route('admin.messages.destroy', 'T-1001'))->assertSessionHas('fail');
        $this->assertNotNull(SupportThread::find($t->id));
        $this->admin()->post(route('admin.messages.quote', 'T-1001'), ['lines' => [['label' => 'X', 'qty' => 1, 'unit' => 1]]])->assertSessionHas('fail');
    }

    // ------------------------------------------------------------ Subscribers

    public function test_subscribers_can_be_added_switched_and_removed(): void
    {
        $this->admin()->post(route('admin.subscribers.store'), ['email' => 'New@School.test'])->assertSessionHas('status');
        $s = Subscriber::where('email', 'new@school.test')->firstOrFail();
        $this->assertSame('Added by staff', $s->source);
        $this->admin()->post(route('admin.subscribers.store'), ['email' => 'bad'])->assertSessionHasErrors('email');

        $this->admin()->post(route('admin.subscribers.status', $s), ['status' => 'unsubscribed']);
        $this->assertSame('unsubscribed', $s->fresh()->status);
        $this->admin()->post(route('admin.subscribers.store'), ['email' => 'new@school.test']);          // adding again brings them back
        $this->assertSame('active', $s->fresh()->status);

        $a = Subscriber::create(['email' => 'a@school.test']);
        $b = Subscriber::create(['email' => 'b@school.test']);
        $this->admin()->post(route('admin.subscribers.bulk'), ['action' => 'unsubscribe', 'ids' => [$a->id, $b->id]]);
        $this->assertSame(['unsubscribed', 'unsubscribed'], [$a->fresh()->status, $b->fresh()->status]);
        $this->admin()->post(route('admin.subscribers.bulk'), ['action' => 'delete', 'ids' => [$a->id]]);
        $this->assertNull(Subscriber::find($a->id));
        $this->admin()->delete(route('admin.subscribers.destroy', $b))->assertSessionHas('status');
        $this->assertNull(Subscriber::find($b->id));
    }

    public function test_a_newsletter_goes_to_active_subscribers_with_their_own_unsubscribe_link(): void
    {
        $a = Subscriber::create(['email' => 'a@school.test']);
        $b = Subscriber::create(['email' => 'b@school.test']);
        Subscriber::create(['email' => 'gone@school.test', 'status' => 'unsubscribed']);

        $this->admin()->post(route('admin.subscribers.send'), ['subject' => 'New papers', 'body' => 'UCE mocks are ready.', 'audience' => 'all'])->assertSessionHas('status');

        Mail::assertSent(GeneralMail::class, 2);
        Mail::assertSent(GeneralMail::class, fn ($g) => $g->hasTo('a@school.test') && $g->unsubscribeUrl === route('newsletter.unsubscribe', $a->token));
        Mail::assertNotSent(GeneralMail::class, fn ($g) => $g->hasTo('gone@school.test'));

        $n = Newsletter::firstOrFail();
        $this->assertSame([2, 0, 'New papers', 'all'], [$n->sent_count, $n->failed_count, $n->subject, $n->audience]);
        $this->assertSame(1, count(AdminEngagement::payload()['newsletters']));
    }

    public function test_newsletter_audiences_follow_the_account_type(): void
    {
        Customer::create(['name' => 'Green Hill', 'type' => 'School', 'email' => 'school@x.test', 'phone' => '0772000001', 'password' => 'secret-pass-1']);
        Customer::create(['name' => 'Teacher T', 'type' => 'Teacher', 'email' => 'teacher@x.test', 'phone' => '0772000002', 'password' => 'secret-pass-1']);
        foreach (['school@x.test', 'teacher@x.test', 'nobody@x.test'] as $e) {
            Subscriber::create(['email' => $e]);
        }

        $this->admin()->post(route('admin.subscribers.send'), ['subject' => 'For schools', 'body' => 'Bulk prices.', 'audience' => 'schools']);
        Mail::assertSent(GeneralMail::class, 1);
        Mail::assertSent(GeneralMail::class, fn ($g) => $g->hasTo('school@x.test'));

        // nobody in the audience: say so, send nothing
        Mail::fake();
        Customer::where('type', 'Teacher')->delete();
        $this->admin()->post(route('admin.subscribers.send'), ['subject' => 'For teachers', 'body' => 'Hello teachers.', 'audience' => 'teachers'])->assertSessionHas('fail');
        Mail::assertNothingSent();
    }

    public function test_a_test_newsletter_goes_only_to_the_admin(): void
    {
        Subscriber::create(['email' => 'a@school.test']);

        $this->admin()->post(route('admin.subscribers.send'), ['subject' => 'Draft', 'body' => 'Just checking.', 'audience' => 'all', 'test' => '1'])->assertSessionHas('status');

        Mail::assertSent(GeneralMail::class, 1);
        Mail::assertSent(GeneralMail::class, fn ($g) => $g->hasTo('super_admin@example.com') && str_starts_with($g->subjectLine, '[Test]'));
        $this->assertSame(0, Newsletter::count());                      // a test is not a send
    }

    public function test_a_newsletter_is_checked_and_capped(): void
    {
        Subscriber::create(['email' => 'a@school.test']);
        $this->admin()->post(route('admin.subscribers.send'), ['subject' => '', 'body' => 'x', 'audience' => 'all'])->assertSessionHasErrors(['subject', 'body']);
        $this->admin()->post(route('admin.subscribers.send'), ['subject' => 'S', 'body' => 'Long enough text.', 'audience' => 'everyone'])->assertSessionHasErrors('audience');

        $rows = [];
        for ($i = 0; $i < 1001; $i++) {
            $rows[] = ['email' => "p$i@bulk.test", 'source' => 'Footer', 'status' => 'active', 'token' => str_pad((string) $i, 40, 'x'), 'subscribed_at' => now(), 'created_at' => now(), 'updated_at' => now()];
        }
        foreach (array_chunk($rows, 200) as $chunk) {
            Subscriber::insert($chunk);
        }
        $this->admin()->post(route('admin.subscribers.send'), ['subject' => 'Big', 'body' => 'Too many people.', 'audience' => 'all'])->assertSessionHas('fail');
        Mail::assertNothingSent();
    }

    // ------------------------------------------------------------ Testimonials and Questions

    public function test_testimonials_are_added_edited_hidden_and_deleted(): void
    {
        $data = ['stars' => 5, 'quote' => 'The practical workbooks changed how we teach.', 'initials' => 'ab', 'role' => 'Head of Science', 'place' => 'Wakiso', 'permission' => '1'];

        $this->admin()->post(route('admin.testimonials.store'), $data)->assertSessionHas('status');
        $t = Testimonial::latest('id')->firstOrFail();
        $this->assertSame(['AB', 11, true], [$t->initials, $t->sort_order, $t->visible]);
        $this->get(route('site.home'))->assertSee('changed how we teach');

        $this->admin()->put(route('admin.testimonials.update', $t), ['quote' => 'Updated quote that is long enough.'] + $data);
        $this->assertSame('Updated quote that is long enough.', $t->fresh()->quote);

        $this->admin()->post(route('admin.testimonials.toggle', $t));
        $this->assertFalse($t->fresh()->visible);
        $this->get(route('site.home'))->assertDontSee('Updated quote that is long enough.');

        $this->admin()->delete(route('admin.testimonials.destroy', $t))->assertSessionHas('status');
        $this->assertNull(Testimonial::find($t->id));
    }

    public function test_a_testimonial_needs_permission_and_a_real_quote(): void
    {
        $ok = ['stars' => 5, 'quote' => 'A proper quote about the papers.', 'initials' => 'AB', 'permission' => '1'];
        $count = Testimonial::count();

        $this->admin()->post(route('admin.testimonials.store'), array_diff_key($ok, ['permission' => 1]))->assertSessionHasErrors('permission');
        $this->admin()->post(route('admin.testimonials.store'), ['quote' => 'short'] + $ok)->assertSessionHasErrors('quote');
        $this->admin()->post(route('admin.testimonials.store'), ['stars' => 9] + $ok)->assertSessionHasErrors('stars');
        $this->admin()->post(route('admin.testimonials.store'), ['initials' => ''] + $ok)->assertSessionHasErrors('initials');
        $this->assertSame($count, Testimonial::count());
    }

    public function test_questions_are_added_edited_reordered_and_deleted(): void
    {
        $this->admin()->post(route('admin.faqs.store'), ['page' => 'holiday', 'question' => 'Is there a family discount?', 'answer' => 'Ask us about three or more packages.', 'icon' => 'check'])->assertSessionHas('status');
        $f = Faq::where('question', 'Is there a family discount?')->firstOrFail();
        $this->assertSame(5, $f->sort_order);                          // after the four that were there

        $this->admin()->put(route('admin.faqs.update', $f), ['page' => 'contact', 'question' => 'Family discount?', 'answer' => 'Yes.', 'icon' => 'card']);
        $this->assertSame(['contact', 'Family discount?'], [$f->fresh()->page, $f->fresh()->question]);

        $ids = Faq::where('page', 'contact')->orderBy('sort_order')->pluck('id')->all();
        $this->admin()->postJson(route('admin.faqs.reorder'), ['page' => 'contact', 'ids' => array_reverse($ids)])->assertOk();
        $this->assertSame(array_reverse($ids), Faq::where('page', 'contact')->orderBy('sort_order')->pluck('id')->all());
        $this->admin()->postJson(route('admin.faqs.reorder'), ['page' => 'holiday', 'ids' => $ids])->assertOk();          // ids of another page: ignored
        $this->assertSame(array_reverse($ids), Faq::where('page', 'contact')->orderBy('sort_order')->pluck('id')->all());

        $this->admin()->post(route('admin.faqs.toggle', $f));
        $this->assertFalse($f->fresh()->visible);
        $this->admin()->delete(route('admin.faqs.destroy', $f))->assertSessionHas('status');
        $this->assertNull(Faq::find($f->id));
    }

    public function test_question_details_are_checked(): void
    {
        $ok = ['page' => 'contact', 'question' => 'Q?', 'answer' => 'A.', 'icon' => 'help'];
        $this->admin()->post(route('admin.faqs.store'), ['page' => 'home'] + $ok)->assertSessionHasErrors('page');
        $this->admin()->post(route('admin.faqs.store'), ['icon' => 'rocket'] + $ok)->assertSessionHasErrors('icon');
        $this->admin()->post(route('admin.faqs.store'), ['question' => ''] + $ok)->assertSessionHasErrors('question');
        $this->admin()->post(route('admin.faqs.store'), ['answer' => ''] + $ok)->assertSessionHasErrors('answer');
        $this->assertSame(10, Faq::count());
    }

    // ------------------------------------------------------------ pages and roles

    public function test_the_engagement_pages_carry_the_real_data(): void
    {
        foreach (['messages', 'subscribers', 'testimonials', 'faqs'] as $page) {
            $this->admin()->get(route('admin.' . $page))->assertOk()->assertSee('WK.eng = ', false);
        }
        foreach (['content', 'settings', 'orders'] as $page) {
            $this->admin()->get(route('admin.' . $page))->assertOk()->assertDontSee('WK.eng = ', false);
        }
        $p = AdminEngagement::payload();
        $this->assertCount(10, $p['testimonials']);
        $this->assertCount(10, $p['faqs']);
        $this->assertContains('store', $p['faqIcons']);
        $this->assertNotEmpty($p['catalogue']);
    }

    public function test_roles_decide_who_may_change_what(): void
    {
        $m = $this->message();

        // support answers messages but cannot edit the website or the settings
        $this->admin('support')->post(route('admin.messages.status', $m->ref), ['status' => 'read'])->assertSessionHas('status');
        $this->admin('support')->post(route('admin.content.save'), ['tab' => 'home', 'h1' => 'Hacked'])->assertSessionHas('fail');
        $this->admin('support')->post(route('admin.testimonials.store'), [])->assertSessionHas('fail');
        $this->admin('support')->post(route('admin.settings.general'), ['email' => 'x@y.test', 'goal' => 1])->assertSessionHas('fail');
        $this->assertSame('Quality assessment, priced for every school.', SiteContent::get('home.title'));

        // an editor writes the website but cannot answer customers or touch settings
        $this->admin('editor')->post(route('admin.content.save'), ['tab' => 'home', 'h1' => 'Editor headline'])->assertSessionHas('status');
        $this->admin('editor')->post(route('admin.messages.reply', $m->ref), ['body' => 'Hi there'])->assertSessionHas('fail');
        $this->admin('editor')->post(route('admin.subscribers.send'), ['subject' => 'S', 'body' => 'Long enough text.', 'audience' => 'all'])->assertSessionHas('fail');
        $this->admin('editor')->post(route('admin.settings.downloads'), ['downloads' => 1])->assertSessionHas('fail');
        $this->assertSame(5, config('wakata.download_limit'));
        $this->assertSame('read', $m->fresh()->status);          // only support's own change went through

        // looking is open to everyone signed in
        $this->admin('support')->get(route('admin.content'))->assertOk();
        $this->admin('editor')->get(route('admin.settings'))->assertOk();

        // exports hold customer details: super admin or manager only
        $this->admin('support')->get(route('admin.settings.export', 'customers'))->assertRedirect();
        $this->admin('manager')->get(route('admin.settings.export', 'customers'))->assertOk();
    }

    public function test_guests_cannot_reach_any_of_it(): void
    {
        $m = $this->message();
        $this->post(route('admin.messages.reply', $m->ref), ['body' => 'Hi'])->assertRedirect(route('admin.login'));
        $this->post(route('admin.content.save'), ['tab' => 'home'])->assertRedirect(route('admin.login'));
        $this->post(route('admin.subscribers.send'), [])->assertRedirect(route('admin.login'));
        $this->get(route('admin.settings.export', 'orders'))->assertRedirect(route('admin.login'));
        $this->assertSame(0, Order::count());
    }
}
