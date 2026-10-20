<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Mail\CustomerOtpMail;
use App\Mail\ResetPasswordMail;
use App\Models\Customer;
use App\Support\PasswordRules;
use App\Support\CustomerSession;
use App\Support\RememberLogin;
use App\Support\ResetTokens;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\View\View;

/** Customer sign-in with a custom session: session('LoggedCustomer') = customer id. */
class AuthController extends Controller
{
    private const GUARD  = 'customer';
    private const COOKIE = 'wk_customer_remember';

    public function showLogin(): View
    {
        return view('account.auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $request->merge(['email' => Str::lower(trim((string) $request->input('email')))]);
        $data = $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $key = 'customer-login|' . $data['email'] . '|' . $request->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            $minutes = (int) ceil(RateLimiter::availableIn($key) / 60);

            return back()->withInput($request->only('email'))
                ->withErrors(['email' => "Too many attempts. Try again in {$minutes} minute(s)."]);
        }

        $customer = Customer::where('email', $data['email'])->first();

        if (! $customer || ! Hash::check($data['password'], $customer->password)) {
            RateLimiter::hit($key, 300);

            return back()->withInput($request->only('email'))
                ->withErrors(['email' => 'These credentials do not match our records.']);
        }

        if (! $customer->isActive()) {
            return back()->withInput($request->only('email'))
                ->withErrors(['email' => 'This account is suspended. Please contact WAKATA support.']);
        }

        RateLimiter::clear($key);

        if ($customer->two_step_enabled) {
            $request->session()->regenerate();
            $request->session()->put('CustomerPending', $customer->id);
            $request->session()->put('CustomerPendingRemember', $request->boolean('remember'));
            $this->sendOtp($customer);

            return redirect()->route('account.verify');
        }

        return $this->signIn($request, $customer, $request->boolean('remember'));
    }

    public function showRegister(): View
    {
        return view('account.auth.register', ['types' => Customer::TYPES]);
    }

    public function register(Request $request): RedirectResponse
    {
        $request->merge(['email' => Str::lower(trim((string) $request->input('email')))]);
        $data = $request->validate([
            'name'     => ['required', 'string', 'max:120'],
            'email'    => ['required', 'email', 'max:190', 'unique:customers,email'],
            'phone'    => ['required', 'string', 'max:30'],
            'type'     => ['required', 'in:' . implode(',', Customer::TYPES)],
            'school'   => ['nullable', 'string', 'max:160'],
            'password' => ['required', 'string', PasswordRules::customer(), 'max:100'],
            'confirm'  => ['required', 'same:password'],
            'terms'    => ['accepted'],
        ], [
            'email.unique'   => 'An account with this email already exists. Try signing in.',
            'confirm.same'   => 'The passwords do not match.',
            'terms.accepted' => 'Please accept to continue.',
        ]);

        $customer = Customer::create([
            'name'     => trim($data['name']),
            'email'    => $data['email'],
            'phone'    => trim($data['phone']),
            'type'     => $data['type'],
            'school'   => $data['school'] ?? null,
            'password' => $data['password'],
        ]);

        return $this->signIn($request, $customer, false, 'Welcome to WAKATA, ' . strtok($customer->name, ' ') . '! Your account has been created.');
    }

    private function signIn(Request $request, Customer $customer, bool $remember, ?string $welcome = null): RedirectResponse
    {
        $request->session()->forget(['CustomerPending', 'CustomerPendingRemember']);
        CustomerSession::start($request, $customer);

        $customer->forceFill(['last_login_at' => now(), 'otp_code' => null, 'otp_expires_at' => null, 'otp_attempts' => 0])->save();

        if ($remember) {
            RememberLogin::issue($customer, self::COOKIE);
        }

        $to = $request->session()->pull('account.intended');

        $redirect = redirect()->to($to && Str::startsWith($to, url('/account')) ? $to : route('account.dashboard'));

        return $welcome ? $redirect->with('status', $welcome) : $redirect;
    }

    /* ---------------------------------------------------- two-step code */

    public function showVerify(Request $request): View|RedirectResponse
    {
        if (! $request->session()->has('CustomerPending')) {
            return redirect()->route('account.login');
        }

        return view('account.auth.verify');
    }

