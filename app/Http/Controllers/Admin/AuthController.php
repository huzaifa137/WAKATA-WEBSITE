<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\AdminOtpMail;
use App\Mail\ResetPasswordMail;
use App\Models\Admin;
use App\Support\Activity;
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

/**
 * Admin sign-in with custom sessions (same idea as SMASA):
 *   session('LoggedAdmin')  = admin id, set once the sign-in is complete
 *   session('AdminPending') = admin id waiting for the two-step code
 *   session('AdminLocked')  = true while the screen is locked
 */
class AuthController extends Controller
{
    private const GUARD  = 'admin';
    private const COOKIE = 'wk_admin_remember';

    /* ---------------------------------------------------------- sign in */

    public function showLogin(): View
    {
        return view('admin.auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $request->merge(['email' => Str::lower(trim((string) $request->input('email')))]);
        $data = $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $key = 'admin-login|' . $data['email'] . '|' . $request->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            $minutes = (int) ceil(RateLimiter::availableIn($key) / 60);

            return back()->withInput($request->only('email'))
                ->withErrors(['email' => "Too many attempts. Try again in {$minutes} minute(s)."]);
        }

        $admin = Admin::where('email', $data['email'])->first();

        if (! $admin || ! Hash::check($data['password'], $admin->password)) {
            RateLimiter::hit($key, 300);

            return back()->withInput($request->only('email'))
                ->withErrors(['email' => 'These credentials do not match our records.']);
        }

        if (! $admin->isActive()) {
            return back()->withInput($request->only('email'))
                ->withErrors(['email' => 'This account is suspended. Please contact the super admin.']);
        }

        RateLimiter::clear($key);
        $request->session()->regenerate();
        $remember = $request->boolean('remember');

        if ($admin->two_factor_enabled) {
            $this->sendOtp($admin);
            $request->session()->put('AdminPending', $admin->id);
            $request->session()->put('AdminPendingRemember', $remember);

            return redirect()->route('admin.verify');
        }

        return $this->signIn($request, $admin, $remember);
    }

    private function signIn(Request $request, Admin $admin, bool $remember): RedirectResponse
    {
        $request->session()->forget(['AdminPending', 'AdminPendingRemember', 'AdminLocked']);
        $request->session()->put('LoggedAdmin', $admin->id);

        $admin->forceFill([
            'last_login_at'  => now(),
            'last_login_ip'  => $request->ip(),
            'otp_code'       => null,
            'otp_expires_at' => null,
            'otp_attempts'   => 0,
        ])->save();

        if ($remember) {
            RememberLogin::issue($admin, self::COOKIE);
        }

        Activity::log($admin, 'auth', 'check', 'Signed in', null, $request->ip());

        $to = $request->session()->pull('admin.intended');

        return redirect()->to($to && Str::startsWith($to, url('/admin')) ? $to : route('admin.dashboard'));
    }

    public function logout(Request $request): RedirectResponse
    {
        $id = $request->session()->get('LoggedAdmin');
        $who = $id ? Admin::find($id) : null;
        RememberLogin::forget($who, self::COOKIE);

        if ($who) {
            Activity::log($who, 'auth', 'logout', 'Signed out', null, $request->ip());
        }

        $request->session()->forget(['LoggedAdmin', 'AdminPending', 'AdminPendingRemember', 'AdminLocked', 'admin.intended']);
        $request->session()->regenerate();

        return redirect()->route('admin.login')->with('status', 'You have been signed out.');
    }

    /* ---------------------------------------------------- two-step code */

    public function showVerify(Request $request): View|RedirectResponse
    {
        if (! $request->session()->has('AdminPending')) {
            return redirect()->route('admin.login');
        }

        return view('admin.auth.verify');
    }

    public function verify(Request $request): RedirectResponse
    {
        $admin = Admin::find($request->session()->get('AdminPending'));
        if (! $admin) {
            return redirect()->route('admin.login');
        }

        $request->validate(['code' => ['required', 'digits:6']], [
            'code.required' => 'Enter the 6-digit code.',
            'code.digits'   => 'Enter the 6-digit code.',
        ]);

        if ($admin->otp_attempts >= 5) {
            $request->session()->forget(['AdminPending', 'AdminPendingRemember']);

            return redirect()->route('admin.login')->with('fail', 'Too many wrong codes. Please sign in again.');
        }

        if (! $admin->otp_code || ! $admin->otp_expires_at || $admin->otp_expires_at->isPast()) {
            return back()->withErrors(['code' => 'That code has expired. Request a new one.']);
        }

        if (! Hash::check((string) $request->input('code'), $admin->otp_code)) {
            $admin->increment('otp_attempts');

            return back()->withErrors(['code' => 'That code is not correct.']);
        }

        return $this->signIn($request, $admin, (bool) $request->session()->get('AdminPendingRemember'));
    }

    public function resendOtp(Request $request): JsonResponse
    {
        $admin = Admin::find($request->session()->get('AdminPending'));
        if (! $admin) {
            return response()->json(['message' => 'Your session expired. Please sign in again.'], 401);
        }

        $key = 'admin-otp|' . $admin->id;
        if (RateLimiter::tooManyAttempts($key, 1)) {
            return response()->json(['message' => 'Please wait a minute before asking for another code.'], 429);
        }
        RateLimiter::hit($key, 60);

        $this->sendOtp($admin);

        return response()->json(['message' => 'A new code has been sent to your email.']);
    }

