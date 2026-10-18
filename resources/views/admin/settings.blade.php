@extends('layouts.admin')

@section('title', 'Settings')
@section('page', 'settings')

@section('content')
<div class="pagehead"><div><h1>Settings</h1><p>General, downloads, alerts, analytics and data. Super admins and managers can change them.</p></div><div class="ph-actions"></div></div>
<div class="tabs" data-tabs role="tablist"><button class="tab on" type="button" role="tab" data-pane="s-gen">General</button><button class="tab" type="button" role="tab" data-pane="payments">Payments</button><button class="tab" type="button" role="tab" data-pane="s-del">Delivery</button><button class="tab" type="button" role="tab" data-pane="s-not">Notifications</button><button class="tab" type="button" role="tab" data-pane="s-inv">Invoices</button><button class="tab" type="button" role="tab" data-pane="s-sec">Security</button><button class="tab" type="button" role="tab" data-pane="s-int">Integrations</button><button class="tab" type="button" role="tab" data-pane="s-data">Data and backups</button></div>

<div class="tabpane mt" id="s-gen">
  <form method="POST" action="{{ route('admin.settings.general') }}" data-native data-validate novalidate>@csrf
    <section class="card"><div class="card-h"><div><h2>Business</h2><span class="sub">The shop name, association and tagline are words on the website: change them under <a href="{{ route('admin.content') }}">Site content</a>.</span></div></div><div class="card-b">
      <div class="row2">
        <div class="field "><label for="gs">Shop name</label><input id="gs" type="text" value="WAKATA Stationers" readonly></div>
        <div class="field "><label for="ga">Association</label><input id="ga" type="text" value="Wakiso Kampala Teachers' Association" readonly></div>
      </div>
      <div class="row2">
        <div class="field "><label for="email">Public email <span class="req">*</span></label><input id="email" name="email" type="email" value="{{ old('email', $email) }}" required><span class="hint">Shown on the Contact page and in emails. The same as “Email” under Site content, Contact.</span><span class="err">Enter a valid email address.</span></div>
        <div class="field "><label for="goal">Monthly sales goal (UGX) <span class="req">*</span></label><input id="goal" name="goal" type="number" min="0" step="1000" value="{{ old('goal', $s['goal']) }}" required><span class="hint">The dashboard compares sales against it.</span><span class="err">Enter the goal as a whole number.</span></div>
      </div>
      <div class="row2">
        <div class="field "><label for="gc">Currency</label><select id="gc" disabled><option>Uganda shilling (UGX)</option></select></div>
        <div class="field "><label for="gz">Time zone</label><select id="gz" disabled><option>{{ config('app.timezone') }}</option></select><span class="hint">Set by APP_TIMEZONE in the .env file.</span></div>
      </div>
    </div></section>
    <div class="flex" style="justify-content:flex-end;margin-top:1.1rem"><button class="btn btn-primary" type="submit">Save changes</button></div>
  </form>
</div>

<div class="tabpane mt" id="payments" hidden>
  <section class="card"><div class="card-h"><div><h2>Payments</h2></div></div><div class="card-b">
    <p>Payment methods (mobile money, card, cash), confirming payments and refunds live on the <a href="{{ route('admin.payments') }}">Payments page</a>, where the money is handled.</p>
    <p class="sm muted mb0">Customers pay by the method they choose at checkout and staff confirm the payment there. Live gateway keys are not used by this version of the site.</p>
  </div></section>
</div>

<div class="tabpane mt" id="s-del" hidden>
  <form method="POST" action="{{ route('admin.settings.downloads') }}" data-native data-validate novalidate>@csrf
    <section class="card"><div class="card-h"><div><h2>PDF downloads</h2><span class="sub">Delivery names, times and fees are under <a href="{{ route('admin.deliveries') }}#t-zones">Deliveries, Options and fees</a>.</span></div></div><div class="card-b">
      <div class="row2">
        <div class="field "><label for="downloads">Downloads allowed for each paid PDF</label>
          <select id="downloads" name="downloads">@foreach ([1 => '1 time', 3 => '3 times', 5 => '5 times', 10 => '10 times', 0 => 'Unlimited'] as $n => $label)<option value="{{ $n }}" @selected((int) old('downloads', $s['downloads']) === $n)>{{ $label }}</option>@endforeach</select>
          <span class="hint">Customers see how many they have left in their library.</span></div>
        <div></div>
      </div>
    </div></section>
    <div class="flex" style="justify-content:flex-end;margin-top:1.1rem"><button class="btn btn-primary" type="submit">Save changes</button></div>
  </form>
</div>

