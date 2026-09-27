@props(['compact' => false])
<form method="post" action="{{ route('subscribe') }}" {{ $attributes->merge(['class' => 'subscribe-form'.($compact ? ' compact' : '')]) }} data-subscribe novalidate>
    @csrf
    <div class="subscribe-row">
        <label class="sr-only" for="sub-email-{{ $uid = uniqid() }}">Email address</label>
        <input class="input" id="sub-email-{{ $uid }}" type="email" name="email" required maxlength="190" placeholder="you@example.com" autocomplete="email">
        <button type="submit" class="btn btn-gold btn-sm"><x-icon name="bell" :size="15" /> Subscribe</button>
    </div>
    @unless ($compact)
        <div class="subscribe-freq" role="radiogroup" aria-label="How often">
            @foreach (\App\Models\Subscriber::FREQUENCIES as $value => $label)
                <label><input type="radio" name="frequency" value="{{ $value }}" @checked($loop->first)> {{ $label }}</label>
            @endforeach
        </div>
    @endunless
    {{-- Honeypot: humans never see or fill this field. --}}
    <div class="hp" aria-hidden="true"><label>Website <input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>
    <p class="subscribe-status {{ session('subscribe_status') ? 'ok' : '' }}" data-subscribe-status role="status" aria-live="polite">{{ session('subscribe_status') }}</p>
</form>
