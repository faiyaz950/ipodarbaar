<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('title', 'Admin') | IPO Darbaar Admin</title>
    <link rel="icon" href="{{ asset('favicon-32.png') }}" sizes="32x32" type="image/png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,700&family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/css/app.css') }}?v={{ filemtime(public_path('assets/css/app.css')) }}">
    <script>
        (function () {
            try {
                var t = localStorage.getItem('theme');
                if (!t) t = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
                document.documentElement.setAttribute('data-theme', t);
            } catch (e) {}
        })();
    </script>
</head>
<body class="admin">

@auth
<header class="admin-bar">
    <div class="container">
        <a href="{{ route('admin.dashboard') }}" class="brand" aria-label="Admin dashboard">
            @include('partials.brand-mark')
            <span class="brand-name" style="color:#fff">IPO <span>Darbaar</span> <small class="admin-tag">Admin</small></span>
        </a>
        <nav class="admin-nav">
            <a href="{{ route('admin.dashboard') }}" class="{{ request()->routeIs('admin.dashboard') ? 'active' : '' }}"><x-icon name="layout" :size="16" /> Dashboard</a>
            <a href="{{ route('admin.ipos.index') }}" class="{{ request()->routeIs('admin.ipos.*') ? 'active' : '' }}"><x-icon name="layers" :size="16" /> IPOs</a>
            <a href="{{ route('admin.news.index') }}" class="{{ request()->routeIs('admin.news.*') ? 'active' : '' }}"><x-icon name="newspaper" :size="16" /> News</a>
            <a href="{{ route('admin.settings') }}" class="{{ request()->routeIs('admin.settings*') ? 'active' : '' }}"><x-icon name="sparkles" :size="16" /> Settings</a>
            <a href="{{ route('admin.system') }}" class="{{ request()->routeIs('admin.system*') ? 'active' : '' }}"><x-icon name="gauge" :size="16" /> System</a>
            <a href="{{ route('home') }}" target="_blank" rel="noopener"><x-icon name="external" :size="16" /> View site</a>
        </nav>
        <form method="post" action="{{ route('admin.logout') }}">
            @csrf
            <button type="submit" class="btn btn-ghost-light btn-sm">Log out</button>
        </form>
    </div>
</header>
@endauth

<main class="admin-main">
    <div class="container">
        @if (session('status'))
            <div class="flash ok" role="status"><x-icon name="check-circle" :size="17" /> {{ session('status') }}</div>
        @endif
        @if (session('error'))
            <div class="flash err" role="alert"><x-icon name="alert" :size="17" /> {{ session('error') }}</div>
        @endif

        @yield('content')
    </div>
</main>

</body>
</html>