<div class="tabpane mt" id="s-not" hidden>
  <form method="POST" action="{{ route('admin.settings.notifications') }}" data-native data-validate novalidate>@csrf
    <section class="card"><div class="card-h"><div><h2>Alerts to your team</h2><span class="sub">Emails to the staff, so nothing waits unseen.</span></div></div><div class="card-b">
      <div class="field {{ $errors->has('recipients') ? 'bad' : '' }}"><label for="recipients">Send alerts to</label><input id="recipients" name="recipients" type="text" value="{{ old('recipients', $s['alert_recipients']) }}" placeholder="{{ config('wakata.order_notify') ?: 'name@wakatastationers.com, other@wakatastationers.com' }}"><span class="hint">One or more emails, separated by commas. Leave empty to use the address in the .env file.</span><span class="err">{{ $errors->first('recipients') }}</span></div>
      <div class="switchlist">
        <label class="switch"><input type="checkbox" name="alert_orders" @checked(old('alert_orders', $s['alert_orders']))><span class="trk"></span><span class="t"><b>New order placed</b><small>An email with the order, every time a customer checks out</small></span></label>
        <label class="switch"><input type="checkbox" name="alert_messages" @checked(old('alert_messages', $s['alert_messages']))><span class="trk"></span><span class="t"><b>New message or customer question</b><small>From the Contact page and from customer accounts</small></span></label>
      </div>
    </div></section>
    <section class="card" style="margin-top:1.1rem"><div class="card-h"><div><h2>Emails to customers</h2></div></div><div class="card-b"><p class="mb0">Customers get an email when they place an order, when payment is received, when it is ready or on its way, when files are ready, and when an order is cancelled or refunded, as long as they gave an email address. These are always on.</p></div></section>
    <div class="flex" style="justify-content:flex-end;margin-top:1.1rem"><button class="btn btn-primary" type="submit">Save changes</button></div>
  </form>
</div>

<div class="tabpane mt" id="s-inv" hidden>
  <section class="card"><div class="card-h"><div><h2>Invoices</h2></div></div><div class="card-b"><p class="mb0">Invoices are made for paid orders and show the order number, the items and the school details the customer saved in their account. Invoice wording is not editable yet.</p></div></section>
</div>

<div class="tabpane mt" id="s-sec" hidden>
  <section class="card"><div class="card-h"><div><h2>Security</h2></div></div><div class="card-b"><p>Staff sign in with a password and an emailed code, and a locked screen asks for the password again. People, roles and the activity log come with the team page.</p><p class="sm muted mb0">Forgot a password? Use “Forgot password” on the admin sign-in page.</p></div></section>
</div>

<div class="tabpane mt" id="s-int" hidden>
  <form method="POST" action="{{ route('admin.settings.integrations') }}" data-native data-validate novalidate>@csrf
    <section class="card"><div class="card-h"><div><h2>Google Analytics</h2><span class="sub">Counts visits to the public website. Nothing is sent until you add an ID.</span></div></div><div class="card-b">
      <div class="row2"><div class="field {{ $errors->has('ga4') ? 'bad' : '' }}"><label for="ga4">Measurement ID</label><input id="ga4" name="ga4" type="text" value="{{ old('ga4', $s['ga4']) }}" placeholder="G-ABC123XYZ"><span class="hint">Leave empty to switch analytics off.</span><span class="err">{{ $errors->first('ga4') }}</span></div><div></div></div>
    </div></section>
    <section class="card" style="margin-top:1.1rem"><div class="card-h"><div><h2>Email and SMS</h2></div></div><div class="card-b"><p class="mb0">Outgoing email is configured in the server's .env file (the MAIL_ settings). Text messages are not used by this version of the site.</p></div></section>
    <div class="flex" style="justify-content:flex-end;margin-top:1.1rem"><button class="btn btn-primary" type="submit">Save changes</button></div>
  </form>
</div>

<div class="tabpane mt" id="s-data" hidden>
  <section class="card"><div class="card-h"><div><h2>Export your data</h2><span class="sub">CSV files open in Excel or Google Sheets.</span></div></div><div class="card-b">
    <div class="flex wrapf" style="gap:.6rem">
      <a class="btn btn-line" href="{{ route('admin.settings.export', 'orders') }}">@icon('download')Orders</a>
      <a class="btn btn-line" href="{{ route('admin.settings.export', 'customers') }}">@icon('download')Customers</a>
      <a class="btn btn-line" href="{{ route('admin.settings.export', 'subscribers') }}">@icon('download')Subscribers</a>
      <a class="btn btn-line" href="{{ route('admin.settings.export', 'books') }}">@icon('download')Books and stock</a>
    </div>
  </div></section>
  <section class="card" style="margin-top:1.1rem"><div class="card-h"><div><h2>Backups</h2></div></div><div class="card-b"><p class="mb0">Back up the database and the <code>storage</code> folder (uploaded PDFs) with your hosting provider. The website does not run backups itself.</p></div></section>
</div>
@endsection
