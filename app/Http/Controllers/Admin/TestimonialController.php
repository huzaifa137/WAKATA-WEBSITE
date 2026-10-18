<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Testimonial;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Testimonials: the "What teachers say" row on the home page. */
class TestimonialController extends Controller
{
    public function index()
    {
        return view('admin.testimonials');
    }

    public function store(Request $request): RedirectResponse
    {
        $t = new Testimonial(['sort_order' => (int) Testimonial::max('sort_order') + 1]);

        return $this->save($request, $t, 'added');
    }

    public function update(Request $request, Testimonial $testimonial): RedirectResponse
    {
        return $this->save($request, $testimonial, 'saved');
    }

    public function destroy(Testimonial $testimonial): RedirectResponse
    {
        $testimonial->delete();

        return back()->with('status', 'Testimonial deleted.');
    }

    /** Show or hide on the website. */
    public function toggle(Testimonial $testimonial): RedirectResponse
    {
        $testimonial->update(['visible' => ! $testimonial->visible]);

        return back()->with('status', $testimonial->visible ? 'Now shown on the home page.' : 'Hidden from the home page.');
    }

    private function save(Request $request, Testimonial $t, string $verb): RedirectResponse
    {
        $data = $request->validate([
            'stars'      => ['required', 'integer', 'between:1,5'],
            'quote'      => ['required', 'string', 'min:10', 'max:600'],
            'initials'   => ['required', 'string', 'max:4'],
            'role'       => ['nullable', 'string', 'max:120'],
            'place'      => ['nullable', 'string', 'max:160'],
            'permission' => ['accepted'],
        ], [
            'quote.required'      => 'Write the quote.', 'quote.min' => 'The quote is too short.', 'quote.max' => 'Keep the quote under 600 characters.',
            'initials.required'   => 'Enter initials, for example HS.',
            'permission.accepted' => 'Please confirm the person agreed to be quoted.',
        ]);

        $t->fill([
            'stars' => $data['stars'], 'quote' => trim($data['quote']), 'initials' => mb_strtoupper(trim($data['initials'])),
            'role' => $data['role'] ?? null, 'place' => $data['place'] ?? null, 'permission' => true,
        ]);
        $t->visible = $t->exists ? $t->visible : true;
        $t->save();

        return back()->with('status', 'Testimonial ' . $verb . '.');
    }
}
