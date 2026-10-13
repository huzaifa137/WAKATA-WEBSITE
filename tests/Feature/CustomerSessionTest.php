<?php

namespace Tests\Feature;

use App\Models\Customer;
use Database\Seeders\CatalogueSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** How the site recognises a signed-in customer: the session, or the "keep me signed in" cookie. */
class CustomerSessionTest extends TestCase
{
    use RefreshDatabase;

    private const COOKIE = 'wk_customer_remember';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CatalogueSeeder::class);
    }

    private function customer(array $over = []): Customer
    {
        return Customer::create(array_merge(['name' => 'Joseph Ssemakula', 'email' => 'joseph@example.com', 'phone' => '0772123456', 'password' => 'secret-pass-1'], $over));
    }

    private function remembered(Customer $c, string $token = 'abc123'): array
    {
        $c->forceFill(['remember_token' => hash('sha256', $token)])->save();

        return [self::COOKIE => $c->id . '|' . $token];
    }

    public function test_the_public_header_shows_the_signed_in_customer(): void
    {
        $c = $this->customer();

        $this->withSession(['LoggedCustomer' => $c->id])->get(route('site.home'))
            ->assertOk()->assertSee('"email":"joseph@example.com"', false);

        $this->flushSession();
        $this->get(route('site.home'))->assertOk()->assertSee('user: null', false);
    }

    public function test_a_valid_remember_cookie_signs_the_customer_back_in_with_a_fresh_session(): void
    {
        $c = $this->customer();
        $cookies = $this->remembered($c);

        $this->withCookies($cookies)->get(route('site.home'))
            ->assertOk()->assertSee('"email":"joseph@example.com"', false);

        $this->assertSame($c->id, session('LoggedCustomer'));
    }

    public function test_the_remember_cookie_also_opens_the_portal(): void
    {
        $c = $this->customer();

        $this->withCookies($this->remembered($c))->get(route('account.dashboard'))->assertOk();
    }

    public function test_a_wrong_or_forged_remember_cookie_is_ignored(): void
    {
        $c = $this->customer();
        $this->remembered($c);

        $this->withCookies([self::COOKIE => $c->id . '|wrong-token'])->get(route('account.dashboard'))
            ->assertRedirect(route('account.login'));
        $this->withCookies([self::COOKIE => 'garbage'])->get(route('account.dashboard'))
            ->assertRedirect(route('account.login'));
    }

    public function test_a_suspended_customer_is_signed_out_everywhere(): void
    {
        $c = $this->customer(['status' => 'suspended']);

        $this->withSession(['LoggedCustomer' => $c->id])->get(route('account.dashboard'))->assertRedirect(route('account.login'));
        $this->flushSession();
        $this->withCookies($this->remembered($c))->get(route('site.home'))->assertSee('user: null', false);
    }

    public function test_signing_in_and_out(): void
    {
        $c = $this->customer();

        $this->post(route('account.login.submit'), ['email' => 'JOSEPH@example.com', 'password' => 'wrong'])->assertSessionHasErrors('email');
        $this->post(route('account.login.submit'), ['email' => 'JOSEPH@example.com', 'password' => 'secret-pass-1'])->assertRedirect(route('account.dashboard'));
        $this->assertSame($c->id, session('LoggedCustomer'));

        $this->post(route('account.logout'))->assertRedirect(route('account.login'));
        $this->assertNull(session('LoggedCustomer'));
    }
}
