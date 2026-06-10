<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: sans-serif; font-size: 14px; color: #333; }
        .header { text-align: center; margin-bottom: 20px; }
        .header h1 { margin: 0; font-size: 24px; color: #10b981; }
        .details { margin-bottom: 30px; border-bottom: 1px solid #eee; padding-bottom: 10px; }
        table { w-full; border-collapse: collapse; margin-top: 20px; width: 100%; }
        th, td { border: 1px solid #ddd; padding: 10px; text-align: left; }
        th { background-color: #f8f9fa; font-weight: bold; }
        .text-right { text-align: right; }
        .summary-box { margin-top: 30px; background: #f8f9fa; padding: 15px; border-radius: 5px; }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ $businessName }}</h1>
        <p>Sales Summary Report</p>
    </div>

    <div class="details">
        <p><strong>Period:</strong> {{ $startDate }} - {{ $endDate }}</p>
        <p><strong>Generated At:</strong> {{ $generatedAt }}</p>
    </div>

    <div class="summary-box">
        <h3>Aggregate Metrics</h3>
        <p><strong>Total Transactions:</strong> {{ number_format($summary['total_items']) }}</p>
        <p><strong>Total Tax Collected:</strong> ${{ number_format($summary['total_tax'], 2) }}</p>
        <p><strong>Total Net Sales:</strong> ${{ number_format($summary['total_sales'], 2) }}</p>
    </div>

    <h3>Daily Breakdown</h3>
    <table>
        <thead>
            <tr>
                <th>Date</th>
                <th class="text-right">Total Revenue</th>
            </tr>
        </thead>
        <tbody>
            @forelse($summary['daily_breakdown'] as $date => $total)
            <tr>
                <td>{{ \Carbon\Carbon::parse($date)->format('M d, Y') }}</td>
                <td class="text-right">${{ number_format($total, 2) }}</td>
            </tr>
            @empty
            <tr>
                <td colspan="2" class="text-center">No sales recorded in this period.</td>
            </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
