<!doctype html>
<html lang="en">
<body style="font-family:Arial,Helvetica,sans-serif;color:#1c2333;line-height:1.55">
  <h2 style="color:#043AA0;margin:0 0 12px">WAKATA</h2>
  @if ($staff)
    <p>New order <b>{{ $order->number }}</b> from {{ $order->customer_name }} ({{ $order->customer_email }}, {{ $order->customer_phone }}).</p>
    <p>Payment: {{ $order->payment_label }} (awaiting confirmation). Delivery: {{ $order->delivery_label }}.</p>
  @else
    <p>Hello {{ $order->customer_name }},</p>
    <p>Thank you for your order. Your reference is <b>{{ $order->number }}</b>.
       We will confirm your {{ $order->payment_label }} payment by phone or email, and your order is prepared once payment is confirmed.</p>
  @endif

  <table style="border-collapse:collapse;width:100%;max-width:560px;font-size:14px">
    <tr style="text-align:left;border-bottom:1px solid #ccd3e3"><th style="padding:6px 8px 6px 0">Item</th><th style="padding:6px 8px">Qty</th><th style="padding:6px 0;text-align:right">Amount</th></tr>
    @foreach ($order->items as $item)
      <tr style="border-bottom:1px solid #eef1f7">
        <td style="padding:6px 8px 6px 0">{{ $item->title }}@if ($item->format === 'pdf') <span style="color:#666">(PDF)</span>@elseif ($item->format === 'print') <span style="color:#666">(printed)</span>@endif</td>
        <td style="padding:6px 8px">{{ $item->quantity }}</td>
        <td style="padding:6px 0;text-align:right">UGX {{ number_format($item->line_total) }}</td>
      </tr>
    @endforeach
    <tr><td colspan="2" style="padding:6px 8px 0 0;text-align:right">Subtotal</td><td style="padding:6px 0 0;text-align:right">UGX {{ number_format($order->subtotal) }}</td></tr>
    <tr><td colspan="2" style="padding:2px 8px 0 0;text-align:right">Delivery ({{ $order->delivery_label }})</td><td style="padding:2px 0 0;text-align:right">{{ $order->delivery_fee ? 'UGX ' . number_format($order->delivery_fee) : 'Free' }}</td></tr>
    <tr><td colspan="2" style="padding:6px 8px 0 0;text-align:right"><b>Total</b></td><td style="padding:6px 0 0;text-align:right"><b>UGX {{ number_format($order->total) }}</b></td></tr>
  </table>

  @if ($order->delivery_address)
    <p style="margin-top:16px"><b>Deliver to:</b><br>{!! nl2br(e($order->delivery_address)) !!}</p>
  @endif

  @unless ($staff)
    <p style="font-size:13px;color:#555;margin-top:18px">Questions? Reply to this email or call {{ config('wakata.phone') }}.</p>
  @endunless
</body>
</html>
