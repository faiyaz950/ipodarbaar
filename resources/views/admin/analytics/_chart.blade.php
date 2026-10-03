{{-- Page views per day (or per hour for a single day): one series, so no legend; hover for the numbers, table below. --}}
@php
    // Four gridline steps of a clean size (1, 2, 2.5, 5 × 10ⁿ; never fractional counts) that cover the tallest column.
    $max = max(4, collect($series)->max('views'));
    $rawStep = $max / 4;
    $magnitude = 10 ** floor(log10($rawStep));
    $step = collect($magnitude >= 10 ? [1, 2, 2.5, 5, 10] : [1, 2, 5, 10])->map(fn ($m) => $m * $magnitude)->first(fn ($v) => $v >= $rawStep);
    $niceMax = $step * 4;
    $labelEvery = (int) ceil(count($series) / 10);
@endphp
<div class="an-chart" data-an-chart role="img" aria-label="{{ $title }}">
    <div class="an-plot">
        @foreach ([1, 0.75, 0.5, 0.25, 0] as $f)
            <div class="an-grid-line" style="top: {{ (1 - $f) * 100 }}%"><span>{{ number_format($niceMax * $f) }}</span></div>
        @endforeach
        <div class="an-cols">
            @foreach ($series as $point)
                <div class="an-slot" data-tip="{{ $point['date'] }}|{{ number_format($point['views']) }}|{{ number_format($point['visitors']) }}">
                    @if ($point['views'] > 0)<i style="height: {{ max(1, round($point['views'] / $niceMax * 100, 2)) }}%"></i>@endif
                </div>
            @endforeach
        </div>
        <div class="an-tip" data-an-tip hidden></div>
    </div>
    <div class="an-x" aria-hidden="true">
        @foreach ($series as $i => $point)<span>{{ $i % $labelEvery === 0 ? $point['label'] : '' }}</span>@endforeach
    </div>
</div>
<details style="padding:0 18px 14px">
    <summary class="muted" style="font-size:12.5px;cursor:pointer">Show as table</summary>
    <div class="table-wrap" style="margin-top:8px">
        <table class="table">
            <thead><tr><th>{{ count($series) === 24 ? 'Hour' : 'Day' }}</th><th class="r">Page views</th><th class="r">Visitors</th></tr></thead>
            <tbody>
                @foreach ($series as $point)
                    <tr><td>{{ $point['date'] }}</td><td class="r">{{ number_format($point['views']) }}</td><td class="r">{{ number_format($point['visitors']) }}</td></tr>
                @endforeach
            </tbody>
        </table>
    </div>
</details>
<script>
(function () {
    var chart = document.currentScript.previousElementSibling.previousElementSibling;
    var plot = chart.querySelector('.an-plot');
    var tip = chart.querySelector('[data-an-tip]');
    chart.querySelectorAll('.an-slot').forEach(function (slot) {
        slot.addEventListener('mouseenter', function () {
            var parts = slot.getAttribute('data-tip').split('|');
            tip.innerHTML = '';
            var head = document.createElement('div');
            head.textContent = parts[0];
            head.style.opacity = '.75';
            tip.appendChild(head);
            [['Page views ', parts[1]], ['Visitors ', parts[2]]].forEach(function (line) {
                var row = document.createElement('div');
                var b = document.createElement('b');
                row.appendChild(document.createTextNode(line[0]));
                b.textContent = line[1];
                row.appendChild(b);
                tip.appendChild(row);
            });
            var box = plot.getBoundingClientRect(), r = slot.getBoundingClientRect();
            tip.style.left = Math.min(Math.max(r.left - box.left + r.width / 2, 70), box.width - 70) + 'px';
            tip.style.top = '0px';
            tip.hidden = false;
        });
        slot.addEventListener('mouseleave', function () { tip.hidden = true; });
    });
})();
</script>
