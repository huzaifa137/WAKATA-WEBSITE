<!doctype html>
<html lang="en">
<body style="font-family:Arial,Helvetica,sans-serif;color:#1c2333;line-height:1.55">
  <h2 style="color:#043AA0;margin:0 0 12px">WAKATA</h2>
  @if ($heading)<p><b>{{ $heading }}</b></p>@endif
  @foreach (preg_split('/\R{2,}/', trim($body)) as $para)
    <p style="margin:0 0 12px">{!! nl2br(e($para)) !!}</p>
  @endforeach
  @if ($rows)
    <table style="border-collapse:collapse;width:100%;margin:14px 0;font-size:14px">
      <thead><tr><th align="left" style="border-bottom:2px solid #043AA0;padding:6px">Item</th><th align="right" style="border-bottom:2px solid #043AA0;padding:6px">Qty</th><th align="right" style="border-bottom:2px solid #043AA0;padding:6px">Unit price</th><th align="right" style="border-bottom:2px solid #043AA0;padding:6px">Total</th></tr></thead>
      <tbody>
      @foreach ($rows as $r)
        <tr><td style="border-bottom:1px solid #e5e8f0;padding:6px">{{ $r[0] }}</td><td align="right" style="border-bottom:1px solid #e5e8f0;padding:6px">{{ $r[1] }}</td><td align="right" style="border-bottom:1px solid #e5e8f0;padding:6px">{{ $r[2] }}</td><td align="right" style="border-bottom:1px solid #e5e8f0;padding:6px">{{ $r[3] }}</td></tr>
      @endforeach
      </tbody>
    </table>
    @foreach ($totals ?? [] as $t)
      <p style="margin:2px 0;text-align:right"><b>{{ $t[0] }}: {{ $t[1] }}</b></p>
    @endforeach
  @endif
  @if ($footerNote)<p style="font-size:13px;color:#555;margin-top:16px">{{ $footerNote }}</p>@endif
  <p style="font-size:13px;color:#555;margin-top:18px">WAKATA &middot; {{ config('wakata.phone') }} &middot; {{ config('wakata.email') }}</p>
  @if ($unsubscribeUrl)<p style="font-size:12px;color:#777">You get this because you signed up for new papers. <a href="{{ $unsubscribeUrl }}">Unsubscribe</a></p>@endif
</body>
</html>
