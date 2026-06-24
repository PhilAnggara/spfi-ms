<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        @if (($format ?? 'pdf') === 'pdf')
        @page {
            size: A4 landscape;
            margin: 26mm 10mm 8mm 10mm;
        }
        body {
            margin: 0;
            font-family: DejaVu Sans, Arial, sans-serif;
            font-size: 8px;
            color: #111;
        }
        .report-header {
            position: fixed;
            top: -22mm;
            left: 0;
            right: 0;
        }
        .data-table { margin-top: 3mm; }
        @else
        body { font-family: Arial, sans-serif; font-size: 8px; color: #111; margin: 0; }
        .report-header { margin-bottom: 10px; }
        @endif

        .report-header-table { width: 100%; border-collapse: collapse; }
        .report-header-table td { padding: 0; vertical-align: top; border: none; }
        .logo-cell { width: 70px; padding-right: 4px !important; }
        .logo { width: 66px; height: auto; display: block; }
        .company-name { font-size: 10px; font-weight: bold; text-transform: uppercase; line-height: 1.2; }
        .doc-title { font-size: 9.5px; font-weight: bold; margin-top: 1px; line-height: 1.2; }
        .header-meta { font-size: 7.5px; line-height: 1.35; margin-top: 2px; }
        .header-meta .muted { font-size: 7px; color: #6b7280; }
        .header-right { text-align: right; font-size: 7.5px; line-height: 1.45; white-space: nowrap; color: #374151; }
        .data-table { width: 100%; border-collapse: separate; border-spacing: 0; }
        .data-table td, .data-table th { border: none; padding: 2px 4px; vertical-align: top; }
        .data-table thead { display: table-header-group; }
        .data-table th { font-size: 7px; font-weight: bold; text-align: left; text-transform: uppercase; }
        .data-table thead th.section-head {
            border-bottom: 1.5px solid #374151;
            padding-bottom: 4px;
        }
        .data-table thead th.section-col {
            border-bottom: 1.5px solid #374151;
            padding-bottom: 4px;
        }
        .data-table thead th.col-sep {
            border-right: 7px solid #fff;
            padding-right: 2px;
        }
        .data-table thead th.section-sep {
            border-right: 18px solid #fff;
            padding-right: 4px;
        }
        .data-table thead th.section-start {
            padding-left: 4px;
        }
        .data-table thead th.section-head.section-sep {
            border-right: 18px solid #fff;
            padding-right: 4px;
        }
        .center { text-align: center; }
        .right { text-align: right; }
        .muted { color: #6b7280; font-size: 7px; }
        .nowrap-date { white-space: nowrap; }
        .nowrap { white-space: nowrap; }
        .data-table thead th.lead-time-head,
        .data-table tbody td.lead-time-col {
            white-space: nowrap;
            min-width: 42px;
        }
        .time-part { display: block; font-size: 7px; color: #4b5563; white-space: nowrap; }
    </style>
</head>
<body>
    @php
        $isPdf = ($format ?? 'pdf') === 'pdf';
        $fmtDate = fn ($value) => $value ? \Carbon\Carbon::parse($value)->format('d-m-Y') : '';
        $fmtQty = fn ($value) => number_format((float) $value, 2, ',', '.');
        $fmtAssigned = function ($value) {
            if (! $value) {
                return ['date' => '', 'time' => ''];
            }
            $dt = \Carbon\Carbon::parse($value);

            return [
                'date' => $dt->format('d-m-Y'),
                'time' => $dt->format('H:i') !== '00:00' ? $dt->format('H:i') : '',
            ];
        };
    @endphp

    <div class="report-header">
        <table class="report-header-table">
            <tr>
                <td class="logo-cell">
                    <img src="{{ $logo_path }}" alt="Company Logo" class="logo">
                </td>
                <td>
                    <div class="company-name">{{ $company }}</div>
                    <div class="doc-title">{{ $title }}</div>
                    <div class="header-meta">
                        Period (RR date): <span class="nowrap-date">{{ $fmtDate($date_from) }}</span> - <span class="nowrap-date">{{ $fmtDate($date_to) }}</span><br>
                        Canvasser: {{ $canvasser }}<br>
                        <span class="muted">Lead Time (days) = RR Date - Assigned Canvasser Date. Each receiving report is listed separately.</span>
                    </div>
                </td>
                <td width="110" class="header-right">
                    @unless ($isPdf)
                        <div>Page 1 of 1</div>
                        <div>Printed: <span class="nowrap-date">{{ $printed_at }}</span></div>
                    @endunless
                </td>
            </tr>
        </table>
    </div>

    <table class="data-table">
        <thead>
            <tr>
                <th colspan="3" class="center section-head section-sep">Purchase Requisition Slip</th>
                <th colspan="3" class="center section-head section-sep">Item</th>
                <th colspan="2" class="center section-head section-sep">Canvasser</th>
                <th colspan="2" class="center section-head section-sep">Purchase Order</th>
                <th class="center section-head section-sep">Supplier</th>
                <th colspan="2" class="center section-head section-sep">Receiving Report</th>
                <th class="right section-head lead-time-head nowrap">Lead Time</th>
            </tr>
            <tr>
                <th class="section-col col-sep">ID</th>
                <th class="section-col col-sep">Number</th>
                <th class="section-col section-sep">Date</th>
                <th class="section-col section-start col-sep">Code</th>
                <th class="section-col col-sep">Description</th>
                <th class="section-col section-sep right">Qty</th>
                <th class="section-col section-start col-sep">Name</th>
                <th class="section-col section-sep">Assigned</th>
                <th class="section-col section-start col-sep">Number</th>
                <th class="section-col section-sep">Date</th>
                <th class="section-col section-start section-sep">Name</th>
                <th class="section-col section-start col-sep">Number</th>
                <th class="section-col section-sep">Date</th>
                <th class="section-col section-start right lead-time-head nowrap">Days</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                @php $assigned = $fmtAssigned($row['assigned_canvasser_at']); @endphp
                <tr>
                    <td>{{ $row['prs_id'] }}</td>
                    <td>{{ $row['prs_number'] }}</td>
                    <td class="nowrap-date">{{ $fmtDate($row['prs_date']) }}</td>
                    <td>{{ $row['item_code'] }}</td>
                    <td style="max-width: 150px; white-space: normal;">{{ $row['item_name'] }}</td>
                    <td class="right">{{ $fmtQty($row['quantity']) }} {{ $row['unit'] }}</td>
                    <td>{{ $row['canvasser'] ?? '-' }}</td>
                    <td class="nowrap-date">
                        {{ $assigned['date'] }}
                        @if ($assigned['time'] !== '')
                            <span class="time-part">{{ $assigned['time'] = '' }}</span>
                        @endif
                    </td>
                    <td>{{ $row['po_number'] ?? '-' }}</td>
                    <td class="nowrap-date">{{ $fmtDate($row['po_date']) }}</td>
                    <td style="max-width: 150px; white-space: normal;">{{ $row['supplier_name'] ?? '-' }}</td>
                    <td>{{ $row['rr_number'] ?? '-' }}</td>
                    <td class="nowrap-date">{{ $fmtDate($row['rr_date']) }}</td>
                    <td class="right lead-time-col">{{ $row['lead_time_days'] }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="14" class="muted">No lead time records found for this period.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    @if ($isPdf)
        <script type="text/php">
            if (isset($pdf)) {
                $pdf->page_script('
                    $font = $fontMetrics->getFont("DejaVu Sans", "normal");
                    if (! $font) {
                        $font = $fontMetrics->getFont("helvetica", "normal");
                    }

                    $fontSize = 7;
                    $rightEdge = $pdf->get_width() - 28.35;

                    $pageLabel = "Page " . $PAGE_NUM . " of " . $PAGE_COUNT;
                    $pageLabelWidth = $fontMetrics->get_text_width($pageLabel, $font, $fontSize);
                    $pdf->text($rightEdge - $pageLabelWidth, 24, $pageLabel, $font, $fontSize, array(0.2, 0.2, 0.2));

                    $printedLabel = "Printed: " . {!! json_encode($printed_at) !!};
                    $printedLabelWidth = $fontMetrics->get_text_width($printedLabel, $font, $fontSize);
                    $pdf->text($rightEdge - $printedLabelWidth, 34, $printedLabel, $font, $fontSize, array(0.2, 0.2, 0.2));
                ');
            }
        </script>
    @endif
</body>
</html>
