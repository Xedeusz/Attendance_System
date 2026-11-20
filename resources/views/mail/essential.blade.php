<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }}</title>
</head>
<body style="margin:0;background:#F8FAFC;color:#1e293b;font-family:Arial,sans-serif">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#F8FAFC">
        <tr><td align="center" style="padding:24px 12px">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;background:#fff;border:1px solid #e2e8f0;border-radius:12px">
                <tr><td style="padding:20px 24px;border-top:5px solid #10AB8C;border-bottom:1px solid #e2e8f0;color:#2F1B72">
                    <table role="presentation" cellpadding="0" cellspacing="0" width="100%">
                        <tr>
                            <td width="80" valign="middle" style="padding-right:12px">
                                <img src="{{ rtrim(config('mail.essential.link_base') ?: config('app.url'), '/').'/LIMCOMA-email.png' }}" width="80" height="80" alt="" style="display:block;width:80px;max-width:100%;height:auto">
                            </td>
                            <td valign="middle" style="line-height:1.4">
                                <strong>LIMCOMA Multipurpose Cooperative</strong><br>
                                <span style="font-size:13px;color:#241454">Employee Attendance and Management System</span>
                            </td>
                        </tr>
                    </table>
                </td></tr>
                <tr><td style="padding:24px;font-size:15px;line-height:1.6">
                    <h1 style="margin:0 0 16px;color:#241454;font-size:22px;line-height:1.25">{{ $title }}</h1>
                    <p style="margin:0 0 10px">Hello {{ $recipientName }},</p>
                    <p style="margin:0 0 18px">{{ $bodyMessage }}</p>
                    @if($fields)
                        <table width="100%" cellpadding="0" cellspacing="0" style="table-layout:fixed;background:#F8FAFC;border:1px solid #e2e8f0;font-size:14px;margin-bottom:20px">
                            @foreach($fields as $label => $value)
                                @continue($value === null)
                                <tr>
                                    <th scope="row" align="left" valign="top" style="width:36%;padding:8px 10px;border-bottom:1px solid #e2e8f0;word-wrap:break-word;overflow-wrap:anywhere">{{ $label }}</th>
                                    <td style="padding:8px 10px;border-bottom:1px solid #e2e8f0;white-space:pre-wrap;word-wrap:break-word;overflow-wrap:anywhere">{{ $value }}</td>
                                </tr>
                            @endforeach
                        </table>
                    @endif
                    <a href="{{ $url }}" style="display:inline-block;padding:11px 18px;border-radius:6px;background:#2F1B72;color:#fff;text-decoration:none;font-weight:bold">{{ $action }}</a>
                    @if($note)<p style="margin:16px 0 0;color:#475569;font-size:14px">{{ $note }}</p>@endif
                </td></tr>
                <tr><td style="padding:16px 24px;background:#E7F7F2;color:#334155;font-size:13px">LIMCOMA Multipurpose Cooperative · Attendance System</td></tr>
            </table>
        </td></tr>
    </table>
</body>
</html>
