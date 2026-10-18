<?php

namespace App\Support;

use App\Models\CatalogueOption;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

/**
 * The words on the public website that staff can change under Site content (module 9).
 *
 * Every text has a built-in default that is exactly what the pages said before this module, so a site that has never been
 * edited looks the same. Edited texts are kept as one JSON list (catalogue_options, key "site_content") and cached.
 * Pages read them with @c('home.title'); contact details and the WhatsApp number also feed config('wakata.*'), which the
 * emails and the page chrome already use (see apply()).
 */
class SiteContent
{
    private const CACHE_KEY = 'wakata.site_content.v1';

    /** The shipped description, kept before apply() replaces config('wakata.description'). */
    private static ?string $baseDescription = null;

    /**
     * editor field id => [key, default, kind, tab].  kind: line (one line), area (paragraph), link (a shop page).
     * The ids are the field names of the Site content form.
     */
    public const FIELDS = [
        'h1' => ['home.title', 'Quality assessment, priced for every school.', 'line', 'home'],
        'lead' => ['home.lead', 'Mock examinations, past papers, practical workbooks and holiday packages, written and marked by teachers of the Wakiso Kampala Teachers\' Association.', 'area', 'home'],
        'b1' => ['home.btn1', 'Shop books', 'line', 'home'],
        'b1l' => ['home.btn1_link', 'books', 'link', 'home'],
        'b2' => ['home.btn2', 'Find a past paper', 'line', 'home'],
        'b2l' => ['home.btn2_link', 'past-papers', 'link', 'home'],
        'tag' => ['home.tag', 'Printed or PDF', 'line', 'home'],
        's1' => ['home.stat1', 'Books and workbooks', 'line', 'home'],
        's2' => ['home.stat2', 'Past papers', 'line', 'home'],
        's3' => ['home.stat3', 'Complete exam sets', 'line', 'home'],
        's4' => ['home.stat4', 'Levels covered: PLE, UCE, UACE', 'line', 'home'],
        'hb' => ['home.books_title', 'Textbooks and practical workbooks', 'line', 'home'],
        'hbs' => ['home.books_sub', 'Biology, Chemistry, Physics, Agriculture and more, with new curriculum editions.', 'line', 'home'],
        'hp' => ['home.papers_title', 'Find the exact paper you need', 'line', 'home'],
        'hps' => ['home.papers_sub', 'Choose a level, subject and year. Buy a single printed paper, or download a PDF in minutes.', 'line', 'home'],
        'hl' => ['home.levels_title', 'Start with your level', 'line', 'home'],
        'hls' => ['home.levels_sub', 'Everything in the shop is grouped by the examination your learners are preparing for.', 'line', 'home'],
        'hk' => ['home.packages_title', 'Holiday packages', 'line', 'home'],
        'hks' => ['home.packages_sub', 'A ready-made bundle of workbooks, tests and mock papers, so learners keep working while school is out.', 'line', 'home'],
        'ht' => ['home.testimonials_title', 'What teachers say', 'line', 'home'],
        'hts' => ['home.testimonials_sub', 'Schools use WAKATA papers and workbooks every term.', 'line', 'home'],
        'at' => ['about.title', 'About WAKATA', 'line', 'about'],
        'ai' => ['about.intro', 'The Wakiso Kampala Teachers\' Association brings teachers together to make good assessment affordable for every learner.', 'area', 'about'],
        'ah' => ['about.heading', 'Written by teachers, for teachers', 'line', 'about'],
        'ap1' => ['about.p1', 'WAKATA is an association of teachers from Wakiso and Kampala. Every paper, workbook and revision guide we publish is written and checked by people who teach the syllabus every day.', 'area', 'about'],
        'ap2' => ['about.p2', 'We believe a school should not need a large budget to give its candidates a proper mock examination. That is why our papers are priced for schools of every size, and why we sell them as printed copies, PDFs or whole sets.', 'area', 'about'],
        'ab' => ['about.button', 'Browse the shop', 'line', 'about'],
        'vt0' => ['about.value1_title', 'Affordable', 'line', 'about'],
        'vx0' => ['about.value1_text', 'Fair prices, with discounts on full sets and holiday packages.', 'area', 'about'],
        'vt1' => ['about.value2_title', 'Quality', 'line', 'about'],
        'vx1' => ['about.value2_text', 'Papers follow the national format, with marking guides teachers can trust.', 'area', 'about'],
        'vt2' => ['about.value3_title', 'Practical', 'line', 'about'],
        'vx2' => ['about.value3_text', 'Real science means practical. Our workbooks put experiments in learners\' hands.', 'area', 'about'],
        'ot0' => ['about.offer1_title', 'Mock examinations', 'line', 'about'],
        'ox0' => ['about.offer1_text', 'PLE, UCE and UACE papers for schools across the region.', 'area', 'about'],
        'ot1' => ['about.offer2_title', 'Textbooks and workbooks', 'line', 'about'],
        'ox1' => ['about.offer2_text', 'Biology, Chemistry, Physics, Agriculture and more.', 'area', 'about'],
        'ot2' => ['about.offer3_title', 'Past papers and sets', 'line', 'about'],
        'ox2' => ['about.offer3_text', 'Buy one paper, or a whole series at a lower price.', 'area', 'about'],
        'cph' => ['contact.phone', '+256 700 000 000', 'line', 'contact'],
        'cem' => ['contact.email', 'info@wakatastationers.com', 'line', 'contact'],
        'coh' => ['contact.hours', 'Monday to Saturday, 8am to 6pm', 'line', 'contact'],
        'csh' => ['contact.address', 'Bat Valley Primary School, Kampala, Uganda', 'line', 'contact'],
        'cmp' => ['contact.map', '0.3225393, 32.5736475', 'line', 'contact'],
        'cpt' => ['contact.title', 'Contact us', 'line', 'contact'],
        'cpi' => ['contact.intro', 'Questions about an order, a bulk quotation or a mock series? Call, write or visit us.', 'line', 'contact'],
        'cty' => ['contact.thanks', 'Thank you. We have your message and will reply within one working day.', 'line', 'contact'],
        'kt' => ['cart.title', 'Your cart', 'line', 'cart'],
        'ki' => ['cart.intro', 'Review your items, choose how you want to receive them and pay.', 'line', 'cart'],
        'ke' => ['cart.empty_title', 'Your cart is empty', 'line', 'cart'],
        'kem' => ['cart.empty_text', 'Add books, past papers or a holiday package to get started.', 'line', 'cart'],
        'oct' => ['cart.done_title', 'Order received', 'line', 'cart'],
        'ocx' => ['cart.done_text', 'Your order reference is [reference]. We will confirm your [payment method] payment by phone or email.', 'area', 'cart'],
        'st1' => ['strip.message', 'Free pickup. Delivery across Kampala and Wakiso.', 'line', 'chrome'],
        'st2' => ['strip.phone', '', 'line', 'chrome'],
        'st3' => ['strip.hours', '', 'line', 'chrome'],
        'sp' => ['header.search', 'Search books', 'line', 'chrome'],
        'wa' => ['header.whatsapp', '256700000000', 'line', 'chrome'],
        'wag' => ['header.wa_greeting', 'Hello WAKATA, I need help with an order.', 'line', 'chrome'],
        'fd' => ['footer.about', 'Mock examinations, past papers and practical workbooks written by teachers, priced so every school can afford them.', 'area', 'chrome'],
        'fc' => ['footer.copyright', '© {year} Wakiso Kampala Teachers\' Association (WAKATA). All rights reserved.', 'line', 'chrome'],
        'ft' => ['footer.tagline', 'Affordable quality assessment', 'line', 'chrome'],
        'nh' => ['news.title', 'Get new papers first', 'line', 'chrome'],
        'nt' => ['news.text', 'One short email after each mock series. No spam.', 'line', 'chrome'],
    ];

