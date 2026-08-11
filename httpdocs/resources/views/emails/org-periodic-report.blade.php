<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <title>تقرير Sanad الدوري</title>
</head>
<body style="font-family:Tajawal,Arial,sans-serif;background:#f5f7fb;padding:24px;color:#1B2F63;">
    <div style="max-width:560px;margin:0 auto;background:#fff;border-radius:16px;padding:24px;">
        <h1 style="margin:0 0 8px;font-size:22px;">تقرير Sanad الدوري</h1>
        <p style="margin:0 0 16px;color:#6F7A92;">{{ $orgName }} — {{ $periodLabel }}</p>
        <table style="width:100%;border-collapse:collapse;">
            <tr>
                <td style="padding:10px 0;border-bottom:1px solid #eef1f7;">الجلسات</td>
                <td style="padding:10px 0;border-bottom:1px solid #eef1f7;text-align:left;"><strong>{{ $summary['sessions'] }}</strong></td>
            </tr>
            <tr>
                <td style="padding:10px 0;border-bottom:1px solid #eef1f7;">المكتملة</td>
                <td style="padding:10px 0;border-bottom:1px solid #eef1f7;text-align:left;"><strong>{{ $summary['completed'] }}</strong></td>
            </tr>
            <tr>
                <td style="padding:10px 0;">المستفيدون</td>
                <td style="padding:10px 0;text-align:left;"><strong>{{ $summary['beneficiaries'] }}</strong></td>
            </tr>
        </table>
        <p style="margin:20px 0 0;color:#6F7A92;font-size:13px;">هذا تقرير آلي من منصة سند. للاستفسارات: {{ config('sanad.support_email') }}</p>
    </div>
</body>
</html>
