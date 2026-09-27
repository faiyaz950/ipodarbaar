@php
    $settings = app(\App\Support\Settings::class);
    $ga4 = $settings->get('analytics.ga4_id');
    $cfToken = $settings->get('analytics.cf_token');
    $adsClient = $settings->enabled('ads.enabled') ? $settings->get('ads.client') : null;
@endphp
<script>
    window.darbaarTrack = function (name, params) {
        try { if (typeof window.gtag === 'function') window.gtag('event', name, params || {}); } catch (e) {}
    };
</script>
@if ($ga4)
    <script async src="https://www.googletagmanager.com/gtag/js?id={{ $ga4 }}"></script>
    <script>
        window.dataLayer = window.dataLayer || [];
        function gtag() { dataLayer.push(arguments); }
        gtag('js', new Date());
        gtag('config', @json($ga4));
    </script>
@endif
@if ($cfToken)
    <script defer src="https://static.cloudflareinsights.com/beacon.min.js" data-cf-beacon='{"token": "{{ $cfToken }}"}'></script>
@endif
@if ($adsClient && ! request()->routeIs('news.shorts'))
    <script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client={{ $adsClient }}" crossorigin="anonymous"></script>
@endif
