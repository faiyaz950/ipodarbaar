{{-- An AdSense unit configured in Admin → Settings. The box reserves its height so the page doesn't jump when the ad loads. --}}
@props(['name'])
@php
    $settings = app(\App\Support\Settings::class);
    $client = $settings->get('ads.client');
    $slot = $settings->get('ads.slots.'.$name);
    $shown = $settings->enabled('ads.enabled') && $settings->adSlotVisible($name);
@endphp
@if ($shown && filled($client) && filled($slot))
    <div {{ $attributes->merge(['class' => 'ad-slot']) }} data-ad="{{ $name }}">
        <span class="ad-label">Advertisement</span>
        <ins class="adsbygoogle" style="display:block" data-ad-client="{{ $client }}" data-ad-slot="{{ $slot }}" data-ad-format="auto" data-full-width-responsive="true"></ins>
        <script>(window.adsbygoogle = window.adsbygoogle || []).push({});</script>
    </div>
@elseif ($shown && config('app.debug'))
    <div {{ $attributes->merge(['class' => 'ad-slot ad-placeholder']) }} data-ad="{{ $name }}">Ad slot · {{ $name }}</div>
@endif
