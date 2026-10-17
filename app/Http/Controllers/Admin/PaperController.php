<?php

namespace App\Http\Controllers\Admin;

use App\Models\CatalogueOption;
use App\Models\Level;
use App\Models\Paper;
use App\Models\Subject;
use App\Support\AdminCatalogue;
use App\Support\Catalogue;
use App\Support\Media;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Admin catalogue: past papers. Each paper can be sold printed, as a PDF, or both (print_on / pdf_on), with its own
 * prices. The PDF itself, and an optional marking guide, are kept on the private disk (never in public/).
 */
class PaperController extends CatalogueController
{
    private const DISK = 'local';

    public function index()
    {
        return view('admin.past-papers', ['levels' => Level::orderBy('sort_order')->orderBy('id')->get(), 'defaults' => AdminCatalogue::paperDefaults()]);
    }

    public function form(Request $request)
    {
        $record = null;
        if ($id = $request->query('id')) {
            $record = Paper::where('slug', $id)->first();
            if (! $record) {
                return redirect()->route('admin.past-papers')->with('fail', 'That paper is not in the catalogue any more.');
            }
        }

        return view('admin.paper-form', [
            'record'   => $record,
            'levels'   => Level::orderBy('sort_order')->orderBy('id')->get(['code', 'label']),
            'subjects' => Subject::orderBy('sort_order')->orderBy('id')->pluck('name'),
            'papers'   => Paper::query()->distinct()->orderBy('paper')->pluck('paper')->whenEmpty(fn ($c) => collect(['Paper 1', 'Paper 2']))->all(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        return $this->save($request, new Paper(), 'added');
    }

    public function update(Request $request, Paper $paper): RedirectResponse
    {
        return $this->save($request, $paper, 'saved');
    }

    public function destroy(Paper $paper): RedirectResponse
    {
        $name = $paper->title . ' ' . $paper->year;
        foreach ([$paper->pdf_path, $paper->guide_path] as $path) {
            if ($path) {
                Storage::disk(self::DISK)->delete($path);
            }
        }
        $paper->delete();

        return $this->flashBack('admin.past-papers', '“' . $name . '” was deleted.');
    }

    /** One price or one format switch changed in the table (JSON, no page reload). */
    public function quick(Request $request, Paper $paper): JsonResponse
    {
        $data = $request->validate([
            'print_price' => ['sometimes', 'integer', 'min:0', 'max:100000000'],
            'pdf_price'   => ['sometimes', 'integer', 'min:0', 'max:100000000'],
            'print_on'    => ['sometimes', 'boolean'],
            'pdf_on'      => ['sometimes', 'boolean'],
        ]);

        $paper->update($data);

        return response()->json(['print' => $paper->print_price, 'pdf' => $paper->pdf_price, 'printOn' => $paper->print_on, 'pdfOn' => $paper->pdf_on]);
    }

    public function bulk(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'action' => ['required', Rule::in(['print_on', 'pdf_on', 'price', 'publish', 'draft'])],
            'ids'    => ['required', 'array', 'min:1', 'max:300'],
            'ids.*'  => ['string', 'max:120'],
            'format' => ['nullable', Rule::in(['print', 'pdf', 'both'])],
            'price'  => ['nullable', 'integer', 'min:0', 'max:100000000'],
        ]);

        if ($data['action'] === 'price' && ! isset($data['price'])) {
            throw ValidationException::withMessages(['price' => 'Enter the new price.']);
        }

        $papers = Paper::whereIn('slug', $data['ids'])->get();
        DB::transaction(function () use ($papers, $data) {
            foreach ($papers as $p) {
                match ($data['action']) {
                    'print_on' => $p->update(['print_on' => true]),
                    'pdf_on'   => $p->update(['pdf_on' => true]),
                    'publish'  => $p->update(['status' => 'published']),
                    'draft'    => $p->update(['status' => 'draft']),
                    'price'    => $p->update(array_filter([
                        'print_price' => in_array($data['format'] ?? 'both', ['print', 'both'], true) ? $data['price'] : null,
                        'pdf_price'   => in_array($data['format'] ?? 'both', ['pdf', 'both'], true) ? $data['price'] : null,
                    ], fn ($v) => $v !== null)),
                };
            }
        });

        $n = $papers->count();

        return back()->with('status', $n . ($n === 1 ? ' paper was' : ' papers were') . ' updated.');
    }

    /** "Default prices by level": save the numbers, and optionally apply them to every paper of that level. */
    public function defaults(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'level' => ['required', Rule::exists('levels', 'code')],
            'print' => ['required', 'integer', 'min:0', 'max:100000000'],
            'pdf'   => ['required', 'integer', 'min:0', 'max:100000000'],
            'time'  => ['required', 'string', 'max:40'],
            'apply' => ['nullable'],
        ]);

