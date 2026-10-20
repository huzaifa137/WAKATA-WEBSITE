@extends('layouts.admin')

@section('title', 'My profile')
@section('page', 'profile')

@section('content')
<div class="pagehead"><div><h1>My profile</h1><p>Your account, password and preferences.</p></div><div class="ph-actions"></div></div>
<div class="layout-2"><div class="stack">

<section class="card "><div class="card-h"><div><h2>Your details</h2></div></div><div class="card-b">
  <div class="flex" style="margin-bottom:1.2rem">
    @if ($me->avatar_url)<img class="avatar lg" src="{{ $me->avatar_url }}" alt="Your photo" style="object-fit:cover">@else<span class="avatar lg">{{ $me->initials }}</span>@endif
    <div>
      <form method="POST" action="{{ route('admin.profile.photo') }}" enctype="multipart/form-data" id="photoForm" style="display:inline">@csrf
        <input type="file" name="photo" id="photoInput" accept="image/png,image/jpeg,image/webp" hidden onchange="this.form.submit()">
        <button class="btn btn-line btn-sm" type="button" onclick="document.getElementById('photoInput').click()">@icon('upload')Change photo</button>
      </form>
      @if ($me->avatar_url)<form method="POST" action="{{ route('admin.profile.photo.remove') }}" style="display:inline">@csrf @method('DELETE')<button class="btn btn-line btn-sm" type="submit">Remove</button></form>@endif
      <div class="xs muted" style="margin-top:.3rem">JPG, PNG or WebP, up to 1 MB</div>
    </div>
  </div>
  <form method="POST" action="{{ route('admin.profile.update') }}" data-native data-validate novalidate>@csrf
    <div class="row2">
      <div class="field {{ $errors->has('name') ? 'bad' : '' }}"><label for="pn">Full name <span class="req">*</span></label><input id="pn" name="name" type="text" value="{{ old('name', $me->name) }}" required><span class="err">{{ $errors->first('name') ?: 'This field is required.' }}</span></div>
      <div class="field"><label for="pe">Email</label><input id="pe" type="email" value="{{ $me->email }}" readonly><span class="hint">This is your sign-in name. A super admin can change it under Team and roles.</span></div>
    </div>
    <div class="row2">
      <div class="field {{ $errors->has('phone') ? 'bad' : '' }}"><label for="pp">Phone</label><input id="pp" name="phone" type="tel" value="{{ old('phone', $me->phone) }}"><span class="err">{{ $errors->first('phone') ?: 'Enter a valid phone number.' }}</span></div>
      <div class="field"><label for="pr">Role</label><input id="pr" type="text" value="{{ $me->role_label }}" readonly></div>
    </div>
    <div class="flex" style="justify-content:flex-end;margin-top:1.1rem"><button class="btn btn-primary" type="submit">@icon('check')Save profile</button></div>
  </form>
</div></section>

<section class="card "><div class="card-h"><div><h2>Password</h2></div></div><div class="card-b">
  <form method="POST" action="{{ route('admin.profile.password') }}" data-native data-validate data-match="new_password,new_password_confirmation" novalidate>@csrf
    <div class="row3">
      <div class="field {{ $errors->has('current_password') ? 'bad' : '' }}"><label for="cp">Current</label><input id="cp" name="current_password" type="password" autocomplete="current-password" required><span class="err">{{ $errors->first('current_password') ?: 'Enter your current password.' }}</span></div>
      <div class="field {{ $errors->has('new_password') ? 'bad' : '' }}"><label for="np">New</label><input id="np" name="new_password" type="password" autocomplete="new-password" data-strength="stP" required><div class="strength" id="stP" data-s="0"><i></i><i></i><i></i><i></i></div><span class="err">{{ $errors->first('new_password') ?: 'Choose a new password.' }}</span><span class="hint">At least 10 characters with a letter and a number.</span></div>
      <div class="field"><label for="np2">Confirm</label><input id="np2" name="new_password_confirmation" type="password" autocomplete="new-password" required><span class="err">The passwords do not match.</span></div>
    </div>
    <div class="flex" style="justify-content:flex-end;margin-top:1.1rem"><button class="btn btn-primary" type="submit">@icon('check')Update password</button></div>
  </form>
</div></section>

</div><div class="rail stack">

<section class="card "><div class="card-h"><div><h2>Appearance</h2></div></div><div class="card-b"><div class="opt-cards"><label class="opt"><input type="radio" name="th" checked onclick="document.documentElement.removeAttribute('data-theme');localStorage.removeItem('wk_theme')"><span><b>Light</b><small>Default</small></span></label><label class="opt"><input type="radio" name="th" onclick="document.documentElement.setAttribute('data-theme','dark');localStorage.setItem('wk_theme','dark')"><span><b>Dark</b><small>Easier at night</small></span></label></div></div></section>

<section class="card "><div class="card-h"><div><h2>Two-step verification</h2></div></div><div class="card-b"><label class="switch sm"><input type="checkbox" name="tfa" id="tfa" data-url="{{ route('admin.two-factor') }}" @checked($me->two_factor_enabled ?? false)><span class="trk"></span><span class="t"><b>Ask for a code at sign-in</b><small>Adds a second step after your password.</small></span></label></div></section>

<section class="card "><div class="card-h"><div><h2>My email alerts</h2></div></div><div class="card-b">
  <form method="POST" action="{{ route('admin.profile.notifications') }}" data-native>@csrf
    <div style="display:grid;gap:.9rem">
      <label class="switch sm"><input type="checkbox" name="alert_orders" value="1" @checked($me->wantsAlert('orders'))><span class="trk"></span><span class="t"><b>New orders</b><small>An email each time a customer places an order</small></span></label>
      <label class="switch sm"><input type="checkbox" name="alert_messages" value="1" @checked($me->wantsAlert('messages'))><span class="trk"></span><span class="t"><b>New messages</b><small>Contact messages and customer questions</small></span></label>
    </div>
    <p class="xs muted" style="margin:.8rem 0 0">These are sent to {{ $me->email }} on top of the shared addresses in Settings, Notifications.</p>
    <div class="flex" style="justify-content:flex-end;margin-top:.9rem"><button class="btn btn-primary btn-sm" type="submit">Save</button></div>
  </form>
</div></section>

</div></div>
@endsection
