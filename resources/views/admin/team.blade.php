@extends('layouts.admin')

@section('title', 'Team and roles')
@section('page', 'team')
@section('ins', '1')

@section('content')
<div class="pagehead"><div><h1>Team and roles</h1><p>Who can sign in to this console and what they can change.</p></div><div class="ph-actions">@if ($ins['can']['manage'])<button class="btn btn-primary btn-sm" type="button" data-open="inviteModal">@icon('plus')Invite member</button>@endif</div></div>
@unless ($ins['can']['manage'])<p class="sec-note">Only a super admin can invite, change or remove team members.</p>@endunless
<div id="tbl"></div>
<div class="mt"><section class="card "><div class="card-h"><div><h2>Roles and what they can change</h2><span class="sub">Every team member can look at every page. The roles decide who can change things.</span></div></div><div class="card-b"><div class="tbl-wrap"><table class="perm"><thead><tr><th>Area</th>@foreach ($ins['roles'] as $label)<th>{{ $label }}</th>@endforeach</tr></thead><tbody>
@foreach ($ins['matrix'] as $row)
<tr><td>{{ $row['area'] }}</td>@foreach ($ins['roles'] as $key => $label)<td><input type="checkbox" disabled @checked($row['roles'][$key]) aria-label="{{ $label }} {{ $row['roles'][$key] ? 'can' : 'cannot' }} change: {{ $row['area'] }}"></td>@endforeach</tr>
@endforeach
</tbody></table></div><p class="sec-note mb0">The roles are fixed so the console always does what this table says. To give someone different rights, change their role.</p></div></section></div>
@endsection

@push('modals')
@if ($ins['can']['manage'])
<div class="mback" id="inviteModal" role="dialog" aria-modal="true" aria-labelledby="inviteModalT"><div class="modal "><form method="POST" action="{{ route('admin.team.store') }}">@csrf<div class="modal-h"><h2 id="inviteModalT">Invite a team member</h2><button class="btn btn-ic" type="button" data-close aria-label="Close">@icon('x')</button></div><div class="modal-b"><div class="field "><label for="un">Full name <span class="req">*</span></label><input id="un" name="name" type="text" value="{{ old('name') }}" required><span class="err">This field is required.</span></div><div class="field "><label for="ue">Work email <span class="req">*</span></label><input id="ue" name="email" type="email" value="{{ old('email') }}" placeholder="name@wakatastationers.com" required><span class="err">Enter a valid email address.</span></div><div class="field "><label for="ur">Role</label><select id="ur" name="role">@foreach ($ins['roles'] as $key => $label)<option value="{{ $key }}" @selected(old('role', 'editor') === $key)>{{ $label }}</option>@endforeach</select><span class="err">Choose a role.</span><span class="hint">Roles decide what they can change. See the table on this page.</span></div><label class="switch sm"><input type="checkbox" name="two_factor" value="1" @checked(old('two_factor', '1'))><span class="trk"></span><span class="t"><b>Require two-step verification</b><small>A code is emailed to them at every sign-in.</small></span></label></div><div class="modal-f"><button class="btn btn-line" type="button" data-close>Cancel</button><button class="btn btn-primary" type="submit">Send invitation</button></div></form></div></div>

<div class="mback" id="editModal" role="dialog" aria-modal="true" aria-labelledby="editModalT"><div class="modal "><form method="POST" action="" id="editForm">@csrf<input type="hidden" name="_method" value="PUT"><div class="modal-h"><h2 id="editModalT">Change team member</h2><button class="btn btn-ic" type="button" data-close aria-label="Close">@icon('x')</button></div><div class="modal-b"><div class="field "><label for="en">Full name</label><input id="en" name="name" type="text" required><span class="err">This field is required.</span></div><p class="sm muted" id="editEmail"></p><div class="field "><label for="er">Role</label><select id="er" name="role">@foreach ($ins['roles'] as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach</select></div><div class="field "><label for="es">Account</label><select id="es" name="status"><option value="active">Active</option><option value="suspended">Suspended (cannot sign in)</option></select><span class="hint" id="editHint"></span></div></div><div class="modal-f"><button class="btn btn-line" type="button" data-close>Cancel</button><button class="btn btn-primary" type="submit">Save changes</button></div></form></div></div>
@endif
@endpush

@if ($ins['can']['manage'] && $errors->any() && old('email'))
@push('scripts')<script>document.addEventListener('DOMContentLoaded', function () { if (window.ADM) ADM.open('inviteModal'); });</script>@endpush
@endif
