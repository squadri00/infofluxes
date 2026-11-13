<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>{{ $subject }}</title></head>
<body style="margin:0;padding:0;background:#f3f4f6;font-family:Arial,Helvetica,sans-serif;color:#111827">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f3f4f6;padding:24px 12px">
    <tr><td align="center">
        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:620px;background:#ffffff;border-radius:10px;overflow:hidden">
            <tr><td style="background:#111827;color:#ffffff;padding:18px 28px;font-size:20px;font-weight:bold">{{ $site }}</td></tr>
            <tr><td style="padding:26px 28px;font-size:16px;line-height:1.6">
                <h1 style="margin:0 0 16px;font-size:22px;line-height:1.3">{{ $subject }}</h1>
                {!! $body !!}
            </td></tr>
            <tr><td style="padding:16px 28px 24px;font-size:13px;color:#6b7280;border-top:1px solid #e5e7eb">
                {{ \App\Support\Newsletter::words('newsletter_mail_footer') }}
                <a href="{{ $unsubscribe }}" style="color:#2563eb">{{ \App\Support\Newsletter::words('newsletter_mail_unsubscribe') }}</a>
            </td></tr>
        </table>
    </td></tr>
</table>
</body>
</html>
