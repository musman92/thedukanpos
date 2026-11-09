<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Reset your theDukanPOS password</title>
</head>
<body style="margin:0;padding:0;background:#f4f7fb;color:#1a2332;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f7fb;padding:32px 16px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;background:#ffffff;border:1px solid #e3e8ef;border-radius:16px;">
                    <tr>
                        <td style="padding:28px 32px 8px;" align="left">
                            <img src="{{ $logoUrl }}" alt="theDukanPOS" height="40" style="display:block;height:40px;width:auto;border:0;">
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:12px 32px 0;">
                            <h1 style="margin:0;font-size:22px;line-height:1.3;color:#1c3c62;">Reset your password</h1>
                            <p style="margin:12px 0 0;font-size:15px;line-height:1.6;color:#3d4f66;">
                                We received a request to reset the password for <strong style="color:#1a2332;">{{ $username }}</strong>
                                at shop <strong style="color:#1a2332;">{{ $shopCode }}</strong>.
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:24px 32px 8px;">
                            <a href="{{ $url }}" style="display:inline-block;background:#285080;color:#ffffff;text-decoration:none;font-size:15px;font-weight:600;padding:12px 20px;border-radius:8px;">Reset password</a>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:8px 32px 28px;">
                            <p style="margin:0;font-size:13px;line-height:1.6;color:#5e6b7a;">
                                This link expires in 60 minutes. If you did not ask for this, you can ignore this email.
                            </p>
                            <p style="margin:16px 0 0;font-size:12px;line-height:1.6;color:#5e6b7a;word-break:break-all;">
                                Or copy this link:<br>
                                <a href="{{ $url }}" style="color:#285080;">{{ $url }}</a>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:16px 32px 24px;border-top:1px solid #e3e8ef;">
                            <p style="margin:0;font-size:13px;line-height:1.6;color:#5e6b7a;">
                                theDukanPOS<br>
                                WhatsApp <a href="https://wa.me/923127032292" style="color:#e07820;text-decoration:none;">0312 7032292</a>
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
