<!doctype html>
<html lang="en">
<body style="font-family:Arial,Helvetica,sans-serif;color:#1c2333;line-height:1.55">
  <h2 style="color:#043AA0;margin:0 0 12px">WAKATA</h2>
  <p>Hello {{ $order->customer_name }},</p>
  <p>Here {{ count($files) === 1 ? 'is your PDF file' : 'are your PDF files' }} for order <b>{{ $order->number }}</b>, attached to this email.</p>
  <ul>@foreach (array_keys($files) as $name)<li>{{ $name }}</li>@endforeach</ul>
  <p style="font-size:13px;color:#555">This does not use up your downloads. If you have an account, the files are also in Downloads and library.</p>
</body>
</html>
