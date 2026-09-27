@extends('layouts.admin')

@section('title', 'Settings')

@section('content')
<div class="admin-head">
    <div>
        <h1>Settings</h1>
        <p class="muted">Ads, analytics, broker links and notifications. Secrets (Telegram token, SMTP password) stay in <code>.env</code>.</p>
    </div>
</div>

@if ($errors->any())
    <div class="flash err" role="alert"><x-icon name="alert" :size="17" /> Please fix the highlighted fields.</div>
@endif

@php $adsOn = $settings->enabled('ads.enabled'); @endphp
<div class="card ads-toggle" style="margin-bottom:20px">
    <div class="admin-row">
        <span>
            <b>Ads on the site</b>
            <small class="muted">{{ $adsOn ? 'Showing in every placement switched on below.' : 'Hidden everywhere. Placements keep their own on/off choice for when you switch back.' }}</small>
        </span>
        <form method="post" action="{{ route('admin.settings.ads.toggle') }}">
            @csrf
            <span class="badge {{ $adsOn ? 'b-open' : 'b-listed' }}">{{ $adsOn ? 'ON' : 'OFF' }}</span>
            <button type="submit" class="btn btn-sm {{ $adsOn ? 'btn-outline' : 'btn-gold' }}">{{ $adsOn ? 'Hide all ads' : 'Show ads' }}</button>
        </form>
    </div>
    @foreach ($slots as $key => $label)
        @php
            $visible = $settings->adSlotVisible($key);
            $live = $adsOn && $visible;
        @endphp
        <div class="admin-row {{ $adsOn ? '' : 'is-muted' }}">
            <span>
                {{ $label }}
                <small class="muted">{{ filled($settings->get('ads.slots.'.$key)) ? 'Slot '.$settings->get('ads.slots.'.$key) : 'No slot ID yet (shows a placeholder in debug mode only)' }}</small>
            </span>
            <form method="post" action="{{ route('admin.settings.ads.toggle') }}">
                @csrf
                <input type="hidden" name="slot" value="{{ $key }}">
                <span class="badge {{ $live ? 'b-open' : 'b-listed' }}">{{ $live ? 'Showing' : 'Hidden' }}</span>
                <button type="submit" class="btn btn-outline btn-sm">{{ $visible ? 'Hide' : 'Show' }}</button>
            </form>
        </div>
    @endforeach
</div>

<form method="post" action="{{ route('admin.settings.update') }}" class="stack">
    @csrf
    @method('PUT')

    <div class="card card-pad">
        <div class="card-title af-section">Google AdSense</div>
        <div class="af-grid">
            <label class="af">
                <span class="af-label">Publisher ID</span>
                <input class="input" type="text" name="ads_client" value="{{ old('ads_client', $settings->get('ads.client')) }}" placeholder="ca-pub-1234567890123456">
                <span class="af-hint">Also published at /ads.txt.</span>
                @error('ads_client')<span class="af-error">{{ $message }}</span>@enderror
            </label>
        </div>
        <div class="af-grid" style="margin-top:16px">
            @foreach ($slots as $key => $label)
                <label class="af">
                    <span class="af-label">{{ $label }}</span>
                    <input class="input" type="text" inputmode="numeric" name="slots[{{ $key }}]" value="{{ old('slots.'.$key, $settings->get('ads.slots.'.$key)) }}" placeholder="Slot ID">
                    @error('slots.'.$key)<span class="af-error">{{ $message }}</span>@enderror
                </label>
            @endforeach
        </div>
    </div>

    <div class="card card-pad">
        <div class="card-title af-section">Broker links (“Apply via your broker”)</div>
        <p class="af-hint" style="margin-bottom:12px">Shown as sponsored boxes on open and upcoming IPO pages. Leave a row empty to remove it.</p>
        @foreach ($brokers as $i => $broker)
            <div class="af-grid" style="margin-bottom:12px">
                <label class="af">
                    <span class="af-label">Broker {{ $i + 1 }}</span>
                    <input class="input" type="text" name="brokers[{{ $i }}][name]" value="{{ old('brokers.'.$i.'.name', $broker['name'] ?? '') }}" maxlength="40" placeholder="Name">
                    @error('brokers.'.$i.'.name')<span class="af-error">{{ $message }}</span>@enderror
                </label>
                <label class="af">
                    <span class="af-label">Link (https)</span>
                    <input class="input" type="url" name="brokers[{{ $i }}][url]" value="{{ old('brokers.'.$i.'.url', $broker['url'] ?? '') }}" placeholder="https://…">
                    @error('brokers.'.$i.'.url')<span class="af-error">{{ $message }}</span>@enderror
                </label>
                <label class="af">
                    <span class="af-label">Tagline</span>
                    <input class="input" type="text" name="brokers[{{ $i }}][tagline]" value="{{ old('brokers.'.$i.'.tagline', $broker['tagline'] ?? '') }}" maxlength="80" placeholder="e.g. Zero brokerage on delivery">
                </label>
            </div>
        @endforeach
    </div>

    <div class="card card-pad">
        <div class="card-title af-section">Analytics</div>
        <div class="af-grid">
            <label class="af">
                <span class="af-label">Google Analytics 4 measurement ID</span>
                <input class="input" type="text" name="ga4_id" value="{{ old('ga4_id', $settings->get('analytics.ga4_id')) }}" placeholder="G-XXXXXXXXXX">
                @error('ga4_id')<span class="af-error">{{ $message }}</span>@enderror
            </label>
            <label class="af">
                <span class="af-label">Cloudflare Web Analytics token</span>
                <input class="input" type="text" name="cf_token" value="{{ old('cf_token', $settings->get('analytics.cf_token')) }}" placeholder="32-character token (cookie-free)">
                @error('cf_token')<span class="af-error">{{ $message }}</span>@enderror
            </label>
        </div>
    </div>

    <div class="card card-pad">
        <div class="card-title af-section">Notifications</div>
        <div class="af-grid">
            <label class="af-check"><input type="checkbox" name="telegram_digest" value="1" @checked(old('telegram_digest', $settings->enabled('telegram.digest')))> Telegram: 9:00 AM daily IPO digest</label>
            <label class="af-check"><input type="checkbox" name="telegram_gmp" value="1" @checked(old('telegram_gmp', $settings->enabled('telegram.gmp')))> Telegram: 6:30 PM GMP board</label>
            <label class="af-check"><input type="checkbox" name="telegram_new_ipo" value="1" @checked(old('telegram_new_ipo', $settings->enabled('telegram.new_ipo')))> Telegram: new IPO alerts</label>
            <label class="af-check"><input type="checkbox" name="email_digest" value="1" @checked(old('email_digest', $settings->enabled('email.digest')))> Email digests to subscribers</label>
        </div>
    </div>

    <div class="admin-save">
        <button type="submit" class="btn btn-gold">Save settings</button>
    </div>
</form>
@endsection
