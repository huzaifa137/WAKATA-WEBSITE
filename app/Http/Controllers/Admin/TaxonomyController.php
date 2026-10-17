<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Book;
use App\Models\CatalogueOption;
use App\Models\Level;
use App\Models\Paper;
use App\Models\Subject;
use App\Support\AdminCatalogue;
use App\Support\Catalogue;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Subjects and levels: the lists behind the filters, the generated covers and the book groups on the website. */
class TaxonomyController extends Controller
{
    public function index()
    {
        return view('admin.taxonomy', [
            'levels' => Level::orderBy('sort_order')->orderBy('id')->get(),
            'types'  => AdminCatalogue::types(),
            'badges' => AdminCatalogue::badges(),
        ]);
    }

    /** The three level cards: label, short name and book group key. Fields are named l-PLE, s-PLE, k-PLE. */
    public function levels(Request $request): RedirectResponse
    {
        $levels = Level::all();
        $rules  = [];
        foreach ($levels as $l) {
            $c = strtoupper($l->code);
            $rules["l-$c"] = ['required', 'string', 'max:60'];
            $rules["s-$c"] = ['required', 'string', 'max:12'];
            $rules["k-$c"] = ['required', 'regex:/^[a-z0-9-]+$/', 'max:12'];
        }
        $data = $request->validate($rules, [
            '*.required' => 'This field is required.',
            'k-*.regex'  => 'Use lower-case letters, numbers and hyphens only.',
        ]);

        $keys = [];
        foreach ($levels as $l) {
            $k = $data['k-' . strtoupper($l->code)];
            if (in_array($k, $keys, true)) {
                throw ValidationException::withMessages(['k-' . strtoupper($l->code) => 'Each level needs its own group key.']);
            }
            $keys[] = $k;
        }

        foreach ($levels as $l) {
            $c = strtoupper($l->code);
            $l->update(['label' => $data["l-$c"], 'short' => $data["s-$c"], 'book_code' => $data["k-$c"]]);
        }
        Catalogue::flush();

        return back()->with('status', 'Levels saved.');
    }

    public function storeSubject(Request $request): RedirectResponse
    {
        $data = $this->subjectData($request, null);
        Subject::create($data + ['sort_order' => (int) Subject::max('sort_order') + 1]);

        return back()->with('status', '“' . $data['name'] . '” was added to the subjects.');
    }

    public function updateSubject(Request $request, Subject $subject): RedirectResponse
    {
        $data = $this->subjectData($request, $subject);
        $subject->update($data);

        return back()->with('status', '“' . $subject->name . '” was saved.');
    }

    public function destroySubject(Subject $subject): RedirectResponse
    {
        $books  = Book::where('subject_id', $subject->id)->count();
        $papers = Paper::where('subject_id', $subject->id)->count();

        if ($books || $papers) {
            return back()->with('fail', '“' . $subject->name . '” is still used by ' . $books . ' book' . ($books === 1 ? '' : 's') . ' and ' . $papers . ' paper' . ($papers === 1 ? '' : 's') . '. Move or delete those first.');
        }

        $subject->delete();

        return back()->with('status', '“' . $subject->name . '” was deleted.');
    }

    /** Book types and book badges (the two chip lists). */
    public function lists(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'types'    => ['nullable', 'array', 'max:40'],
            'types.*'  => ['nullable', 'string', 'max:60'],
            'badges'   => ['nullable', 'array', 'max:40'],
            'badges.*' => ['nullable', 'string', 'max:60'],
        ]);

        $clean = fn (array $v) => array_values(array_unique(array_filter(array_map('trim', $v), fn ($s) => $s !== '')));
        $types = $clean($data['types'] ?? []);

        if (! $types) {
            throw ValidationException::withMessages(['types' => 'Keep at least one book type.']);
        }

        CatalogueOption::write('book_types', $types);
        CatalogueOption::write('book_badges', $clean($data['badges'] ?? []));

        return back()->with('status', 'Book types and badges saved.');
    }

    private function subjectData(Request $request, ?Subject $subject): array
    {
        $color = ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'];

        $data = $request->validate([
            'name' => ['required', 'string', 'max:80', Rule::unique('subjects', 'name')->ignore($subject?->id)],
            'c1'   => $color, 'c2' => $color, 'c3' => $color,
        ], [
            'name.required' => 'Enter the subject name.',
            'name.unique'   => 'This subject already exists.',
            'c*.regex'      => 'Choose a colour.',
        ]);

        return ['name' => trim($data['name']), 'color_1' => strtoupper($data['c1']), 'color_2' => strtoupper($data['c2']), 'color_3' => strtoupper($data['c3'])];
    }
}
