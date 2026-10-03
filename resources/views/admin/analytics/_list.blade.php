{{-- A ranked list with views, visitors and a share bar. Rows may carry a path (linked) and a title. --}}
@php $listMax = max(1, collect($rows)->max('views')); @endphp
<div class="card">
    <div class="card-head"><div class="card-title">{{ $title }}</div></div>
    @if (count($rows))
        <div class="an-head"><span>{{ $column ?? 'Name' }}</span><span>Views</span><span>Visitors</span></div>
        @foreach ($rows as $row)
            <div class="an-bar-row">
                <div class="lbl">
                    @if (! empty($row['path']))
                        <b><a href="{{ route('admin.analytics.page', ['path' => $row['path'], 'range' => $range]) }}">{{ $row['title'] ?: $row['path'] }}</a></b>
                        <small class="an-path">{{ $row['path'] }}</small>
                    @else
                        <b>{{ $row['value'] !== '' ? $row['value'] : '—' }}</b>
                    @endif
                    <div class="an-share"><i style="width: {{ round($row['views'] / $listMax * 100, 1) }}%"></i></div>
                </div>
                <span class="num">{{ number_format($row['views']) }}</span>
                <span class="num muted">{{ number_format($row['visitors']) }}</span>
            </div>
        @endforeach
    @else
        <p class="muted" style="padding:14px 18px;margin:0;font-size:13.5px">No data for this range yet.</p>
    @endif
</div>
