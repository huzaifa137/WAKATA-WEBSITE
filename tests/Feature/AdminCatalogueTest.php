<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Book;
use App\Models\CatalogueOption;
use App\Models\ExamSet;
use App\Models\HolidayPackage;
use App\Models\Level;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Paper;
use App\Models\StockAdjustment;
use App\Models\Subject;
use App\Support\AdminCatalogue;
use App\Support\Catalogue;
use Database\Seeders\CatalogueSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Module 7: the admin catalogue (books, past papers, exam sets, packages, subjects and levels, media, inventory). */
class AdminCatalogueTest extends TestCase
{
    use RefreshDatabase;

    private string $imgDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CatalogueSeeder::class);
        Storage::fake('local');

        // Uploaded images go to a throw-away folder, never into the real public/assets/img
        $this->imgDir = sys_get_temp_dir() . '/wk-img-' . uniqid();
        File::ensureDirectoryExists($this->imgDir . '/books');
        File::ensureDirectoryExists($this->imgDir . '/papers');
        File::put($this->imgDir . '/logo.png', 'png');
        config(['wakata.image_dir' => $this->imgDir]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->imgDir);
        parent::tearDown();
    }

    private function admin(string $role = 'super_admin'): self
    {
        $a = Admin::where('email', $role . '@example.com')->first()
            ?? Admin::create(['name' => 'Test ' . $role, 'email' => $role . '@example.com', 'password' => 'secret-pass-1', 'role' => $role, 'status' => 'active']);

        return $this->withSession(['LoggedAdmin' => $a->id]);
    }

    private function bookData(array $over = []): array
    {
        return array_merge([
            'title' => 'UCE Test Workbook', 'subject' => 'Biology', 'level' => 'o', 'type' => 'Workbook', 'edition' => '2026 edition',
            'pages' => 120, 'price' => 20000, 'desc' => 'A short description.', 'intent' => 'publish',
        ], $over);
    }

    private function paperData(array $over = []): array
    {
        return array_merge([
            'level' => 'uce', 'subject' => 'Biology', 'paper' => 'Paper 3', 'year' => 2030, 'time' => '3 hours',
            'printOn' => '1', 'print' => 4500, 'pdfOn' => '1', 'pdf' => 3000, 'intent' => 'publish',
        ], $over);
    }

    /** A real 1x1 image (no GD extension needed). The type follows the file name. */
    private function img(string $name = 'pic.jpg'): UploadedFile
    {
        $png  = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';
        $jpeg = '/9j/4AAQSkZJRgABAQEASABIAAD/2wBDAP//////////////////////////////////////////////////////////////////////////////////////wgALCAABAAEBAREA/8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQABPxA=';

        return UploadedFile::fake()->createWithContent($name, base64_decode(str_ends_with($name, '.png') ? $png : $jpeg));
    }

    private function pdf(string $name = 'x.pdf'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, "%PDF-1.4\n%fake\n");
    }

    // ---------------------------------------------------------------- access

    public function test_guests_cannot_change_the_catalogue(): void
    {
        $this->post(route('admin.books.store'), $this->bookData())->assertRedirect(route('admin.login'));
        $this->postJson(route('admin.inventory.adjust'), [])->assertUnauthorized();
        $this->assertSame(0, Book::where('title', 'UCE Test Workbook')->count());
    }

    public function test_support_staff_can_look_but_not_change(): void
    {
        $this->admin('support')->get(route('admin.books'))->assertOk();
        $this->admin('support')->post(route('admin.books.store'), $this->bookData())->assertSessionHas('fail');
        $this->admin('support')->postJson(route('admin.inventory.adjust'), ['kind' => 'book', 'id' => 'bio-uce-practical', 'mode' => 'add', 'qty' => 5, 'reason' => 'print'])->assertForbidden();

        $this->assertSame(0, Book::where('title', 'UCE Test Workbook')->count());
        $this->assertSame(0, Book::where('slug', 'bio-uce-practical')->value('stock'));
    }

    public function test_the_catalogue_pages_carry_the_real_catalogue(): void
    {
        foreach (['books', 'past-papers', 'exam-sets', 'packages', 'taxonomy', 'media', 'inventory', 'book-form', 'paper-form', 'set-form', 'package-form'] as $page) {
            $this->admin()->get(route('admin.' . $page))->assertOk()->assertSee('WK.adm = ', false);
        }
        // pages of later modules do not pay for it
        $this->admin()->get(route('admin.orders'))->assertOk()->assertDontSee('WK.adm = ', false);
    }

    // ---------------------------------------------------------------- books

    public function test_a_book_can_be_added_and_shows_on_the_shop(): void
    {
        $this->admin()->post(route('admin.books.store'), $this->bookData(['old' => 25000, 'badge' => 'Popular', 'featured' => '1', 'stock' => 12]))
            ->assertRedirect(route('admin.books'))->assertSessionHas('status');

        $b = Book::where('title', 'UCE Test Workbook')->firstOrFail();
        $this->assertSame('uce-test-workbook', $b->slug);
        $this->assertSame('published', $b->status);
        $this->assertSame(20000, $b->price);
        $this->assertSame(25000, $b->old_price);
        $this->assertSame('Workbook', $b->format);
        $this->assertSame('Biology', $b->subject->name);
        $this->assertSame('uce', $b->level->code);
        $this->assertMatchesRegularExpression('/^BK-\d+$/', $b->sku);
        $this->assertNotNull($b->featured_order);
        $this->assertSame(12, $b->stock);

        // the stock typed on the form is in the history too
        $this->assertSame(1, StockAdjustment::where('item_slug', $b->slug)->where('delta', 12)->count());

        $shop = Catalogue::frontend();
        $this->assertContains('uce-test-workbook', array_column($shop['books'], 'id'));
        $this->assertContains('uce-test-workbook', $shop['featured']['books']);
    }

    public function test_save_as_draft_hides_the_book_from_the_shop(): void
    {
        $this->admin()->post(route('admin.books.store'), $this->bookData(['intent' => 'draft']))->assertSessionHas('status');

        $this->assertSame('draft', Book::where('title', 'UCE Test Workbook')->value('status'));
        $this->assertNotContains('uce-test-workbook', array_column(Catalogue::frontend()['books'], 'id'));
        // the admin still sees it
        $this->assertContains('uce-test-workbook', array_column(AdminCatalogue::payload()['books'], 'id'));
    }

    public function test_book_details_are_checked(): void
    {
        $this->admin()->post(route('admin.books.store'), $this->bookData(['old' => 15000]))->assertSessionHasErrors('old');
        $this->admin()->post(route('admin.books.store'), $this->bookData(['subject' => 'Astrology']))->assertSessionHasErrors('subject');
        $this->admin()->post(route('admin.books.store'), $this->bookData(['title' => '']))->assertSessionHasErrors('title');
        $this->admin()->post(route('admin.books.store'), $this->bookData(['id' => 'bio-uce-practical']))->assertSessionHasErrors('id');
        $this->admin()->post(route('admin.books.store'), $this->bookData(['id' => 'Bad Slug!']))->assertSessionHasErrors('id');
        $this->admin()->post(route('admin.books.store'), $this->bookData(['img' => UploadedFile::fake()->create('cover.pdf', 10, 'application/pdf')]))->assertSessionHasErrors('img');

        $this->assertSame(0, Book::where('title', 'UCE Test Workbook')->count());
    }

    public function test_a_book_can_be_edited_and_keeps_its_page_address(): void
    {
        $this->admin()->put(route('admin.books.update', 'bio-uce-practical'), $this->bookData(['title' => 'Renamed Biology', 'price' => 30000, 'stock' => 7, 'low' => 3]))
            ->assertRedirect(route('admin.books'));

        $b = Book::where('slug', 'bio-uce-practical')->firstOrFail();
        $this->assertSame('Renamed Biology', $b->title);
        $this->assertSame(30000, $b->price);
        $this->assertSame(7, $b->stock);
        $this->assertSame(3, $b->low_stock_at);
        $this->assertNull($b->featured_order);        // the switch was not sent: no longer featured
        $this->assertSame(1, StockAdjustment::where('item_slug', 'bio-uce-practical')->where('mode', 'set')->count());

        // a changed page address is allowed (the form warns about old links)
        $this->admin()->put(route('admin.books.update', 'bio-uce-practical'), $this->bookData(['id' => 'new-address']))->assertSessionHas('status');
        $this->assertNotNull(Book::where('slug', 'new-address')->first());
    }

    public function test_book_covers_are_stored_and_other_files_are_refused(): void
    {
        $this->admin()->post(route('admin.books.store'), $this->bookData(['img' => $this->img('My Cover.jpg'), 'full' => $this->img('back.png')]))
            ->assertSessionHasNoErrors();

        $b = Book::where('title', 'UCE Test Workbook')->firstOrFail();
        $this->assertMatchesRegularExpression('#^books/my-cover-[a-z0-9]{6}\.jpg$#', $b->cover_image);
        $this->assertFileExists($this->imgDir . '/' . $b->cover_image);
        $this->assertFileExists($this->imgDir . '/' . $b->back_image);
        $this->assertStringContainsString('/assets/img/' . $b->cover_image, AdminCatalogue::payload()['books'][23]['img']);
    }

    public function test_deleting_a_book_never_breaks_old_orders(): void
    {
        $order = Order::create(['number' => 'WK-20001', 'customer_name' => 'A', 'customer_email' => 'a@example.com', 'customer_phone' => '0772000000', 'delivery_method' => 'pickup', 'payment_method' => 'cash', 'payment_status' => 'paid', 'status' => 'completed', 'subtotal' => 28000, 'delivery_fee' => 0, 'total' => 28000]);
        $book  = Book::where('slug', 'bio-uce-practical')->firstOrFail();
        OrderItem::create(['order_id' => $order->id, 'item_type' => 'book', 'item_id' => $book->id, 'item_slug' => $book->slug, 'title' => $book->title, 'unit_price' => 28000, 'quantity' => 1, 'line_total' => 28000]);

        $this->admin()->delete(route('admin.books.destroy', 'bio-uce-practical'))->assertRedirect(route('admin.books'))->assertSessionHas('status');

        $this->assertNull(Book::where('slug', 'bio-uce-practical')->first());
        $this->assertSame(1, OrderItem::where('item_slug', 'bio-uce-practical')->count());
    }

    public function test_a_book_can_be_duplicated_as_a_draft(): void
    {
        $this->admin()->post(route('admin.books.duplicate', 'bio-uce-practical'))->assertSessionHas('status');

        $copy = Book::where('slug', 'bio-uce-practical-copy')->firstOrFail();
        $this->assertSame('draft', $copy->status);
        $this->assertStringEndsWith('(copy)', $copy->title);
        $this->assertNull($copy->featured_order);
        $this->assertNotSame(Book::where('slug', 'bio-uce-practical')->value('sku'), $copy->sku);
    }

    public function test_bulk_actions_on_books(): void
    {
        $a = 'bio-uce-practical';
        $this->admin()->post(route('admin.books.bulk'), ['action' => 'draft', 'ids' => [$a]])->assertSessionHas('status');
        $this->assertSame('draft', Book::where('slug', $a)->value('status'));

        $this->admin()->post(route('admin.books.bulk'), ['action' => 'publish', 'ids' => [$a]]);
        $this->assertSame('published', Book::where('slug', $a)->value('status'));

        $this->admin()->post(route('admin.books.bulk'), ['action' => 'badge', 'ids' => [$a], 'badge' => 'Popular']);
        $this->assertSame('Popular', Book::where('slug', $a)->value('badge'));
        $this->admin()->post(route('admin.books.bulk'), ['action' => 'badge', 'ids' => [$a], 'badge' => '']);
        $this->assertNull(Book::where('slug', $a)->value('badge'));

        // 28,000 + 10% = 30,800; the old price is cleared because the price went up
        Book::where('slug', $a)->update(['old_price' => 35000]);
        $this->admin()->post(route('admin.books.bulk'), ['action' => 'price', 'ids' => [$a], 'mode' => 'up', 'value' => 10]);
        $this->assertSame(30800, Book::where('slug', $a)->value('price'));
        $this->assertSame(35000, Book::where('slug', $a)->value('old_price'));

        // a cut with "show the old price" keeps the previous price struck through
        $this->admin()->post(route('admin.books.bulk'), ['action' => 'price', 'ids' => [$a], 'mode' => 'down', 'value' => 50, 'keepold' => '1']);
        $this->assertSame(15400, Book::where('slug', $a)->value('price'));
        $this->assertSame(30800, Book::where('slug', $a)->value('old_price'));

        $this->admin()->post(route('admin.books.bulk'), ['action' => 'price', 'ids' => [$a], 'mode' => 'set', 'value' => 9000]);
        $this->assertSame(9000, Book::where('slug', $a)->value('price'));

        $this->admin()->post(route('admin.books.bulk'), ['action' => 'price', 'ids' => [$a], 'mode' => 'up'])->assertSessionHasErrors('value');
        $this->admin()->post(route('admin.books.bulk'), ['action' => 'price', 'ids' => [$a], 'mode' => 'up', 'value' => 900])->assertSessionHasErrors('value');

        $this->admin()->post(route('admin.books.bulk'), ['action' => 'delete', 'ids' => [$a]]);
        $this->assertNull(Book::where('slug', $a)->first());
    }

    public function test_the_book_form_loads_for_a_new_and_an_existing_book(): void
    {
        $this->admin()->get(route('admin.book-form'))->assertOk()->assertSee('name="title"', false);
        $this->admin()->get(route('admin.book-form', ['id' => 'bio-uce-practical']))->assertOk()->assertSee('_method', false)->assertSee('delForm', false);
        $this->admin()->get(route('admin.book-form', ['id' => 'no-such-book']))->assertRedirect(route('admin.books'))->assertSessionHas('fail');
    }

    // ---------------------------------------------------------------- past papers

    public function test_a_paper_is_added_with_its_title_slug_and_options(): void
    {
        $this->admin()->post(route('admin.papers.store'), $this->paperData(['bulk' => 50, 'wm' => '1', 'printStock' => 40]))->assertSessionHas('status');

        $p = Paper::where('year', 2030)->firstOrFail();
        $this->assertSame('Biology Paper 3', $p->title);
        $this->assertSame('pp-uce-biology-paper-3-2030', $p->slug);
        $this->assertTrue($p->print_on && $p->pdf_on);
        $this->assertSame(['bulk' => 50, 'watermark' => true, 'auto_email' => false], $p->options);
        $this->assertSame(40, $p->print_stock);
        $this->assertContains($p->slug, array_column(Catalogue::frontend()['papers'], 'id'));

        // primary papers are titled "PLE Biology"
        $this->admin()->post(route('admin.papers.store'), $this->paperData(['level' => 'ple', 'paper' => 'Paper 1', 'year' => 2031]));
        $this->assertSame('PLE Biology', Paper::where('year', 2031)->value('title'));
    }

    public function test_a_paper_that_exists_cannot_be_added_twice(): void
    {
        $this->admin()->post(route('admin.papers.store'), $this->paperData())->assertSessionHas('status');
        $this->admin()->post(route('admin.papers.store'), $this->paperData())->assertSessionHasErrors('year');
        $this->assertSame(1, Paper::where('year', 2030)->count());
    }

    public function test_a_published_paper_needs_a_format_but_a_draft_does_not(): void
    {
        $none = $this->paperData(['printOn' => null, 'pdfOn' => null, 'print' => null, 'pdf' => null]);
        $this->admin()->post(route('admin.papers.store'), $none)->assertSessionHasErrors('printOn');
        $this->admin()->post(route('admin.papers.store'), array_merge($none, ['intent' => 'draft']))->assertSessionHas('status');

        $p = Paper::where('year', 2030)->firstOrFail();
        $this->assertSame('draft', $p->status);
        $this->assertSame(4000, $p->print_price);     // the UCE default price fills in
    }

    public function test_paper_prices_are_needed_for_the_formats_that_are_on(): void
    {
        $this->admin()->post(route('admin.papers.store'), $this->paperData(['print' => null]))->assertSessionHasErrors('print');
        $this->admin()->post(route('admin.papers.store'), $this->paperData(['year' => 1800]))->assertSessionHasErrors('year');
    }

    public function test_a_paper_pdf_is_kept_privately_and_only_admins_can_fetch_it(): void
    {
        $this->admin()->post(route('admin.papers.store'), $this->paperData(['pdffile' => $this->pdf('paper.pdf'), 'guide' => $this->pdf('guide.pdf')]))->assertSessionHasNoErrors();

        $p = Paper::where('year', 2030)->firstOrFail();
        $this->assertSame('papers/' . $p->slug . '.pdf', $p->pdf_path);
        Storage::disk('local')->assertExists($p->pdf_path);
        Storage::disk('local')->assertExists($p->guide_path);
        $this->assertFileDoesNotExist(public_path($p->pdf_path));

        $row = collect(AdminCatalogue::payload()['papers'])->firstWhere('id', $p->slug);
        $this->assertTrue($row['hasFile']);
        $this->assertTrue($row['hasGuide']);

        $this->admin()->get(route('admin.papers.file', $p->slug))->assertOk()->assertHeader('content-disposition');
        $this->flushSession();
        $this->get(route('admin.papers.file', $p->slug))->assertRedirect(route('admin.login'));
        $this->admin()->get(route('admin.papers.file', 'pp-uce-agriculture-paper-1-2026'))->assertNotFound();
    }

    public function test_a_pdf_can_be_uploaded_later_and_must_be_a_pdf(): void
    {
        $slug = 'pp-uce-agriculture-paper-1-2026';
        $this->admin()->post(route('admin.papers.upload', $slug), ['pdffile' => $this->img('x.png')])->assertSessionHasErrors('pdffile');
        $this->admin()->post(route('admin.papers.upload', $slug), ['pdffile' => $this->pdf()])->assertSessionHas('status');

        Storage::disk('local')->assertExists('papers/' . $slug . '.pdf');
        $this->assertSame('papers/' . $slug . '.pdf', Paper::where('slug', $slug)->value('pdf_path'));
    }

    public function test_switching_a_format_off_hides_it_in_the_shop_and_the_cart(): void
    {
        $slug = 'pp-uce-agriculture-paper-1-2026';

        $this->admin()->patchJson(route('admin.papers.quick', $slug), ['print_on' => false])->assertOk()->assertJson(['printOn' => false, 'pdfOn' => true]);

        $row = collect(Catalogue::frontend()['papers'])->firstWhere('id', $slug);
        $this->assertTrue($row['noPrint']);
        $this->assertArrayNotHasKey('noPdf', $row);

        $this->postJson(route('cart.add'), ['items' => [['type' => 'paper', 'id' => $slug, 'fmt' => 'print']]])->assertJson(['ok' => false, 'count' => 0]);
        $this->postJson(route('cart.add'), ['items' => [['type' => 'paper', 'id' => $slug, 'fmt' => 'pdf']]])->assertJson(['ok' => true, 'count' => 1]);

        // with both off the paper is not for sale at all
        $this->admin()->patchJson(route('admin.papers.quick', $slug), ['pdf_on' => false])->assertOk();
        $this->assertNotContains($slug, array_column(Catalogue::frontend()['papers'], 'id'));
    }

    public function test_paper_prices_can_be_changed_in_the_table(): void
    {
        $slug = 'pp-uce-agriculture-paper-1-2026';
        $this->admin()->patchJson(route('admin.papers.quick', $slug), ['print_price' => 4500, 'pdf_price' => 2800])->assertOk()->assertJson(['print' => 4500, 'pdf' => 2800]);
        $this->admin()->patchJson(route('admin.papers.quick', $slug), ['print_price' => -5])->assertStatus(422);

        $row = collect(Catalogue::frontend()['papers'])->firstWhere('id', $slug);
        $this->assertSame([4500, 2800], [$row['print'], $row['pdf']]);
    }

    public function test_bulk_actions_and_default_prices_for_papers(): void
    {
        $ids = Paper::where('subject_id', Subject::where('name', 'Agriculture')->value('id'))->pluck('slug')->all();
        $this->assertNotEmpty($ids);

        $this->admin()->post(route('admin.papers.bulk'), ['action' => 'price', 'ids' => $ids, 'format' => 'pdf', 'price' => 1234]);
        $this->assertSame([1234], Paper::whereIn('slug', $ids)->pluck('pdf_price')->unique()->values()->all());
        $this->admin()->post(route('admin.papers.bulk'), ['action' => 'draft', 'ids' => $ids]);
        $this->assertSame(['draft'], Paper::whereIn('slug', $ids)->pluck('status')->unique()->values()->all());
        $this->admin()->post(route('admin.papers.bulk'), ['action' => 'price', 'ids' => $ids])->assertSessionHasErrors('price');

        // default prices are saved, and optionally applied to the whole level
        $this->admin()->post(route('admin.papers.defaults'), ['level' => 'uace', 'print' => 5500, 'pdf' => 3500, 'time' => '3 hours'])->assertSessionHas('status');
        $this->assertSame(5500, AdminCatalogue::paperDefaults()['uace']['print']);
        $uace = Level::where('code', 'uace')->value('id');
        $this->assertNotSame([5500], Paper::where('level_id', $uace)->pluck('print_price')->unique()->values()->all());

        $this->admin()->post(route('admin.papers.defaults'), ['level' => 'uace', 'print' => 5500, 'pdf' => 3500, 'time' => '3 hours', 'apply' => '1']);
        $this->assertSame([5500], Paper::where('level_id', $uace)->pluck('print_price')->unique()->values()->all());
        $this->assertSame(['3 hours'], Paper::where('level_id', $uace)->pluck('duration')->unique()->values()->all());
    }

    public function test_bulk_pdf_upload_matches_files_by_name(): void
    {
        $this->admin()->post(route('admin.papers.bulk-upload'), ['files' => [
            $this->pdf('agriculture-paper1-2026.pdf'),       // matches one UCE paper
            $this->pdf('Agriculture_Paper2_2026.pdf'),       // case and underscores are fine
            $this->pdf('nonsense.pdf'),                      // no pattern
            $this->pdf('astronomy-paper1-2026.pdf'),         // no such subject
        ]])->assertSessionHas('status')->assertSessionHas('fail');

        Storage::disk('local')->assertExists('papers/pp-uce-agriculture-paper-1-2026.pdf');
        Storage::disk('local')->assertExists('papers/pp-uce-agriculture-paper-2-2026.pdf');
        $this->assertStringContainsString('nonsense.pdf', session('fail'));
        $this->assertStringContainsString('astronomy', session('fail'));

        // two levels have a Biology Paper 1 for 2023: the level in front picks one
        $this->admin()->post(route('admin.papers.bulk-upload'), ['files' => [$this->pdf('biology-paper1-2023.pdf')]])->assertSessionHas('fail');
        $this->assertStringContainsString('add the level', session('fail'));
        $this->admin()->post(route('admin.papers.bulk-upload'), ['files' => [$this->pdf('uace-biology-paper1-2023.pdf')]])->assertSessionHas('status');
        Storage::disk('local')->assertExists('papers/pp-uace-biology-paper-1-2023.pdf');
    }

    public function test_deleting_a_paper_removes_its_files(): void
    {
        $slug = 'pp-uce-agriculture-paper-1-2026';
        $this->admin()->post(route('admin.papers.upload', $slug), ['pdffile' => $this->pdf()]);
        Storage::disk('local')->assertExists('papers/' . $slug . '.pdf');

        $this->admin()->delete(route('admin.papers.destroy', $slug))->assertSessionHas('status');

        $this->assertNull(Paper::where('slug', $slug)->first());
        Storage::disk('local')->assertMissing('papers/' . $slug . '.pdf');
    }

    // ---------------------------------------------------------------- exam sets and packages

    public function test_an_exam_set_is_added_edited_and_deleted(): void
    {
        $data = ['title' => 'UCE Test Set', 'level' => 'uce', 'year' => '2030', 'papers' => 10, 'blurb' => 'Ten papers.', 'subjects' => ['Biology', ' Chemistry ', ''], 'price' => 90000, 'worth' => 120000,
            'printOn' => '1', 'guides' => '1', 'stock' => 5, 'intent' => 'publish',
            'preview1' => $this->img('one.jpg'), 'guidefile' => $this->pdf('guides.pdf')];

        $this->admin()->post(route('admin.exam-sets.store'), $data)->assertSessionHasNoErrors()->assertSessionHas('status');

        $s = ExamSet::where('title', 'UCE Test Set')->firstOrFail();
        $this->assertSame('set-uce-2030', $s->slug);
        $this->assertSame(['Biology', 'Chemistry'], $s->contents);
        $this->assertCount(1, $s->previews);
        $this->assertFileExists($this->imgDir . '/papers/' . $s->previews[0]);
        $this->assertSame(['print_on' => true, 'pdf_on' => false, 'guides' => true], $s->options);
        $this->assertSame(5, $s->stock);
        Storage::disk('local')->assertExists('guides/set-uce-2030.pdf');
        $this->assertSame('guides/set-uce-2030.pdf', $s->guide_path);
        $this->assertSame(10, collect(Catalogue::frontend()['sets'])->firstWhere('id', 'set-uce-2030')['papers']);

        // editing keeps the old preview unless a new one is chosen
        $keep = $s->previews[0];
        $this->admin()->put(route('admin.exam-sets.update', $s->slug), ['title' => 'UCE Test Set v2', 'price' => 95000, 'worth' => 100000, 'intent' => 'draft'] + array_diff_key($data, array_flip(['preview1', 'guidefile'])))->assertSessionHas('status');
        $s->refresh();
        $this->assertSame('UCE Test Set v2', $s->title);
        $this->assertSame('draft', $s->status);
        $this->assertSame([$keep], array_slice($s->previews, 0, 1));

        $this->admin()->delete(route('admin.exam-sets.destroy', $s->slug))->assertSessionHas('status');
        $this->assertNull(ExamSet::where('slug', 'set-uce-2030')->first());
        Storage::disk('local')->assertMissing('guides/set-uce-2030.pdf');
    }

    public function test_exam_set_prices_are_checked(): void
    {
        $base = ['title' => 'X', 'level' => 'uce', 'papers' => 4, 'price' => 90000];
        $this->admin()->post(route('admin.exam-sets.store'), $base + ['worth' => 50000])->assertSessionHasErrors('worth');
        $this->admin()->post(route('admin.exam-sets.store'), ['title' => 'X', 'level' => 'uce', 'price' => 1])->assertSessionHasErrors('papers');

        // "worth" left empty means no saving is shown
        $this->admin()->post(route('admin.exam-sets.store'), $base + ['year' => '2031'])->assertSessionHasNoErrors();
        $this->assertSame(90000, ExamSet::where('slug', 'set-uce-2031')->value('worth_price'));
    }

    public function test_a_package_is_added_edited_and_deleted(): void
    {
        $data = ['name' => 'UCE Test Pack', 'for' => 'Senior 4 candidates', 'level' => 'uce', 'price' => 70000, 'includes' => ['Workbook', ' ', 'Mock papers'], 'best' => '1', 'swap' => '1', 'stock' => 9, 'intent' => 'publish', 'timetablefile' => $this->pdf('t.pdf')];

        $this->admin()->post(route('admin.packages.store'), $data)->assertSessionHasNoErrors()->assertSessionHas('status');

        $k = HolidayPackage::where('name', 'UCE Test Pack')->firstOrFail();
        $this->assertSame('hp-uce-test-pack', $k->slug);
        $this->assertSame(['Workbook', 'Mock papers'], $k->includes);
        $this->assertTrue($k->is_best);
        $this->assertSame(['swap' => true], $k->options);
        $this->assertSame(9, $k->stock);
        Storage::disk('local')->assertExists('timetables/hp-uce-test-pack.pdf');

        $this->admin()->put(route('admin.packages.update', $k->slug), ['name' => 'UCE Test Pack 2', 'for' => 'S4', 'level' => 'uce', 'price' => 71000, 'intent' => 'publish'])->assertSessionHas('status');
        $k->refresh();
        $this->assertFalse($k->is_best);                       // the switch was not sent
        $this->assertSame([], $k->includes);
        $this->assertSame('hp-uce-test-pack', $k->slug);

        $this->admin()->delete(route('admin.packages.destroy', $k->slug))->assertSessionHas('status');
        $this->assertNull(HolidayPackage::where('slug', 'hp-uce-test-pack')->first());
        Storage::disk('local')->assertMissing('timetables/hp-uce-test-pack.pdf');
    }

    public function test_skus_are_unique(): void
    {
        $other = Book::where('slug', 'bio-uce-practical')->value('sku');
        $this->admin()->post(route('admin.books.store'), $this->bookData(['sku' => $other]))->assertSessionHasErrors('sku');
        $this->admin()->post(route('admin.books.store'), $this->bookData(['sku' => 'MY-1']))->assertSessionHasNoErrors();
        $this->assertSame('MY-1', Book::where('title', 'UCE Test Workbook')->value('sku'));
    }

    // ---------------------------------------------------------------- subjects and levels

    public function test_levels_can_be_renamed_but_keys_stay_unique(): void
    {
        $this->admin()->put(route('admin.taxonomy.levels'), ['l-PLE' => 'Primary 7', 's-PLE' => 'PLE', 'k-PLE' => 'primary', 'l-UCE' => 'Senior 4', 's-UCE' => 'UCE', 'k-UCE' => 'o', 'l-UACE' => 'Senior 6', 's-UACE' => 'UACE', 'k-UACE' => 'a'])->assertSessionHas('status');
        $this->assertSame('Senior 4', Level::where('code', 'uce')->value('label'));
        $this->assertSame('Senior 4', Catalogue::frontend()['LEVELS']['uce']['label']);

        $dup = ['l-PLE' => 'A', 's-PLE' => 'PLE', 'k-PLE' => 'same', 'l-UCE' => 'B', 's-UCE' => 'UCE', 'k-UCE' => 'same', 'l-UACE' => 'C', 's-UACE' => 'UACE', 'k-UACE' => 'a'];
        $this->admin()->put(route('admin.taxonomy.levels'), $dup)->assertSessionHasErrors('k-UCE');
        $this->admin()->put(route('admin.taxonomy.levels'), array_merge($dup, ['k-UCE' => 'Bad Key']))->assertSessionHasErrors('k-UCE');
        $this->assertSame('o', Level::where('code', 'uce')->value('book_code'));
    }

    public function test_subjects_can_be_added_edited_and_only_deleted_when_unused(): void
    {
        $color = ['c1' => '#112233', 'c2' => '#445566', 'c3' => '#ffd43b'];
        $this->admin()->post(route('admin.taxonomy.subjects.store'), ['name' => 'Astronomy'] + $color)->assertSessionHas('status');

        $s = Subject::where('name', 'Astronomy')->firstOrFail();
        $this->assertSame('#FFD43B', $s->color_3);
        $this->assertSame(['#112233', '#445566', '#FFD43B'], Catalogue::frontend()['SUBJECTS']['Astronomy']);

        $this->admin()->post(route('admin.taxonomy.subjects.store'), ['name' => 'Astronomy'] + $color)->assertSessionHasErrors('name');
        $this->admin()->post(route('admin.taxonomy.subjects.store'), ['name' => 'Bad colour', 'c1' => 'red', 'c2' => '#000000', 'c3' => '#000000'])->assertSessionHasErrors('c1');

        $this->admin()->put(route('admin.taxonomy.subjects.update', $s), ['name' => 'Space science'] + $color)->assertSessionHas('status');
        $this->assertSame('Space science', $s->refresh()->name);

        // a used subject stays
        $biology = Subject::where('name', 'Biology')->firstOrFail();
        $this->admin()->delete(route('admin.taxonomy.subjects.destroy', $biology))->assertSessionHas('fail');
        $this->assertNotNull($biology->fresh());

        $this->admin()->delete(route('admin.taxonomy.subjects.destroy', $s))->assertSessionHas('status');
        $this->assertNull($s->fresh());
    }

    public function test_book_types_and_badges_are_saved_as_lists(): void
    {
        $this->admin()->put(route('admin.taxonomy.lists'), ['types' => ['Textbook', ' Atlas ', 'Textbook', ''], 'badges' => ['Hot']])->assertSessionHas('status');

        $this->assertSame(['Textbook', 'Atlas'], CatalogueOption::read('book_types'));
        $this->assertSame(['Hot'], AdminCatalogue::badges());

        $this->admin()->put(route('admin.taxonomy.lists'), ['types' => [], 'badges' => []])->assertSessionHasErrors('types');
        $this->assertSame(['Textbook', 'Atlas'], CatalogueOption::read('book_types'));

        // the book form offers the saved lists
        $this->admin()->get(route('admin.book-form'))->assertSee('Atlas')->assertSee('Hot');
    }

    // ---------------------------------------------------------------- media

    public function test_images_are_uploaded_listed_replaced_and_deleted(): void
    {
        $this->admin()->post(route('admin.media.store'), ['kind' => 'papers', 'images' => [$this->img('sheet.png'), $this->img('two.jpg')]])->assertSessionHas('status');
        $this->assertCount(2, glob($this->imgDir . '/papers/*'));
        $this->admin()->post(route('admin.media.store'), ['kind' => 'books', 'images' => [UploadedFile::fake()->create('a.pdf', 5, 'application/pdf')]])->assertSessionHasErrors('images.0');
        $this->admin()->post(route('admin.media.store'), ['kind' => 'secrets', 'images' => [$this->img('a.png')]])->assertSessionHasErrors('kind');

        // use one as a book cover: the library says so
        $this->admin()->post(route('admin.books.store'), $this->bookData(['img' => $this->img('cover.jpg')]));
        $book = Book::where('title', 'UCE Test Workbook')->firstOrFail();

        $media = collect(AdminCatalogue::payload()['media']);
        $this->assertStringContainsString('Cover: UCE Test Workbook', $media->firstWhere('path', $book->cover_image)['use']);
        $this->assertSame('Not used yet', $media->firstWhere('kind', 'papers')['use']);
        $this->assertTrue($media->firstWhere('path', 'logo.png')['locked']);

        // replacing needs the same file type
        $this->admin()->post(route('admin.media.replace'), ['path' => $book->cover_image, 'file' => $this->img('new.png')])->assertSessionHas('fail');
        $this->admin()->post(route('admin.media.replace'), ['path' => $book->cover_image, 'file' => $this->img('new.jpg')])->assertSessionHas('status');

        // deleting a used cover just removes it from the book
        $this->admin()->post(route('admin.media.destroy'), ['path' => $book->cover_image])->assertSessionHas('status');
        $this->assertFileDoesNotExist($this->imgDir . '/' . $book->cover_image);
        $this->assertNull($book->fresh()->cover_image);
    }

    public function test_the_logo_and_odd_paths_are_protected(): void
    {
        $this->admin()->post(route('admin.media.destroy'), ['path' => 'logo.png'])->assertSessionHas('fail');
        $this->admin()->post(route('admin.media.destroy'), ['path' => '../../.env'])->assertSessionHas('fail');
        $this->admin()->post(route('admin.media.replace'), ['path' => 'books/../../x.jpg', 'file' => $this->img('a.jpg')])->assertSessionHas('fail');
        $this->assertFileExists($this->imgDir . '/logo.png');
    }

    public function test_an_image_in_an_exam_set_preview_cannot_be_deleted(): void
    {
        File::put($this->imgDir . '/papers/sheet-a.jpg', 'x');
        ExamSet::where('slug', 'set-uce-2026')->update(['previews' => ['sheet-a.jpg']]);

        $this->admin()->post(route('admin.media.destroy'), ['path' => 'papers/sheet-a.jpg'])->assertSessionHas('fail');
        $this->assertFileExists($this->imgDir . '/papers/sheet-a.jpg');
    }

    // ---------------------------------------------------------------- inventory

    public function test_stock_can_be_added_removed_and_set(): void
    {
        $adj = fn (array $d) => $this->admin()->postJson(route('admin.inventory.adjust'), $d + ['kind' => 'book', 'id' => 'bio-uce-practical', 'reason' => 'print']);

        $adj(['mode' => 'add', 'qty' => 50])->assertOk()->assertJson(['stock' => 50, 'delta' => 50]);
        $adj(['mode' => 'remove', 'qty' => 8, 'reason' => 'damage', 'note' => 'Wet shelf'])->assertOk()->assertJson(['stock' => 42, 'delta' => -8]);
        $adj(['mode' => 'remove', 'qty' => 500])->assertOk()->assertJson(['stock' => 0, 'delta' => -42]);     // never below zero
        $adj(['mode' => 'set', 'qty' => 15, 'reason' => 'count'])->assertOk()->assertJson(['stock' => 15, 'delta' => 15]);

        $this->assertSame(15, Book::where('slug', 'bio-uce-practical')->value('stock'));
        $log = StockAdjustment::where('item_slug', 'bio-uce-practical')->orderBy('id')->get();
        $this->assertSame([50, -8, -42, 15], $log->pluck('delta')->all());
        $this->assertSame('Wet shelf', $log[1]->note);
        $this->assertSame('Test super_admin', $log[0]->admin_name);
    }

    public function test_every_kind_of_item_has_stock(): void
    {
        foreach ([['paper', 'pp-uce-agriculture-paper-1-2026', Paper::class, 'print_stock'], ['set', 'set-uce-2026', ExamSet::class, 'stock'], ['pack', 'hp-p4-p6', HolidayPackage::class, 'stock']] as [$kind, $slug, $model, $col]) {
            $this->admin()->postJson(route('admin.inventory.adjust'), ['kind' => $kind, 'id' => $slug, 'mode' => 'add', 'qty' => 12, 'reason' => 'print'])->assertOk()->assertJson(['stock' => 12]);
            $this->assertSame(12, $model::where('slug', $slug)->value($col));
        }
    }

    public function test_stock_changes_are_checked(): void
    {
        $base = ['kind' => 'book', 'id' => 'bio-uce-practical', 'reason' => 'print'];
        $this->admin()->postJson(route('admin.inventory.adjust'), $base + ['mode' => 'add', 'qty' => 0])->assertStatus(422);
        $this->admin()->postJson(route('admin.inventory.adjust'), $base + ['mode' => 'add', 'qty' => -3])->assertStatus(422);
        $this->admin()->postJson(route('admin.inventory.adjust'), $base + ['mode' => 'add', 'qty' => 'lots'])->assertStatus(422);
        $this->admin()->postJson(route('admin.inventory.adjust'), $base + ['mode' => 'fly', 'qty' => 3])->assertStatus(422);
        $this->admin()->postJson(route('admin.inventory.adjust'), ['kind' => 'ghost', 'id' => 'x', 'mode' => 'add', 'qty' => 3, 'reason' => 'print'])->assertStatus(422);
        $this->admin()->postJson(route('admin.inventory.adjust'), ['kind' => 'book', 'id' => 'no-such', 'mode' => 'add', 'qty' => 3, 'reason' => 'print'])->assertNotFound();
        $this->admin()->postJson(route('admin.inventory.adjust'), $base + ['mode' => 'set', 'qty' => 0])->assertOk();    // zero is fine when counting

        $this->assertSame(1, StockAdjustment::count());
    }

    public function test_the_alert_level_can_be_set_for_many_items(): void
    {
        $this->admin()->postJson(route('admin.inventory.alert'), ['items' => ['book:bio-uce-practical', 'set:set-uce-2026', 'paper:pp-uce-agriculture-paper-1-2026', 'book:nothing'], 'level' => 7])->assertOk()->assertJson(['updated' => 3, 'level' => 7]);

        $this->assertSame(7, Book::where('slug', 'bio-uce-practical')->value('low_stock_at'));
        $this->assertSame(7, ExamSet::where('slug', 'set-uce-2026')->value('low_stock_at'));
        $this->assertSame(7, Paper::where('slug', 'pp-uce-agriculture-paper-1-2026')->value('low_stock_at'));
        $this->admin()->postJson(route('admin.inventory.alert'), ['items' => ['book:bio-uce-practical'], 'level' => -1])->assertStatus(422);
    }

    // ---------------------------------------------------------------- numbers on the pages

    public function test_sales_counts_only_paid_orders_that_were_not_cancelled(): void
    {
        $make = function (string $no, string $pay, string $status, int $qty, bool $physical = true) {
            $o = Order::create(['number' => $no, 'customer_name' => 'A', 'customer_email' => 'a@example.com', 'customer_phone' => '0772000000', 'delivery_method' => 'pickup', 'payment_method' => 'cash', 'payment_status' => $pay, 'status' => $status, 'subtotal' => 1, 'delivery_fee' => 0, 'total' => 1]);
            OrderItem::create(['order_id' => $o->id, 'item_type' => 'book', 'item_id' => 1, 'item_slug' => 'bio-uce-practical', 'title' => 'B', 'unit_price' => 1, 'quantity' => $qty, 'line_total' => $qty, 'is_physical' => $physical]);
        };
        $make('WK-1', 'paid', 'completed', 3);
        $make('WK-2', 'paid', 'processing', 2);
        $make('WK-3', 'pending', 'pending', 40);
        $make('WK-4', 'paid', 'cancelled', 40);
        $make('WK-5', 'paid', 'refunded', 40);

        $payload = AdminCatalogue::payload();
        $this->assertSame(5, $payload['sold']['book']['bio-uce-practical']);
        $this->assertSame(5, array_sum($payload['movement']['sold']));
        $this->assertCount(10, $payload['movement']['labels']);
    }

    public function test_the_movement_chart_counts_stock_received_this_week(): void
    {
        $this->admin()->postJson(route('admin.inventory.adjust'), ['kind' => 'book', 'id' => 'bio-uce-practical', 'mode' => 'add', 'qty' => 30, 'reason' => 'print']);
        $this->admin()->postJson(route('admin.inventory.adjust'), ['kind' => 'book', 'id' => 'bio-uce-practical', 'mode' => 'remove', 'qty' => 5, 'reason' => 'damage']);

        $received = AdminCatalogue::payload()['movement']['received'];
        $this->assertSame(30, $received[9]);          // only additions, in the current week
        $this->assertSame(30, array_sum($received));
    }

    public function test_the_payload_has_the_admin_only_fields(): void
    {
        $p = AdminCatalogue::payload();
        $book = collect($p['books'])->firstWhere('id', 'bio-uce-practical');

        $this->assertSame(['id', 'title', 'subject', 'level', 'price', 'old', 'badge', 'img', 'imgPath', 'full', 'fullPath', 'pages', 'edition', 'type', 'desc', 'meta', 'sku', 'stock', 'low', 'status', 'featured'], array_keys($book));
        $this->assertSame('o', $book['level']);
        $this->assertSame(23, count($p['books']));
        $this->assertSame(129, count($p['papers']));
        $this->assertSame(['ple', 'uce', 'uace'], array_column($p['levels'], 'code'));
        $this->assertSame(['print' => 4000, 'pdf' => 2500, 'time' => '2 hours 15 minutes'], $p['paperDefaults']['uce']);
    }

    public function test_drafts_stay_visible_to_the_admin_only(): void
    {
        Book::where('slug', 'bio-uce-practical')->update(['status' => 'draft']);
        Paper::where('slug', 'pp-uce-agriculture-paper-1-2026')->update(['status' => 'draft']);
        Catalogue::flush();

        $this->assertNotContains('bio-uce-practical', array_column(Catalogue::frontend()['books'], 'id'));
        $this->assertNotContains('pp-uce-agriculture-paper-1-2026', array_column(Catalogue::frontend()['papers'], 'id'));
        $this->assertContains('bio-uce-practical', array_column(AdminCatalogue::payload()['books'], 'id'));
        $this->assertContains('pp-uce-agriculture-paper-1-2026', array_column(AdminCatalogue::payload()['papers'], 'id'));
        $this->get(route('site.book', ['id' => 'bio-uce-practical']))->assertNotFound();
    }
}
