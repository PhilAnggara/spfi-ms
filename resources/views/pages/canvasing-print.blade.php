<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Supplier Comparison - {{ $prsItem->prs->prs_number }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: Arial, sans-serif;
            padding: 20px;
            font-size: 12px;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #333;
            padding-bottom: 15px;
        }
        .header h1 {
            font-size: 20px;
            margin-bottom: 5px;
        }
        .header h2 {
            font-size: 16px;
            font-weight: normal;
            color: #666;
        }
        .info-section {
            margin-bottom: 20px;
        }
        .info-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 10px;
            margin-bottom: 15px;
        }
        .info-item {
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .info-label {
            font-size: 10px;
            color: #666;
            text-transform: uppercase;
            margin-bottom: 4px;
        }
        .info-value {
            font-weight: bold;
            font-size: 12px;
        }
        .item-info {
            background-color: #f5f5f5;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        th {
            background-color: #333;
            color: white;
            padding: 10px;
            text-align: left;
            font-size: 11px;
        }
        td {
            padding: 10px;
            border-bottom: 1px solid #ddd;
        }
        tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        .text-right {
            text-align: right;
        }
        .text-center {
            text-align: center;
        }
        .selected-row {
            background-color: #d4edda !important;
            font-weight: bold;
        }
        .footer {
            margin-top: 30px;
            padding-top: 15px;
            border-top: 1px solid #ddd;
            font-size: 10px;
            color: #666;
            text-align: center;
        }
        @media print {
            body {
                padding: 10px;
            }
            .no-print {
                display: none;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>SUPPLIER COMPARISON REPORT</h1>
        <h2>Purchase Requisition System</h2>
    </div>

    <div class="info-section">
        <div class="info-grid">
            <div class="info-item">
                <div class="info-label">PRS Number</div>
                <div class="info-value">{{ $prsItem->prs->prs_number }}</div>
            </div>
            <div class="info-item">
                <div class="info-label">Submitted By</div>
                <div class="info-value">{{ $prsItem->prs->user->name }}</div>
            </div>
            <div class="info-item">
                <div class="info-label">Department</div>
                <div class="info-value">{{ $prsItem->prs->department->name }}</div>
            </div>
            <div class="info-item">
                <div class="info-label">Date Needed</div>
                <div class="info-value">{{ tgl($prsItem->prs->date_needed) }}</div>
            </div>
        </div>

        <div class="item-info">
            <strong>Item:</strong> {{ $prsItem->item->name }} ({{ $prsItem->item->code }}) <br>
            <strong>Quantity:</strong> {{ $prsItem->quantity }} {{ $prsItem->item->unit?->name ?? 'PCS' }}
        </div>
    </div>

    <h3 style="margin-bottom: 10px;">Supplier Comparison</h3>
    
    <table>
        <thead>
            <tr>
                <th style="width: 5%;">#</th>
                <th style="width: 20%;">Supplier</th>
                <th style="width: 12%;" class="text-right">Unit Price</th>
                <th style="width: 10%;" class="text-center">Lead Time</th>
                <th style="width: 20%;">Term of Payment</th>
                <th style="width: 18%;">Term of Delivery</th>
                <th style="width: 15%;">Notes</th>
            </tr>
        </thead>
        <tbody>
            @forelse($prsItem->canvasingItems as $index => $canvasing)
                <tr @if($prsItem->selected_canvasing_item_id === $canvasing->id) class="selected-row" @endif>
                    <td class="text-center">{{ $index + 1 }}</td>
                    <td>
                        {{ $canvasing->supplier?->name ?? '-' }}
                        @if($prsItem->selected_canvasing_item_id === $canvasing->id)
                            <br><small style="color: green;">✓ SELECTED</small>
                        @endif
                    </td>
                    <td class="text-right">{{ number_format($canvasing->unit_price, 2) }}</td>
                    <td class="text-center">{{ $canvasing->lead_time_days ? $canvasing->lead_time_days . ' days' : '-' }}</td>
                    <td>
                        @php
                            $paymentParts = [];
                            if ($canvasing->term_of_payment_type) {
                                $paymentParts[] = ucfirst($canvasing->term_of_payment_type);
                            }
                            if ($canvasing->term_of_payment) {
                                $paymentParts[] = $canvasing->term_of_payment;
                            }
                            $payment = implode(' - ', $paymentParts);
                        @endphp
                        {{ $payment !== '' ? $payment : '-' }}
                    </td>
                    <td>{{ $canvasing->term_of_delivery ?? '-' }}</td>
                    <td>{{ $canvasing->notes ?? '-' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="text-center">No supplier data available</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">
        <p>Printed on {{ now()->format('d/m/Y H:i:s') }} by {{ auth()->user()->name }}</p>
        <p>This is a computer-generated document. No signature is required.</p>
    </div>

    <script>
        window.onload = function() {
            window.print();
        }
    </script>
</body>
</html>