        $all = AdminCatalogue::paperDefaults();
        $all[$data['level']] = ['print' => (int) $data['print'], 'pdf' => (int) $data['pdf'], 'time' => $data['time']];
        CatalogueOption::write('paper_defaults', $all);

        $level = Level::where('code', $data['level'])->firstOrFail();
        if ($request->boolean('apply')) {
            $n = 0;
            foreach (Paper::where('level_id', $level->id)->get() as $p) {
                $p->update(['print_price' => $data['print'], 'pdf_price' => $data['pdf'], 'duration' => $data['time']]);
                $n++;
            }

            return back()->with('status', 'Prices applied to ' . $n . ' ' . $level->short . ' papers.');
        }

        return back()->with('status', 'Default prices for ' . $level->short . ' saved. New papers start with them.');
    }

    /** Upload or replace the PDF of one paper (Media library, PDF files). */
    public function uploadFile(Request $request, Paper $paper): RedirectResponse
    {
        $request->validate(['pdffile' => ['required', 'file', 'mimes:pdf', 'max:10240']], [
            'pdffile.required' => 'Choose a PDF file to upload.',
            'pdffile.*'        => 'The file must be a PDF under 10 MB.',
        ]);

        $this->storePdf($paper, 'pdf_path', $request->file('pdffile'), $paper->slug . '.pdf');
        $paper->save();

        return back()->with('status', 'The PDF for ' . $paper->title . ' ' . $paper->year . ' was uploaded.');
    }

    /** Opens the stored PDF (admin only). */
    public function file(Paper $paper)
    {
        $kind = request('guide') ? 'guide_path' : 'pdf_path';
        $path = $paper->{$kind};

        abort_unless($path && Storage::disk(self::DISK)->exists($path), 404);

        return Storage::disk(self::DISK)->download($path, Str::slug($paper->subject->name) . '-' . Str::slug($paper->paper) . '-' . $paper->year . ($kind === 'guide_path' ? '-guide' : '') . '.pdf');
    }

    /** Many PDFs at once. File names like mathematics-paper1-2026.pdf (or uce-mathematics-paper1-2026.pdf) pick the paper. */
    public function bulkUpload(Request $request): RedirectResponse
    {
        $request->validate([
            'files'   => ['required', 'array', 'min:1', 'max:25'],
            'files.*' => ['file', 'mimes:pdf', 'max:10240'],
        ], [
            'files.required' => 'Choose at least one PDF file.',
            'files.max'      => 'Upload up to 25 files at a time.',
            'files.*'        => 'Every file must be a PDF under 10 MB.',
        ]);

        $papers  = Paper::with(['level', 'subject'])->get();
        $done    = 0;
        $missed  = [];

        foreach ($request->file('files') as $file) {
            $name = Str::lower(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME));
            $name = preg_replace('/[\s_]+/', '-', $name);

            if (! preg_match('/^(?:(ple|uce|uace)-)?(.+?)-paper-?(\d+)-(\d{4})$/', $name, $m)) {
                $missed[] = $file->getClientOriginalName();
                continue;
            }

            $matches = $papers->filter(fn (Paper $p) => Str::slug($p->subject->name) === $m[2]
                && (int) preg_replace('/\D/', '', $p->paper) === (int) $m[3]
                && $p->year === (int) $m[4]
                && ($m[1] === '' || $p->level->code === $m[1]));

            if ($matches->count() !== 1) {
                $missed[] = $file->getClientOriginalName() . ($matches->count() > 1 ? ' (add the level at the start, for example uce-)' : '');
                continue;
            }

            $paper = $matches->first();
            $this->storePdf($paper, 'pdf_path', $file, $paper->slug . '.pdf');
            $paper->save();
            $done++;
        }

        $redirect = back();
        if ($done) {
            $redirect->with('status', $done . ($done === 1 ? ' PDF was' : ' PDFs were') . ' matched and uploaded.');
        }
        if ($missed) {
            $redirect->with('fail', 'Not matched: ' . implode(', ', array_slice($missed, 0, 5)) . (count($missed) > 5 ? ' and ' . (count($missed) - 5) . ' more.' : '.'));
        }

        return $redirect;
    }

    private function storePdf(Paper $paper, string $column, $file, string $name): void
    {
        $path = 'papers/' . $name;
        Storage::disk(self::DISK)->putFileAs('papers', $file, $name);
        $paper->{$column} = $path;
    }

    private function save(Request $request, Paper $paper, string $verb): RedirectResponse
    {
        $printOn = $request->boolean('printOn');
        $pdfOn   = $request->boolean('pdfOn');
        $status  = $this->statusFrom($request);

        $data = $request->validate([
            'level'      => ['required', Rule::exists('levels', 'code')],
            'subject'    => ['required', Rule::exists('subjects', 'name')],
            'paper'      => ['nullable', 'string', 'max:30'],
            'year'       => ['required', 'integer', 'between:1990,2100'],
            'time'       => ['nullable', 'string', 'max:40'],
            'print'      => [Rule::requiredIf($printOn), 'nullable', 'integer', 'min:0', 'max:100000000'],
            'pdf'        => [Rule::requiredIf($pdfOn), 'nullable', 'integer', 'min:0', 'max:100000000'],
            'printStock' => ['nullable', 'integer', 'min:0', 'max:10000000'],
            'bulk'       => ['nullable', 'integer', 'min:0', 'max:100000'],
            'pdffile'    => ['nullable', 'file', 'mimes:pdf', 'max:10240'],
            'guide'      => ['nullable', 'file', 'mimes:pdf', 'max:10240'],
            'img'        => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ], [
            'level.required'   => 'Please choose a level.',
            'subject.required' => 'Please choose a subject.',
            'year.required'    => 'Enter the year.',
            'year.between'     => 'Enter a year between 1990 and 2100.',
            'print.required'   => 'Enter a price.',
            'pdf.required'     => 'Enter a price.',
            'pdffile.*'        => 'The paper must be a PDF under 10 MB.',
            'guide.*'          => 'The marking guide must be a PDF under 10 MB.',
            'img.*'            => 'The first page must be a JPG, PNG or WebP image under 2 MB.',
        ]);

        if ($status === 'published' && ! $printOn && ! $pdfOn) {
            throw ValidationException::withMessages(['printOn' => 'Switch at least one format on, or save this paper as a draft.']);
        }

        $level   = $this->levelByCode($data['level']);
        $subject = $this->subjectByName($data['subject']);
        $label   = trim($data['paper'] ?? '') ?: 'Paper 1';
        $defaults = AdminCatalogue::paperDefaults()[$level->code] ?? ['print' => 0, 'pdf' => 0, 'time' => ''];

        $clash = Paper::where('level_id', $level->id)->where('subject_id', $subject->id)->where('paper', $label)->where('year', $data['year'])
            ->when($paper->exists, fn ($q) => $q->whereKeyNot($paper->id))->exists();
        if ($clash) {
            throw ValidationException::withMessages(['year' => 'This paper is already in the catalogue for that level, subject and year.']);
        }

        $paper->fill([
            'level_id'    => $level->id,
            'subject_id'  => $subject->id,
            'paper'       => $label,
            'year'        => $data['year'],
            'title'       => Paper::makeTitle($level, $subject->name, $label),
            'duration'    => ($data['time'] ?? '') !== '' ? $data['time'] : ($paper->duration ?: $defaults['time']),
            'print_on'    => $printOn,
            'pdf_on'      => $pdfOn,
            'print_price' => $data['print'] ?? $paper->print_price ?? $defaults['print'],
            'pdf_price'   => $data['pdf'] ?? $paper->pdf_price ?? $defaults['pdf'],
            'status'      => $status,
        ]);

        if (! $paper->exists) {
            $paper->slug       = $this->uniqueSlug(Paper::class, 'pp-' . $level->code . '-' . Str::slug($subject->name) . '-' . Str::slug($label) . '-' . $data['year']);
            $paper->sort_order = $this->nextOrder(Paper::class);
        }

        $options = $paper->options ?? [];
        if ($printOn && isset($data['bulk'])) {
            $options['bulk'] = (int) $data['bulk'];
        }
        if ($pdfOn) {
            $options['watermark']  = $request->boolean('wm');
            $options['auto_email'] = $request->boolean('auto');
        }
        $paper->options = $options;

        if ($request->hasFile('img')) {
            $paper->cover_image = Media::store($request->file('img'), 'papers');
        }

        $wasNew = ! $paper->exists;
        $paper->save();

        if ($request->hasFile('pdffile')) {
            $this->storePdf($paper, 'pdf_path', $request->file('pdffile'), $paper->slug . '.pdf');
        }
        if ($request->hasFile('guide')) {
            $this->storePdf($paper, 'guide_path', $request->file('guide'), $paper->slug . '-guide.pdf');
        }
        $paper->save();

        if ($printOn) {
            $this->syncStock($paper, 'paper', isset($data['printStock']) ? (int) $data['printStock'] : null, $request, $wasNew ? 'Opening stock' : 'Edited on the paper form');
        }
        Catalogue::flush();

        return redirect()->route('admin.past-papers')->with('status', '“' . $paper->title . ' ' . $paper->year . '” was ' . $verb . ($status === 'draft' ? ' as a draft (hidden from the website).' : '.'));
    }
}
