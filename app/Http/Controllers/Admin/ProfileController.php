<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Support\PasswordRules;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Module 11: "My profile". Every signed-in team member manages their own name, phone, photo, password and email alerts.
 * The email address is the sign-in name, so only a super admin can change it (Team and roles).
 */
class ProfileController extends Controller
{
    public const AVATAR_DIR = 'uploads/avatars';

    public function show(Request $request): View
    {
        return view('admin.profile', ['me' => $request->attributes->get('admin')]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name'  => ['required', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+()\-\s]{6,30}$/'],
        ], ['name.required' => 'Enter your name.', 'phone.regex' => 'Enter a phone number with digits, spaces, + or -.']);

        $request->attributes->get('admin')->forceFill(['name' => trim($data['name']), 'phone' => trim((string) ($data['phone'] ?? '')) ?: null])->save();

        return back()->with('status', 'Your details were saved.');
    }

    public function photo(Request $request): RedirectResponse
    {
        $request->validate(
            ['photo' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:1024']],
            ['photo.*' => 'Choose a JPG, PNG or WebP picture up to 1 MB.']
        );

        /** @var Admin $me */
        $me   = $request->attributes->get('admin');
        $file = $request->file('photo');
        $ext  = $file->guessExtension() === 'jpeg' ? 'jpg' : $file->guessExtension();
        $name = 'admin-' . $me->id . '-' . Str::lower(Str::random(8)) . '.' . $ext;

        File::ensureDirectoryExists(public_path(self::AVATAR_DIR));
        $file->move(public_path(self::AVATAR_DIR), $name);

        $this->forgetPhoto($me);
        $me->forceFill(['avatar_path' => self::AVATAR_DIR . '/' . $name])->save();

        return back()->with('status', 'Your photo was changed.');
    }

    public function removePhoto(Request $request): RedirectResponse
    {
        $me = $request->attributes->get('admin');
        $this->forgetPhoto($me);
        $me->forceFill(['avatar_path' => null])->save();

        return back()->with('status', 'Your photo was removed.');
    }

    public function password(Request $request): RedirectResponse
    {
        /** @var Admin $me */
        $me  = $request->attributes->get('admin');
        $key = 'admin-password|' . $me->id;

        if (RateLimiter::tooManyAttempts($key, 5)) {
            return back()->with('fail', 'Too many wrong passwords. Try again in ' . (int) ceil(RateLimiter::availableIn($key) / 60) . ' minute(s).');
        }

        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'new_password'     => ['required', 'string', PasswordRules::admin(), 'max:100', 'different:current_password', 'confirmed'],
        ], [
            'new_password.confirmed' => 'The two new passwords are not the same.',
            'new_password.different' => 'Choose a password you have not used just now.',
        ]);

        if (! Hash::check($data['current_password'], $me->password)) {
            RateLimiter::hit($key, 300);

            return back()->withErrors(['current_password' => 'This is not your current password.']);
        }

        RateLimiter::clear($key);

        // A new password also signs out every "keep me signed in" device
        $me->forceFill(['password' => $data['new_password'], 'remember_token' => null])->save();

        return back()->with('status', 'Your password was changed. Devices that were set to stay signed in have been signed out.');
    }

    public function notifications(Request $request): RedirectResponse
    {
        $request->attributes->get('admin')->forceFill(['prefs' => [
            'alert_orders'   => $request->boolean('alert_orders'),
            'alert_messages' => $request->boolean('alert_messages'),
        ]])->save();

        return back()->with('status', 'Your email alerts were saved.');
    }

    private function forgetPhoto(Admin $me): void
    {
        if ($me->avatar_path && str_starts_with($me->avatar_path, self::AVATAR_DIR . '/')) {
            File::delete(public_path($me->avatar_path));
        }
    }
}
