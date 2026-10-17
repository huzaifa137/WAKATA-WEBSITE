<!doctype html>
<html lang="en">
<body style="font-family:Arial,Helvetica,sans-serif;color:#1c2333;line-height:1.55">
  <h2 style="color:#043AA0;margin:0 0 12px">WAKATA</h2>
  <p>Hello {{ $order->customer_name }},</p>
  <p><b>{{ $headline }}</b></p>
  <p>{{ $body }}</p>

  <table style="border-collapse:collapse;width:100%;max-width:560px;font-size:14px">
    <tr style="text-align:left;border-bottom:1px solid #ccd3e3"><th style="padding:6px 8px 6px 0">Item</th><th style="padding:6px 8px">Qty</th><th style="padding:6px 0;text-align:right">Amount</th></tr>
    @foreach ($order->items as $item)
      <tr style="border-bottom:1px solid #eef1f7">
        <td style="padding:6px 8px 6px 0">{{ $item->title }}@if ($item->format === 'pdf') <span style="color:#666">(PDF)</span>@endif</td>
        <td style="padding:6px 8px">{{ $item->quantity }}</td>
        <td style="padding:6px 0;text-align:right">UGX {{ number_format($item->line_total) }}</td>
      </tr>
    @endforeach
    <tr><td colspan="2" style="padding:6px 8px 0 0;text-align:right"><b>Total</b></td><td style="padding:6px 0 0;text-align:right"><b>UGX {{ number_format($order->total) }}</b></td></tr>
  </table>

  <p style="margin-top:16px">Order {{ $order->number }} &middot; {{ $order->delivery_label }} &middot; {{ $order->payment_label }}</p>
  <p style="color:#666;font-size:13px">Questions? Reply to this email or call {{ config('wakata.phone') }}.</p>
</body>
</html>
