<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Product QR Labels</title>
    <style>
        @page {
            size: A4 portrait;
            margin: 5mm;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 9px;
            color: #0f172a;
            background: #ffffff;
        }

        .label-grid {
            width: 100%;
            border-collapse: separate;
            border-spacing: 2mm;
        }

        .label-cell {
            width: 33.33%;
            vertical-align: top;
            padding: 0;
        }

        .product-label {
            width: 100%;
            border: 0.3mm solid #cbd5e1;
            padding: 1.8mm;
            page-break-inside: avoid;
        }

        .label-table {
            width: 100%;
            border-collapse: collapse;
        }

        .qr-cell {
            width: 18mm;
            vertical-align: middle;
            text-align: center;
        }

        .qr-image {
            width: 16mm;
            height: 16mm;
        }

        .meta-cell {
            vertical-align: middle;
            padding-left: 1.8mm;
        }

        .product-code {
            font-size: 8.5pt;
            font-weight: bold;
            letter-spacing: 0.02em;
            color: #0f172a;
            margin-bottom: 0.6mm;
            word-break: break-all;
            line-height: 1.15;
        }

        .product-name {
            font-size: 6.5pt;
            color: #475569;
            line-height: 1.2;
            word-wrap: break-word;
        }
    </style>
</head>
<body>
    <table class="label-grid">
        @foreach ($items->chunk(3) as $row)
            <tr>
                @foreach ($row as $item)
                    @php
                        $displayName = \Illuminate\Support\Str::limit((string) $item['name'], 48);
                    @endphp
                    <td class="label-cell">
                        <div class="product-label">
                            <table class="label-table">
                                <tr>
                                    <td class="qr-cell">
                                        <img class="qr-image" src="{{ $item['qr_data_uri'] }}" alt="QR {{ $item['code'] }}">
                                    </td>
                                    <td class="meta-cell">
                                        <div class="product-code">{{ $item['code'] }}</div>
                                        <div class="product-name">{{ $displayName }}</div>
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </td>
                @endforeach

                @for ($i = $row->count(); $i < 3; $i++)
                    <td class="label-cell"></td>
                @endfor
            </tr>
        @endforeach
    </table>
</body>
</html>
