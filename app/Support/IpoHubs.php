<?php

namespace App\Support;

/**
 * Keyword landing pages for IPO lists ("upcoming IPO", "SME IPO", "IPO allotment status"...).
 * Each hub has a clean URL, a fixed filter and its own search-focused copy. Copy may use
 * the placeholders {month}, {year}, {count} and {date}, filled in by fill().
 */
class IpoHubs
{
    /**
     * @return array<string, array{path: string, status: string|null, type: string|null, label: string, title: string, h1: string, description: string, lead: string, about: array<int, array{0: string, 1: string}>, faqs: array<int, array{0: string, 1: string}>}>
     */
    public static function all(): array
    {
        return [
            'upcoming' => [
                'path' => 'upcoming-ipo',
                'status' => 'upcoming',
                'type' => null,
                'label' => 'Upcoming IPOs',
                'title' => 'Upcoming IPO {month} {year}: Dates, Price Band & GMP',
                'h1' => 'Upcoming IPOs in {month} {year}',
                'description' => 'Upcoming IPO list for {month} {year}: {count} mainboard and SME IPOs with open and close dates, price band, lot size, issue size and latest GMP. Updated {date}.',
                'lead' => 'All upcoming mainboard and SME IPOs in India with their subscription dates, price band, issue size and grey market premium, updated through the day.',
                'about' => [
                    ['What counts as an upcoming IPO?', 'An upcoming IPO is a public issue whose subscription window has not opened yet. Dates and the price band are usually announced a few days before the issue opens, after the company files its Red Herring Prospectus (RHP) with the Registrar of Companies. Until then the listing shows "Dates awaited".'],
                    ['How to use this list', 'IPOs are sorted by opening date, so the next issues to open are at the top. Open any company for its full timeline (allotment, refund, demat credit and listing dates), lot size, minimum investment for retail and HNI investors, and the GMP trend. Tap the star to add an IPO to your watchlist and get its dates in one place.'],
                ],
                'faqs' => [
                    ['Which IPOs are opening this week?', 'The list above is sorted by opening date: the first rows are the IPOs opening soonest. The IPO calendar also shows every opening, closing and listing date for the month.'],
                    ['How do I apply for an upcoming IPO?', 'Once the issue opens you can apply through your broker app or net banking using UPI or ASBA. Choose the number of lots, bid at the cut-off price and approve the UPI mandate before 5 PM on the closing day.'],
                    ['Is GMP available for upcoming IPOs?', 'Grey market premium usually appears a few days before an issue opens. It is unofficial and changes quickly, so treat it as a sentiment indicator only.'],
                ],
            ],
            'current' => [
                'path' => 'current-ipo',
                'status' => 'open',
                'type' => null,
                'label' => 'Current IPOs',
                'title' => 'Current IPO Open Today: Live GMP & Subscription',
                'h1' => 'Current IPOs Open for Subscription',
                'description' => '{count} IPOs open today ({date}): live GMP, price band, lot size, closing date and subscription status of every current mainboard and SME IPO in India.',
                'lead' => 'Every IPO you can apply for right now, sorted by closing date so the ones closing today come first.',
                'about' => [
                    ['Applying before the issue closes', 'Applications are accepted until 5 PM on the closing day and the UPI mandate must be approved by then. If you apply through UPI, check your UPI app for the mandate request right after placing the bid.'],
                    ['Reading the GMP column', 'GMP is the premium grey-market dealers are quoting over the upper price band. Adding it to the issue price gives an indicative listing price, but the actual listing is decided by demand on listing day.'],
                ],
                'faqs' => [
                    ['Which IPO is open today?', 'The table above lists every IPO open for subscription today, with its closing date and live GMP. IPOs closing today are marked in red.'],
                    ['What time does an IPO close?', 'IPO bidding closes at 5 PM on the last day. Retail investors should also approve the UPI mandate before that time.'],
                    ['When will I know if I got an allotment?', 'Under the T+3 timeline the basis of allotment is finalised one working day after the issue closes. Check the IPO allotment status page for the registrar link.'],
                ],
            ],
            'allotment' => [
                'path' => 'ipo-allotment-status',
                'status' => 'closed',
                'type' => null,
                'label' => 'IPO Allotment Status',
                'title' => 'IPO Allotment Status: Check Online by PAN',
                'h1' => 'IPO Allotment Status',
                'description' => 'Check IPO allotment status online for {count} recent IPOs: allotment date, registrar link (KFintech, MUFG Intime, Bigshare), BSE & NSE status pages and listing date.',
                'lead' => 'Recently closed IPOs with their allotment date, the registrar that handles the allotment and direct links to check your status by PAN or application number.',
                'about' => [
                    ['How to check IPO allotment status', 'Open the registrar\'s allotment page (linked in the table), choose the IPO, and enter your PAN, application number or DP/client ID. You can also check on the BSE or NSE websites. The result is available once the basis of allotment is finalised, usually one working day after the issue closes.'],
                    ['Allotted or not: what happens next', 'If you are allotted, the shares are credited to your demat account one day before listing and the blocked amount for unallotted lots is released. If not, the full amount blocked through UPI or ASBA is unblocked automatically.'],
                ],
                'faqs' => [
                    ['When is the IPO allotment date?', 'Under SEBI\'s T+3 timeline the basis of allotment is finalised on T+1, one working day after the IPO closes. The table shows the expected date for each IPO.'],
                    ['Can I check allotment status by PAN?', 'Yes. Every registrar lets you search by PAN, and BSE and NSE also offer PAN-based allotment status for the IPOs listing on them.'],
                    ['Why was I not allotted shares?', 'When an IPO is oversubscribed, retail allotment is done by lottery with one lot per successful application, so most applicants do not get shares. Applying from different family PAN accounts improves the overall odds.'],
                ],
            ],
            'listed' => [
                'path' => 'recently-listed-ipo',
                'status' => 'listed',
                'type' => null,
                'label' => 'Recently Listed IPOs',
                'title' => 'Recently Listed IPOs {year}: Listing Price & Gain',
                'h1' => 'Recently Listed IPOs',
                'description' => 'Recently listed IPOs in India with listing date, issue price, listing price, listing gain and last GMP for mainboard and SME issues. Updated {date}.',
                'lead' => 'IPOs that have already listed on NSE and BSE, newest first, with their listing date and how they performed against the issue price.',
                'about' => [
                    ['Listing gain explained', 'Listing gain is the difference between the price at which the shares open on listing day and the issue price, shown as a percentage. It tells you how an IPO performed on debut, not how it will perform later.'],
                ],
                'faqs' => [
                    ['What time do IPO shares list?', 'Mainboard IPOs start trading at 10 AM on the listing day after a pre-open session from 9 AM to 9:45 AM (SME IPOs follow the same pre-open window on NSE Emerge and BSE SME).'],
                    ['Is the listing gain taxable?', 'Yes. Shares sold within 12 months are taxed as short-term capital gains at 20% (plus cess). See the capital gains calculator for your exact tax.'],
                ],
            ],
            'sme' => [
                'path' => 'sme-ipo',
                'status' => null,
                'type' => 'sme',
                'label' => 'SME IPOs',
                'title' => 'SME IPO {year}: Upcoming & Current SME IPO GMP',
                'h1' => 'SME IPOs {year}',
                'description' => 'SME IPO list {year}: upcoming, open and recently listed SME IPOs on NSE Emerge and BSE SME with price band, lot size, issue size, GMP and listing dates.',
                'lead' => 'All SME IPOs on NSE Emerge and BSE SME, with live status, price band, issue size and grey market premium.',
                'about' => [
                    ['How SME IPOs differ', 'SME IPOs are smaller issues by small and medium enterprises that list on the SME platforms of NSE (Emerge) and BSE. Lot sizes are larger, so the minimum application is higher than for mainboard IPOs: individual investors apply for a minimum of two lots.'],
                    ['Risks to keep in mind', 'SME shares can be less liquid and more volatile, and trade in lots through a market maker. Read the prospectus carefully and treat GMP as an indicator only.'],
                ],
                'faqs' => [
                    ['What is the minimum investment in an SME IPO?', 'Individual investors must apply for at least two lots in an SME IPO, which usually works out to more than ₹2 lakh. The exact amount is shown on each IPO page.'],
                    ['Where do SME IPOs list?', 'SME IPOs list on NSE Emerge or BSE SME, the dedicated SME platforms of the two exchanges.'],
                    ['Can SME IPOs move to the main board?', 'Yes. After meeting eligibility conditions an SME-listed company can migrate to the main board of the exchange.'],
                ],
            ],
            'mainboard' => [
                'path' => 'mainboard-ipo',
                'status' => null,
                'type' => 'mainboard',
                'label' => 'Mainboard IPOs',
                'title' => 'Mainboard IPO {year}: Upcoming & Current IPO List',
                'h1' => 'Mainboard IPOs {year}',
                'description' => 'Mainboard IPO list {year}: upcoming, open and listed IPOs on NSE and BSE with price band, lot size, issue size, GMP, subscription and listing dates.',
                'lead' => 'Every mainboard IPO listing on NSE and BSE, with live status, price band, issue size and grey market premium.',
                'about' => [
                    ['Applying in a mainboard IPO', 'Retail investors can apply for up to ₹2 lakh. Bids above ₹2 lakh fall in the non-institutional (HNI) category. In oversubscribed issues each retail allottee receives at least one lot, decided by lottery.'],
                ],
                'faqs' => [
                    ['What is the minimum investment in a mainboard IPO?', 'The minimum is one lot at the upper price band; SEBI requires this to be between ₹10,000 and ₹15,000. Each IPO page shows the exact amount along with the retail maximum.'],
                    ['What is the difference between mainboard and SME IPOs?', 'Mainboard IPOs are larger issues listing on the main NSE and BSE boards with lower minimum investment. SME IPOs list on NSE Emerge or BSE SME and require a minimum of two lots.'],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function find(string $key): array
    {
        return self::all()[$key] ?? throw new \InvalidArgumentException("Unknown IPO hub [{$key}]");
    }

    /**
     * Hub that lists IPOs in a given status (null for the full IPO list).
     */
    public static function keyForStatus(?string $status): ?string
    {
        return match ($status) {
            'open' => 'current',
            'upcoming' => 'upcoming',
            'closed' => 'allotment',
            'listed' => 'listed',
            default => null,
        };
    }

    public static function routeForStatus(?string $status): string
    {
        $key = self::keyForStatus($status);

        return $key ? 'ipos.'.$key : 'ipos.index';
    }

    public static function urlForStatus(?string $status): string
    {
        return route(self::routeForStatus($status));
    }

    /**
     * @param  array<string, string|int>  $values
     */
    public static function fill(string $text, array $values = []): string
    {
        $values += [
            'month' => now()->format('F'),
            'year' => now()->format('Y'),
            'date' => now()->format('j M Y'),
        ];

        return strtr($text, collect($values)->mapWithKeys(fn ($value, $key): array => ['{'.$key.'}' => (string) $value])->all());
    }
}
