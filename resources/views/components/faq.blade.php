{{--
    Visible FAQ plus matching FAQPage structured data. Answers are escaped unless they are
    an HtmlString built from escaped parts (for answers that contain links).
--}}
@props(['faqs' => [], 'title' => 'Frequently asked questions', 'id' => 'faq'])
@if (count($faqs))
    <section {{ $attributes->merge(['class' => 'card']) }} id="{{ $id }}">
        <div class="card-head">
            <h2 class="card-title"><span class="ico"><x-icon name="message" :size="16" /></span> {{ $title }}</h2>
        </div>
        <div class="faq" style="border-top:0">
            @foreach ($faqs as [$question, $answer])
                <details @if ($loop->first) open @endif>
                    <summary><h3 class="faq-q">{{ $question }}</h3> <x-icon name="chevron-down" :size="18" /></summary>
                    <p>{{ $answer }}</p>
                </details>
            @endforeach
        </div>
    </section>

    @push('head')
        <x-jsonld :data="[
            '@context' => 'https://schema.org',
            '@type' => 'FAQPage',
            'mainEntity' => collect($faqs)->map(fn (array $faq): array => [
                '@type' => 'Question',
                'name' => (string) $faq[0],
                'acceptedAnswer' => ['@type' => 'Answer', 'text' => trim(strip_tags((string) $faq[1]))],
            ])->values()->all(),
        ]" />
    @endpush
@endif
