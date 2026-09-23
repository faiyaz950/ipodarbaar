<?php

namespace App\Support;

/**
 * Registry of calculators. Maths lives in public/assets/js/calculators.js,
 * keyed by the same slug; this class holds the page copy and navigation.
 */
class Calculators
{
    public const GROUPS = [
        'ipo' => 'IPO Tools',
        'invest' => 'Investing & Returns',
        'savings' => 'Savings & Loans',
        'trading' => 'Trading & Tax',
    ];

    public static function all(): array
    {
        return [
            'ipo-gmp' => [
                'name' => 'IPO GMP Calculator',
                'short' => 'Estimate listing price and profit per lot from the grey market premium.',
                'icon' => 'trending-up',
                'group' => 'ipo',
                'about' => 'The Grey Market Premium (GMP) is the premium at which IPO shares trade unofficially before listing. Add it to the issue price to get an indicative listing price, and multiply by lot size to see the expected gain per lot.',
                'formula' => 'Expected listing price = Issue price + GMP · Gain per lot = GMP × Lot size × Lots',
                'faqs' => [
                    ['Is GMP a guarantee of listing gains?', 'No. GMP is an unofficial, unregulated indicator that can change quickly. Actual listing prices are decided by demand on listing day.'],
                    ['Why can GMP be negative?', 'A negative GMP means grey-market participants expect the stock to list below its issue price.'],
                ],
            ],
            'ipo-profit' => [
                'name' => 'IPO Listing Profit Calculator',
                'short' => 'Work out your actual profit or loss after selling allotted IPO shares.',
                'icon' => 'rocket',
                'group' => 'ipo',
                'about' => 'Enter the issue price, the price you sold at and how many lots were allotted to see your absolute profit, return percentage and total sale value.',
                'formula' => 'Profit = (Sell price − Issue price) × Lot size × Lots allotted',
                'faqs' => [
                    ['Are listing gains taxable?', 'Yes. Shares sold within 12 months are taxed as short-term capital gains (currently 20% for listed equity, plus cess). Use the Capital Gains calculator for details.'],
                ],
            ],
            'ipo-application' => [
                'name' => 'IPO Application Amount Calculator',
                'short' => 'Find the amount to block and which investor category your bid falls in.',
                'icon' => 'wallet',
                'group' => 'ipo',
                'about' => 'Your application amount is the upper price band multiplied by the shares you bid for. Bids up to ₹2 lakh fall in the retail category, ₹2–10 lakh in small HNI (S-HNI) and above ₹10 lakh in big HNI (B-HNI).',
                'formula' => 'Amount = Upper price band × Lot size × Lots',
                'faqs' => [
                    ['Why bid at the cut-off price?', 'Retail investors can bid at cut-off, meaning you agree to buy at whatever final price is set within the band. The upper band amount is blocked in your bank account via UPI/ASBA.'],
                ],
            ],
            'ipo-allotment-chance' => [
                'name' => 'IPO Allotment Chance Calculator',
                'short' => 'Estimate your odds of getting an allotment based on subscription.',
                'icon' => 'ticket',
                'group' => 'ipo',
                'about' => 'For oversubscribed mainboard IPOs, retail allotment is done by lottery, with one lot per winning application. Your approximate chance per application is 1 ÷ retail subscription. Applying from more family PAN accounts improves the chance that at least one gets allotted.',
                'formula' => 'Chance per application ≈ 1 ÷ Subscription (×) · Chance of ≥1 allotment = 1 − (1 − p)ⁿ',
                'faqs' => [
                    ['Does applying for more lots help retail investors?', 'In an oversubscribed mainboard IPO, no — each retail application is treated as one entry in the lottery for a single lot.'],
                ],
            ],
            'sip' => [
                'name' => 'SIP Calculator',
                'short' => 'See how monthly investments grow, with optional yearly step-up.',
                'icon' => 'repeat',
                'group' => 'invest',
                'about' => 'A Systematic Investment Plan (SIP) invests a fixed amount every month. Compounding on regular contributions can build a large corpus over time. Add an annual step-up to model increasing your SIP as your income grows.',
                'formula' => 'FV = P × [((1 + i)ⁿ − 1) ÷ i] × (1 + i), where i = annual rate ÷ 12 and n = months',
                'faqs' => [
                    ['Are SIP returns guaranteed?', 'No. Mutual fund returns depend on market performance. The calculator assumes a constant rate for illustration.'],
                ],
            ],
            'lumpsum' => [
                'name' => 'Lumpsum Calculator',
                'short' => 'Future value of a one-time investment at an expected return.',
                'icon' => 'banknote',
                'group' => 'invest',
                'about' => 'A lumpsum investment is a one-time amount left to compound. The calculator shows what it could be worth after a number of years at a constant annual return.',
                'formula' => 'FV = P × (1 + r)ᵗ',
                'faqs' => [],
            ],
            'swp' => [
                'name' => 'SWP Calculator',
                'short' => 'Plan regular monthly withdrawals from an invested corpus.',
                'icon' => 'arrow-down-right',
                'group' => 'invest',
                'about' => 'A Systematic Withdrawal Plan (SWP) lets you withdraw a fixed amount every month while the remaining corpus stays invested. See how long your money lasts and what is left at the end.',
                'formula' => 'Each month: Balance = Balance × (1 + r/12) − Withdrawal',
                'faqs' => [],
            ],
            'cagr' => [
                'name' => 'CAGR Calculator',
                'short' => 'Annualised growth rate between a starting and ending value.',
                'icon' => 'bar-chart',
                'group' => 'invest',
                'about' => 'Compound Annual Growth Rate (CAGR) smooths returns into a single yearly rate, making it easy to compare investments held for different periods.',
                'formula' => 'CAGR = (Final value ÷ Initial value)^(1 ÷ years) − 1',
                'faqs' => [],
            ],
            'stock-average' => [
                'name' => 'Stock Average Calculator',
                'short' => 'Your new average buy price after averaging up or down.',
                'icon' => 'layers',
                'group' => 'invest',
                'about' => 'When you buy more of a stock you already hold, your average cost changes. Enter both purchases to see the new average price and total investment.',
                'formula' => 'Average = (Q₁ × P₁ + Q₂ × P₂) ÷ (Q₁ + Q₂)',
                'faqs' => [],
            ],
            'inflation' => [
                'name' => 'Inflation Calculator',
                'short' => 'What today’s expenses will cost in the future.',
                'icon' => 'flame',
                'group' => 'invest',
                'about' => 'Inflation erodes purchasing power over time. Use this to estimate the future cost of a goal, or how much today’s money will be worth later.',
                'formula' => 'Future cost = Current cost × (1 + inflation)ᵗ',
                'faqs' => [],
            ],
            'fd' => [
                'name' => 'FD Calculator',
                'short' => 'Maturity value and interest earned on a fixed deposit.',
                'icon' => 'landmark',
                'group' => 'savings',
                'about' => 'Fixed deposits pay a fixed rate for a chosen tenure. Most Indian banks compound interest quarterly. Choose the compounding frequency your bank uses.',
                'formula' => 'A = P × (1 + r/n)^(n × t)',
                'faqs' => [],
            ],
            'rd' => [
                'name' => 'RD Calculator',
                'short' => 'Maturity value of a monthly recurring deposit.',
                'icon' => 'calendar',
                'group' => 'savings',
                'about' => 'A Recurring Deposit (RD) lets you deposit a fixed sum every month. Banks typically compound RD interest quarterly; this calculator follows that convention.',
                'formula' => 'M = Σ R × (1 + r/4)^(months remaining ÷ 3)',
                'faqs' => [],
            ],
            'ppf' => [
                'name' => 'PPF Calculator',
                'short' => 'Tax-free maturity value of your Public Provident Fund.',
                'icon' => 'shield',
                'group' => 'savings',
                'about' => 'The Public Provident Fund is a government-backed, tax-free savings scheme with a 15-year lock-in, extendable in blocks of 5 years. Deposits of ₹500 to ₹1.5 lakh a year qualify for Section 80C (old regime).',
                'formula' => 'Each year: Balance = (Balance + Deposit) × (1 + r)',
                'faqs' => [
                    ['What is the current PPF rate?', 'The government revises the rate quarterly. Update the rate field to the latest notified rate.'],
                ],
            ],
            'emi' => [
                'name' => 'EMI Calculator',
                'short' => 'Monthly EMI, total interest and payment for any loan.',
                'icon' => 'home',
                'group' => 'savings',
                'about' => 'Equated Monthly Instalments (EMIs) repay both principal and interest over the loan tenure. Use it for home, car, personal or education loans.',
                'formula' => 'EMI = P × r × (1 + r)ⁿ ÷ ((1 + r)ⁿ − 1), where r = monthly rate',
                'faqs' => [],
            ],
            'brokerage' => [
                'name' => 'Brokerage Calculator',
                'short' => 'All-in charges and net P&L for equity delivery or intraday trades.',
                'icon' => 'receipt',
                'group' => 'trading',
                'about' => 'Every equity trade attracts brokerage plus statutory charges: STT, exchange transaction charges, SEBI fees, stamp duty and GST. This calculator uses commonly applicable rates to show your breakeven and net profit.',
                'formula' => 'Net P&L = (Sell − Buy) × Qty − (Brokerage + STT + Exchange + SEBI + Stamp + GST + DP)',
                'faqs' => [
                    ['Are these charges exact?', 'Rates are indicative and can differ by broker and exchange revisions. Check your broker’s contract note for exact figures.'],
                ],
            ],
            'capital-gains' => [
                'name' => 'Capital Gains Tax Calculator',
                'short' => 'STCG / LTCG tax on listed shares and equity funds.',
                'icon' => 'scale',
                'group' => 'trading',
                'about' => 'For listed equity shares and equity mutual funds, gains on holdings up to 12 months are short-term (STCG, 20%) and above 12 months are long-term (LTCG, 12.5% on gains above ₹1.25 lakh a year). A 4% health & education cess applies on the tax.',
                'formula' => 'STCG tax = Gain × 20% · LTCG tax = (Gain − Exemption) × 12.5% · plus 4% cess',
                'faqs' => [
                    ['Is this tax advice?', 'No. Rates reflect the rules applicable from 23 July 2024 for listed equity. Surcharge, set-offs and special cases are not included. Consult a tax professional.'],
                ],
            ],
        ];
    }

    public static function find(string $slug): ?array
    {
        $all = self::all();

        return isset($all[$slug]) ? ['slug' => $slug] + $all[$slug] : null;
    }

    /** @return array<string, array<int, array>> */
    public static function grouped(): array
    {
        $out = [];
        foreach (self::all() as $slug => $calc) {
            $out[$calc['group']][] = ['slug' => $slug] + $calc;
        }

        return $out;
    }

    public static function featured(): array
    {
        return array_map(fn ($s) => self::find($s), ['ipo-gmp', 'ipo-profit', 'ipo-allotment-chance', 'sip', 'emi', 'brokerage', 'capital-gains', 'fd']);
    }
}
