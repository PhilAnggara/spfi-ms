<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 9px; color: #111; }
        h1 { font-size: 13px; margin: 0 0 6px; }
        .meta { margin-bottom: 8px; font-size: 8px; color: #555; }
        .meta span { margin-right: 10px; }
        table { width: 100%; border-collapse: separate; border-spacing: 0 3px; }
        th, td { border: none; padding: 2px 6px; text-align: left; }
        th { font-size: 8px; font-weight: bold; text-align: left; text-transform: uppercase; }
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
