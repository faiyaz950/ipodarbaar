@php
    $terms = [
        'Allotment' => 'The shares actually given to an applicant after the IPO closes. In oversubscribed issues it is decided by lottery for retail investors.',
        'Anchor investor' => 'A large institution that commits to buy shares one working day before the IPO opens. Anchor shares are locked in: half for 30 days and the rest for 90 days after allotment.',
        'ASBA' => 'Application Supported by Blocked Amount: the bid amount stays in your bank account, blocked, and is debited only if you get shares.',
        'Basis of allotment' => 'The document finalising how many shares each category and applicant gets, prepared by the registrar with the exchange on T+1.',
        'Book building' => 'The price discovery process in which investors bid within a price band and the final issue price is set based on demand.',
        'bNII (big HNI)' => 'Non-institutional investors applying for more than ₹10 lakh. They get two-thirds of the NII portion.',
        'Cut-off price' => 'An option for retail, employee and shareholder bidders to accept the final issue price, whatever it is within the band. It keeps the bid valid.',
        'DRHP' => 'Draft Red Herring Prospectus: the first offer document a company files with SEBI (or the exchange, for SME IPOs) before the IPO.',
        'Face value' => 'The nominal value of a share (often ₹1, ₹2, ₹5 or ₹10), unrelated to the price at which the IPO is sold.',
        'Fresh issue' => 'New shares issued by the company. The money goes to the company for the stated objects of the issue.',
        'GMP (grey market premium)' => 'The unofficial premium at which IPO shares trade before listing. Issue price plus GMP gives an indicative listing price.',
        'Issue size' => 'The total amount raised in the IPO: fresh issue plus offer for sale.',
        'Kostak' => 'A fixed amount an unofficial grey-market dealer pays for an IPO application, regardless of allotment.',
        'Listing date' => 'The day the shares start trading on the exchange, T+3 after the issue closes. Trading starts at 10 AM after a special pre-open session.',
        'Listing gain' => 'The difference between the listing price and the issue price, usually shown as a percentage.',
        'Lock-in period' => 'A period during which certain shareholders (promoters, anchor investors, pre-IPO investors) cannot sell their shares.',
        'Lot size (market lot)' => 'The minimum number of shares you can bid for, and the multiple in which you bid. For mainboard IPOs one lot is worth ₹10,000–15,000.',
        'Mainboard IPO' => 'An IPO that lists on the main board of NSE and BSE, as opposed to the SME platforms.',
        'Market maker' => 'A broker who must quote buy and sell prices for an SME stock for three years after listing to provide liquidity.',
        'NII (non-institutional investor)' => 'Individuals, HUFs, companies and others bidding above ₹2 lakh, also called HNIs. Split into sNII and bNII.',
        'Objects of the issue' => 'What the company plans to do with the money from the fresh issue, for example repaying debt or funding expansion.',
        'OFS (offer for sale)' => 'Existing shareholders selling their shares in the IPO. This money goes to the sellers, not the company.',
        'Oversubscription' => 'When bids exceed the shares on offer. "Subscribed 40 times" means bids were 40 times the shares available in that category.',
        'Price band' => 'The range, such as ₹190–200, within which investors can bid in a book-built IPO. The upper end is the cap price.',
        'Promoter' => 'The person or group that controls the company. Promoter holding before and after the IPO is disclosed in the prospectus.',
        'QIB (qualified institutional buyer)' => 'Mutual funds, insurers, banks, foreign portfolio investors and other institutions. They get up to half of a typical IPO.',
        'Refund / unblocking' => 'Release of the blocked money for bids that did not get shares, done on T+2.',
        'Registrar (RTA)' => 'The agency (such as KFin Technologies, MUFG Intime or Bigshare) that processes applications and runs the allotment.',
        'RHP (Red Herring Prospectus)' => 'The final offer document filed before the IPO opens, with the price band and issue details. Read it before investing.',
        'RII (retail individual investor)' => 'An individual applying for up to ₹2 lakh. Retail gets at least 35% of a typical mainboard IPO.',
        'SME IPO' => 'An IPO by a small or medium enterprise that lists on NSE Emerge or BSE SME, with larger lots and different rules.',
        'sNII (small HNI)' => 'Non-institutional investors applying between ₹2 lakh and ₹10 lakh. They get one-third of the NII portion.',
        'Subject to Sauda' => 'A grey-market deal where the dealer pays for an IPO application only if it gets an allotment.',
        'Subscription status' => 'How many times each category has been bid for, updated through the subscription period.',
        'T+3 timeline' => 'SEBI\'s schedule for listing within three working days of the issue closing (T): allotment on T+1, credit and refunds on T+2, listing on T+3.',
        'UPI mandate' => 'The request you approve in your UPI app to block the bid amount. Bids count only after approval, which must happen by 5 PM on the closing day.',
    ];
@endphp

<p>IPO documents, broker apps and news are full of jargon. Here are the terms you will meet most often, in plain English, from A to Z. For step-by-step help, start with <a href="{{ route('guides.show', 'how-to-apply-for-ipo') }}">how to apply for an IPO</a>.</p>

<h2 id="terms">IPO terms A–Z</h2>
<dl>
    @foreach ($terms as $term => $definition)
        <dt id="{{ \Illuminate\Support\Str::slug($term) }}">{{ $term }}</dt>
        <dd>{{ $definition }}</dd>
    @endforeach
</dl>

@push('head')
<x-jsonld :data="[
    '@context' => 'https://schema.org',
    '@type' => 'DefinedTermSet',
    'name' => 'IPO Glossary',
    'url' => route('guides.show', 'ipo-glossary'),
    'hasDefinedTerm' => collect($terms)->map(fn (string $definition, string $term): array => [
        '@type' => 'DefinedTerm',
        'name' => $term,
        'description' => $definition,
        'url' => route('guides.show', 'ipo-glossary').'#'.\Illuminate\Support\Str::slug($term),
    ])->values()->all(),
]" />
@endpush
