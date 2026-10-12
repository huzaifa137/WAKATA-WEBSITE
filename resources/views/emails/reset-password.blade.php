<!doctype html>
<html lang="en">
<body style="font-family:Arial,Helvetica,sans-serif;color:#1c2333;line-height:1.55">
  <h2 style="color:#043AA0;margin:0 0 12px">{{ $area }}</h2>
  <p>Hello {{ $name }},</p>
  <p>We received a request to reset your password. Use the button below to choose a new one. The link works for 60 minutes.</p>
  <p><a href="{{ $url }}" style="display:inline-block;background:#043AA0;color:#fff;text-decoration:none;padding:10px 18px;border-radius:6px">Choose a new password</a></p>
  <p style="font-size:13px;color:#555">If the button does not work, copy this link into your browser:<br>{{ $url }}</p>
  <p style="font-size:13px;color:#555">If you did not ask for this, you can ignore this email. Your password stays the same.</p>
</body>
</html>
