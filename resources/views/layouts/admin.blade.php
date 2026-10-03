<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('title', 'Admin') | IPO Darbaar Admin</title>
    <link rel="icon" href="{{ asset('favicon-48.png') }}" sizes="48x48" type="image/png">
    <link rel="icon" href="{{ asset('favicon-32.png') }}" sizes="32x32" type="image/png">
    <link rel="stylesheet" href="{{ asset('assets/css/fonts.css') }}?v={{ filemtime(public_path('assets/css/fonts.css')) }}">
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
    @auth
        {{-- This browser belongs to an editor: keep its visits out of the site analytics. --}}
        <script>try { localStorage.setItem('darbaar:no-track', '1'); } catch (e) {}</script>
    @endauth
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
            <a href="{{ route('admin.actions.index') }}" class="{{ request()->routeIs('admin.actions.*') ? 'active' : '' }}"><x-icon name="repeat" :size="16" /> Buybacks &amp; NCDs</a>
            <a href="{{ route('admin.analytics') }}" class="{{ request()->routeIs('admin.analytics*') ? 'active' : '' }}"><x-icon name="bar-chart" :size="16" /> Analytics</a>
            <a href="{{ route('admin.blog.index') }}" class="{{ request()->routeIs('admin.blog.*') ? 'active' : '' }}"><x-icon name="file-text" :size="16" /> Blog</a>
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
