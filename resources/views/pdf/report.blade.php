<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ $title }}</title>
    <style>
        * { box-sizing: border-box; }

        @page { margin: 104px 32px 58px 32px; }

        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 10px;
            color: #111;
            margin: 0;
            padding: 0;
        }

        /* Fixed blocks repeat on every page. */
        .sheet-header {
            position: fixed;
            top: -84px;
            left: 0;
            right: 0;
            height: 74px;
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

        .head-table { width: 100%; border-collapse: collapse; }
        .head-table td { vertical-align: top; padding: 0; }
        .logo { max-height: 42px; max-width: 120px; }
        .company-name {
            font-size: 15px;
            font-weight: bold;
            text-transform: uppercase;
            margin: 0 0 2px 0;
        }
        .company-line { color: #555; margin: 0; font-size: 8.5px; line-height: 1.4; }
        .doc-title {
            font-size: 16px;
            font-weight: bold;
            margin: 0 0 3px 0;
            letter-spacing: 0.03em;
        }
        .doc-subtitle { font-size: 9px; color: #555; margin: 0; }

        .meta-strip {
            width: 100%;
            border-collapse: collapse;
            background: #f5f5f5;
            margin-bottom: 12px;
        }
        .meta-strip td {
            padding: 6px 9px;
            border-right: 1px solid #e0e0e0;
            vertical-align: top;
        }
        .meta-strip td:last-child { border-right: 0; }
        .meta-label {
            font-size: 7.5px;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: #777;
            margin: 0 0 2px 0;
        }
        .meta-value { font-size: 9.5px; font-weight: bold; margin: 0; }

        .summary { width: 100%; border-collapse: separate; border-spacing: 6px 0; margin-bottom: 12px; }
        .summary td {
            border: 1px solid #ddd;
            padding: 7px 9px;
            vertical-align: top;
        }
        .summary-value { font-size: 13px; font-weight: bold; margin: 2px 0 0 0; }

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
                    @if(! empty($company['logo_src']))
                        <img src="{{ $company['logo_src'] }}" alt="" class="logo"><br>
                    @endif
                    <p class="company-name">{{ $company['name'] }}</p>
                    @if(! empty($company['address']))
                        <p class="company-line">{{ $company['address'] }}</p>
                    @endif
                    <p class="company-line">
                        @if(! empty($company['phone'])) Tel: {{ $company['phone'] }} @endif
                        @if(! empty($company['email'])) &nbsp;·&nbsp; {{ $company['email'] }} @endif
                        @if(! empty($company['tax_id'])) &nbsp;·&nbsp; NTN: {{ $company['tax_id'] }} @endif
                    </p>
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

    @if(count($meta))
        <table class="meta-strip">
            <tr>
                @foreach($meta as $item)
                    <td style="width: {{ round(100 / count($meta), 4) }}%;">
                        <p class="meta-label">{{ $item['label'] }}</p>
                        <p class="meta-value">{{ $item['value'] }}</p>
                    </td>
                @endforeach
            </tr>
        </table>
    @endif

    @if(count($summary))
        <table class="summary">
            <tr>
                @foreach($summary as $item)
                    <td style="width: {{ round(100 / count($summary), 4) }}%;">
                        <p class="meta-label">{{ $item['label'] }}</p>
                        <p class="summary-value">{{ $item['value'] }}</p>
                    </td>
                @endforeach
            </tr>
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
</body>
</html>
