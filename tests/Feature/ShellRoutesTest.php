<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ShellRoutesTest extends TestCase
{
    use RefreshDatabase;

    /** Pages behind the sign-in (admin.auth / customer.auth). */
    public static function signedInPages(): array
    {
        $admin = ['dashboard', 'books', 'book-form', 'past-papers', 'paper-form', 'exam-sets', 'set-form', 'packages',
            'package-form', 'taxonomy', 'media', 'inventory', 'orders', 'order', 'payments', 'deliveries', 'customers',
            'messages', 'subscribers', 'testimonials', 'faqs', 'content', 'reports', 'team', 'activity', 'settings',
            'profile'];
        $account = ['dashboard', 'orders', 'order', 'library', 'invoices', 'invoice', 'saved', 'addresses', 'support',
            'profile'];

        $rows = [];
        foreach ($admin as $p) {
            $rows["admin.$p"] = ["admin.$p"];
        }
        foreach ($account as $p) {
            $rows["account.$p"] = ["account.$p"];
        }

        return $rows;
    }

    /** Sign-in screens, open to people who are not signed in. */
    public static function guestPages(): array
    {
        return [
            'admin.login'              => ['admin.login'],
            'admin.forgot-password'    => ['admin.forgot-password'],
            'account.login'            => ['account.login'],
            'account.register'         => ['account.register'],
            'account.forgot-password'  => ['account.forgot-password'],
        ];
    }

    private function signedInAdmin(): array
    {
        $admin = Admin::create(['name' => 'Test Admin', 'email' => 'test.admin@example.com', 'password' => 'secret-pass-1', 'role' => 'super_admin', 'status' => 'active']);

        return ['LoggedAdmin' => $admin->id];
    }

    private function signedInCustomer(): array
    {
        $c = Customer::create(['name' => 'Test Customer', 'email' => 'test.customer@example.com', 'password' => 'secret-pass-1', 'type' => 'Teacher']);

        return ['LoggedCustomer' => $c->id];
    }

    #[DataProvider('signedInPages')]
    public function test_signed_in_page_renders(string $route): void
    {
        $session = str_starts_with($route, 'admin.') ? $this->signedInAdmin() : $this->signedInCustomer();

        $this->withSession($session)->get(route($route))->assertOk();
    }

    #[DataProvider('signedInPages')]
    public function test_signed_in_pages_send_visitors_to_sign_in(string $route): void
    {
        $login = str_starts_with($route, 'admin.') ? 'admin.login' : 'account.login';

        $this->get(route($route))->assertRedirect(route($login));
    }

    #[DataProvider('guestPages')]
    public function test_sign_in_screen_renders(string $route): void
    {
        $this->get(route($route))->assertOk();
    }

    public function test_reset_pages_need_a_valid_link(): void
    {
        $this->get(route('admin.reset-password'))->assertRedirect(route('admin.forgot-password'));
        $this->get(route('account.reset-password'))->assertRedirect(route('account.forgot-password'));
    }

    public function test_roots_redirect_to_dashboards(): void
    {
        $this->get('/admin')->assertRedirect(route('admin.dashboard'));
        $this->get('/account')->assertRedirect(route('account.dashboard'));
    }

    public function test_legacy_html_links_redirect_and_keep_query(): void
    {
        $this->get('/admin/index.html')->assertRedirect(route('admin.dashboard'));
        $this->get('/admin/order.html?id=WK-1')->assertRedirect(route('admin.order') . '?id=WK-1');
        $this->get('/account/login.html?next=orders')->assertRedirect(route('account.login') . '?next=orders');
    }

    public function test_unknown_admin_url_shows_admin_404(): void
    {
        $this->get('/admin/nope')->assertNotFound()->assertSee('Page not found');
    }

    public function test_sidebar_marks_active_link(): void
    {
        $this->withSession($this->signedInAdmin())->get(route('admin.book-form'))->assertSee('class="sb-link on"', false);
        $this->withSession($this->signedInCustomer())->get(route('account.orders'))->assertSee('aria-current="page"', false);
    }

    public function test_shop_pages_set_data_page(): void
    {
        $this->get(route('site.books'))->assertSee('data-page="books"', false);
        $this->get(route('site.cart'))->assertSee('data-page="cartPage"', false);
    }
}
