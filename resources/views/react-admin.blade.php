<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ __('admin.clinic_brand') }} — React</title>
    @vite(['resources/react-admin/src/main.tsx'])
</head>
<body>
    <div id="react-admin-root"></div>
</body>
</html>
