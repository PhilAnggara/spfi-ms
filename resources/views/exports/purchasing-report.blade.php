<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 11px; color: #111; }
        h1 { font-size: 16px; margin: 0 0 8px; }
        .meta { margin-bottom: 10px; font-size: 10px; color: #555; }
        .meta span { margin-right: 10px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #ccc; padding: 6px 8px; text-align: left; }
        th { background: #f2f2f2; }
    </style>
</head>
<body>
    <h1>{{ $title }}</h1>
    <div class="meta">
        <span>Generated: {{ $generated_at }}</span>
        @foreach ($filters as $label => $value)
            <span>{{ ucfirst(str_replace('_', ' ', $label)) }}: {{ $value ?: '-' }}</span>
        @endforeach
    </div>

    <table>
        <thead>
            <tr>
                @foreach ($columns as $column)
                    <th>{{ $column }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    @foreach ($row as $cell)
                        <td>{{ $cell }}</td>
                    @endforeach
                </tr>
            @empty
                <tr>
                    <td colspan="{{ count($columns) }}" style="text-align:center;">No data available.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
