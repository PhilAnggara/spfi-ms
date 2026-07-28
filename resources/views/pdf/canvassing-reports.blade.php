<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Supplier Canvassing Reports</title>
    @include('pdf.partials.canvassing-report-styles')
</head>
<body>
    @php
        $totalReports = $reports->count();
    @endphp

    <div class="document-title">Supplier Canvassing Report</div>
    <table class="meta-table">
        <tr>
            <td>Generated: {{ now()->format('d M Y H:i') }} — {{ $totalReports }} item{{ $totalReports === 1 ? '' : 's' }}</td>
            <td class="text-right">Prepared by: {{ $generatedBy->name ?? '-' }}</td>
        </tr>
    </table>

    @foreach ($reports as $index => $report)
        @include('pdf.partials.canvassing-report-item', [
            'prsItem' => $report['prsItem'],
            'canvassingItems' => $report['canvassingItems'],
            'maxUnitPrice' => $report['maxUnitPrice'],
            'itemIndex' => $index + 1,
            'itemTotal' => $totalReports,
        ])
    @endforeach
</body>
</html>
