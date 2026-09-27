@extends('layouts.admin')

@section('title', 'System')

@section('content')
<div class="admin-head">
    <div>
        <h1>System</h1>
        <p class="muted">Hosting health, cron status and one-click maintenance.</p>
    </div>
    <div class="admin-actions">
        <form method="post" action="{{ route('admin.system.optimize') }}">
            @csrf
            <button type="submit" class="btn btn-outline btn-sm"><x-icon name="refresh" :size="15" /> Rebuild caches</button>
        </form>
        <form method="post" action="{{ route('admin.sync') }}">
            @csrf
            <button type="submit" class="btn btn-navy btn-sm"><x-icon name="refresh" :size="15" /> Sync IPOs now</button>
        </form>
    </div>
</div>

<div class="admin-stats">
    <div class="card card-pad">
        <span class="muted">Cron (scheduler)</span>
        <b class="kpi {{ $cronAlive ? 'up' : 'down' }}">{{ $cronAlive ? 'Running' : 'Not running' }}</b>
        <small class="muted">{{ $lastBeat ? 'Last beat '.$lastBeat->diffForHumans() : 'No heartbeat yet' }}</small>
    </div>
    <div class="card card-pad">
        <span class="muted">Last IPO sync</span>
        <b class="kpi">{{ $lastSynced?->diffForHumans(short: true) ?? 'Never' }}</b>
        <small class="muted">{{ $lastSynced?->timezone(config('app.timezone'))->format('j M, g:i a') }}</small>
    </div>
    <div class="card card-pad">
        <span class="muted">Queued jobs</span>
        <b class="kpi">{{ number_format($queue['pending']) }}</b>
        <small class="muted">Failed: {{ number_format($queue['failed']) }}</small>
    </div>
    <div class="card card-pad">
        <span class="muted">Pending migrations</span>
        <b class="kpi {{ count($pendingMigrations) ? 'down' : 'up' }}">{{ count($pendingMigrations) }}</b>
        @if (count($pendingMigrations))
            <form method="post" action="{{ route('admin.system.migrate') }}">
                @csrf
                <button type="submit" class="btn btn-gold btn-sm">Run migrations</button>
            </form>
        @else
            <small class="muted">Database is up to date</small>
        @endif
    </div>
</div>

<div class="layout">
    <div class="stack">
        <div class="card">
            <div class="card-head"><div class="card-title">Server checks</div></div>
            @foreach ($checks as $check)
                <div class="admin-row">
                    <span>
                        <b>{{ $check['label'] }}</b>
                        <small class="muted">{{ $check['detail'] }}</small>
                    </span>
                    @if ($check['ok'])
                        <span class="badge b-open">OK</span>
                    @else
                        <span class="badge {{ $check['required'] ? 'b-closed' : 'b-listed' }}">{{ $check['required'] ? 'Fix needed' : 'Optional' }}</span>
                    @endif
                </div>
            @endforeach
        </div>

        <div class="card card-pad">
            <div class="card-title af-section">Cron job</div>
            <p class="muted" style="margin-bottom:10px">Add this single entry in cPanel → Cron Jobs (every minute). It runs the IPO sync, digests, Telegram posts and the mail queue.</p>
            <pre class="formula" style="white-space:pre-wrap;word-break:break-all">{{ $cronLine }}</pre>
        </div>
    </div>

    <aside class="sidebar">
        <div class="card card-pad">
            <div class="card-title af-section">Email</div>
            <p class="muted" style="margin-bottom:12px">Mailer: <b>{{ $mailer }}</b>. Sends a test message to your admin address.</p>
            <form method="post" action="{{ route('admin.system.test-mail') }}">
                @csrf
                <button type="submit" class="btn btn-outline btn-sm btn-block"><x-icon name="mail" :size="15" /> Send test email</button>
            </form>
        </div>
        <div class="card card-pad">
            <div class="card-title af-section">Telegram</div>
            @if ($telegramReady)
                <p class="muted" style="margin-bottom:12px">Bot and channel are configured.</p>
                <form method="post" action="{{ route('admin.system.test-telegram') }}">
                    @csrf
                    <button type="submit" class="btn btn-outline btn-sm btn-block"><x-icon name="message" :size="15" /> Post test message</button>
                </form>
            @else
                <p class="muted">Set <code>TELEGRAM_BOT_TOKEN</code> and <code>TELEGRAM_CHANNEL_ID</code> in <code>.env</code>, then rebuild caches.</p>
            @endif
        </div>
    </aside>
</div>
@endsection
