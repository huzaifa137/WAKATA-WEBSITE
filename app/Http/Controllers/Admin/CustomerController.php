<?php

namespace App\Http\Controllers\Admin;

use App\Models\Customer;
use App\Support\AdminSales;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/** Customers (module 8). Looking is open to every signed-in admin; adding and notes need admin.can:sales. */
class CustomerController extends SalesController
{
    public function index()
    {
        return view('admin.customers');
    }

    /**
     * "Add a customer": for people who order by phone or at the shop. The account gets a random password nobody knows,
     * so the person signs in the first time with "Forgot password" (or never, and just orders through staff).
     */
    public function store(Request $request): JsonResponse
    {
        $request->merge(['email' => Str::lower(trim((string) $request->input('email')))]);

        $data = $request->validate([
            'name'     => ['required', 'string', 'max:120'],
            'type'     => ['required', Rule::in(Customer::TYPES)],
            'phone'    => ['nullable', 'string', 'max:30'],
            'email'    => ['required', 'email', 'max:190', Rule::unique('customers', 'email')],
            'contact'  => ['nullable', 'string', 'max:120'],
            'district' => ['nullable', 'string', 'max:80'],
        ], [
            'name.required'  => 'Enter the name or school.',
            'email.required' => 'Enter an email address: orders and receipts are sent there.',
            'email.email'    => 'Enter a valid email address.',
            'email.unique'   => 'A customer with this email already exists.',
        ]);

        $customer = Customer::create([
            'name'           => trim($data['name']),
            'type'           => $data['type'],
            'phone'          => $data['phone'] ?? null,
            'email'          => $data['email'],
            'contact_person' => $data['contact'] ?? null,
            'district'       => $data['district'] ?? null,
            'school'         => in_array($data['type'], ['School', 'Bookshop'], true) ? trim($data['name']) : null,
            'password'       => Str::random(40),
            'status'         => 'active',
        ]);

        return response()->json(['ok' => true, 'message' => $customer->name . ' added.', 'customer' => AdminSales::customerPayload($customer)], 201);
    }

    public function note(Request $request, Customer $customer): JsonResponse
    {
        $data = $request->validate(['admin_note' => ['nullable', 'string', 'max:2000']]);
        $customer->forceFill(['admin_note' => trim((string) ($data['admin_note'] ?? '')) ?: null])->save();

        return response()->json(['ok' => true, 'message' => 'Note saved.', 'customer' => AdminSales::customerPayload($customer->fresh())]);
    }
}
