<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Support\PasswordRules;
use App\Support\CustomerSession;
use App\Support\RememberLogin;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Profile and settings. Every form posts to its own route and comes back to the right tab with a message.
 * Anything that weakens the account (new email, new password, two-step off, delete) asks for the current password.
 */
class ProfileController extends Controller
{
    public function show(Request $request): View
    {
        return view('account.profile', ['customer' => $this->customer($request), 'types' => Customer::TYPES]);
    }

    /** "Your details" tab. Changing the email asks for the current password, because the email is how you sign in. */
    public function update(Request $request): RedirectResponse
    {
        $customer = $this->customer($request);
        $request->merge(['email' => Str::lower(trim((string) $request->input('email')))]);

        $data = $request->validate([
            'name'           => ['required', 'string', 'max:120'],
            'email'          => ['required', 'email', 'max:190', Rule::unique('customers', 'email')->ignore($customer->id)],
            'phone'          => ['nullable', 'string', 'max:30'],
            'type'           => ['required', Rule::in(Customer::TYPES)],
            'email_password' => [Rule::requiredIf($request->input('email') !== $customer->email), 'nullable', 'string'],
        ], [
            'email.unique'            => 'Another account already uses this email.',
            'email_password.required' => 'Enter your current password to change your email.',
        ]);

        if ($data['email'] !== $customer->email && ! $this->passwordOk($request, $customer, $data['email_password'] ?? '', 'email_password')) {
            return $this->back('p-prof')->withErrors(['email_password' => 'That password is not correct.'])->withInput($request->except('email_password'));
        }

        $customer->forceFill([
            'name'  => trim($data['name']),
            'email' => $data['email'],
            'phone' => trim((string) ($data['phone'] ?? '')) ?: null,
            'type'  => $data['type'],
        ])->save();

        return $this->back('p-prof')->with('status', 'Your details have been saved.');
    }

    /** "School or organisation" tab: what invoices say. */
    public function organisation(Request $request): RedirectResponse
    {
        $customer = $this->customer($request);

        $data = $request->validate([
            'school'         => ['nullable', 'string', 'max:160'],
            'invoice_name'   => ['nullable', 'string', 'max:160'],
            'org_address'    => ['nullable', 'string', 'max:255'],
            'accounts_email' => ['nullable', 'email', 'max:190'],
        ]);

        $customer->forceFill([
            'school'         => trim((string) ($data['school'] ?? '')) ?: null,
            'invoice_name'   => trim((string) ($data['invoice_name'] ?? '')) ?: null,
            'org_address'    => trim((string) ($data['org_address'] ?? '')) ?: null,
            'accounts_email' => ($data['accounts_email'] ?? null) ? Str::lower(trim($data['accounts_email'])) : null,
            'preferences'    => array_replace($customer->preferences ?? [], ['show_stamp' => $request->boolean('show_stamp')]),
        ])->save();

        return $this->back('p-org')->with('status', 'Your school details have been saved.');
    }

