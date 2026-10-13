<?php

namespace Tests\Feature;

use App\Models\Book;
use Database\Seeders\CatalogueSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SitePagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CatalogueSeeder::class);
    }

    public static function publicPages(): array
    {
        return [
            'home' => ['site.home'], 'books' => ['site.books'], 'papers' => ['site.papers'], 'sets' => ['site.sets'],
            'packages' => ['site.packages'], 'cart' => ['site.cart'], 'about' => ['site.about'], 'contact' => ['site.contact'],
        ];
    }

    #[DataProvider('publicPages')]
    public function test_public_page_renders_with_the_database_catalogue(string $route): void
    {
        $this->get(route($route))
            ->assertOk()
            ->assertSee('WK.catalogue = ', false)
            ->assertSee('bio-uce-practical', false)
            ->assertSee('pp-uce-mathematics-paper-1-2026', false);
    }

    public function test_book_page_shows_the_requested_book(): void
    {
        $this->get(route('site.book', ['id' => 'phy-uclse-new']))
            ->assertOk()
            ->assertSee('<title>UCLSE New Curriculum Physics Practical Workbook | WAKATA Stationers</title>', false)
            ->assertSee('bookId: "phy-uclse-new"', false)
            ->assertSee('application/ld+json', false)
            ->assertSee('rel="canonical"', false);
    }

    public function test_unknown_book_is_a_branded_404(): void
    {
        $this->get(route('site.book', ['id' => 'no-such-book']))
            ->assertNotFound()
            ->assertSee('We could not find that page');
    }

    public function test_book_page_without_an_id_goes_to_the_shop(): void
    {
        $this->get(route('site.book'))->assertRedirect(route('site.books'));
    }

    public function test_draft_book_is_not_reachable(): void
    {
        Book::where('slug', 'agric-uace')->update(['status' => 'draft']);

        $this->get(route('site.book', ['id' => 'agric-uace']))->assertNotFound();
        $this->get(route('site.books'))->assertDontSee('agric-uace', false);
    }

    public function test_unknown_public_url_shows_the_branded_404(): void
    {
        $this->get('/definitely-not-a-page')->assertNotFound()->assertSee('We could not find that page');
    }

    public function test_admin_and_account_previews_get_the_same_catalogue(): void
    {
        $admin = \App\Models\Admin::create(['name' => 'T', 'email' => 't@example.com', 'password' => 'secret-pass-1', 'role' => 'super_admin', 'status' => 'active']);
        $cust  = \App\Models\Customer::create(['name' => 'C', 'email' => 'c@example.com', 'password' => 'secret-pass-1', 'type' => 'Teacher']);

        $this->withSession(['LoggedAdmin' => $admin->id])->get(route('admin.books'))->assertOk()->assertSee('WK.catalogue = ', false);
        $this->withSession(['LoggedCustomer' => $cust->id])->get(route('account.library'))->assertOk()->assertSee('WK.catalogue = ', false);
    }
}
