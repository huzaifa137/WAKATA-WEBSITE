<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\CustomerAddress;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/** Delivery addresses, plus the "Delivery preferences" form. One address is the default (the one checkout pre-fills). */
class AddressController extends Controller
{
    private const MAX = 10;

    public function index(): View
    {
        return view('account.addresses');
    }

    public function store(Request $request): JsonResponse
    {
        $customer = $this->customer($request);
        $data     = $this->validated($request);

        if ($customer->addresses()->count() >= self::MAX) {
            return response()->json(['ok' => false, 'message' => 'You can keep up to ' . self::MAX . ' addresses. Delete one to add another.'], 422);
        }

        DB::transaction(function () use ($customer, $data) {
            $first   = ! $customer->addresses()->exists();
            $address = $customer->addresses()->create(Arr::except($data, 'is_default') + ['is_default' => false]);

            if ($first || $data['is_default']) {
                $this->makeDefault($customer, $address);
            }
        });

        return $this->state($customer, 'Address saved.', 201);
    }

    public function update(Request $request, CustomerAddress $address): JsonResponse
    {
        $customer = $this->customer($request);
        abort_unless($address->customer_id === $customer->id, 404);

        $data = $this->validated($request);

        DB::transaction(function () use ($customer, $address, $data) {
            $address->update(Arr::except($data, 'is_default'));

            if ($data['is_default']) {
                $this->makeDefault($customer, $address);
            }
        });

        return $this->state($customer, 'Address updated.');
    }

    public function makeDefaultAddress(Request $request, CustomerAddress $address): JsonResponse
    {
        $customer = $this->customer($request);
        abort_unless($address->customer_id === $customer->id, 404);

        $this->makeDefault($customer, $address);

        return $this->state($customer, 'Default address updated.');
    }

    public function destroy(Request $request, CustomerAddress $address): JsonResponse
    {
        $customer = $this->customer($request);
        abort_unless($address->customer_id === $customer->id, 404);

        DB::transaction(function () use ($customer, $address) {
            $wasDefault = $address->is_default;
            $address->delete();

            // The oldest remaining address takes over as the default
            if ($wasDefault && ($next = $customer->addresses()->orderBy('id')->first())) {
                $this->makeDefault($customer, $next);
            }
        });

        return $this->state($customer, 'Address deleted.');
    }

    /** The "Delivery preferences" card: preferred method and a note for the courier. */
    public function preferences(Request $request): JsonResponse
    {
        $customer = $this->customer($request);

        $data = $request->validate([
            'delivery_method' => ['required', Rule::in(['pickup', 'kampala', 'upcountry'])],
            'delivery_notes'  => ['nullable', 'string', 'max:200'],
        ]);

        $customer->forceFill(['preferences' => array_replace($customer->preferences ?? [], [
            'delivery_method' => $data['delivery_method'],
            'delivery_notes'  => trim((string) ($data['delivery_notes'] ?? '')),
        ])])->save();

        return response()->json(['ok' => true, 'message' => 'Delivery preferences saved.', 'prefs' => $customer->allPreferences()]);
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'label'      => ['nullable', 'string', 'max:40'],
            'recipient'  => ['required', 'string', 'max:120'],
            'line'       => ['required', 'string', 'max:255'],
            'district'   => ['nullable', 'string', 'max:80'],
            'phone'      => ['nullable', 'string', 'max:30'],
            'is_default' => ['nullable', 'boolean'],
        ], [
            'recipient.required' => 'Enter the name of the person receiving the order.',
            'line.required'      => 'Enter the street, building or school name.',
        ]);

        return [
            'label'      => trim((string) ($data['label'] ?? '')) ?: 'Home',
            'recipient'  => trim($data['recipient']),
            'line'       => trim($data['line']),
            'district'   => trim((string) ($data['district'] ?? '')) ?: null,
            'phone'      => trim((string) ($data['phone'] ?? '')) ?: null,
            'is_default' => (bool) ($data['is_default'] ?? false),
        ];
    }

    private function makeDefault(Customer $customer, CustomerAddress $address): void
    {
        $customer->addresses()->where('id', '!=', $address->id)->update(['is_default' => false]);
        $address->forceFill(['is_default' => true])->save();
    }

    private function state(Customer $customer, string $message, int $status = 200): JsonResponse
    {
        $list = $customer->addresses()->orderByDesc('is_default')->orderBy('id')->get()->map->toPortal()->all();

        return response()->json(['ok' => true, 'message' => $message, 'addresses' => $list], $status);
    }

    private function customer(Request $request): Customer
    {
        return $request->attributes->get('customer');
    }
}
