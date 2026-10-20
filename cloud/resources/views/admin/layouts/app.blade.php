<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'پنل مدیریت') — SafirCharge</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/admin/app.css">
    @stack('styles')
</head>
<body data-page="{{ $page ?? 'dashboard' }}" data-title="{{ $title ?? '' }}" data-crumb="{{ $crumb ?? 'مدیریت شبکه' }}" data-admin-base="{{ rtrim(route('admin.dashboard'), '/') }}" data-nav='@json($navStats ?? [])'>
<div class="app">
<div class="main">
    <div class="content">
        @if(session('success'))
            <div class="alert alert-success" style="margin-bottom:16px;padding:12px 16px;background:var(--green-soft);border-radius:var(--r);color:var(--green)">{{ session('success') }}</div>
        @endif
        @if(isset($errors) && $errors->any())
            <div class="alert alert-danger" style="margin-bottom:16px;padding:12px 16px;background:var(--red-soft);border-radius:var(--r);color:var(--red)">
                <ul style="margin:0;padding-right:18px">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
            </div>
        @endif
        @yield('content')
    </div>
</div>
</div>
@stack('modals')
<script src="/assets/admin/admin-interactive.js"></script>
<script src="/assets/admin/app.js"></script>
@stack('scripts')
</body>
</html>
