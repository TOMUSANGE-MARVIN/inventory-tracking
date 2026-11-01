<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: sans-serif; font-size: 9pt; color: #0f172a; }
        h1 { font-size: 16pt; margin: 0; color: #2563eb; }
        .sub { color: #64748b; margin: 2px 0 12px; }
        .summary td { padding: 4px 14px 4px 0; }
        .summary .label { color: #64748b; }
        .summary .value { font-weight: bold; font-size: 11pt; }
        table.data { width: 100%; border-collapse: collapse; margin-top: 12px; }
        table.data th { background: #2563eb; color: #fff; text-align: left; padding: 5px 6px; font-size: 8pt; }
        table.data td { padding: 4px 6px; border-bottom: 1px solid #e2e8f0; }
        table.data tr:nth-child(even) td { background: #f8fafc; }
        .foot { margin-top: 10px; color: #94a3b8; font-size: 7.5pt; }
    </style>
</head>
<body>
    <h1>Sparkle Salons – {{ $title }}</h1>
    <div class="sub">{{ $subtitle }}</div>

    <table class="summary">
        <tr>
            @foreach ($summary as $label => $value)
                <td><div class="label">{{ $label }}</div><div class="value">{{ $value }}</div></td>
            @endforeach
        </tr>
    </table>

    <table class="data">
        <thead>
            <tr>
                @foreach ($headings as $h)
                    <th>{{ $h }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    @foreach ($row as $cell)
                        <td>{{ is_float($cell) || is_int($cell) ? number_format($cell, is_float($cell) && floor($cell) != $cell ? 1 : 0) : $cell }}</td>
                    @endforeach
                </tr>
            @empty
                <tr><td colspan="{{ count($headings) }}">Nothing in this period.</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="foot">Generated {{ now()->format('j M Y H:i') }} by {{ auth()->user()?->name }}</div>
</body>
</html>
