{{-- Date range presets and a custom range; $params are kept on every link (e.g. the page being viewed). --}}
@php $params = $params ?? []; @endphp
<div class="an-range">
    <div class="seg">
        @foreach (\App\Http\Controllers\Admin\AnalyticsController::RANGES as $key => $label)
            <a href="{{ request()->url() }}?{{ http_build_query($params + ['range' => $key]) }}" class="{{ $range === $key ? 'active' : '' }}">{{ $label }}</a>
        @endforeach
    </div>
    <form method="get" action="{{ request()->url() }}">
        @foreach ($params as $name => $value)<input type="hidden" name="{{ $name }}" value="{{ $value }}">@endforeach
        <input class="input" type="date" name="from" value="{{ $from->toDateString() }}" max="{{ now()->toDateString() }}" aria-label="From">
        <span class="muted">to</span>
        <input class="input" type="date" name="to" value="{{ $to->toDateString() }}" max="{{ now()->toDateString() }}" aria-label="To">
        <button type="submit" class="btn btn-outline btn-sm">Apply</button>
    </form>
</div>