    /** Pages with search-engine text. page => [editor label, file name shown, default title]. */
    public const SEO = [
        'home'     => ['Home', 'index', 'WAKATA Stationers | Books, past papers and mock examinations'],
        'books'    => ['Books', 'books', 'Books | WAKATA Stationers'],
        'papers'   => ['Past papers', 'past-papers', 'Past papers | WAKATA Stationers'],
        'sets'     => ['Exam sets', 'exam-sets', 'Exam sets | WAKATA Stationers'],
        'packages' => ['Holiday packages', 'holiday-packages', 'Holiday packages | WAKATA Stationers'],
        'about'    => ['About', 'about', 'About | WAKATA Stationers'],
        'contact'  => ['Contact', 'contact', 'Contact | WAKATA Stationers'],
        'cart'     => ['Cart and checkout', 'cart', 'Cart and checkout | WAKATA Stationers'],
    ];

    /** The shop pages a button can lead to (stored as the first word, shown with the route name). */
    public const LINKS = [
        'books'            => ['Books', 'site.books'],
        'past-papers'      => ['Past papers', 'site.papers'],
        'exam-sets'        => ['Exam sets', 'site.sets'],
        'holiday-packages' => ['Holiday packages', 'site.packages'],
    ];

    /** @return array<string, string> key => saved text (only what staff changed) */
    private static function saved(): array
    {
        try {
            return Cache::rememberForever(self::CACHE_KEY, fn () => CatalogueOption::read('site_content'));
        } catch (\Throwable $e) {
            return [];            // before the first migration there is no table yet: use the built-in text
        }
    }