    private function sendOtp(Admin $admin): void
    {
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        $admin->forceFill([
            'otp_code'       => Hash::make($code),
            'otp_expires_at' => now()->addMinutes(10),
            'otp_attempts'   => 0,
        ])->save();

        try {
            Mail::to($admin->email)->send(new AdminOtpMail($admin->name, $code));
        } catch (\Throwable $e) {
            report($e); // with MAIL_MAILER=log the code is written to storage/logs/laravel.log
        }
    }

    /** The "Two-step verification" switch on the Settings and Profile pages. */
    public function twoFactor(Request $request): JsonResponse
    {
        $enabled = $request->boolean('enabled');
        $request->attributes->get('admin')->forceFill(['two_factor_enabled' => $enabled])->save();

        return response()->json(['enabled' => $enabled]);
    }

    /* ------------------------------------------------- forgot and reset */

    public function showForgot(): View
    {
        return view('admin.auth.forgot-password');
    }

    public function sendReset(Request $request): RedirectResponse
    {
        $request->merge(['email' => Str::lower(trim((string) $request->input('email')))]);
        $data = $request->validate(['email' => ['required', 'email']]);

        $key = 'admin-forgot|' . $data['email'] . '|' . $request->ip();
        if (! RateLimiter::tooManyAttempts($key, 3)) {
            RateLimiter::hit($key, 600);

            $admin = Admin::where('email', $data['email'])->first();
            if ($admin && $admin->isActive()) {
                $token = ResetTokens::create(self::GUARD, $admin->email);
                $url = route('admin.reset-password', ['token' => $token, 'email' => $admin->email]);

                try {
                    Mail::to($admin->email)->send(new ResetPasswordMail($admin->name, $url, 'WAKATA Admin'));
                } catch (\Throwable $e) {
                    report($e);
                }
            }
        }

        // Same answer whether or not the account exists.
        return back()->with('reset_sent', $data['email']);
    }

    public function showReset(Request $request): View|RedirectResponse
    {
        $token = (string) $request->query('token');
        $email = Str::lower((string) $request->query('email'));

        if (! ResetTokens::valid(self::GUARD, $email, $token)) {
            return redirect()->route('admin.forgot-password')->with('fail', 'That reset link is invalid or has expired. Request a new one.');
        }

        return view('admin.auth.reset-password', compact('token', 'email'));
    }

    public function reset(Request $request): RedirectResponse
    {
        $request->merge(['email' => Str::lower(trim((string) $request->input('email')))]);
        $data = $request->validate([
            'token'    => ['required', 'string'],
            'email'    => ['required', 'email'],
            'password' => ['required', 'string', 'min:8', 'max:100'],
            'confirm'  => ['required', 'same:password'],
        ], [
            'confirm.same' => 'The passwords do not match.',
        ]);

        $admin = Admin::where('email', $data['email'])->first();

        if (! $admin || ! ResetTokens::valid(self::GUARD, $data['email'], $data['token'])) {
            return redirect()->route('admin.forgot-password')->with('fail', 'That reset link is invalid or has expired. Request a new one.');
        }

        // Clearing remember_token signs out every "keep me signed in" device.
        // The same link is how an invited team member (status "invited") chooses a first password and joins.
        $joined = $admin->status === 'invited';
        $admin->forceFill(['password' => $data['password'], 'remember_token' => null, 'status' => $joined ? 'active' : $admin->status])->save();
        ResetTokens::forget(self::GUARD, $admin->email);
        Activity::log($admin, 'auth', 'key', $joined ? 'Accepted the invitation and chose a password' : 'Reset the password', null, $request->ip());

        return redirect()->route('admin.login')->with('status', 'Password updated. Please sign in with your new password.');
    }

    /* ------------------------------------------------------ lock screen */

    public function lock(Request $request): RedirectResponse
    {
        $request->session()->put('AdminLocked', true);

        return redirect()->route('admin.locked');
    }

    public function showLocked(Request $request): View|RedirectResponse
    {
        if (! $request->session()->get('AdminLocked')) {
            return redirect()->route('admin.dashboard');
        }

        return view('admin.auth.locked');
    }

    public function unlock(Request $request): RedirectResponse
    {
        $admin = $request->attributes->get('admin');
        $request->validate(['password' => ['required', 'string']]);

        $key = 'admin-unlock|' . $admin->id . '|' . $request->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            return back()->withErrors(['password' => 'Too many attempts. Try again in a few minutes.']);
        }

        if (! Hash::check((string) $request->input('password'), $admin->password)) {
            RateLimiter::hit($key, 300);

            return back()->withErrors(['password' => 'That password is not correct.']);
        }

        RateLimiter::clear($key);
        $request->session()->forget('AdminLocked');

        $to = $request->session()->pull('admin.intended');

        return redirect()->to($to && Str::startsWith($to, url('/admin')) ? $to : route('admin.dashboard'));
    }
}
