@if ($ipos->isEmpty())
    <div class="card empty">
        <div class="table-empty">
            <div class="ico"><x-icon name="star" :size="22" /></div>
            <h3>No IPOs in your watchlist yet</h3>
            <p>Star the IPOs you're interested in to see their key dates in one place.</p>
        </div>
    </div>
@else
    <div class="stack">
        @if ($agenda->isNotEmpty())
            <div class="card">
                <div class="card-head">
                    <div class="card-title"><span class="ico"><x-icon name="bell" :size="16" /></span> Coming up in the next 14 days</div>
                </div>
                @foreach ($agenda as $event)
                    <a class="admin-row" href="{{ $event['ipo']->url() }}">
                        <span>
                            <b>{{ $event['ipo']->name }}</b>
                            <small class="muted">{{ $event['label'] }}{{ $event['tentative'] ? ' (tentative)' : '' }}</small>
                        </span>
                        <span class="badge {{ $event['date']->isToday() ? 'b-open' : 'b-plain' }}">{{ $event['date']->isToday() ? 'Today' : ($event['date']->isTomorrow() ? 'Tomorrow' : $event['date']->format('D, j M')) }}</span>
                    </a>
                @endforeach
            </div>
        @endif

        <div class="card">
            <div class="card-head">
                <div class="card-title"><span class="ico"><x-icon name="star" :size="16" /></span> Watching {{ $ipos->count() }} {{ $ipos->count() === 1 ? 'IPO' : 'IPOs' }}</div>
                @if ($ipos->count() >= 2)
                    <a class="link-gold" href="{{ route('compare', ['ipos' => $ipos->take(3)->pluck('slug')->implode(',')]) }}"><x-icon name="columns" :size="14" /> Compare {{ min(3, $ipos->count()) }}</a>
                @endif
            </div>
            <x-ipo-table :ipos="$ipos" />
        </div>
    </div>
@endif
