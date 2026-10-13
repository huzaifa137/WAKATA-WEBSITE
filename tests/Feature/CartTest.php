<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Paper;
use Database\Seeders\CatalogueSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CartTest extends TestCase
{
    use RefreshDatabase;

    private const BOOK = 'bio-uce-practical';            // UGX 28,000
    private const PAPER = 'pp-uce-agriculture-paper-1-2026'; // print 4,000 / pdf 2,500
    private const SET = 'set-uce-2026';                    // UGX 120,000
    private const PACK = 'hp-p4-p6';                       // UGX 45,000

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CatalogueSeeder::class);
    }

    private function add(array $items)
    {
        return $this->postJson(route('cart.add'), ['items' => $items]);
    }

    private function cartPayload(): array
    {
        return $this->get(route('site.cart'))->viewData('cart');
    }

    public function test_adding_items_counts_units_and_prices_come_from_the_database(): void
    {
        $this->add([['type' => 'book', 'id' => self::BOOK, 'qty' => 2]])->assertOk()->assertJson(['ok' => true, 'count' => 2]);
        $this->add([['type' => 'paper', 'id' => self::PAPER, 'fmt' => 'pdf']])->assertOk()->assertJson(['count' => 3]);
        $this->add([['type' => 'set', 'id' => self::SET], ['type' => 'pack', 'id' => self::PACK]])->assertOk()->assertJson(['count' => 5]);

        $cart = $this->cartPayload();
        $this->assertSame(5, $cart['count']);
        $this->assertSame(2 * 28000 + 2500 + 120000 + 45000, $cart['subtotal']);
        $this->assertTrue($cart['physical']);
        $this->assertSame(['book', 'paper', 'set', 'pack'], array_column($cart['lines'], 'type'));
        $this->assertSame('Holiday package for Primary 4 to Primary 6', $cart['lines'][3]['sub']);
    }

    public function test_the_same_product_adds_up_and_formats_are_separate_lines(): void
    {
        $this->add([['type' => 'book', 'id' => self::BOOK]]);
        $this->add([['type' => 'book', 'id' => self::BOOK, 'qty' => 3]]);
        $this->add([['type' => 'paper', 'id' => self::PAPER, 'fmt' => 'print']]);
        $this->add([['type' => 'paper', 'id' => self::PAPER, 'fmt' => 'pdf']]);

        $cart = $this->cartPayload();
        $this->assertCount(3, $cart['lines']);
        $this->assertSame(4, $cart['lines'][0]['qty']);
        $this->assertSame(4000, $cart['lines'][1]['price']);
        $this->assertSame(2500, $cart['lines'][2]['price']);
        $this->assertFalse($cart['lines'][2]['phys']);
    }

    public function test_the_browser_cannot_choose_a_price(): void
    {
        $this->add([['type' => 'book', 'id' => self::BOOK, 'price' => 1, 'unit' => 1, 'line' => 1]])->assertOk();

        $this->assertSame(28000, $this->cartPayload()['subtotal']);
    }

    public function test_quantities_are_kept_between_1_and_99(): void
    {
        $this->add([['type' => 'book', 'id' => self::BOOK, 'qty' => 500]])->assertOk();
        $this->assertSame(99, $this->cartPayload()['count']);

        $k = 'book:' . self::BOOK . ':';
        $this->patchJson(route('cart.update'), ['k' => $k, 'qty' => 0])->assertStatus(422);
        $this->patchJson(route('cart.update'), ['k' => $k, 'qty' => 100])->assertStatus(422);
        $this->patchJson(route('cart.update'), ['k' => $k, 'qty' => 7])->assertOk()->assertJsonPath('cart.count', 7);
    }

    public function test_remove_and_clear(): void
    {
        $this->add([['type' => 'book', 'id' => self::BOOK], ['type' => 'set', 'id' => self::SET]]);

        $this->deleteJson(route('cart.remove'), ['k' => 'book:' . self::BOOK . ':'])
            ->assertOk()->assertJsonPath('cart.count', 1)->assertJsonPath('cart.subtotal', 120000);

        $this->deleteJson(route('cart.clear'))->assertOk()->assertJsonPath('cart.count', 0);
        $this->assertSame([], $this->cartPayload()['lines']);
    }

    public function test_unknown_unpublished_or_malformed_items_are_refused(): void
    {
        $this->add([['type' => 'book', 'id' => 'no-such-book']])->assertStatus(422)->assertJson(['ok' => false]);
        $this->add([['type' => 'laptop', 'id' => self::BOOK]])->assertStatus(422);
        $this->add([['type' => 'book', 'id' => '../etc/passwd']])->assertStatus(422);
        $this->postJson(route('cart.add'), [])->assertStatus(422);

        Book::where('slug', self::BOOK)->update(['status' => 'draft']);
        $this->add([['type' => 'book', 'id' => self::BOOK]])->assertStatus(422);
    }

    public function test_a_good_item_is_added_even_when_another_in_the_request_is_not(): void
    {
        $this->add([['type' => 'book', 'id' => 'nope'], ['type' => 'set', 'id' => self::SET]])
            ->assertOk()->assertJson(['added' => 1, 'skipped' => 1, 'count' => 1]);
    }

    public function test_an_unpublished_product_drops_out_of_the_cart(): void
    {
        $this->add([['type' => 'book', 'id' => self::BOOK], ['type' => 'set', 'id' => self::SET]]);
        Book::where('slug', self::BOOK)->update(['status' => 'draft']);

        $cart = $this->cartPayload();
        $this->assertSame(1, $cart['removed']);
        $this->assertSame(['set'], array_column($cart['lines'], 'type'));
        // ...and stays gone
        $this->assertSame(0, $this->cartPayload()['removed']);
    }

    public function test_a_price_change_shows_on_the_next_view(): void
    {
        $this->add([['type' => 'paper', 'id' => self::PAPER, 'fmt' => 'print']]);
        Paper::where('slug', self::PAPER)->update(['print_price' => 5000]);

        $this->assertSame(5000, $this->cartPayload()['subtotal']);
    }

    public function test_the_header_badge_is_rendered_by_the_server(): void
    {
        $this->add([['type' => 'book', 'id' => self::BOOK, 'qty' => 3]]);

        $this->get(route('site.home'))
            ->assertOk()
            ->assertSee('<span class="cart-count" data-n="3">3</span>', false)
            ->assertSee('cart: {"count":3}', false);
    }

    public function test_the_cart_belongs_to_one_browser(): void
    {
        $this->add([['type' => 'book', 'id' => self::BOOK]]);

        $this->flushSession();
        $this->assertSame(0, $this->cartPayload()['count']);
    }

    public function test_the_cart_page_ships_the_server_cart_and_shop_settings(): void
    {
        $this->add([['type' => 'set', 'id' => self::SET]]);

        $this->get(route('site.cart'))
            ->assertOk()
            ->assertSee(self::SET, false)
            ->assertSee('"kampala":{"label":"Kampala and Wakiso","fee":8000}', false)
            ->assertSee('value="upcountry"', false)
            ->assertDontSee('design preview', false)
            ->assertHeader('Cache-Control', 'max-age=0, no-store, private');
    }
}
