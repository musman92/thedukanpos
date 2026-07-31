<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Quotation {{ $quotation->number }}</title>
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 11px;
            color: #111;
            margin: 0;
            padding: 24px 28px;
        }
        .header {
            width: 100%;
            border-bottom: 2px solid #111;
            padding-bottom: 14px;
            margin-bottom: 16px;
        }
        .header-table { width: 100%; border-collapse: collapse; }
        .header-table td { vertical-align: top; padding: 0; }
        .logo { max-height: 56px; max-width: 140px; }
        .business-name {
            font-size: 18px;
            font-weight: bold;
            text-transform: uppercase;
            margin: 0 0 4px 0;
        }
        .muted { color: #444; line-height: 1.45; margin: 0; }
        .doc-title {
            font-size: 20px;
            font-weight: bold;
            text-align: right;
            margin: 0 0 6px 0;
            letter-spacing: 0.04em;
        }
        .meta-table { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
        .meta-table td { vertical-align: top; padding: 0; width: 50%; }
        .box-label {
            font-size: 9px;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: #666;
            margin: 0 0 4px 0;
        }
        .box-value { font-size: 12px; font-weight: bold; margin: 0 0 2px 0; }
        table.items {
            width: 100%;
            border-collapse: collapse;
            margin-top: 8px;
            table-layout: fixed;
        }
        table.items th {
            background: #f3f3f3;
            border-bottom: 1px solid #111;
            padding: 7px 5px;
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }
        table.items td {
            border-bottom: 1px solid #ddd;
            padding: 7px 5px;
            vertical-align: top;
        }
        .totals {
            width: 42%;
            margin-left: auto;
            margin-top: 12px;
            border-collapse: collapse;
        }
        .totals td { padding: 4px 0; }
        .totals .label { color: #444; text-align: left; }
        .totals .value { text-align: right; white-space: nowrap; }
        .totals .grand td {
            border-top: 2px solid #111;
            font-weight: bold;
            font-size: 13px;
            padding-top: 8px;
        }
        .notes {
            margin-top: 22px;
            padding-top: 10px;
            border-top: 1px dashed #999;
        }
        .footer {
            margin-top: 28px;
            padding-top: 10px;
            border-top: 1px dashed #999;
            font-size: 9px;
            color: #666;
            text-align: center;
            line-height: 1.5;
        }
        .footer-brand {
            margin-top: 8px;
            font-size: 9px;
            color: #444;
        }
    </style>
</head>
<body>
    <div class="header">
        <table class="header-table">
            <tr>
                <td style="width: 62%;">
                    @if(! empty($company['logo_src']))
                        <img src="{{ $company['logo_src'] }}" alt="" class="logo"><br>
                    @endif
                    <p class="business-name">{{ $company['name'] }}</p>
                    @if(! empty($company['address']))
                        <p class="muted">{{ $company['address'] }}</p>
                    @endif
                    @if(! empty($company['phone']))
                        <p class="muted">Tel: {{ $company['phone'] }}</p>
                    @endif
                    @if(! empty($company['email']))
                        <p class="muted">{{ $company['email'] }}</p>
                    @endif
                    @if(! empty($company['tax_id']))
                        <p class="muted">NTN: {{ $company['tax_id'] }}</p>
                    @endif
                </td>
                <td style="width: 38%; text-align: right;">
                    <p class="doc-title">QUOTATION</p>
                    <p class="muted"><strong>{{ $quotation->number }}</strong></p>
                    <p class="muted">Status: {{ $statusLabel }}</p>
                    <p class="muted">Date: {{ format_company_date($quotation->quote_date) }}</p>
                    @if($quotation->valid_until)
                        <p class="muted">Valid until: {{ format_company_date($quotation->valid_until) }}</p>
                    @endif
                    @if($quotation->branch)
                        <p class="muted">Branch: {{ $quotation->branch->name }}</p>
                    @endif
                </td>
            </tr>
        </table>
    </div>

    <table class="meta-table">
        <tr>
            <td>
                <p class="box-label">Bill to</p>
                @if($quotation->customer)
                    <p class="box-value">{{ $quotation->customer->name }}</p>
                    @if($quotation->customer->phone)
                        <p class="muted">{{ $quotation->customer->phone }}</p>
                    @endif
                    @if($quotation->customer->email)
                        <p class="muted">{{ $quotation->customer->email }}</p>
                    @endif
                    @if($quotation->customer->address)
                        <p class="muted">{{ $quotation->customer->address }}</p>
                    @endif
                @else
                    <p class="box-value">Walk-in / General</p>
                @endif
            </td>
            <td style="text-align: right;">
                @if($quotation->creator)
                    <p class="box-label">Prepared by</p>
                    <p class="box-value">{{ $quotation->creator->name }}</p>
                @endif
            </td>
        </tr>
    </table>

    <table class="items">
        <thead>
            <tr>
                <th style="width: 4%; text-align: left;">#</th>
                <th style="width: 40%; text-align: left;">Item</th>
                <th style="width: 8%; text-align: center;">Qty</th>
                <th style="width: 12%; text-align: right;">Price</th>
                <th style="width: 12%; text-align: right;">Disc.</th>
                <th style="width: 12%; text-align: right;">Tax</th>
                <th style="width: 12%; text-align: right;">Amount</th>
            </tr>
        </thead>
        <tbody>
            @foreach($quotation->items as $index => $item)
                @php
                    $label = $item->product?->name ?? 'Item';
                    if ($item->variant?->name) {
                        $label .= ' — '.$item->variant->name;
                    }
                    $unit = $item->unit?->code ?: $item->unit?->name;
                @endphp
                <tr>
                    <td style="width: 4%; text-align: left;">{{ $index + 1 }}</td>
                    <td style="width: 40%; text-align: left;">
                        {{ $label }}
                        @if($unit)
                            <br><span class="muted" style="font-size: 9px;">Unit: {{ $unit }}</span>
                        @endif
                    </td>
                    <td style="width: 8%; text-align: center; white-space: nowrap;">
                        {{ rtrim(rtrim(number_format((float) $item->quantity, 4, '.', ''), '0'), '.') }}
                    </td>
                    <td style="width: 12%; text-align: right; white-space: nowrap;">{{ format_money($item->unit_price) }}</td>
                    <td style="width: 12%; text-align: right; white-space: nowrap;">{{ format_money($item->discount) }}</td>
                    <td style="width: 12%; text-align: right; white-space: nowrap;">{{ format_money($item->tax_amount) }}</td>
                    <td style="width: 12%; text-align: right; white-space: nowrap;">{{ format_money($item->line_total) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="totals">
        <tr>
            <td class="label">Subtotal</td>
            <td class="value">{{ format_money($quotation->subtotal) }}</td>
        </tr>
        @if((float) $quotation->discount_total > 0.0001)
            <tr>
                <td class="label">Discount</td>
                <td class="value">-{{ format_money($quotation->discount_total) }}</td>
            </tr>
        @endif
        @if((float) $quotation->tax_total > 0.0001)
            <tr>
                <td class="label">Tax</td>
                <td class="value">{{ format_money($quotation->tax_total) }}</td>
            </tr>
        @endif
        <tr class="grand">
            <td class="label">Total</td>
            <td class="value">{{ format_money($quotation->total) }}</td>
        </tr>
    </table>

    @if($quotation->notes)
        <div class="notes">
            <p class="box-label">Notes</p>
            <p class="muted" style="white-space: pre-wrap;">{{ $quotation->notes }}</p>
        </div>
    @endif

    <div class="footer">
        <p style="margin: 0;">This quotation is not a tax invoice. Prices are valid until the date shown above unless otherwise agreed.</p>
        <p class="footer-brand">Generated by {{ config('app.name', 'DukanPOS') }}</p>
    </div>
</body>
</html>
