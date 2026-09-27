@props(['ipo', 'results', 'mine' => null])
@php
    $open = $ipo->pollIsOpen();
    $total = $results['total'];
    $emoji = ['apply' => '👍', 'skip' => '👎', 'unsure' => '🤔'];
@endphp
<div {{ $attributes->merge(['class' => 'card poll'.($mine || ! $open ? ' voted' : '')]) }}
     data-poll data-url="{{ route('ipos.vote', $ipo) }}" data-ipo="{{ $ipo->slug }}">
    <div class="card-head">
        <div>
            <div class="card-title"><span class="ico"><x-icon name="users" :size="16" /></span> {{ $open ? 'Will you apply for '.$ipo->name.' IPO?' : 'Final visitor sentiment' }}</div>
            <div class="card-sub">Visitor opinion poll, not investment advice.</div>
        </div>
        <span class="muted poll-total" data-poll-total>{{ number_format($total) }} {{ $total === 1 ? 'vote' : 'votes' }}</span>
    </div>
    <div class="poll-options">
        @foreach (\App\Models\IpoVote::CHOICES as $key => $label)
            @php $pct = $total ? (int) round($results['counts'][$key] * 100 / $total) : 0; @endphp
            <div class="poll-row {{ $mine === $key ? 'mine' : '' }}" data-poll-choice="{{ $key }}">
                @if ($open)
                    <button type="button" class="poll-btn" data-poll-vote="{{ $key }}">
                        <span class="poll-label"><span aria-hidden="true">{{ $emoji[$key] }}</span> {{ $label }}</span>
                        <span class="poll-bar"><i data-poll-bar style="width: {{ $pct }}%"></i></span>
                        <b data-poll-pct>{{ $pct }}%</b>
                    </button>
                @else
                    <div class="poll-btn">
                        <span class="poll-label"><span aria-hidden="true">{{ $emoji[$key] }}</span> {{ $label }}</span>
                        <span class="poll-bar"><i data-poll-bar style="width: {{ $pct }}%"></i></span>
                        <b data-poll-pct>{{ $pct }}%</b>
                    </div>
                @endif
            </div>
        @endforeach
    </div>
</div>
