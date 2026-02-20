<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>PRS for GM Approval - {{ $prs->prs_number }}</title>
    @include('pdf.partials.header-style')
    <style>
        /* Document Info */
        .doc-info {
            margin: 15px 0;
            padding: 10px;
            background: #f9fafb;
            border-left: 4px solid #3b82f6;
        }

        .info-grid {
            display: table;
            width: 100%;
            margin-bottom: 10px;
        }

        .info-row {
            display: table-row;
        }

        .info-label {
            display: table-cell;
            width: 25%;
            font-weight: 600;
            padding: 4px 8px;
            vertical-align: top;
        }

        .info-value {
            display: table-cell;
            width: 75%;
            padding: 4px 8px;
            vertical-align: top;
        }

        /* Items Table */
        .items-section {
            margin: 20px 0;
        }

        .section-title {
            font-size: 12px;
            font-weight: 700;
            background: #e5e7eb;
            padding: 8px 10px;
            margin: 15px 0 10px 0;
            border-radius: 3px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }

        th, td {
            border: 1px solid #d1d5db;
            padding: 8px;
            vertical-align: middle;
        }

        th {
            background: #f3f4f6;
            font-weight: 700;
            text-align: left;
            font-size: 10px;
        }

        tr:nth-child(even) {
            background: #f9fafb;
        }

        .text-center {
            text-align: center;
        }

        .text-right {
            text-align: right;
        }

        /* Signature Section */
        .signature-section {
            margin-top: 40px;
            page-break-inside: avoid;
        }

        .signature-grid {
            display: table;
            width: 100%;
            margin-top: 40px;
        }

        .sig-column {
            display: table-cell;
            width: 33.33%;
            text-align: center;
            padding: 10px;
        }

        .sig-line {
            border-top: 1px solid #1f2937;
            margin-top: 60px;
            padding-top: 5px;
            font-weight: 600;
            font-size: 10px;
        }

        .sig-title {
            font-size: 10px;
            color: #6b7280;
            margin-top: 5px;
        }

        .sig-date {
            font-size: 10px;
            color: #6b7280;
            margin-top: 3px;
        }

        /* Footer */
        .footer {
            font-size: 9px;
            text-align: center;
            color: #9ca3af;
            border-top: 1px solid #e5e7eb;
            padding-top: 10px;
            margin-top: 20px;
        }

        /* Utilities */
        .badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 4px;
            background: #dbeafe;
            font-size: 9px;
            font-weight: 600;
        }

        .remarks-box {
            background: #fffbeb;
            border: 1px solid #fbbf24;
            padding: 10px;
            border-radius: 3px;
            margin: 10px 0;
            font-size: 10px;
        }
    </style>
</head>
<body>
    @include('pdf.partials.header', ['documentTitle' => 'Purchase Requisition Slip (PRS) - for General Manager Approval'])

    <!-- Informasi Dokumen -->
    <div class="doc-info">
        <div class="info-grid">
            <div class="info-row">
                <div class="info-label">PRS Number :</div>
                <div class="info-value"><strong>{{ $prs->prs_number }}</strong></div>
            </div>
            {{-- <div class="info-row">
                <div class="info-label">Status :</div>
                <div class="info-value"><span class="badge">{{ $prs->status }}</span></div>
            </div> --}}
            <div class="info-row">
                <div class="info-label">Requested By :</div>
                <div class="info-value">{{ $prs->user->name }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">Department :</div>
                <div class="info-value">{{ $prs->department->name }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">PRS Date :</div>
                <div class="info-value">{{ \Carbon\Carbon::parse($prs->prs_date)->format('d F Y') }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">Date Needed :</div>
                <div class="info-value">{{ \Carbon\Carbon::parse($prs->date_needed)->format('d F Y') }}</div>
            </div>
        </div>
    </div>

    <!-- Detail Item PRS -->
    <div class="items-section">
        <div class="section-title">REQUESTED ITEMS DETAIL</div>

        <table>
            <thead>
                <tr>
                    <th style="width: 5%; text-align: center;">No</th>
                    <th style="width: 15%;">Item Code</th>
                    <th style="width: 50%;">Item Description</th>
                    <th style="width: 15%; text-align: right;">Qty Needed</th>
                    <th style="width: 15%; text-align: center;">Unit</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($prs->items as $idx => $item)
                    <tr>
                        <td class="text-center">{{ $idx + 1 }}</td>
                        <td><strong>{{ $item->item->code ?? '-' }}</strong></td>
                        <td>{{ $item->item->name ?? '-' }}</td>
                        <td class="text-right">{{ $item->quantity }}</td>
                        <td class="text-center">{{ $item->item->unit?->name ?? 'PCS' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-center" style="padding: 20px; color: #9ca3af;">
                            No items found in this PRS
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <!-- Remarks -->
    @if ($prs->remarks)
        <div class="remarks-box">
            <strong>Special Remarks / Notes:</strong><br>
            {{ $prs->remarks }}
        </div>
    @endif

    <!-- QR Code -->
    <div style="text-align: center; margin: 20px 0;">
        <img src="{{ $qrCodeBase64 }}" alt="QR Code" style="width: 100px; height: 100px; display: inline-block; border: 1px solid #d1d5db; padding: 5px; background: #ffffff;">
        <p style="font-size: 8px; font-weight: 300; margin-top: 10px; color: #1f2937;">PRS QR Code</p>
    </div>

    <!-- Bagian Tanda Tangan -->
    <div class="signature-section">
        @php
            $manager = get_manager($prs->user);
        @endphp

        <p style="font-size: 11px; font-weight: 600; margin-bottom: 30px;">
            This document requires General Manager approval before processing by the Purchasing Department.
        </p>

        <div class="signature-grid">
            <div class="sig-column">
                <div style="text-align: left;">
                    <p style="font-size: 10px; margin-bottom: 5px;"><strong>Requested By:</strong></p>
                    <div class="sig-line"></div>
                    <div class="sig-name" style="font-size:11px; font-weight:600; color:#1f2937; margin-top:5px;">{{ $prs->user->name }}</div>
                    <div class="sig-title">Requester</div>
                    <div class="sig-date">{{ \Carbon\Carbon::parse($prs->prs_date)->format('d F Y') }}</div>
                </div>
            </div>
            <div class="sig-column">
                <div style="text-align: left;">
                    <p style="font-size: 10px; margin-bottom: 5px;"><strong>Reviewed By:</strong></p>
                    <div class="sig-line"></div>
                    <div class="sig-name" style="font-size:11px; font-weight:600; color:#1f2937; margin-top:5px;">{{ $manager?->name ?? 'N/A' }}</div>
                    <div class="sig-title">{{ $manager ? get_job_title($manager) : 'Manager' }}</div>
                    <div class="sig-date">Date: _____________</div>
                </div>
            </div>
            <div class="sig-column">
                <div style="text-align: left;">
                    <p style="font-size: 10px; margin-bottom: 5px;"><strong>Approved By:</strong></p>
                    <div class="sig-line"></div>
                    <div class="sig-name" style="font-size:11px; font-weight:600; color:#1f2937; margin-top:5px;">{{ get_gm_name() }}</div>
                    <div class="sig-title">General Manager</div>
                    <div class="sig-date">Date: _____________</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <div class="footer">
        <p>
            Generated on {{ \Carbon\Carbon::now()->format('d F Y H:i') }} |
            This is a system-generated document and requires official signature for approval.
        </p>
    </div>

</body>
</html>
