<x-mail::message>
# One tap to confirm

You asked for the **IPO Darbaar digest** ({{ $frequency }}): open and upcoming IPOs, GMP moves, allotment and listing dates, and top market news.

<x-mail::button :url="$confirmUrl">
Confirm my subscription
</x-mail::button>

This link is valid for {{ $hours }} hours. If you didn't sign up, simply ignore this email; you won't hear from us again.

{{ config('app.name') }}
</x-mail::message>
