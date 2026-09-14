<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ $reportName }}</title>
    <style>
        @font-face {
            font-family: 'FuturaLT';
            src: url('file://{{ public_path('fonts/futuralt.ttf') }}') format('truetype');
        }
        body {
            font-family: 'FuturaLT', Arial, Helvetica, sans-serif;
            font-size: 11px;
            color: #1f2937;
            margin: 24px;
            line-height: 1.5;
        }
        table { width: 100%; border-collapse: collapse; }
        .header-row { margin-bottom: 0; padding-bottom: 0; }
        .header-row td { border-bottom: none; }
        .doc-title { font-size: 16px; color: #02338d; text-align: right; margin-bottom: 8px; }
        .doc-ref   { font-size: 11px; color: #374151; text-align: right; margin-bottom: 5px; }
        .doc-generated { font-size: 11px; color: #6b7280; text-align: right; margin-top: 8px; }
        .filters {
            background: #f9fafb;
            border-top: 1px solid #e5e7eb;
            border-bottom: 1px solid #e5e7eb;
            padding: 4px 6px;
            font-size: 10px;
            color: #374151;
            margin-bottom: 14px;
        }
        .filters strong { color: #02338d; font-weight: normal; }
        thead th { background: #02338d; color: #fff; padding: 5px 8px; text-align: left; font-size: 10px; font-weight: 600; }
        tbody td { padding: 6px 8px; border-bottom: 1px solid #e5e7eb; font-size: 10px; color: #1f2937; vertical-align: middle; }
        tbody tr:nth-child(even) { background: #f9fafb; }
        .page-break { page-break-after: always; }
    </style>
</head>
<body>

    @php
        $imgPath = public_path('images/branding/logo.png');
        $imgSrc  = 'data:image/' . pathinfo($imgPath, PATHINFO_EXTENSION) . ';base64,' . base64_encode(file_get_contents($imgPath));
    @endphp

    <table class="header-row" style="margin-bottom: 14px;">
        <tr>
            <td><img src="{{ $imgSrc }}" height="85" alt="Logo" style="margin-left: -20px"></td>
            <td style="text-align:right">
                <div class="doc-title">{{ $reportName }}</div>
                @if(!empty($description))
                    <div class="doc-ref">{{ $description }}</div>
                @endif
                <div class="doc-generated">Generated: {{ $generatedAt }}</div>
            </td>
        </tr>
    </table>

    @if (!empty($modifiers))
        <div class="filters">
            <strong>Filters:</strong>
            @foreach ($modifiers as $key => $value)
                {{ ucfirst(str_replace('_', ' ', $key)) }}: {{ is_array($value) ? implode(', ', $value) : $value }}@if (!$loop->last)  | @endif
            @endforeach
        </div>
    @endif

    <table>
        <thead>
            <tr>
                @foreach ($headers as $header)
                    <th>{{ $header }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse ($data as $row)
                <tr>
                    @foreach ($headers as $header)
                        <td>{{ $row[\Illuminate\Support\Str::snake($header)] ?? $row[$header] ?? '' }}</td>
                    @endforeach
                </tr>
            @empty
                <tr>
                    <td colspan="{{ count($headers) }}" style="text-align:center; color:#9ca3af; padding: 12px 8px;">
                        No data available
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

</body>
</html>