    /** Security tab: new password. Other devices are signed out; this one stays signed in. */
    public function password(Request $request): RedirectResponse
    {
        $customer = $this->customer($request);

        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'new_password'     => ['required', 'string', PasswordRules::customer(), 'max:100', 'different:current_password'],
            'new_password_confirmation' => ['required', 'same:new_password'],
        ], [
            'current_password.required'          => 'Enter your current password.',
            'new_password.min'                   => 'Use at least 8 characters.',
            'new_password.different'             => 'Choose a password you have not used here before.',
            'new_password_confirmation.required' => 'Confirm the new password.',
            'new_password_confirmation.same'     => 'The passwords do not match.',
        ]);

        if (! $this->passwordOk($request, $customer, $data['current_password'], 'current_password')) {
            return $this->back('p-sec')->withErrors(['current_password' => 'That password is not correct.']);
        }

        $customer->forceFill(['password' => $data['new_password']])->save();
        CustomerSession::endOtherSessions($request, $customer);

        return $this->back('p-sec')->with('status', 'Password updated. Your other devices have been signed out.');
    }

    /** Security tab: two-step verification on or off. */
    public function twoStep(Request $request): RedirectResponse
    {
        $customer = $this->customer($request);

        $request->validate(['two_step_password' => ['required', 'string']], ['two_step_password.required' => 'Enter your current password.']);

        if (! $this->passwordOk($request, $customer, (string) $request->input('two_step_password'), 'two_step_password')) {
            return $this->back('p-sec')->withErrors(['two_step_password' => 'That password is not correct.']);
        }

        $on = $request->boolean('enabled');
        $customer->forceFill(['two_step_enabled' => $on])->save();

        return $this->back('p-sec')->with('status', $on
            ? 'Two-step verification is on. We will email you a code each time you sign in.'
            : 'Two-step verification is off.');
    }

    /** Security tab: sign out every other browser. */
    public function signOutOthers(Request $request): RedirectResponse
    {
        CustomerSession::endOtherSessions($request, $this->customer($request));

        return $this->back('p-sec')->with('status', 'You have been signed out everywhere else.');
    }

    /** Notifications tab. These are saved now and used as the order, file and support emails are sent. */
    public function notifications(Request $request): RedirectResponse
    {
        $customer = $this->customer($request);

        $customer->forceFill(['preferences' => array_replace($customer->preferences ?? [], [
            'notify_orders'   => $request->boolean('notify_orders'),
            'notify_files'    => $request->boolean('notify_files'),
            'notify_replies'  => $request->boolean('notify_replies'),
            'notify_news'     => $request->boolean('notify_news'),
            'notify_holidays' => $request->boolean('notify_holidays'),
        ])])->save();

        return $this->back('p-not')->with('status', 'Your notification choices have been saved.');
    }

    /** Payment numbers tab: mobile money numbers only. Cards are never stored. */
    public function paymentNumbers(Request $request): RedirectResponse
    {
        $customer = $this->customer($request);

        $data = $request->validate([
            'mtn_number'    => ['nullable', 'string', 'max:30', 'regex:/^[0-9+()\s-]*$/'],
            'airtel_number' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+()\s-]*$/'],
        ], [
            'mtn_number.regex'    => 'Use digits only, for example +256 77 123 4567.',
            'airtel_number.regex' => 'Use digits only, for example +256 75 123 4567.',
        ]);

        $customer->forceFill(['preferences' => array_replace($customer->preferences ?? [], [
            'mtn_number'      => trim((string) ($data['mtn_number'] ?? '')),
            'airtel_number'   => trim((string) ($data['airtel_number'] ?? '')),
            'prefill_payment' => $request->boolean('prefill_payment'),
        ])])->save();

        return $this->back('p-pay')->with('status', 'Your payment numbers have been saved.');
    }

    /** Privacy tab: a copy of everything we hold, as a JSON file. */
    public function export(Request $request): Response
    {
        $customer = $this->customer($request);

        $payload = [
            'exported_at' => now()->toIso8601String(),
            'profile'     => $customer->only(['name', 'email', 'phone', 'type', 'school', 'invoice_name', 'org_address', 'accounts_email', 'created_at']) + ['two_step_enabled' => $customer->two_step_enabled],
            'preferences' => $customer->allPreferences(),
            'addresses'   => $customer->addresses()->get(['label', 'recipient', 'line', 'district', 'phone', 'is_default'])->all(),
            'saved_items' => $customer->savedItems()->get(['item_type', 'item_slug', 'format', 'created_at'])->all(),
            'orders'      => $customer->orders()->with('items')->latest('id')->get()->map(fn ($o) => $o->only([
                'number', 'status', 'payment_status', 'payment_method', 'delivery_method', 'delivery_address', 'subtotal', 'delivery_fee', 'total', 'created_at',
            ]) + ['items' => $o->items->map->only(['title', 'item_type', 'format', 'unit_price', 'quantity', 'line_total'])->all()])->all(),
            'support'     => $customer->supportThreads()->with('messages')->get()->map(fn ($t) => $t->only(['ref', 'topic', 'subject', 'status', 'created_at']) + [
                'messages' => $t->messages->map->only(['sender', 'body', 'created_at'])->all(),
            ])->all(),
        ];

        return response(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 200, [
            'Content-Type'        => 'application/json',
            'Content-Disposition' => 'attachment; filename="wakata-my-data.json"',
            'Cache-Control'       => 'private, no-store',
        ]);
    }

    /**
     * Privacy tab: delete the account. Addresses, saved items and conversations go with it. Orders stay (without the
     * link to the account), because invoices for paid orders are kept for accounting.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $customer = $this->customer($request);

        $request->validate(['delete_password' => ['required', 'string']], ['delete_password.required' => 'Enter your current password.']);

        if (! $this->passwordOk($request, $customer, (string) $request->input('delete_password'), 'delete_password')) {
            return $this->back('p-priv')->withErrors(['delete_password' => 'That password is not correct.']);
        }

        RememberLogin::forget($customer, 'wk_customer_remember');
        $customer->delete();

        $request->session()->forget(['LoggedCustomer', 'LoggedCustomerEpoch', 'CustomerPending', 'CustomerPendingRemember', 'account.intended']);
        $request->session()->regenerate();

        return redirect()->route('site.home')->with('status', 'Your account has been deleted.');
    }

    /** Wrong passwords are rate limited, so this cannot be used to guess a password from a stolen session. */
    private function passwordOk(Request $request, Customer $customer, string $password, string $field): bool
    {
        $key = 'customer-pw-check|' . $customer->id;

        if (RateLimiter::tooManyAttempts($key, 5)) {
            abort(429, 'Too many wrong passwords. Please wait a few minutes.');
        }

        if (Hash::check($password, $customer->password)) {
            RateLimiter::clear($key);

            return true;
        }

        RateLimiter::hit($key, 300);

        return false;
    }

    private function back(string $tab): RedirectResponse
    {
        return redirect(route('account.profile') . '#' . $tab);
    }

    private function customer(Request $request): Customer
    {
        return $request->attributes->get('customer');
    }
}
