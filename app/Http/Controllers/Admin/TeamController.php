<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\GeneralMail;
use App\Models\Admin;
use App\Support\AdminInsights;
use App\Support\ResetTokens;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Module 10: Team and roles. Everyone on the team can see who is on it; only a super admin can invite, change or remove.
 *
 * Inviting creates the account (status "invited", a long random password nobody knows) and emails a link that works for
 * seven days. The link is the normal reset-password page: choosing a password there makes the account active.
 * The four roles are fixed in code (see Admin::permissionMatrix), so the table on the page always matches what the console does.
 */
class TeamController extends Controller
{
    private const INVITE_DAYS = 7;

    public function index(Request $request): View
    {
        return view('admin.team', ['ins' => AdminInsights::team($request->attributes->get('admin'))]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->merge(['email' => Str::lower(trim((string) $request->input('email')))]);

        $data = $request->validate([
            'name'  => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190', Rule::unique('admins', 'email')],
            'role'  => ['required', Rule::in(array_keys(Admin::ROLES))],
        ], [
            'email.unique' => 'Someone on the team already uses this email.',
            'role.in'      => 'Choose one of the roles in the list.',
        ]);

        $admin = Admin::create([
            'name'               => trim($data['name']),
            'email'              => $data['email'],
            'password'           => Str::random(48),                // never used: the invitation link sets the real one
            'role'               => $data['role'],
            'status'             => 'invited',
            'two_factor_enabled' => $request->boolean('two_factor'),
            'invited_at'         => now(),
            'invited_by'         => $request->attributes->get('admin')->id,
        ]);

        $sent = $this->sendInvitation($admin, $request->attributes->get('admin'));

        return back()->with('status', $sent
            ? $admin->name . ' was invited. The link in the email works for ' . self::INVITE_DAYS . ' days.'
            : $admin->name . ' was added but the email could not be sent. Use "Send invitation again" once email is working.');
    }

    public function update(Request $request, Admin $member): RedirectResponse
    {
        $me = $request->attributes->get('admin');

        $data = $request->validate([
            'name'   => ['required', 'string', 'max:120'],
            'role'   => ['required', Rule::in(array_keys(Admin::ROLES))],
            'status' => ['required', Rule::in(['active', 'suspended'])],
        ]);

        // You cannot lock yourself out or take your own super admin role away: ask another super admin.
        if ($member->id === $me->id && ($data['role'] !== $member->role || $data['status'] !== $member->status)) {
            return back()->with('fail', 'You cannot change your own role or suspend yourself. Ask another super admin.');
        }

        // An invited person has no password yet, so "active" only makes sense once they have joined
        if ($member->status === 'invited') {
            $data['status'] = 'invited';
        }

        if ($this->losesLastSuperAdmin($member, $data['role'], $data['status'])) {
            return back()->with('fail', 'There must always be one active super admin.');
        }

        $member->forceFill(['name' => trim($data['name']), 'role' => $data['role'], 'status' => $data['status']]);

        if ($data['status'] === 'suspended') {
            $member->remember_token = null;                         // signs out their "keep me signed in" devices
        }
        $member->save();

        return back()->with('status', $member->name . ' was updated.');
    }

    public function invite(Request $request, Admin $member): RedirectResponse
    {
        if ($member->status !== 'invited') {
            return back()->with('fail', $member->name . ' has already joined.');
        }

        $member->forceFill(['invited_at' => now()])->save();

        return $this->sendInvitation($member, $request->attributes->get('admin'))
            ? back()->with('status', 'A new invitation was sent to ' . $member->email . '.')
            : back()->with('fail', 'The email could not be sent. Check the mail settings and try again.');
    }

    public function destroy(Request $request, Admin $member): RedirectResponse
    {
        if ($member->id === $request->attributes->get('admin')->id) {
            return back()->with('fail', 'You cannot remove yourself.');
        }

        if ($this->losesLastSuperAdmin($member, null, null)) {
            return back()->with('fail', 'There must always be one active super admin.');
        }

        ResetTokens::forget('admin', $member->email);
        $name = $member->name;
        $member->delete();

        return back()->with('status', $name . ' was removed from the team. Their past activity stays in the log.');
    }

    /** True when this change would leave no active super admin. $role and $status are the new values (null = the account goes away). */
    private function losesLastSuperAdmin(Admin $member, ?string $role, ?string $status): bool
    {
        $stays = $role === 'super_admin' && $status === 'active';

        if ($stays || $member->role !== 'super_admin' || $member->status !== 'active') {
            return false;
        }

        return Admin::where('role', 'super_admin')->where('status', 'active')->where('id', '!=', $member->id)->doesntExist();
    }

    private function sendInvitation(Admin $admin, Admin $by): bool
    {
        $token = ResetTokens::create('admin', $admin->email, self::INVITE_DAYS * 24 * 60);
        $url   = route('admin.reset-password', ['token' => $token, 'email' => $admin->email]);

        try {
            Mail::to($admin->email)->send(new GeneralMail(
                'You have been invited to the WAKATA admin console',
                $by->name . ' invited you to join the WAKATA team as ' . $admin->role_label . ".\n\nChoose your password here to get started:\n" . $url,
                'Hello ' . $admin->name . ',',
                null, null,
                'This link works for ' . self::INVITE_DAYS . ' days. If you were not expecting it, you can ignore this email.',
            ));

            return true;
        } catch (\Throwable $e) {
            report($e);

            return false;
        }
    }
}
