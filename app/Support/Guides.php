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
