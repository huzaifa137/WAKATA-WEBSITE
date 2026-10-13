<!doctype html>
<html lang="en">
<body style="font-family:Arial,Helvetica,sans-serif;color:#1c2333;line-height:1.55">
  <h2 style="color:#043AA0;margin:0 0 12px">WAKATA support</h2>
  <p><b>{{ $thread->customer->name }}</b> ({{ $thread->customer->email }}, {{ $thread->customer->phone }}) wrote in
     about <b>{{ $thread->topic }}</b>@if ($thread->order) (order {{ $thread->order->number }})@endif.</p>
  <p style="white-space:pre-line;border-left:3px solid #ccd3e3;padding-left:12px">{{ $message->body }}</p>
  <p style="font-size:13px;color:#555">Thread {{ $thread->ref }}. Reply to this email to answer the customer directly.</p>
</body>
</html>
