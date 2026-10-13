<!doctype html>
<html lang="en">
<body style="font-family:Arial,Helvetica,sans-serif;color:#1c2333;line-height:1.55">
  <h2 style="color:#043AA0;margin:0 0 12px">WAKATA</h2>
  <p>Hello {{ $name }},</p>
  @if ($purpose === 'order')
    <p>Use this code to add your order to your WAKATA account:</p>
  @else
    <p>Your sign-in code is:</p>
  @endif
  <p style="font-size:30px;letter-spacing:8px;font-weight:700;margin:8px 0">{{ $code }}</p>
  <p style="font-size:13px;color:#555">The code works for {{ $purpose === 'order' ? '15' : '10' }} minutes. If this was not you, you can ignore this email@if ($purpose !== 'order') and change your password@endif.</p>
</body>
</html>
