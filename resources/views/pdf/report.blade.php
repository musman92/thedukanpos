<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ $title }}</title>
    <style>
        * { box-sizing: border-box; }

        @page { margin: 108px 24px 58px 24px; }

        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 10px;
            color: #111;
            margin: 0;
            padding: 0;
        }

        /* Fixed blocks repeat on every page. Height is content-driven — do not
           cap with a fixed pixel height or letterhead overflows the meta strip. */
        .sheet-header {
            position: fixed;
            top: -92px;
            left: 0;
            right: 0;
            padding-bottom: 8px;
            border-bottom: 2px solid #111;
        }
        .sheet-footer {
            position: fixed;
            bottom: -42px;
            left: 0;
            right: 0;
            height: 32px;
            border-top: 1px solid #ddd;
            padding-top: 6px;
            font-size: 8px;
            color: #777;
        }

        .report-content {
            padding-top: 0;
        }

        .head-table { width: 100%; border-collapse: collapse; }
        .head-table td { vertical-align: top; padding: 0; }
        .brand-table { width: 100%; border-collapse: collapse; }
        .brand-table td { vertical-align: top; padding: 0; }
        .brand-table .logo-cell { width: 92px; padding-right: 14px !important; }
        .brand-table .logo { max-height: 48px; max-width: 84px; display: block; margin: 0; }
        .company-name {
            font-size: 14px;
            font-weight: bold;
            text-transform: uppercase;
            margin: 0 0 3px 0;
            line-height: 1.2;
        }
        .company-line { color: #555; margin: 0 0 2px 0; font-size: 8.5px; line-height: 1.35; }
        .doc-title {
            font-size: 16px;
            font-weight: bold;
            margin: 0 0 3px 0;
            letter-spacing: 0.03em;
        }
        .doc-subtitle { font-size: 9px; color: #555; margin: 0; }

        .stats-grid {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            margin-bottom: 14px;
        }
        .stats-meta-row td {
            background: #f5f5f5;
            padding: 11px 14px;
            border: 1px solid #e0e0e0;
            vertical-align: top;
        }
        .stats-summary-row td {
            padding: 11px 14px;
            border: 1px solid #ddd;
            border-top: 0;
            vertical-align: top;
        }
        .meta-label {
            font-size: 7.5px;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: #777;
            margin: 0 0 7px 0;
            line-height: 1.4;
        }
        .meta-value {
            font-size: 10px;
            font-weight: bold;
            margin: 0;
            line-height: 1.45;
        }

        .summary-value { font-size: 13px; font-weight: bold; margin: 4px 0 0 0; line-height: 1.3; }

        table.data {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }
        table.data thead th {
            background: #ececec;
            border-top: 1px solid #111;
            border-bottom: 1px solid #111;
            padding: 6px 5px;
            font-size: 8.5px;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            text-align: left;
        }
        table.data tbody td {
            border-bottom: 1px solid #e6e6e6;
            padding: 5px;
            vertical-align: top;
            word-wrap: break-word;
        }
        table.data tbody tr:nth-child(even) td { background: #fafafa; }
        table.data tr.totals td {
            border-top: 1.5px solid #111;
            border-bottom: 1.5px solid #111;
            background: #f0f0f0;
            font-weight: bold;
            padding: 7px 5px;
        }
        /* Must out-specify `table.data thead th`, which sets a left default. */
        table.data th.align-right,
        table.data td.align-right { text-align: right; }
        table.data th.align-center,
        table.data td.align-center { text-align: center; }
        .empty {
            padding: 26px 5px;
            text-align: center;
            color: #888;
            border-bottom: 1px solid #e6e6e6;
        }
        .note {
            margin-top: 14px;
            padding-top: 8px;
            border-top: 1px dashed #bbb;
            font-size: 8.5px;
            color: #555;
            line-height: 1.5;
        }
        /* Dompdf resolves counter(page); counter(pages) is not supported, so the
           footer shows the current page only rather than a wrong total. */
        .pagenum:after { content: counter(page); }
    </style>
</head>
<body>
    <div class="sheet-header">
        <table class="head-table">
            <tr>
                <td style="width: 58%;">
                    @include('pdf.partials.company-brand', ['company' => $company])
                </td>
                <td style="width: 42%; text-align: right;">
                    <p class="doc-title">{{ strtoupper($title) }}</p>
                    @if($subtitle)
                        <p class="doc-subtitle">{{ $subtitle }}</p>
                    @endif
                    <p class="doc-subtitle">Generated {{ $generatedAt }}</p>
                </td>
            </tr>
        </table>
    </div>

    <div class="sheet-footer">
        <table style="width: 100%; border-collapse: collapse;">
            <tr>
                <td style="text-align: left;">{{ $company['name'] }} · {{ $title }}</td>
                <td style="text-align: right;">Page <span class="pagenum"></span></td>
            </tr>
        </table>
    </div>

    <div class="report-content">
    @if(count($meta) || count($summary))
        @php
            $statsCols = max(count($meta), count($summary), 1);
            $colWidth = round(100 / $statsCols, 4);
        @endphp
        <table class="stats-grid">
            @if(count($meta))
                <tr class="stats-meta-row">
                    @foreach($meta as $item)
                        <td style="width: {{ $colWidth }}%;">
                            <p class="meta-label">{{ $item['label'] }}</p>
                            <p class="meta-value">{{ $item['value'] }}</p>
                        </td>
                    @endforeach
                </tr>
            @endif
            @if(count($summary))
                <tr class="stats-summary-row">
                    @foreach($summary as $item)
                        <td style="width: {{ $colWidth }}%;">
                            <p class="meta-label">{{ $item['label'] }}</p>
                            <p class="summary-value">{{ $item['value'] }}</p>
                        </td>
                    @endforeach
                </tr>
            @endif
        </table>
    @endif

    <table class="data">
        <thead>
            <tr>
                @foreach($columns as $column)
                    <th class="align-{{ $column['align'] }}"
                        @if($column['width']) style="width: {{ $column['width'] }};" @endif>
                        {{ $column['label'] }}
                    </th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse($rows as $row)
                <tr>
                    @foreach($columns as $column)
                        <td class="align-{{ $column['align'] }}">{{ $row[$column['key']] }}</td>
                    @endforeach
                </tr>
            @empty
                <tr>
                    <td class="empty" colspan="{{ max(count($columns), 1) }}">
                        No records for the selected period.
                    </td>
                </tr>
            @endforelse

            @if($totals)
                <tr class="totals">
                    @foreach($columns as $index => $column)
                        <td class="align-{{ $column['align'] }}">
                            @if($index === 0 && ($totals[$column['key']] ?? '') === '')
                                {{ $totalsLabel }}
                            @else
                                {{ $totals[$column['key']] ?? '' }}
                            @endif
                        </td>
                    @endforeach
                </tr>
            @endif
        </tbody>
    </table>

    @if($note)
        <p class="note">{{ $note }}</p>
    @endif
    </div>
</body>
</html>