    public function verify(Request $request): RedirectResponse
    {
        $customer = Customer::find($request->session()->get('CustomerPending'));
        if (! $customer || ! $customer->isActive()) {
            $request->session()->forget(['CustomerPending', 'CustomerPendingRemember']);

            return redirect()->route('account.login');
        }

        $request->validate(['code' => ['required', 'digits:6']], [
            'code.required' => 'Enter the 6-digit code.',
            'code.digits'   => 'Enter the 6-digit code.',
        ]);

        if ($customer->otp_attempts >= 5) {
            $request->session()->forget(['CustomerPending', 'CustomerPendingRemember']);

            return redirect()->route('account.login')->with('fail', 'Too many wrong codes. Please sign in again.');
        }

        if (! $customer->otp_code || ! $customer->otp_expires_at || $customer->otp_expires_at->isPast()) {
            return back()->withErrors(['code' => 'That code has expired. Request a new one.']);
        }

        if (! Hash::check((string) $request->input('code'), $customer->otp_code)) {
            $customer->increment('otp_attempts');

            return back()->withErrors(['code' => 'That code is not correct.']);
        }

        return $this->signIn($request, $customer, (bool) $request->session()->get('CustomerPendingRemember'));
    }

    public function resendOtp(Request $request): JsonResponse
    {
        $customer = Customer::find($request->session()->get('CustomerPending'));
        if (! $customer) {
            return response()->json(['message' => 'Your session expired. Please sign in again.'], 401);
        }

        $key = 'customer-otp|' . $customer->id;
        if (RateLimiter::tooManyAttempts($key, 1)) {
            return response()->json(['message' => 'Please wait a minute before asking for another code.'], 429);
        }
        RateLimiter::hit($key, 60);

        $this->sendOtp($customer);

        return response()->json(['message' => 'A new code has been sent to your email.']);
    }

    /** Emails a fresh 6-digit code (stored hashed, valid 10 minutes). With MAIL_MAILER=log it lands in storage/logs/laravel.log. */
    private function sendOtp(Customer $customer): void
    {
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        $customer->forceFill(['otp_code' => Hash::make($code), 'otp_expires_at' => now()->addMinutes(10), 'otp_attempts' => 0])->save();

        try {
            Mail::to($customer->email)->send(new CustomerOtpMail($customer->name, $code));
        } catch (\Throwable $e) {
            report($e);
        }
    }

    public function logout(Request $request): RedirectResponse
    {
        $id = $request->session()->get('LoggedCustomer');
        RememberLogin::forget($id ? Customer::find($id) : null, self::COOKIE);

        $request->session()->forget(['LoggedCustomer', 'LoggedCustomerEpoch', 'CustomerPending', 'CustomerPendingRemember', 'account.intended']);
        $request->session()->regenerate();

        return redirect()->route('account.login')->with('status', 'You have been signed out.');
    }

    /* ------------------------------------------------- forgot and reset */

    public function showForgot(): View
    {
        return view('account.auth.forgot-password');
    }

    public function sendReset(Request $request): RedirectResponse
    {
        $request->merge(['email' => Str::lower(trim((string) $request->input('email')))]);
        $data = $request->validate(['email' => ['required', 'email']]);

        $key = 'customer-forgot|' . $data['email'] . '|' . $request->ip();
        if (! RateLimiter::tooManyAttempts($key, 3)) {
            RateLimiter::hit($key, 600);

            $customer = Customer::where('email', $data['email'])->first();
            if ($customer && $customer->isActive()) {
                $token = ResetTokens::create(self::GUARD, $customer->email);
                $url = route('account.reset-password', ['token' => $token, 'email' => $customer->email]);

                try {
                    Mail::to($customer->email)->send(new ResetPasswordMail($customer->name, $url, 'WAKATA'));
                } catch (\Throwable $e) {
                    report($e);
                }
            }
        }

        return back()->with('reset_sent', $data['email']);
    }

    public function showReset(Request $request): View|RedirectResponse
    {
        $token = (string) $request->query('token');
        $email = Str::lower((string) $request->query('email'));

        if (! ResetTokens::valid(self::GUARD, $email, $token)) {
            return redirect()->route('account.forgot-password')->with('fail', 'That reset link is invalid or has expired. Request a new one.');
        }

        return view('account.auth.reset-password', compact('token', 'email'));
    }

    public function reset(Request $request): RedirectResponse
    {
        $request->merge(['email' => Str::lower(trim((string) $request->input('email')))]);
        $data = $request->validate([
            'token'    => ['required', 'string'],
            'email'    => ['required', 'email'],
            'password' => ['required', 'string', PasswordRules::customer(), 'max:100'],
            'confirm'  => ['required', 'same:password'],
        ], [
            'confirm.same' => 'The passwords do not match.',
        ]);

        $customer = Customer::where('email', $data['email'])->first();

        if (! $customer || ! ResetTokens::valid(self::GUARD, $data['email'], $data['token'])) {
            return redirect()->route('account.forgot-password')->with('fail', 'That reset link is invalid or has expired. Request a new one.');
        }

        $customer->forceFill(['password' => $data['password']])->save();
        CustomerSession::endOtherSessions($request, $customer);   // a reset signs the account out everywhere
        ResetTokens::forget(self::GUARD, $customer->email);

        return redirect()->route('account.login')->with('status', 'Password updated. Please sign in with your new password.');
    }
}
