<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Paper;
use App\Support\Catalogue;
use Database\Seeders\CatalogueSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogueTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CatalogueSeeder::class);
    }

    /** What the browser receives, with image URLs reduced to their path under assets/img. */
    private function payload(): array
    {
        $data = Catalogue::frontend();
        $rel  = fn (?string $u) => $u === null ? null : preg_replace('#^.*assets/img/#', '', $u);

        foreach ($data['books'] as &$b) {
            foreach (['img', 'full'] as $k) {
                if (isset($b[$k])) {
                    $b[$k] = $rel($b[$k]);
                }
            }
        }
        unset($b);
        foreach ($data['papers'] as &$p) {
            $p['img'] = $rel($p['img']);
        }

        return $data;
    }

    public function test_seeded_counts_match_the_prototype(): void
    {
        $this->assertSame(23, Book::count());
        $this->assertSame(129, Paper::count());

        $data = Catalogue::frontend();
        $this->assertCount(6, $data['sets']);
        $this->assertCount(6, $data['packages']);
    }

    /** The database must give the browser exactly what the old data.js built (same ids, order and fields). */
    public function test_payload_is_identical_to_the_prototype_data_js(): void
    {
        $expected = json_decode(file_get_contents(base_path('tests/Fixtures/catalogue-prototype.json')), true);
        $actual   = $this->payload();

        foreach ($actual['LEVELS'] as &$level) {
            unset($level['full']); // new: full exam names (used on the generated paper sheets)
        }
        unset($level);

        // Same values AND same types (a price must be a number, not "28000"); object key order is not significant.
        $sorted = function ($v) use (&$sorted) {
            if (! is_array($v)) {
                return $v;
            }
            $v = array_map($sorted, $v);
            if (! array_is_list($v)) {
                ksort($v);
            }

            return $v;
        };

        foreach (['LEVELS', 'BOOK_LEVELS', 'SUBJECTS', 'books', 'papers', 'sets', 'packages'] as $key) {
            $this->assertSame($sorted($expected[$key]), $sorted($actual[$key]), "Catalogue section [$key] differs from data.js");
        }

        // Key order matters for the filter lists (levels are listed in this order).
        $this->assertSame(array_keys($expected['LEVELS']), array_keys($actual['LEVELS']));
        $this->assertSame(array_keys($expected['BOOK_LEVELS']), array_keys($actual['BOOK_LEVELS']));
    }

    public function test_home_page_picks_keep_their_order(): void
    {
        $f = Catalogue::frontend()['featured'];

        $this->assertSame([
            'bio-uce-practical', 'phy-uclse-new', 'bio-uclse-new', 'math-uce-revision',
            'phy-practicals-s123', 'phonics-short-vowels', 'chem-uce-practical', 'agric-uce-notes',
        ], $f['books']);
        $this->assertSame([
            'pp-uce-mathematics-paper-1-2026', 'pp-uace-literature-in-english-paper-2-2024',
            'pp-ple-mathematics-paper-1-2025', 'pp-uce-art-design-paper-2-2026',
        ], $f['papers']);
        $this->assertSame(['hp-p7', 'hp-s4', 'hp-s6'], $f['packages']);
    }

    public function test_image_urls_are_absolute_and_paper_base_is_set(): void
    {
        $data = Catalogue::frontend();

        $this->assertStringEndsWith('/assets/img/books/biology-uce.jpg', $data['books'][0]['img']);
        $this->assertStringStartsWith('http', $data['books'][0]['img']);
        $this->assertStringEndsWith('/assets/img/papers/', $data['PAPER_IMG']);
    }

    public function test_seeding_twice_creates_no_duplicates_and_keeps_edits(): void
    {
        Book::where('slug', 'bio-uce-practical')->update(['price' => 29500]);

        $this->seed(CatalogueSeeder::class);

        $this->assertSame(23, Book::count());
        $this->assertSame(129, Paper::count());
        $this->assertSame(29500, Book::where('slug', 'bio-uce-practical')->value('price'));
    }

    public function test_drafts_are_hidden_and_changes_clear_the_cache(): void
    {
        $this->assertCount(23, Catalogue::frontend()['books']); // primes the cache

        Book::where('slug', 'agric-uace')->firstOrFail()->update(['status' => 'draft']);

        $ids = array_column(Catalogue::frontend()['books'], 'id');
        $this->assertCount(22, $ids);
        $this->assertNotContains('agric-uace', $ids);
    }

    public function test_changing_a_price_shows_on_the_next_request(): void
    {
        $this->get(route('site.books'))->assertOk(); // primes the cache

        $book = Book::where('slug', 'ent-uce')->firstOrFail();
        $book->price = 20500;
        $book->save();

        $found = collect(Catalogue::frontend()['books'])->firstWhere('id', 'ent-uce');
        $this->assertSame(20500, $found['price']);
    }
}
