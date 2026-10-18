<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\SiteContent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Site content: the words on the home, about, contact and cart pages, the top strip, header and footer, and the search-engine
 * title and description of each page. One form per tab; a field left empty (or set back to the original wording) goes back to the
 * built-in text.
 */
class ContentController extends Controller
{
    public function index()
    {
        return view('admin.content', ['v' => SiteContent::editorValues(), 'seo' => SiteContent::SEO, 'links' => SiteContent::LINKS]);
    }

    public function save(Request $request): RedirectResponse
    {
        $tab = (string) $request->input('tab');
        $values = [];
        $errors = [];

        if ($tab === 'seo') {
            foreach (SiteContent::SEO as $page => $meta) {
                foreach (['title' => 70, 'desc' => 200] as $f => $max) {
                    $id = 'seo_' . $page . '_' . $f;
                    $text = trim((string) $request->input($id));
                    if (mb_strlen($text) > $max) {
                        $errors[$id] = ($f === 'title' ? 'Keep the title under ' : 'Keep the description under ') . $max . ' characters.';
                    }
                    $values['seo.' . $page . '.' . $f] = $text;
                }
            }
        } else {
            $known = collect(SiteContent::FIELDS)->filter(fn ($f) => $f[3] === $tab);
            abort_if($known->isEmpty(), 422, 'Unknown tab.');

            foreach ($known as $id => [$key, , $kind]) {
                if (! $request->has($id)) {
                    continue;
                }
                $text = trim((string) $request->input($id));
                $max  = $kind === 'area' ? 2000 : 255;

                if (mb_strlen($text) > $max) {
                    $errors[$id] = 'This is too long (up to ' . $max . ' characters).';
                } elseif ($text !== '' && ($msg = $this->rule($key, $text))) {
                    $errors[$id] = $msg;
                }
                $values[$key] = $text;
            }
        }

        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        SiteContent::save($values);

        return back()->with('status', 'Saved. The website now shows your changes.');
    }

    /** Extra rules for fields that feed links and contact details. */
    private function rule(string $key, string $text): ?string
    {
        return match ($key) {
            'contact.phone'   => preg_match('/\d{7,}/', preg_replace('/\D/', '', $text)) ? null : 'Enter a phone number.',
            'contact.email'   => filter_var($text, FILTER_VALIDATE_EMAIL) ? null : 'Enter a valid email address.',
            'header.whatsapp' => strlen(preg_replace('/\D/', '', $text)) >= 9 ? null : 'Enter the WhatsApp number with the country code, for example 256700000000.',
            'contact.map'     => preg_match('/^-?\d{1,3}(\.\d+)?\s*,\s*-?\d{1,3}(\.\d+)?$/', $text) ? null : 'Enter coordinates like 0.3225393, 32.5736475.',
            'home.btn1_link', 'home.btn2_link' => isset(SiteContent::LINKS[\Illuminate\Support\Str::before($text, '.html')]) ? null : 'Choose one of the pages in the list.',
            default           => null,
        };
    }
}
