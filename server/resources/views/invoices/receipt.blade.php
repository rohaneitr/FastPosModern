<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Invoice {{ $tx->invoice_no }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Arial, sans-serif; font-size: 13px; color: #333; padding: 20px; max-width: 380px; margin: 0 auto; }
        .header { text-align: center; padding-bottom: 16px; border-bottom: 2px dashed #ccc; margin-bottom: 16px; }
        .header h1 { font-size: 20px; font-weight: 700; margin-bottom: 2px; }
        .header p { font-size: 11px; color: #888; }
        .meta { display: flex; flex-direction: column; gap: 4px; margin-bottom: 16px; font-size: 12px; }
        .meta-row { display: flex; justify-content: space-between; }
        .meta-label { color: #888; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
        th { text-align: left; padding: 6px 4px; border-bottom: 1px solid #ddd; font-size: 11px; color: #888; text-transform: uppercase; }
        td { padding: 6px 4px; border-bottom: 1px solid #f0f0f0; }
        .text-right { text-align: right; }
        .totals { border-top: 2px dashed #ccc; padding-top: 12px; margin-top: 8px; }
        .total-row { display: flex; justify-content: space-between; padding: 3px 0; font-size: 13px; }
        .total-row.grand { font-size: 18px; font-weight: 700; padding-top: 8px; border-top: 1px solid #ddd; margin-top: 4px; }
        .payments { margin-top: 16px; padding-top: 12px; border-top: 1px dashed #ccc; }
        .footer { text-align: center; margin-top: 24px; padding-top: 16px; border-top: 2px dashed #ccc; font-size: 11px; color: #888; }
        @media print { body { padding: 0; } }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ $tx->business_name ?? 'FastPOS' }}</h1>
        <p>{{ $tx->location_name ?? '' }}</p>
        <p style="margin-top: 8px; font-size: 12px; font-weight: 600;">INVOICE</p>
    </div>

    <div class="meta">
        <div class="meta-row"><span class="meta-label">Invoice #</span><span>{{ $tx->invoice_no }}</span></div>
        <div class="meta-row"><span class="meta-label">Date</span><span>{{ \Carbon\Carbon::parse($tx->transaction_date)->format('M d, Y h:i A') }}</span></div>
        <div class="meta-row"><span class="meta-label">Cashier</span><span>{{ trim(($tx->cashier_first ?? '') . ' ' . ($tx->cashier_last ?? '')) ?: '—' }}</span></div>
        <div class="meta-row"><span class="meta-label">Status</span><span>{{ ucfirst($tx->status) }}</span></div>
    </div>

    <table>
        <thead>
            <tr>
                <th>Item</th>
                <th class="text-right">Qty</th>
                <th class="text-right">Price</th>
                <th class="text-right">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach($lines as $line)
            <tr>
                <td>{{ $line->name }}<br><small style="color:#999">{{ $line->sku ?? '' }}</small></td>
                <td class="text-right">{{ $line->quantity }}</td>
                <td class="text-right">${{ number_format($line->unit_price, 2) }}</td>
                <td class="text-right">${{ number_format($line->unit_price * $line->quantity, 2) }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="totals">
        <div class="total-row"><span>Subtotal</span><span>${{ number_format($tx->total_before_tax, 2) }}</span></div>
        @if(isset($tx->discount_amount) && $tx->discount_amount > 0)
        <div class="total-row" style="color: #e11d48;"><span>Discount</span><span>-${{ number_format($tx->discount_amount, 2) }}</span></div>
        @endif
        <div class="total-row"><span>Tax</span><span>${{ number_format($tx->tax_amount, 2) }}</span></div>
        <div class="total-row grand"><span>TOTAL</span><span>${{ number_format($tx->final_total, 2) }}</span></div>
    </div>

    <div class="payments">
        <p style="font-size: 11px; color: #888; text-transform: uppercase; margin-bottom: 8px;">Payment(s)</p>
        @foreach($payments as $payment)
        <div class="total-row">
            <span>{{ ucfirst($payment->method) }}</span>
            <span>${{ number_format($payment->amount, 2) }}</span>
        </div>
        @endforeach
    </div>

    <div class="footer">
        <p>Thank you for your purchase!</p>
        <p style="margin-top: 4px;">{{ $tx->business_name ?? 'FastPOS' }}</p>
    </div>
</body>
</html>
