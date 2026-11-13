<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $heading }}</title>
</head>
<body style="margin:0;padding:0;background:#f4f7fb;color:#1a2332;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f7fb;padding:32px 16px;">
        <tr>
            <td align="center" style="padding-bottom:20px;">
                <img src="{{ $message->embed($details->posLogoPath) }}" alt="theDukanPOS" height="40" style="display:block;height:40px;width:auto;margin:0 auto;border:0;">
            </td>
        </tr>
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;background:#ffffff;border:1px solid #e3e8ef;border-radius:16px;">
                    <tr>
                        <td align="center" style="padding:28px 32px 0;">
                            @if ($details->shopLogoPath)
                                <img src="{{ $message->embed($details->shopLogoPath) }}" alt="{{ $details->shop }}" height="56" style="display:block;height:56px;width:auto;margin:0 auto 12px;border:0;">
                            @endif
                            <p style="margin:0;font-size:18px;font-weight:600;line-height:1.3;color:#1c3c62;text-align:center;">{{ $details->shop }}</p>
                            @if ($details->shopPhone || $details->shopEmail)
                                <p style="margin:6px 0 0;font-size:13px;line-height:1.5;color:#5e6b7a;text-align:center;">
                                    {{ collect([$details->shopPhone, $details->shopEmail])->filter()->implode(' · ') }}
                                </p>
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:20px 32px 0;">
                            <h1 style="margin:0;font-size:22px;line-height:1.3;color:#1c3c62;">{{ $heading }}</h1>
                            <p style="margin:12px 0 0;font-size:15px;line-height:1.6;color:#3d4f66;">{{ $intro }}</p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:20px 32px 28px;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f6f3ee;border-radius:12px;">
                                <tr>
                                    <td style="padding:16px 18px;font-size:14px;line-height:1.5;color:#1a2332;">
                                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                                            <tr>
                                                <td style="padding:4px 12px 4px 0;color:#5e6b7a;width:110px;">Booking</td>
                                                <td style="padding:4px 0;">{{ $details->number }}</td>
                                            </tr>
                                            <tr>
                                                <td style="padding:4px 12px 4px 0;color:#5e6b7a;">Service</td>
                                                <td style="padding:4px 0;">{{ $details->service }}</td>
                                            </tr>
                                            <tr>
                                                <td style="padding:4px 12px 4px 0;color:#5e6b7a;">When</td>
                                                <td style="padding:4px 0;">{{ $details->when }}</td>
                                            </tr>
                                            @if ($audience !== 'customer')
                                                <tr>
                                                    <td style="padding:4px 12px 4px 0;color:#5e6b7a;">Customer</td>
                                                    <td style="padding:4px 0;">{{ $details->customer }}</td>
                                                </tr>
                                                <tr>
                                                    <td style="padding:4px 12px 4px 0;color:#5e6b7a;">Phone</td>
                                                    <td style="padding:4px 0;">{{ $details->phone }}</td>
                                                </tr>
                                            @endif
                                            @if ($details->branch)
                                                <tr>
                                                    <td style="padding:4px 12px 4px 0;color:#5e6b7a;">Branch</td>
                                                    <td style="padding:4px 0;">{{ $details->branch }}</td>
                                                </tr>
                                            @endif
                                            @if ($details->address)
                                                <tr>
                                                    <td style="padding:4px 12px 4px 0;color:#5e6b7a;vertical-align:top;">Address</td>
                                                    <td style="padding:4px 0;">{{ $details->address }}</td>
                                                </tr>
                                            @endif
                                            @if ($audience !== 'customer' && $details->notes)
                                                <tr>
                                                    <td style="padding:4px 12px 4px 0;color:#5e6b7a;vertical-align:top;">Notes</td>
                                                    <td style="padding:4px 0;">{{ $details->notes }}</td>
                                                </tr>
                                            @endif
                                        </table>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
        <tr>
            <td align="center" style="padding-top:16px;">
                <p style="margin:0;font-size:13px;line-height:1.6;color:#5e6b7a;text-align:center;">
                    theDukanPOS · Billing, stock, and khata in one place · WhatsApp <a href="https://wa.me/923127032292" style="color:#e07820;text-decoration:none;">0312 7032292</a>
                </p>
            </td>
        </tr>
    </table>
</body>
</html>
