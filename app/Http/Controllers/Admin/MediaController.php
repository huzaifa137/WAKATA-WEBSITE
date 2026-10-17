<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Paper;
use App\Support\Media;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Media library: upload, replace and delete images (the PDF side lives in PaperController). */
class MediaController extends Controller
{
    public function index()
    {
        return view('admin.media');
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'images'   => ['required', 'array', 'min:1', 'max:20'],
            'images.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'kind'     => ['required', Rule::in(Media::KINDS)],
        ], [
            'images.required' => 'Choose at least one image.',
            'images.*'        => 'Images must be JPG, PNG or WebP files under 2 MB.',
        ]);

        $n = 0;
        foreach ($request->file('images') as $file) {
            Media::store($file, $request->input('kind'));
            $n++;
        }

        return back()->with('status', $n . ($n === 1 ? ' image was' : ' images were') . ' uploaded.');
    }

    public function replace(Request $request): RedirectResponse
    {
        $request->validate([
            'path' => ['required', 'string'],
            'file' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ], ['file.*' => 'Choose a JPG, PNG or WebP image under 2 MB.']);

        $path = $request->input('path');
        if (! Media::validPath($path) || ! Media::exists($path)) {
            return back()->with('fail', 'That file does not exist.');
        }

        try {
            Media::replace($path, $request->file('file'));
        } catch (\InvalidArgumentException $e) {
            return back()->with('fail', $e->getMessage());
        }
        \App\Support\Catalogue::flush();

        return back()->with('status', basename($path) . ' was replaced.');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $path = (string) $request->input('path');

        if (in_array($path, Media::BRAND, true)) {
            return back()->with('fail', 'The site logo and icon cannot be deleted.');
        }

        try {
            Media::delete($path);
        } catch (\InvalidArgumentException $e) {
            return back()->with('fail', $e->getMessage());
        }

        return back()->with('status', basename($path) . ' was deleted.');
    }
}
