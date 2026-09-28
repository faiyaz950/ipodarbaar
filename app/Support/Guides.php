<?php

namespace App\Support;

/**
 * Evergreen IPO guides (the "IPO Academy"). Each guide's body lives in
 * resources/views/guides/articles/{slug}.blade.php; this registry holds its search copy.
 */
class Guides
{
    /**
     * @return array<string, array{title: string, h1: string, description: string, summary: string, icon: string, published: string, updated: string, minutes: int, faqs: array<int, array{0: string, 1: string}>, related: array<int, string>}>
     */
    public static function all(): array
    {
        return [
            'how-to-apply-for-ipo' => [
                'title' => 'How to Apply for an IPO Online (UPI & ASBA): Step-by-Step Guide',
                'h1' => 'How to Apply for an IPO Online',
                'description' => 'How to apply for an IPO in India step by step, with UPI in your broker app or through net banking (ASBA): lots, cut-off price, UPI mandate and common mistakes.',
                'summary' => 'UPI and ASBA, step by step: choosing lots, bidding at cut-off, approving the mandate and what happens after you apply.',
                'icon' => 'send',
                'published' => '2026-09-28',
                'updated' => '2026-09-28',
                'minutes' => 7,
                'faqs' => [
                    ['Can I apply for an IPO without a demat account?', 'No. Allotted shares are credited to your demat account, so you need a demat account (and usually a trading account with the same broker) before applying.'],
                    ['Can I apply for the same IPO from two accounts?', 'Only one application is allowed per PAN in a category. Multiple applications with the same PAN are rejected. Family members can apply with their own PAN and demat accounts.'],
                    ['What is the UPI limit for IPO applications?', 'Individual investors can apply through UPI for bids up to ₹5 lakh. Larger bids have to go through ASBA net banking.'],
                    ['Can I cancel or modify my IPO application?', 'Yes, you can modify or cancel a bid from your broker app or bank until the issue closes at 5 PM on the last day.'],
                ],
                'related' => ['how-ipo-allotment-works', 'how-to-check-ipo-allotment-status', 'what-is-ipo-gmp'],
            ],
            'what-is-ipo-gmp' => [
                'title' => 'What is IPO GMP (Grey Market Premium)? Meaning, Calculation & Risks',
                'h1' => 'What is IPO GMP (Grey Market Premium)?',
                'description' => 'IPO GMP explained: what grey market premium means, how to calculate expected listing price, Kostak and Subject to Sauda, and how reliable GMP really is.',
                'summary' => 'What grey market premium means, how to turn it into an expected listing price, and why it should never be your only reason to apply.',
                'icon' => 'trending-up',
                'published' => '2026-09-28',
                'updated' => '2026-09-28',
                'minutes' => 6,
                'faqs' => [
                    ['Is trading in the IPO grey market legal?', 'The grey market is an informal, unregulated market that SEBI does not recognise. Deals have no legal protection, and there is no recourse if a counterparty defaults.'],
                    ['Can GMP be negative?', 'Yes. A negative GMP means grey-market dealers expect the shares to list below the issue price.'],
                    ['When does GMP start for an IPO?', 'GMP usually starts being quoted a few days before the issue opens, once the price band is announced, and keeps changing until listing day.'],
                ],
                'related' => ['how-to-apply-for-ipo', 'ipo-listing-gains-tax', 'ipo-glossary'],
            ],
            'how-to-check-ipo-allotment-status' => [
                'title' => 'How to Check IPO Allotment Status Online by PAN (Registrar, BSE & NSE)',
                'h1' => 'How to Check IPO Allotment Status',
                'description' => 'Check IPO allotment status online by PAN or application number on the registrar website (KFintech, MUFG Intime, Bigshare), BSE and NSE, step by step.',
                'summary' => 'Three ways to check allotment by PAN, when results come out, and what "allotted" or "not allotted" means for your money.',
                'icon' => 'ticket',
                'published' => '2026-09-28',
                'updated' => '2026-09-28',
                'minutes' => 5,
                'faqs' => [
                    ['What time is IPO allotment status released?', 'Registrars usually publish the result on the evening of the allotment day (T+1), but it can come earlier or later. BSE and NSE update their pages around the same time.'],
                    ['My allotment status says "PAN not found". What should I do?', 'Check that you selected the right IPO and typed the PAN correctly. If the result has just been published, try again after some time, or check with your application number instead.'],
                    ['When will my money be unblocked if I did not get shares?', 'The amount blocked through UPI or ASBA is released on T+2, one day after the basis of allotment.'],
                ],
                'related' => ['how-ipo-allotment-works', 'how-to-apply-for-ipo', 'ipo-listing-gains-tax'],
            ],
            'how-ipo-allotment-works' => [
                'title' => 'How IPO Allotment Works: Lottery, Categories & Tips for Better Chances',
                'h1' => 'How IPO Allotment Works',
                'description' => 'How IPO shares are allotted in India: retail lottery, sNII and bNII rules, QIB quota, SME IPO allotment and practical ways to improve your chances.',
                'summary' => 'Why most retail applicants miss out in hot IPOs, how the lottery works for each category, and what actually improves your odds.',
                'icon' => 'users',
                'published' => '2026-09-28',
                'updated' => '2026-09-28',
                'minutes' => 7,
                'faqs' => [
                    ['Does applying for more lots increase my chance of allotment?', 'Not in the retail category of an oversubscribed mainboard IPO. Each successful retail applicant gets one lot through a lottery, so bidding for more lots does not improve the odds.'],
                    ['Does applying on the first day improve allotment chances?', 'No. Allotment does not depend on when you applied during the subscription period, as long as the application is valid and the mandate is approved in time.'],
                    ['What is the shareholder quota?', 'Some IPOs of subsidiaries reserve a portion for shareholders of the listed parent company. If you hold the parent\'s shares on the record date, you can apply in that category as well as in the retail category.'],
                ],
                'related' => ['how-to-check-ipo-allotment-status', 'how-to-apply-for-ipo', 'sme-ipo-vs-mainboard-ipo'],
            ],
            'sme-ipo-vs-mainboard-ipo' => [
                'title' => 'SME IPO vs Mainboard IPO: Key Differences, Rules & Risks',
                'h1' => 'SME IPO vs Mainboard IPO',
                'description' => 'SME IPO vs mainboard IPO in India: exchange platform, minimum investment, market maker, SEBI rules, liquidity and the risks to know before applying.',
                'summary' => 'Where each type lists, how much you need to apply, who regulates them and why SME IPOs carry extra risk.',
                'icon' => 'columns',
                'published' => '2026-09-28',
                'updated' => '2026-09-28',
                'minutes' => 6,
                'faqs' => [
                    ['Are SME IPOs riskier than mainboard IPOs?', 'Generally yes. SME companies are smaller, disclosures are lighter, shares trade in large lots and liquidity can be thin, which makes prices more volatile.'],
                    ['Can an SME-listed company move to the main board?', 'Yes. Once it meets the exchange\'s migration criteria, an SME-listed company can migrate to the main board.'],
                ],
                'related' => ['how-ipo-allotment-works', 'what-is-ipo-gmp', 'ipo-glossary'],
            ],
            'ipo-listing-gains-tax' => [
                'title' => 'Tax on IPO Listing Gains in India: STCG, LTCG & Worked Example',
                'h1' => 'Tax on IPO Listing Gains',
                'description' => 'How IPO listing gains are taxed in India: STCG at 20%, LTCG at 12.5% above ₹1.25 lakh, set-off of losses, which ITR to file, with a worked example.',
                'summary' => 'Short-term vs long-term capital gains, the current rates, a worked example and when IPO flipping becomes business income.',
                'icon' => 'scale',
                'published' => '2026-09-28',
                'updated' => '2026-09-28',
                'minutes' => 6,
                'faqs' => [
                    ['Is there tax if I sell IPO shares on the listing day?', 'Yes. Shares sold within 12 months are short-term capital assets, so listing-day gains are taxed as short-term capital gains at 20% plus cess.'],
                    ['Can I set off an IPO loss against other gains?', 'A short-term capital loss can be set off against both short-term and long-term capital gains, and any balance can be carried forward for eight years if you file your return on time.'],
                    ['Which ITR form should I file for IPO gains?', 'Individuals with capital gains but no business income generally file ITR-2. If you trade frequently and treat it as a business, ITR-3 applies.'],
                ],
                'related' => ['what-is-ipo-gmp', 'how-to-apply-for-ipo', 'ipo-glossary'],
            ],
            'nri-ipo-investment' => [
                'title' => 'How NRIs Can Apply for IPOs in India: Accounts, Rules & Tax',
                'h1' => 'How NRIs Can Apply for IPOs in India',
                'description' => 'Can NRIs invest in Indian IPOs? Yes: NRE and NRO accounts, NRI demat, PIS, how to apply through ASBA, allotment categories, tax, TDS and repatriation.',
                'summary' => 'The accounts an NRI needs, how to apply through ASBA, which category to bid in, and how tax and repatriation work.',
                'icon' => 'users',
                'published' => '2026-09-28',
                'updated' => '2026-09-28',
                'minutes' => 7,
                'faqs' => [
                    ['Can NRIs apply for IPOs in India?', 'Yes. NRIs can apply for Indian IPOs using an NRE or NRO bank account and an NRI demat and trading account, and they bid in the same retail and NII categories as resident investors.'],
                    ['Do NRIs need a PIS account to apply for an IPO?', 'Usually not to apply, but a PIS permission is generally needed to sell shares on the stock exchange on a repatriable basis. Requirements differ between banks and brokers, so check with yours before applying.'],
                    ['Can NRIs use UPI to apply for an IPO?', 'Some Indian banks let NRIs use UPI on NRE or NRO accounts, including with certain international mobile numbers. If your bank does not, apply through ASBA in net banking.'],
                    ['Is tax deducted when an NRI sells IPO shares?', 'Yes. Tax on capital gains is deducted at source (TDS) when an NRI sells shares. The rates are the same as for residents, and a lower rate may apply under a tax treaty (DTAA) if you have a Tax Residency Certificate.'],
                ],
                'related' => ['how-to-apply-for-ipo', 'ipo-listing-gains-tax', 'how-ipo-allotment-works'],
            ],
            'ipo-refund-status' => [
                'title' => 'IPO Refund Status: When Is Blocked Money Released?',
                'h1' => 'IPO Refund Status: When Do You Get Your Money Back?',
                'description' => 'When is IPO money released if you don\'t get an allotment? The T+2 unblocking timeline, how to check it in your bank or UPI app, and what to do if it is late.',
                'summary' => 'When blocked IPO money is released, how to check it in your bank or UPI app, and who to contact if it is late.',
                'icon' => 'wallet',
                'published' => '2026-09-29',
                'updated' => '2026-09-29',
                'minutes' => 5,
                'faqs' => [
                    ['When will I get my IPO money back if I am not allotted?', 'Under SEBI\'s T+3 timeline the blocked amount is released on T+2, two working days after the IPO closes. It often happens on the evening of the allotment day itself.'],
                    ['Is IPO money debited from my account when I apply?', 'No. With UPI or ASBA the bid amount is only blocked. It is debited if you get shares and released if you do not.'],
                    ['What if my IPO money is not released on time?', 'Contact your bank first, then the IPO\'s registrar with your application number. If the problem is not solved, file a complaint on SEBI\'s SCORES portal.'],
                ],
                'related' => ['how-to-check-ipo-allotment-status', 'how-ipo-allotment-works', 'how-to-apply-for-ipo'],
            ],
            'how-to-sell-ipo-shares-on-listing-day' => [
                'title' => 'How to Sell IPO Shares on Listing Day: Timings & Steps',
                'h1' => 'How to Sell IPO Shares on Listing Day',
                'description' => 'Sell IPO shares on listing day step by step: when shares reach your demat, the 9 to 10 AM pre-open session, limit orders, TPIN or DDPI, SME lots and tax.',
                'summary' => 'When allotted shares reach your demat, how the listing-day pre-open session works and how to place the sell order.',
                'icon' => 'rocket',
                'published' => '2026-09-29',
                'updated' => '2026-09-29',
                'minutes' => 6,
                'faqs' => [
                    ['What time can I sell IPO shares on listing day?', 'You can place a sell order during the special pre-open session from 9:00 to 9:45 AM, or once normal trading starts at 10:00 AM.'],
                    ['Why can\'t I sell my IPO shares?', 'Usually because the sale needs authorisation: approve it with your CDSL TPIN or NSDL OTP (eDIS), or give your broker a DDPI once. For SME IPOs, you must also sell in multiples of the lot size.'],
                    ['Do I pay tax if I sell IPO shares on listing day?', 'Yes. The gain is a short-term capital gain, taxed at 20% plus cess.'],
                ],
                'related' => ['ipo-listing-gains-tax', 'how-to-check-ipo-allotment-status', 'what-is-ipo-gmp'],
            ],
            'what-is-pre-apply-ipo' => [
                'title' => 'What Is Pre-Apply in IPO? How It Works & UPI Mandate',
                'h1' => 'What Is Pre-Apply in an IPO?',
                'description' => 'Pre-apply lets you bid for an IPO before it opens. How it works, when the UPI mandate arrives, and whether pre-applying improves your allotment chances.',
                'summary' => 'How brokers hold your bid until the issue opens, when to approve the UPI mandate, and why it does not change your allotment odds.',
                'icon' => 'send',
                'published' => '2026-09-29',
                'updated' => '2026-09-29',
                'minutes' => 4,
                'faqs' => [
                    ['Does pre-applying for an IPO increase allotment chances?', 'No. Retail allotment in an oversubscribed IPO is decided by lottery among all valid applications, not by who applied first.'],
                    ['When will I get the UPI mandate for a pre-applied IPO?', 'After the broker sends your bid to the exchange, usually on the day the issue opens. Approve it before 5 PM on the closing day.'],
                    ['Can I cancel a pre-applied IPO bid?', 'Yes. You can modify or cancel it from your broker app until the issue closes.'],
                ],
                'related' => ['how-to-apply-for-ipo', 'ipo-cut-off-price', 'how-ipo-allotment-works'],
            ],
            'ipo-cut-off-price' => [
                'title' => 'What Is Cut-Off Price in IPO? Meaning, Rules & Refund',
                'h1' => 'What Is the Cut-Off Price in an IPO?',
                'description' => 'Cut-off price in IPO explained: what ticking cut-off means, who can bid at cut-off, why the upper band is blocked and what happens if the final price is lower.',
                'summary' => 'What bidding at cut-off means, who is allowed to do it and how the blocked amount works.',
                'icon' => 'target',
                'published' => '2026-09-29',
                'updated' => '2026-09-29',
                'minutes' => 4,
                'faqs' => [
                    ['Who can bid at the cut-off price in an IPO?', 'Retail individual investors (bids up to ₹2 lakh), and eligible employees and shareholders in their reserved quotas. Non-institutional and institutional investors cannot bid at cut-off.'],
                    ['Why is the upper price band amount blocked when I bid at cut-off?', 'Because the final price is not known yet. If it is set below the upper band, the difference is released after allotment.'],
                    ['What happens if I bid below the final issue price?', 'The bid is rejected. Bidding at cut-off avoids this.'],
                ],
                'related' => ['how-to-apply-for-ipo', 'how-ipo-allotment-works', 'what-is-pre-apply-ipo'],
            ],
            'drhp-vs-rhp' => [
                'title' => 'DRHP vs RHP vs Prospectus: IPO Documents Explained',
                'h1' => 'DRHP vs RHP vs Prospectus',
                'description' => 'DRHP, UDRHP, RHP and prospectus explained: who files them, when, what each contains, where to find them and which sections to read before an IPO.',
                'summary' => 'The three offer documents of an IPO, how they differ and which parts are worth reading before you apply.',
                'icon' => 'file-text',
                'published' => '2026-09-29',
                'updated' => '2026-09-29',
                'minutes' => 6,
                'faqs' => [
                    ['What is the difference between DRHP and RHP?', 'The DRHP is the draft filed for review before the IPO. The RHP is the final document filed with the Registrar of Companies just before the issue opens; it has everything except the final price and exact issue size.'],
                    ['Why is it called a red herring prospectus?', 'Because it does not contain the final issue price or the number of shares. That is why the cover carries a red-ink notice that the details are incomplete.'],
                    ['Where can I read the RHP of an IPO?', 'On the SEBI website under public issue filings, and on the websites of the stock exchanges, the company and its lead managers. IPO pages on IPO Darbaar link to it when available.'],
                ],
                'related' => ['anchor-investor-lock-in', 'ipo-glossary', 'sme-ipo-vs-mainboard-ipo'],
            ],
            'anchor-investor-lock-in' => [
                'title' => 'Anchor Investors in IPO: Allotment & Lock-in Period',
                'h1' => 'Anchor Investors and Their Lock-in Period',
                'description' => 'Who anchor investors are, how much of an IPO they can take, the anchor price, and the 30-day and 90-day lock-in periods, with what lock-in expiry means.',
                'summary' => 'Who anchor investors are, how their shares are allotted and when their 30- and 90-day lock-ins end.',
                'icon' => 'landmark',
                'published' => '2026-09-29',
                'updated' => '2026-09-29',
                'minutes' => 5,
                'faqs' => [
                    ['What is the lock-in period for anchor investors?', 'Half of the shares allotted to anchor investors are locked in for 30 days from the date of allotment and the other half for 90 days.'],
                    ['When are anchor investors allotted shares?', 'One working day before the IPO opens for the public.'],
                    ['Does the share price fall when the anchor lock-in ends?', 'Not necessarily. More shares become free to trade, which can add selling pressure, but the price also depends on the company\'s results and the market.'],
                ],
                'related' => ['drhp-vs-rhp', 'how-ipo-allotment-works', 'ipo-glossary'],
            ],
            'ipo-glossary' => [
                'title' => 'IPO Glossary: 35+ IPO Terms Explained in Simple Words',
                'h1' => 'IPO Glossary',
                'description' => 'IPO terms explained simply: anchor investor, ASBA, basis of allotment, cut-off price, DRHP, RHP, GMP, Kostak, lot size, OFS, QIB, NII, RII and more.',
                'summary' => 'Plain-English meanings of the 35+ terms you meet in IPO documents, apps and news.',
                'icon' => 'file-text',
                'published' => '2026-09-28',
                'updated' => '2026-09-28',
                'minutes' => 8,
                'faqs' => [],
                'related' => ['how-to-apply-for-ipo', 'what-is-ipo-gmp', 'how-ipo-allotment-works'],
            ],
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function find(string $slug): ?array
    {
        $all = self::all();

        return isset($all[$slug]) ? ['slug' => $slug] + $all[$slug] : null;
    }
}
