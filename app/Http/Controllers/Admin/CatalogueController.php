<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\Level;
use App\Models\Subject;
use App\Support\Inventory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/** Shared helpers of the catalogue controllers (books, papers, exam sets, packages). */
abstract class CatalogueController extends Controller
{
    protected function admin(Request $request): ?Admin
    {
        return $request->attributes->get('admin');
    }

    /** "Save as draft" and "Save and publish" send intent; otherwise the Status select decides. */
    protected function statusFrom(Request $request): string
    {
        return match ($request->input('intent')) {
            'draft'   => 'draft',
            'publish' => 'published',
            default   => $request->input('status') === 'draft' ? 'draft' : 'published',
        };
    }

    /** @param class-string<Model> $model */
    protected function uniqueSlug(string $model, string $wanted, ?int $ignoreId = null): string
    {
        $base = Str::limit(Str::slug($wanted) ?: 'item', 110, '');
        $slug = $base;
        $n    = 2;

        while ($model::query()->where('slug', $slug)->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))->exists()) {
            $slug = $base . '-' . $n++;
        }

        return $slug;
    }

    /** @param class-string<Model> $model */
    protected function nextOrder(string $model): int
    {
        return (int) $model::query()->max('sort_order') + 1;
    }

    protected function levelByCode(string $code): Level
    {
        return Level::where('code', $code)->firstOrFail();
    }

    protected function subjectByName(string $name): Subject
    {
        return Subject::where('name', $name)->firstOrFail();
    }

    /** Printed stock typed into a form goes through Inventory so the history stays complete. */
    protected function syncStock(Model $item, string $kind, ?int $wanted, Request $request, string $note): void
    {
        if ($wanted === null) {
            return;
        }
        if ((int) $item->{Inventory::column($kind)} === $wanted) {
            return;
        }

        Inventory::adjust($item, $kind, 'set', $wanted, 'count', $note, $this->admin($request));
    }

    protected function flashBack(string $route, string $message)
    {
        return redirect()->route($route)->with('status', $message);
    }
}
