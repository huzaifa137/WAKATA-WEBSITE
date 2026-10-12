<?php

namespace Tests\Feature;

use Tests\TestCase;

class ShellRoutesTest extends TestCase
{
    public static function pages(): array
    {
        $admin = ['dashboard', 'books', 'book-form', 'past-papers', 'paper-form', 'exam-sets', 'set-form', 'packages',
            'package-form', 'taxonomy', 'media', 'inventory', 'orders', 'order', 'payments', 'deliveries', 'customers',
            'messages', 'subscribers', 'testimonials', 'faqs', 'content', 'reports', 'team', 'activity', 'settings',
            'profile', 'login', 'forgot-password', 'reset-password', 'verify', 'locked'];
        $account = ['dashboard', 'orders', 'order', 'library', 'invoices', 'invoice', 'saved', 'addresses', 'support',
            'profile', 'login', 'register', 'forgot-password', 'reset-password'];

        $rows = [];
        foreach ($admin as $p) {
            $rows["admin.$p"] = ["admin.$p"];
        }
        foreach ($account as $p) {
            $rows["account.$p"] = ["account.$p"];
        }

        return $rows;
    }

    /** @dataProvider pages */
    public function test_page_renders(string $route): void
    {
        $this->get(route($route))->assertOk();
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
        $this->get(route('admin.book-form'))->assertSee('class="sb-link on"', false);
        $this->get(route('account.orders'))->assertSee('aria-current="page"', false);
    }

    public function test_shop_pages_set_data_page(): void
    {
        $this->get(route('site.books'))->assertSee('data-page="books"', false);
        $this->get(route('site.cart'))->assertSee('data-page="cartPage"', false);
    }
}