    /** @return array<string, array{0:string,1:string,2:string,3:string}> key => [id, default, kind, tab] */
    public static function byKey(): array
    {
        static $map;

        return $map ??= collect(self::FIELDS)->mapWithKeys(fn ($f, $id) => [$f[0] => [$id, $f[1], $f[2], $f[3]]])->all();
    }

    public static function default(string $key): string
    {
        if (isset(self::byKey()[$key])) {
            return self::byKey()[$key][1];
        }
        if (preg_match('/^seo\.([a-z]+)\.(title|desc)$/', $key, $m) && isset(self::SEO[$m[1]])) {
            return $m[2] === 'title' ? self::SEO[$m[1]][2] : (self::$baseDescription ??= (string) config('wakata.description'));
        }

        return '';
    }

    /** The text to show: what staff saved, or the built-in wording when nothing (or only blanks) was saved. */
    public static function get(string $key): string
    {
        $v = self::saved()[$key] ?? null;

        if ($v === null || trim($v) === '') {
            // The two strip fields follow the contact details until staff give them their own text
            return match ($key) {
                'strip.phone' => (string) config('wakata.phone'),
                'strip.hours' => (string) config('wakata.hours'),
                default       => self::default($key),
            };
        }

        return $v;
    }

    public static function seo(string $page, string $field): string
    {
        return self::get('seo.' . $page . '.' . $field);
    }

    /** URL of a "goes to" button setting. Old values like "books.html" still work. */
    public static function link(string $key): string
    {
        $v = Str::before(self::get($key), '.html');

        return route(self::LINKS[$v][1] ?? 'site.books');
    }

    /** Copyright line: the year is filled in by the site. */
    public static function copyright(): string
    {
        return str_replace('{year}', date('Y'), self::get('footer.copyright'));
    }

    /** Text of the "Order received" box. [reference] and [payment method] become the places the page fills in. */
    public static function orderDone(): HtmlString
    {
        $text = e(self::get('cart.done_text'));
        $text = str_replace(['[reference]', '[payment method]'], ['<b id="ref"></b>', '<span id="pay-name"></span>'], $text);

        return new HtmlString($text);
    }

    /** Everything as it is now, for the editor. @return array<string, string> editor field id => text, plus seo_* fields */
    public static function editorValues(): array
    {
        $out = [];
        foreach (self::FIELDS as $id => $f) {
            $out[$id] = self::get($f[0]);
        }
        foreach (array_keys(self::SEO) as $p) {
            $out['seo_' . $p . '_title'] = self::seo($p, 'title');
            $out['seo_' . $p . '_desc']  = self::seo($p, 'desc');
        }

        return $out;
    }

    /** Saves the fields of one editor tab. Texts equal to the built-in wording are not kept, so later wording fixes still reach the site. */
    public static function save(array $values): void
    {
        $saved = self::saved();

        foreach ($values as $key => $value) {
            $value = trim((string) $value);
            if ($value === '' || $value === self::default($key)) {
                unset($saved[$key]);
            } else {
                $saved[$key] = $value;
            }
        }

        CatalogueOption::write('site_content', $saved);
        self::refresh();
    }

    public static function refresh(): void
    {
        Cache::forget(self::CACHE_KEY);
        self::apply();
    }

    /**
     * Runs on every request (AppServiceProvider): the contact details staff edit become config('wakata.*'), which the strip,
     * the WhatsApp button, the emails and the contact page already read.
     */
    public static function apply(): void
    {
        self::$baseDescription ??= (string) config('wakata.description');
        $phone = self::get('contact.phone');
        $tel   = preg_replace('/[^\d+]/', '', $phone);

        config([
            'wakata.phone'       => $phone,
            'wakata.phone_tel'   => $tel !== '' ? $tel : config('wakata.phone_tel'),
            'wakata.email'       => self::get('contact.email'),
            'wakata.hours'       => self::get('contact.hours'),
            'wakata.whatsapp'    => preg_replace('/\D/', '', self::get('header.whatsapp')) ?: config('wakata.whatsapp'),
            'wakata.description' => self::seo('home', 'desc'),
        ]);
    }
}
