<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Invoice {{ $sale->number }}</title>
    <style>
        * { box-sizing: border-box; }

        @page {
            margin: 16mm 12mm 18mm 12mm;
        }

        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 10px;
            color: #333;
            margin: 0;
            padding: 0;
            line-height: 1.5;
        }

        p { margin: 0; }

        .muted { color: #666; }
        .muted-light { color: #888; font-size: 9px; }

        /* ── Top header ── */
        .top-table { width: 100%; border-collapse: collapse; margin-bottom: 28px; }
        .top-table td { vertical-align: top; padding: 0; }
        .brand-table { width: 100%; border-collapse: collapse; }
        .brand-table td { vertical-align: top; padding: 0; }
        .brand-table .logo-cell { width: 92px; padding-right: 14px !important; }
        .brand-table .logo { max-height: 52px; max-width: 84px; display: block; margin: 0; }
        .company-name {
            font-size: 13px;
            font-weight: bold;
            color: #111;
            margin-bottom: 4px;
        }
        .company-line {
            font-size: 9px;
            color: #666;
            line-height: 1.55;
        }
        .doc-title {
            font-size: 28px;
            font-weight: bold;
            color: #111;
            letter-spacing: 0.02em;
            margin-bottom: 6px;
        }
        .doc-number {
            font-size: 11px;
            color: #555;
            margin-bottom: 18px;
        }
        .balance-label {
            font-size: 9px;
            color: #888;
            margin-bottom: 2px;
        }
        .balance-amount {
            font-size: 18px;
            font-weight: bold;
            color: #111;
        }

        /* ── Bill to + meta ── */
        .info-table { width: 100%; border-collapse: collapse; margin-bottom: 24px; }
        .info-table td { vertical-align: top; padding: 0; }
        .section-label {
            font-size: 10px;
            color: #888;
            margin-bottom: 6px;
        }
        .bill-to-name {
            font-size: 12px;
            font-weight: bold;
            color: #111;
            margin-bottom: 3px;
        }
        .bill-to-line {
            font-size: 9px;
            color: #666;
            line-height: 1.5;
        }
        .meta-table { width: 100%; border-collapse: collapse; }
        .meta-table td {
            padding: 2px 0;
            font-size: 9px;
            vertical-align: top;
        }
        .meta-table .meta-label {
            color: #888;
            text-align: right;
            padding-right: 8px;
            white-space: nowrap;
        }
        .meta-table .meta-colon {
            color: #888;
            width: 8px;
        }
        .meta-table .meta-value {
            color: #333;
            text-align: left;
        }

        /* ── Line items (original readable table style) ── */
        table.items {
            width: 100%;
            border-collapse: collapse;
            margin-top: 8px;
            margin-bottom: 0;
            table-layout: fixed;
        }
        table.items th {
            background: #f3f3f3;
            border-bottom: 1px solid #111;
            padding: 7px 5px;
            font-size: 10px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: #111;
        }
        table.items td {
            border-bottom: 1px solid #ddd;
            padding: 7px 5px;
            vertical-align: top;
            font-size: 11px;
            color: #111;
        }
        .item-unit {
            font-size: 9px;
            color: #666;
            line-height: 1.45;
        }

        /* ── Totals block ── */
        .totals-wrap { width: 100%; margin-top: 0; }
        .totals-table {
            width: 320px;
            margin-left: auto;
            border-collapse: collapse;
        }
        .totals-table td {
            padding: 5px 0;
            font-size: 10px;
            vertical-align: middle;
        }
        .totals-table .t-label {
            color: #555;
            text-align: left;
        }
        .totals-table .t-value {
            text-align: right;
            white-space: nowrap;
            color: #333;
        }
        .totals-table .t-total td {
            padding-top: 8px;
            font-weight: bold;
            font-size: 11px;
            color: #111;
        }
        .totals-table .t-balance td {
            background: #f0f0f0;
            padding: 8px 10px;
            font-weight: bold;
            font-size: 11px;
            color: #111;
        }
        .totals-table .t-balance .t-label { color: #111; }
        .totals-table .t-paid td {
            color: #555;
            font-size: 9px;
        }

        /* ── Payments ── */
        .payments-wrap {
            width: 320px;
            margin-left: auto;
            margin-top: 12px;
        }
        .payments-title {
            font-size: 9px;
            color: #888;
            margin-bottom: 4px;
        }
        .payments-table { width: 100%; border-collapse: collapse; }
        .payments-table td {
            padding: 3px 0;
            font-size: 9px;
            color: #555;
            border-bottom: 1px solid #eee;
        }
        .payments-table .p-value { text-align: right; }

        /* ── Notes & footer ── */
        .notes-block { margin-top: 32px; }
        .notes-label {
            font-size: 10px;
            color: #888;
            margin-bottom: 4px;
        }
        .notes-text {
            font-size: 10px;
            color: #555;
            white-space: pre-wrap;
        }
        .page-footer {
            margin-top: 40px;
            padding-top: 10px;
            border-top: 1px solid #e0e0e0;
            font-size: 8px;
            color: #aaa;
            text-align: center;
        }
    </style>
</head>
<body>
    @php
        $saleDate = $sale->business_date ?? $sale->created_at;
        $balanceDue = max(0, (float) $sale->total - (float) $sale->paid_total);
    @endphp

    {{-- Header: company left, INVOICE + balance right --}}
    <table class="top-table">
        <tr>
            <td style="width: 55%;">
                @include('pdf.partials.company-brand', ['company' => $company])
            </td>
            <td style="width: 45%; text-align: right;">
                <p class="doc-title">INVOICE</p>
                <p class="doc-number"># {{ $sale->number }}</p>
                <p class="balance-label">Balance Due</p>
                <p class="balance-amount">{{ format_money($balanceDue) }}</p>
            </td>
        </tr>
    </table>

    {{-- Bill to + invoice meta --}}
    <table class="info-table">
        <tr>
            <td style="width: 50%;">
                <p class="section-label">Bill To</p>
                @if($sale->customer)
                    <p class="bill-to-name">{{ $sale->customer->name }}</p>
                    @if($sale->customer->phone)
                        <p class="bill-to-line">{{ $sale->customer->phone }}</p>
                    @endif
                    @if($sale->customer->email)
                        <p class="bill-to-line">{{ $sale->customer->email }}</p>
                    @endif
                    @if($sale->customer->address)
                        <p class="bill-to-line" style="white-space: pre-wrap;">{{ $sale->customer->address }}</p>
                    @endif
                @else
                    <p class="bill-to-name">Walk-in Customer</p>
                @endif
            </td>
            <td style="width: 50%;">
                <table class="meta-table" align="right">
                    <tr>
                        <td class="meta-label">Invoice Date</td>
                        <td class="meta-colon">:</td>
                        <td class="meta-value">{{ format_company_date($saleDate) }}</td>
                    </tr>
                    <tr>
                        <td class="meta-label">Payment</td>
                        <td class="meta-colon">:</td>
                        <td class="meta-value">{{ $paymentLabel }}</td>
                    </tr>
                    @if($sale->branch)
                        <tr>
                            <td class="meta-label">Branch</td>
                            <td class="meta-colon">:</td>
                            <td class="meta-value">{{ $sale->branch->name }}</td>
                        </tr>
                    @endif
                    @if($sale->cashier)
                        <tr>
                            <td class="meta-label">Served by</td>
                            <td class="meta-colon">:</td>
                            <td class="meta-value">{{ $sale->cashier->name ?: $sale->cashier->username }}</td>
                        </tr>
                    @endif
                    @if($sale->is_delivery)
                        <tr>
                            <td class="meta-label">Delivery</td>
                            <td class="meta-colon">:</td>
                            <td class="meta-value">Yes</td>
                        </tr>
                    @endif
                </table>
            </td>
        </tr>
    </table>

    @if($sale->is_delivery && ($sale->delivery_address || $sale->rider))
        <table class="info-table" style="margin-top: -12px; margin-bottom: 20px;">
            <tr>
                <td>
                    @if($sale->delivery_address)
                        <p class="section-label">Deliver To</p>
                        <p class="bill-to-line" style="white-space: pre-wrap;">{{ $sale->delivery_address }}</p>
                    @endif
                    @if($sale->rider)
                        <p class="bill-to-line" style="margin-top: 4px;">
                            Rider: {{ $sale->rider->name ?: $sale->rider->username }}
                        </p>
                    @endif
                </td>
            </tr>
        </table>
    @endif

    {{-- Line items --}}
    <table class="items">
        <thead>
            <tr>
                <th style="width: 4%; text-align: left;">#</th>
                <th style="width: 34%; text-align: left;">Item</th>
                <th style="width: 8%; text-align: center;">Qty</th>
                <th style="width: 14%; text-align: right;">Price</th>
                <th style="width: 13%; text-align: right;">Disc.</th>
                <th style="width: 13%; text-align: right;">Tax</th>
                <th style="width: 14%; text-align: right;">Amount</th>
            </tr>
        </thead>
        <tbody>
            @foreach($sale->items as $index => $item)
                @php
                    $label = $item->product?->name ?? 'Item';
                    if ($item->variant?->name) {
                        $label .= ' — '.$item->variant->name;
                    }
                    $unit = $item->unit?->code ?: $item->unit?->name;
                @endphp
                <tr>
                    <td style="text-align: left;">{{ $index + 1 }}</td>
                    <td style="text-align: left;">
                        {{ $label }}
                        @if($unit)
                            <br><span class="item-unit">Unit: {{ $unit }}</span>
                        @endif
                    </td>
                    <td style="text-align: center; white-space: nowrap;">
                        {{ rtrim(rtrim(number_format((float) $item->quantity, 4, '.', ''), '0'), '.') }}
                    </td>
                    <td style="text-align: right; white-space: nowrap;">{{ format_money($item->unit_price) }}</td>
                    <td style="text-align: right; white-space: nowrap;">{{ format_money($item->discount) }}</td>
                    <td style="text-align: right; white-space: nowrap;">{{ format_money($item->tax_amount) }}</td>
                    <td style="text-align: right; white-space: nowrap;">{{ format_money($item->line_total) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    {{-- Totals --}}
    <div class="totals-wrap">
        <table class="totals-table">
            <tr>
                <td class="t-label">Sub Total</td>
                <td class="t-value">{{ format_amount($sale->subtotal) }}</td>
            </tr>
            @if((float) $sale->discount_total > 0.0001)
                <tr>
                    <td class="t-label">Discount</td>
                    <td class="t-value">-{{ format_amount($sale->discount_total) }}</td>
                </tr>
            @endif
            @if((float) $sale->tax_total > 0.0001)
                <tr>
                    <td class="t-label">Tax</td>
                    <td class="t-value">{{ format_amount($sale->tax_total) }}</td>
                </tr>
            @endif
            @if($sale->is_delivery && (float) ($sale->delivery_charge ?? 0) > 0.0001)
                <tr>
                    <td class="t-label">Delivery</td>
                    <td class="t-value">{{ format_amount($sale->delivery_charge) }}</td>
                </tr>
            @endif
            <tr class="t-total">
                <td class="t-label">Total</td>
                <td class="t-value">{{ format_money($sale->total) }}</td>
            </tr>
            @if((float) $sale->paid_total > 0.01)
                <tr class="t-paid">
                    <td class="t-label">Paid</td>
                    <td class="t-value">{{ format_money($sale->paid_total) }}</td>
                </tr>
            @endif
            <tr class="t-balance">
                <td class="t-label">Balance Due</td>
                <td class="t-value">{{ format_money($balanceDue) }}</td>
            </tr>
        </table>

        @if($sale->payments->isNotEmpty())
            <div class="payments-wrap">
                <p class="payments-title">Payments Received</p>
                <table class="payments-table">
                    @foreach($sale->payments as $payment)
                        <tr>
                            <td>{{ $payment->moneySource?->name ?? 'Payment' }}</td>
                            <td class="p-value">{{ format_money($payment->amount) }}</td>
                        </tr>
                    @endforeach
                </table>
            </div>
        @endif
    </div>

    {{-- Notes --}}
    <div class="notes-block">
        <p class="notes-label">Notes</p>
        <p class="notes-text">{{ $sale->notes ?: 'Thanks for your business.' }}</p>
    </div>

    <div class="page-footer">
        Generated by {{ config('app.name', 'DukanPOS') }}
    </div>
</body>
</html>
