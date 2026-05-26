@php($rtl = app()->getLocale() === 'ar')
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ $rtl ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $alertTitle }}</title>
</head>
<body style="margin:0; padding:0; background:#f3f4f6; font-family: Tahoma, Arial, sans-serif;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f3f4f6; padding:24px 0;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px; background:#ffffff; border-radius:12px; overflow:hidden;">
                    <tr>
                        <td style="background:#0d6e6e; padding:20px 24px; color:#ffffff; font-size:18px; font-weight:bold;">
                            {{ config('app.name', 'دليل المجمعات الطبية') }}
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:24px;">
                            <h1 style="margin:0 0 12px; font-size:18px; color:#111827;">{{ $alertTitle }}</h1>
                            <p style="margin:0 0 20px; font-size:15px; line-height:1.7; color:#374151;">{{ $alertBody }}</p>
                            @if($actionUrl)
                                <a href="{{ $actionUrl }}"
                                   style="display:inline-block; background:#0d6e6e; color:#ffffff; text-decoration:none; padding:11px 22px; border-radius:8px; font-size:14px; font-weight:bold;">
                                    {{ __('site.email_view_details') }}
                                </a>
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:16px 24px; background:#f9fafb; color:#9ca3af; font-size:12px;">
                            {!! __('site.footer_rights', ['year' => date('Y')]) !!}
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
