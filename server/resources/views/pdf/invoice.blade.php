<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Invoice {{ $transaction->invoice_no }}</title>
    <style>
        body { font-family: 'Helvetica', 'Arial', sans-serif; font-size: 14px; color: #333; }
        .header { text-align: center; margin-bottom: 30px; }
        .header h1 { margin: 0; padding: 0; font-size: 24px; color: #2d3748; }
        .details { margin-bottom: 30px; width: 100%; border-collapse: collapse; }
        .details td { vertical-align: top; width: 50%; }
        .items { width: 100%; border-collapse: collapse; margin-bottom: 30px; }
        .items th { background-color: #f7fafc; border-bottom: 2px solid #e2e8f0; padding: 10px; text-align: left; }
        .items td { border-bottom: 1px solid #e2e8f0; padding: 10px; }
        .totals { width: 100%; border-collapse: collapse; }
        .totals td { padding: 5px 10px; }
        .totals .label { text-align: right; font-weight: bold; }
        .totals .value { text-align: right; width: 120px; }
        .footer { text-align: center; margin-top: 50px; font-size: 12px; color: #718096; }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ $transaction->business_name }}</h1>
        <p>INVOICE / RECEIPT</p>
    </div>

    <table class="details">
        <tr>
            <td>
                <strong>Billed To:</strong><br>
                {{ $transaction->customer_name ?? 'Walk-in Customer' }}<br>
                {{ $transaction->customer_email }}
            </td>
            <td style="text-align: right;">
                <strong>Invoice No:</strong> {{ $transaction->invoice_no }}<br>
                <strong>Date:</strong> {{ date('M d, Y', strtotime($transaction->transaction_date)) }}<br>
                <strong>Status:</strong> {{ strtoupper($transaction->payment_status) }}
            </td>
        </tr>
    </table>

    <table class="items">
        <thead>
            <tr>
                <th>Item Description</th>
                <th style="text-align: right;">Qty</th>
                <th style="text-align: right;">Price</th>
                <th style="text-align: right;">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach($items as $item)
            <tr>
                <td>{{ $item->product_name }}</td>
                <td style="text-align: right;">{{ number_format($item->quantity, 2) }}</td>
                <td style="text-align: right;">${{ number_format($item->unit_price_inc_tax, 2) }}</td>
                <td style="text-align: right;">${{ number_format($item->quantity * $item->unit_price_inc_tax, 2) }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <table class="totals">
        <tr>
            <td class="label">Subtotal:</td>
            <td class="value">${{ number_format($transaction->total_before_tax, 2) }}</td>
        </tr>
        <tr>
            <td class="label">Tax:</td>
            <td class="value">${{ number_format($transaction->tax_amount, 2) }}</td>
        </tr>
        <tr>
            <td class="label">Discount:</td>
            <td class="value">-${{ number_format($transaction->discount_amount, 2) }}</td>
        </tr>
        <tr>
            <td class="label" style="font-size: 18px;">Total:</td>
            <td class="value" style="font-size: 18px;"><strong>${{ number_format($transaction->final_total, 2) }}</strong></td>
        </tr>
    </table>

    <div class="footer">
        <p>Thank you for your business!</p>
    </div>
</body>
</html>
