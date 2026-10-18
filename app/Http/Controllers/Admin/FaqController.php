<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Faq;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Questions and answers on the Contact page and the Holiday packages page. */
class FaqController extends Controller
{
    public function index()
    {
        return view('admin.faqs');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->data($request);
        Faq::create($data + ['visible' => true, 'sort_order' => (int) Faq::where('page', $data['page'])->max('sort_order') + 1]);

        return back()->with('status', 'Question added.');
    }

    public function update(Request $request, Faq $faq): RedirectResponse
    {
        $faq->update($this->data($request));

        return back()->with('status', 'Question saved.');
    }

    public function destroy(Faq $faq): RedirectResponse
    {
        $faq->delete();

        return back()->with('status', 'Question deleted.');
    }

    public function toggle(Faq $faq): RedirectResponse
    {
        $faq->update(['visible' => ! $faq->visible]);

        return back()->with('status', $faq->visible ? 'Question is shown on the website.' : 'Question is hidden from the website.');
    }

    /** New order of one page's questions after dragging: { ids: [3, 1, 2] }. Only ids of that page count. */
    public function reorder(Request $request): JsonResponse
    {
        $data = $request->validate(['page' => ['required', Rule::in(array_keys(Faq::PAGES))], 'ids' => ['required', 'array', 'max:200'], 'ids.*' => ['integer']]);

        foreach (array_values($data['ids']) as $i => $id) {
            Faq::where('id', $id)->where('page', $data['page'])->update(['sort_order' => $i + 1]);
        }

        return response()->json(['ok' => true]);
    }

    private function data(Request $request): array
    {
        $d = $request->validate([
            'page'     => ['required', Rule::in(array_keys(Faq::PAGES))],
            'question' => ['required', 'string', 'max:255'],
            'answer'   => ['required', 'string', 'max:2000'],
            'icon'     => ['required', Rule::in(array_keys(config('faq_icons')))],
        ], ['question.required' => 'Enter the question.', 'answer.required' => 'Enter the answer.']);

        return ['page' => $d['page'], 'question' => trim($d['question']), 'answer' => trim($d['answer']), 'icon' => $d['icon']];
    }
}
