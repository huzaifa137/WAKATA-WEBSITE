<?php

namespace App\Http\Controllers\Admin;

use App\Models\ExamSet;
use App\Models\Level;
use App\Support\Catalogue;
use App\Support\Media;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/** Admin catalogue: exam sets (complete mock series sold as one bundle). */
class ExamSetController extends CatalogueController
{
    private const DISK = 'local';

    public function index()
    {
        return view('admin.exam-sets');
    }

    public function form(Request $request)
    {
        $record = null;
        if ($id = $request->query('id')) {
            $record = ExamSet::where('slug', $id)->first();
            if (! $record) {
                return redirect()->route('admin.exam-sets')->with('fail', 'That exam set is not in the catalogue any more.');
            }
        }

        return view('admin.set-form', ['record' => $record, 'levels' => Level::orderBy('sort_order')->orderBy('id')->get(['code', 'label'])]);
    }

    public function store(Request $request): RedirectResponse
    {
        return $this->save($request, new ExamSet(), 'added');
    }

    public function update(Request $request, ExamSet $set): RedirectResponse
    {
        return $this->save($request, $set, 'saved');
    }

    public function destroy(ExamSet $set): RedirectResponse
    {
        $title = $set->title;
        if ($set->guide_path) {
            Storage::disk(self::DISK)->delete($set->guide_path);
        }
        $set->delete();

        return $this->flashBack('admin.exam-sets', '“' . $title . '” was deleted.');
    }

    private function save(Request $request, ExamSet $set, string $verb): RedirectResponse
    {
        $id = $set->exists ? $set->id : null;

        $data = $request->validate([
            'title'     => ['required', 'string', 'max:255'],
            'level'     => ['required', Rule::exists('levels', 'code')],
            'year'      => ['nullable', 'string', 'max:40'],
            'papers'    => ['required', 'integer', 'min:1', 'max:1000'],
            'blurb'     => ['nullable', 'string', 'max:1000'],
            'subjects'  => ['nullable', 'array', 'max:40'],
            'subjects.*' => ['nullable', 'string', 'max:120'],
            'price'     => ['required', 'integer', 'min:0', 'max:100000000'],
            'worth'     => ['nullable', 'integer', 'gte:price', 'max:100000000'],
            'preview1'  => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'preview2'  => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'preview3'  => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'guidefile' => ['nullable', 'file', 'mimes:pdf', 'max:20480'],
            'sku'       => ['nullable', 'string', 'max:30', Rule::unique('exam_sets', 'sku')->ignore($id)],
            'stock'     => ['nullable', 'integer', 'min:0', 'max:10000000'],
        ], [
            'title.required'  => 'Enter the set title.',
            'level.required'  => 'Please choose a level.',
            'papers.required' => 'Enter how many papers are in the set.',
            'price.required'  => 'Enter a price.',
            'worth.gte'       => 'The “worth” cannot be lower than the set price.',
            'preview1.*'      => 'Preview sheets must be JPG, PNG or WebP images under 2 MB.',
            'preview2.*'      => 'Preview sheets must be JPG, PNG or WebP images under 2 MB.',
            'preview3.*'      => 'Preview sheets must be JPG, PNG or WebP images under 2 MB.',
            'guidefile.*'     => 'The marking guides must be one PDF under 20 MB.',
            'sku.unique'      => 'Another item already uses this SKU.',
        ]);

        $level = $this->levelByCode($data['level']);
        $year  = trim($data['year'] ?? '') ?: (string) now()->year;

        $previews = array_values($set->previews ?? []);
        foreach ([1, 2, 3] as $i) {
            if ($request->hasFile('preview' . $i)) {
                $previews[$i - 1] = basename(Media::store($request->file('preview' . $i), 'papers'));
            }
        }
        ksort($previews);

        $options = $set->options ?? [];
        $options['print_on'] = $request->boolean('printOn');
        $options['pdf_on']   = $request->boolean('pdfOn');
        $options['guides']   = $request->boolean('guides');

        $set->fill([
            'level_id'     => $level->id,
            'title'        => $data['title'],
            'year_label'   => $year,
            'papers_count' => $data['papers'],
            'blurb'        => $data['blurb'] ?? '',
            'contents'     => array_values(array_filter(array_map('trim', $data['subjects'] ?? []), fn ($s) => $s !== '')),
            'previews'     => array_values($previews),
            'price'        => $data['price'],
            'worth_price'  => $data['worth'] ?? $data['price'],
            'status'       => $this->statusFrom($request),
            'options'      => $options,
        ]);

        if (! empty($data['sku'])) {
            $set->sku = $data['sku'];
        }
        if (! $set->exists) {
            $set->slug       = $this->uniqueSlug(ExamSet::class, 'set-' . $level->code . '-' . Str::slug($year));
            $set->sort_order = $this->nextOrder(ExamSet::class);
        }

        $wasNew = ! $set->exists;
        $set->save();

        if ($request->hasFile('guidefile')) {
            Storage::disk(self::DISK)->putFileAs('guides', $request->file('guidefile'), $set->slug . '.pdf');
            $set->guide_path = 'guides/' . $set->slug . '.pdf';
            $set->save();
        }

        $this->syncStock($set, 'set', isset($data['stock']) ? (int) $data['stock'] : null, $request, $wasNew ? 'Opening stock' : 'Edited on the exam set form');
        Catalogue::flush();

        return redirect()->route('admin.exam-sets')->with('status', '“' . $set->title . '” was ' . $verb . ($set->status === 'draft' ? ' as a draft (hidden from the website).' : '.'));
    }
}
