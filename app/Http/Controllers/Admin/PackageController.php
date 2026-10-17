<?php

namespace App\Http\Controllers\Admin;

use App\Models\HolidayPackage;
use App\Models\Level;
use App\Support\Catalogue;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/** Admin catalogue: holiday packages. */
class PackageController extends CatalogueController
{
    private const DISK = 'local';

    public function index()
    {
        return view('admin.packages');
    }

    public function form(Request $request)
    {
        $record = null;
        if ($id = $request->query('id')) {
            $record = HolidayPackage::where('slug', $id)->first();
            if (! $record) {
                return redirect()->route('admin.packages')->with('fail', 'That package is not in the catalogue any more.');
            }
        }

        return view('admin.package-form', ['record' => $record, 'levels' => Level::orderBy('sort_order')->orderBy('id')->get(['code', 'label'])]);
    }

    public function store(Request $request): RedirectResponse
    {
        return $this->save($request, new HolidayPackage(), 'added');
    }

    public function update(Request $request, HolidayPackage $package): RedirectResponse
    {
        return $this->save($request, $package, 'saved');
    }

    public function destroy(HolidayPackage $package): RedirectResponse
    {
        $name = $package->name;
        if ($package->timetable_path) {
            Storage::disk(self::DISK)->delete($package->timetable_path);
        }
        $package->delete();

        return $this->flashBack('admin.packages', '“' . $name . '” was deleted.');
    }

    private function save(Request $request, HolidayPackage $package, string $verb): RedirectResponse
    {
        $id = $package->exists ? $package->id : null;

        $data = $request->validate([
            'name'          => ['required', 'string', 'max:255'],
            'for'           => ['required', 'string', 'max:255'],
            'level'         => ['required', Rule::exists('levels', 'code')],
            'price'         => ['required', 'integer', 'min:0', 'max:100000000'],
            'includes'      => ['nullable', 'array', 'max:30'],
            'includes.*'    => ['nullable', 'string', 'max:200'],
            'timetablefile' => ['nullable', 'file', 'mimes:pdf', 'max:10240'],
            'sku'           => ['nullable', 'string', 'max:30', Rule::unique('holiday_packages', 'sku')->ignore($id)],
            'stock'         => ['nullable', 'integer', 'min:0', 'max:10000000'],
        ], [
            'name.required'  => 'Enter the package name.',
            'for.required'   => 'Say who the package is for.',
            'level.required' => 'Please choose a level.',
            'price.required' => 'Enter a price.',
            'timetablefile.*' => 'The revision timetable must be a PDF under 10 MB.',
            'sku.unique'     => 'Another item already uses this SKU.',
        ]);

        $level   = $this->levelByCode($data['level']);
        $options = $package->options ?? [];
        $options['swap'] = $request->boolean('swap');

        $package->fill([
            'level_id' => $level->id,
            'name'     => $data['name'],
            'audience' => $data['for'],
            'price'    => $data['price'],
            'is_best'  => $request->boolean('best'),
            'includes' => array_values(array_filter(array_map('trim', $data['includes'] ?? []), fn ($s) => $s !== '')),
            'status'   => $this->statusFrom($request),
            'options'  => $options,
        ]);

        if (! empty($data['sku'])) {
            $package->sku = $data['sku'];
        }
        if (! $package->exists) {
            $package->slug       = $this->uniqueSlug(HolidayPackage::class, 'hp-' . $data['name']);
            $package->sort_order = $this->nextOrder(HolidayPackage::class);
        }

        $wasNew = ! $package->exists;
        $package->save();

        if ($request->hasFile('timetablefile')) {
            Storage::disk(self::DISK)->putFileAs('timetables', $request->file('timetablefile'), $package->slug . '.pdf');
            $package->timetable_path = 'timetables/' . $package->slug . '.pdf';
            $package->save();
        }

        $this->syncStock($package, 'pack', isset($data['stock']) ? (int) $data['stock'] : null, $request, $wasNew ? 'Opening stock' : 'Edited on the package form');
        Catalogue::flush();

        return redirect()->route('admin.packages')->with('status', '“' . $package->name . '” was ' . $verb . ($package->status === 'draft' ? ' as a draft (hidden from the website).' : '.'));
    }
}
