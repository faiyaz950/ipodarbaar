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
        'fno' => 'F&O & Derivatives',
        'trading' => 'Trading, Tax & Business',
    ];

    /**
     * Search titles and "how to use" steps for each calculator page.
     *
     * @var array<string, array{0: string, 1: array<int, string>}>
     */
    public const SEO = [
        'ipo-gmp' => ['IPO GMP Calculator: Expected Listing Price & Profit per Lot', [
            'Enter the IPO\'s upper price band and the latest GMP from the IPO GMP page.',
            'Add the lot size and the number of lots you expect to get.',
            'Read the expected listing price, gain per share and estimated profit per lot.',
        ]],
        'ipo-profit' => ['IPO Listing Gain Calculator: Profit on Listing Day', [
            'Enter the issue price you paid and the price you sold at on listing day.',
            'Add the lot size and the number of lots allotted to you.',
            'See your profit or loss in rupees and as a percentage of your investment.',
        ]],
        'ipo-application' => ['IPO Application Amount Calculator: Lots, Amount & Category', [
            'Choose mainboard or SME and enter the upper price band.',
            'Enter the lot size and how many lots you plan to apply for.',
            'See the amount that will be blocked and whether your bid falls in retail, small HNI or big HNI.',
        ]],
        'ipo-allotment-chance' => ['IPO Allotment Chance Calculator: Probability by Subscription', [
            'Enter the retail subscription figure (for example 45 times) once bidding closes.',
            'Enter how many separate PAN applications your family made.',
            'See the chance of each application and of at least one allotment.',
        ]],
        'hni-funding-cost' => ['HNI IPO Funding Cost Calculator: Break-Even Listing Price', [
            'Enter the application amount, the upper price band and the interest rate on the borrowed money.',
            'Add the number of days the money is blocked, any processing charges and the allotment you expect.',
            'See the funding cost, the listing price needed to break even and your profit at the current GMP.',
        ]],
        'buyback-acceptance-ratio' => ['Buyback Acceptance Ratio Calculator: Profit from Share Buyback', [
            'Enter the buyback price and the current market price of the share.',
            'Enter how many shares you will tender and the acceptance ratio you expect.',
            'See how many shares will be accepted, how many come back and your gain before tax.',
        ]],
        'pe-ratio' => ['P/E Ratio Calculator: Price to Earnings for IPOs & Stocks', [
            'Enter the share price, or the upper price band for an IPO.',
            'Enter the earnings per share (EPS) from the results or the RHP.',
            'Add the P/E of listed peers to see whether the price is at a premium or a discount.',
        ]],
        'sip' => ['SIP Calculator: Calculate SIP Returns Online (with Step-up)', [
            'Enter your monthly SIP amount and the expected annual return.',
            'Choose the investment period and, optionally, a yearly step-up percentage.',
            'See the total invested, estimated returns and final value, with a year-wise breakdown.',
        ]],
        'lumpsum' => ['Lumpsum Calculator: Mutual Fund Lumpsum Returns Online', [
            'Enter the one-time investment amount.',
            'Set the expected annual return and number of years.',
            'See the future value and total gain of your lumpsum investment.',
        ]],
        'swp' => ['SWP Calculator: Systematic Withdrawal Plan Returns', [
            'Enter your total investment and the amount you want to withdraw every month.',
            'Set the expected return and the withdrawal period.',
            'See the balance left at the end and how long your money lasts.',
        ]],
        'cagr' => ['CAGR Calculator: Compound Annual Growth Rate Online', [
            'Enter the starting value of the investment.',
            'Enter the ending value and the number of years held.',
            'See the compound annual growth rate (CAGR) and total return.',
        ]],
        'stock-average' => ['Stock Average Calculator: Average Share Price After Buying More', [
            'Enter the quantity and price of your existing holding.',
            'Enter the quantity and price of your new purchase.',
            'See your new average buy price and total investment.',
        ]],
        'return' => ['Investment Return Calculator: Absolute & Annualised Return', [
            'Enter the amount invested and the current or final value.',
            'Enter how long you held the investment.',
            'See the absolute return and the annualised return.',
        ]],
        'compound-interest' => ['Compound Interest Calculator: Daily, Monthly & Yearly Compounding', [
            'Enter the principal and the annual interest rate.',
            'Choose the compounding frequency and the time period.',
            'See the maturity amount and total interest earned.',
        ]],
        'retirement' => ['Retirement Calculator: Corpus Needed & Monthly SIP', [
            'Enter your current age, retirement age and current monthly expenses.',
            'Set expected inflation and returns before and after retirement.',
            'See the corpus you need and the monthly investment required to build it.',
        ]],
        'inflation' => ['Inflation Calculator India: Future Cost & Value of Money', [
            'Enter today\'s cost of an expense or goal.',
            'Set the expected inflation rate and number of years.',
            'See what it will cost in future and how much purchasing power money loses.',
        ]],
        'fd' => ['FD Calculator: Fixed Deposit Interest & Maturity Amount', [
            'Enter the deposit amount and the interest rate offered by the bank.',
            'Choose the tenure and compounding frequency (usually quarterly).',
            'See the maturity amount and total interest earned.',
        ]],
        'rd' => ['RD Calculator: Recurring Deposit Maturity & Interest', [
            'Enter your monthly deposit amount.',
            'Enter the interest rate and the tenure.',
            'See the maturity value and the interest earned on your recurring deposit.',
        ]],
        'ppf' => ['PPF Calculator: PPF Maturity Amount & Interest Online', [
            'Enter your yearly PPF deposit (₹500 to ₹1.5 lakh).',
            'Check the interest rate and choose the tenure (15 years, extendable in blocks of 5).',
            'See the tax-free maturity value and total interest.',
        ]],
        'emi' => ['EMI Calculator: Loan EMI, Total Interest & Schedule', [
            'Enter the loan amount and the annual interest rate.',
            'Choose the loan tenure in years.',
            'See the monthly EMI, total interest and a year-wise repayment schedule.',
        ]],
        'home-loan' => ['Home Loan Calculator: EMI, Total Interest & Repayment Schedule', [
            'Enter the property loan amount and interest rate.',
            'Choose the tenure of the home loan.',
            'See your EMI, the total interest over the loan and how the balance falls each year.',
        ]],
        'loan-eligibility' => ['Loan Eligibility Calculator: How Much Loan Can I Get?', [
            'Enter your monthly income and existing EMIs.',
            'Set the interest rate and tenure you expect.',
            'See the maximum loan and EMI you are likely to be eligible for.',
        ]],
        'fno-margin' => ['F&O Margin Calculator: Futures & Options Margin Required', [
            'Choose the contract type and enter the lot size and price.',
            'Enter the margin percentages for your position.',
            'See the approximate margin blocked for the trade.',
        ]],
        'options-pnl' => ['F&O P&L Calculator: Options & Futures Profit and Loss', [
            'Choose futures or options and whether you are buying or selling.',
            'Enter the entry price, exit price, lot size and number of lots.',
            'See your profit or loss for the trade.',
        ]],
        'option-premium' => ['Option Premium Calculator: Black-Scholes Price & Greeks', [
            'Enter the spot price, strike price and days to expiry.',
            'Enter the volatility and interest rate.',
            'See the theoretical call and put premium along with delta, gamma, theta and vega.',
        ]],
        'hedging' => ['Portfolio Hedging Calculator: Hedge with Nifty Futures or Puts', [
            'Enter your portfolio value and its beta.',
            'Enter the index level and lot size of the hedging contract.',
            'See how many lots you need to hedge the portfolio.',
        ]],
        'beta' => ['Stock Beta Calculator: Beta of a Stock vs the Index', [
            'Enter the stock\'s returns for a series of periods.',
            'Enter the index returns for the same periods.',
            'See the stock\'s beta, which shows how much it moves relative to the market.',
        ]],
        'income-tax' => ['Income Tax Calculator FY 2025-26: Old vs New Regime', [
            'Enter your annual income and choose your age group.',
            'Add deductions such as 80C and 80D if you are comparing the old regime.',
            'See your tax under the old and new regimes and which one is lower.',
        ]],
        'break-even' => ['Break-even Calculator: Break-even Point in Units & Sales', [
            'Enter your fixed costs.',
            'Enter the selling price and variable cost per unit.',
            'See how many units you must sell, and the sales value needed, to break even.',
        ]],
        'brokerage' => ['Brokerage Calculator: Delivery & Intraday Charges, STT & Net P&L', [
            'Choose delivery or intraday and the exchange.',
            'Enter the buy price, sell price, quantity and your broker\'s charge per order.',
            'See every charge (STT, exchange, SEBI, stamp duty, GST) and your net profit.',
        ]],
        'capital-gains' => ['Capital Gains Tax Calculator: STCG & LTCG on Shares', [
            'Enter the buy price, sell price and quantity of shares.',
            'Enter how many months you held them.',
            'See whether the gain is short- or long-term and the tax payable with cess.',
        ]],
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
            'hni-funding-cost' => [
                'name' => 'HNI IPO Funding Cost Calculator',
                'short' => 'Funding cost and break-even listing price for borrowed IPO applications.',
                'icon' => 'hand-coins',
                'group' => 'ipo',
                'about' => 'HNIs often borrow to apply for large amounts in oversubscribed IPOs. Interest is charged for every day the money is used, while usually only a small part of the application is allotted. This calculator shows the total funding cost, the listing price needed to break even and the profit or loss at the current GMP.',
                'formula' => 'Funding cost = Amount × Rate × Days ÷ 365 + Charges · Break-even listing price = Issue price + Funding cost ÷ Shares allotted',
                'faqs' => [
                    ['How are NII (HNI) shares allotted?', 'Under SEBI rules, each successful NII applicant gets at least the minimum NII application size, subject to availability. When the category is heavily oversubscribed, the successful applicants are picked by a draw of lots.'],
                    ['Why is IPO funding risky?', 'Interest and charges are payable whatever happens. If the IPO lists below the break-even price, the funding cost adds to the loss.'],
                ],
            ],
            'buyback-acceptance-ratio' => [
                'name' => 'Buyback Acceptance Ratio Calculator',
                'short' => 'Shares accepted and gain from a tender-offer buyback.',
                'icon' => 'repeat',
                'group' => 'ipo',
                'about' => 'In a tender-offer buyback the company buys back shares at a fixed price, usually above the market price. Only part of the shares you tender is accepted (the acceptance ratio); the rest are returned to your demat account. 15% of the buyback is reserved for small shareholders, whose holding is worth up to ₹2 lakh on the record date.',
                'formula' => 'Shares accepted = Shares tendered × Acceptance ratio · Gain = Shares accepted × (Buyback price − Market price)',
                'faqs' => [
                    ['How is buyback income taxed?', 'For buybacks from 1 October 2024, the entire amount you receive is taxed as dividend income at your slab rate, and the cost of the shares bought back becomes a capital loss you can set off against capital gains. This calculator shows the gain before tax.'],
                    ['Who is a small shareholder in a buyback?', 'A shareholder whose shares in the company are worth ₹2 lakh or less at the closing price on the record date. As 15% of the buyback is reserved for them, their acceptance ratio is often higher.'],
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
                'icon' => 'hand-coins',
                'group' => 'invest',
                'about' => 'A Systematic Withdrawal Plan (SWP) lets you withdraw a fixed amount every month while the remaining corpus stays invested. See how long your money lasts and what is left at the end.',
                'formula' => 'Each month: Balance = Balance × (1 + r/12) − Withdrawal',
                'faqs' => [],
            ],
            'cagr' => [
                'name' => 'CAGR Calculator',
                'short' => 'Annualised growth rate between a starting and ending value.',
                'icon' => 'chart-line',
                'group' => 'invest',
                'about' => 'Compound Annual Growth Rate (CAGR) smooths returns into a single yearly rate, making it easy to compare investments held for different periods.',
                'formula' => 'CAGR = (Final value ÷ Initial value)^(1 ÷ years) − 1',
                'faqs' => [],
            ],
            'stock-average' => [
                'name' => 'Stock Average Calculator',
                'short' => 'Your new average buy price after averaging up or down.',
                'icon' => 'candlestick',
                'group' => 'invest',
                'about' => 'When you buy more of a stock you already hold, your average cost changes. Enter both purchases to see the new average price and total investment.',
                'formula' => 'Average = (Q₁ × P₁ + Q₂ × P₂) ÷ (Q₁ + Q₂)',
                'faqs' => [],
            ],
            'pe-ratio' => [
                'name' => 'P/E Ratio Calculator',
                'short' => 'Price-to-earnings ratio and premium or discount to peers.',
                'icon' => 'scale',
                'group' => 'invest',
                'about' => 'The price-to-earnings (P/E) ratio shows how many rupees investors pay for each rupee of a company\'s yearly earnings. For an IPO, use the upper price band and the post-issue EPS from the RHP, then compare the result with listed peers in the same industry.',
                'formula' => 'P/E = Price ÷ Earnings per share (EPS) · Earnings yield = EPS ÷ Price × 100',
                'faqs' => [
                    ['Is a lower P/E always better?', 'Not always. A low P/E can mean a stock is cheap, or that its earnings are expected to fall. Compare P/E with growth, return ratios and peers in the same industry.'],
                    ['Where do I find the EPS for an IPO?', 'The RHP shows EPS and the P/E of listed peers in its "Basis for Offer Price" section. Use the diluted, post-issue EPS for a fair comparison.'],
                ],
            ],
            'return' => [
                'name' => 'Investment Return Calculator',
                'short' => 'Compare what a monthly SIP or a one-time investment could grow to.',
                'icon' => 'percent',
                'group' => 'invest',
                'about' => 'Pick monthly (SIP) or one-time (lumpsum) investing, enter the amount, expected annual return and period to see the total invested, the estimated gains and the final value. Handy for comparing the two ways of investing the same money.',
                'formula' => 'SIP: FV = P × [((1 + i)ⁿ − 1) ÷ i] × (1 + i), i = rate ÷ 12 · Lumpsum: FV = P × (1 + r)ᵗ',
                'faqs' => [
                    ['Is SIP better than lumpsum?', 'A lumpsum invested early earns more if markets rise steadily, while a SIP spreads your entry price over time and suits regular income. The right choice depends on when you have the money and your risk appetite.'],
                ],
            ],
            'compound-interest' => [
                'name' => 'Compound Interest Calculator',
                'short' => 'Growth of a deposit with monthly, quarterly or yearly compounding.',
                'icon' => 'sprout',
                'group' => 'invest',
                'about' => 'Compound interest earns interest on previously earned interest, so money grows faster than with simple interest. Choose how often interest is compounded to see the maturity value and how much extra compounding earns over simple interest.',
                'formula' => 'A = P × (1 + r/n)^(n × t) · Simple interest = P × r × t',
                'faqs' => [
                    ['Does compounding frequency matter?', 'Yes. The more often interest is compounded, the higher the maturity value at the same annual rate, although the difference between monthly and quarterly is small.'],
                ],
            ],
            'retirement' => [
                'name' => 'Retirement Calculator',
                'short' => 'Corpus you need to retire and the monthly SIP to build it.',
                'icon' => 'hourglass',
                'group' => 'invest',
                'about' => 'Your current monthly expenses are grown by inflation until retirement. The corpus must then fund those rising expenses for every year of retirement while the remaining money keeps earning a (usually lower, safer) return. Finally, the calculator works out the monthly SIP needed to reach that corpus.',
                'formula' => 'Corpus = E × (1 + r) × [1 − ((1 + i) ÷ (1 + r))ⁿ] ÷ (r − i), where E = first-year expense at retirement · SIP = Corpus × j ÷ [((1 + j)ᵐ − 1) × (1 + j)]',
                'faqs' => [
                    ['What return should I assume after retirement?', 'Most retirees move to safer debt-heavy investments, so a post-retirement return of 6–8% is a common assumption, lower than the 10–12% often assumed for equity before retirement.'],
                    ['Does this include EPF, NPS or other savings?', 'No. Subtract what your existing savings are expected to be worth at retirement from the corpus shown.'],
                ],
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
                'icon' => 'vault',
                'group' => 'savings',
                'about' => 'Fixed deposits pay a fixed rate for a chosen tenure. Most Indian banks compound interest quarterly. Choose the compounding frequency your bank uses.',
                'formula' => 'A = P × (1 + r/n)^(n × t)',
                'faqs' => [],
            ],
            'rd' => [
                'name' => 'RD Calculator',
                'short' => 'Maturity value of a monthly recurring deposit.',
                'icon' => 'calendar-clock',
                'group' => 'savings',
                'about' => 'A Recurring Deposit (RD) lets you deposit a fixed sum every month. Banks typically compound RD interest quarterly; this calculator follows that convention.',
                'formula' => 'M = Σ R × (1 + r/4)^(months remaining ÷ 3)',
                'faqs' => [],
            ],
            'ppf' => [
                'name' => 'PPF Calculator',
                'short' => 'Tax-free maturity value of your Public Provident Fund.',
                'icon' => 'piggy-bank',
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
                'icon' => 'credit-card',
                'group' => 'savings',
                'about' => 'Equated Monthly Instalments (EMIs) repay both principal and interest over the loan tenure. Use it for home, car, personal or education loans.',
                'formula' => 'EMI = P × r × (1 + r)ⁿ ÷ ((1 + r)ⁿ − 1), where r = monthly rate',
                'faqs' => [],
            ],
            'home-loan' => [
                'name' => 'Home Loan Calculator',
                'short' => 'Loan amount, EMI and the total cost of buying a home.',
                'icon' => 'home',
                'group' => 'savings',
                'about' => 'Start from the property price and your down payment. The rest is the home loan: see the monthly EMI, the total interest over the tenure and the full cost of the home including your down payment. Banks usually fund up to 75–90% of the property value.',
                'formula' => 'Loan = Price × (1 − Down payment %) · EMI = L × r × (1 + r)ⁿ ÷ ((1 + r)ⁿ − 1)',
                'faqs' => [
                    ['How much down payment do banks need?', 'RBI rules cap the loan at 90% of the property value for loans up to ₹30 lakh, 80% for ₹30–75 lakh and 75% above ₹75 lakh, so plan for a 10–25% down payment plus stamp duty and registration.'],
                ],
            ],
            'loan-eligibility' => [
                'name' => 'Loan Eligibility Calculator',
                'short' => 'Maximum loan you can get based on income and existing EMIs.',
                'icon' => 'user-check',
                'group' => 'savings',
                'about' => 'Lenders cap your total EMIs at a share of your monthly income, called FOIR (Fixed Obligation to Income Ratio), typically 50–60%. Existing EMIs are subtracted from that limit, and the remaining EMI capacity is converted into a loan amount for the chosen rate and tenure.',
                'formula' => 'Max EMI = Income × FOIR − Existing EMIs · Max loan = EMI × ((1 + r)ⁿ − 1) ÷ (r × (1 + r)ⁿ)',
                'faqs' => [
                    ['How can I increase my loan eligibility?', 'Close small existing loans, choose a longer tenure, add a co-applicant with income, or improve your credit score to qualify for a lower rate.'],
                ],
            ],
            'fno-margin' => [
                'name' => 'F&O Margin Calculator',
                'short' => 'Margin needed for futures positions and the leverage you get.',
                'icon' => 'coins',
                'group' => 'fno',
                'about' => 'Futures let you control a large contract value by blocking only a margin (SPAN + exposure), usually 12–25% of the contract value depending on the stock or index volatility. This calculator shows the contract value, margin required, leverage and how much a 1% move changes your P&L.',
                'formula' => 'Contract value = Price × Lot size × Lots · Margin = Contract value × Margin % · Leverage = Contract value ÷ Margin',
                'faqs' => [
                    ['Is this the exact margin my broker will block?', 'No. Exchanges publish SPAN and exposure margins daily and they change with volatility. Use your broker’s margin calculator for the exact figure; this tool helps you understand the size of the position.'],
                ],
            ],
            'options-pnl' => [
                'name' => 'F&O P&L Calculator',
                'short' => 'Profit or loss, breakeven and max risk for a call or put at expiry.',
                'icon' => 'target',
                'group' => 'fno',
                'about' => 'Choose call or put, and whether you bought or sold the option. Enter the strike, premium per unit, lot size and the underlying price at expiry to see your P&L. Option buyers risk only the premium; option sellers keep the premium but can face large losses.',
                'formula' => 'Call payoff = max(S − K, 0) · Put payoff = max(K − S, 0) · Buyer P&L = (Payoff − Premium) × Qty · Seller P&L = (Premium − Payoff) × Qty',
                'faqs' => [
                    ['What is the breakeven of an option?', 'For a call it is strike + premium; for a put it is strike − premium. At expiry the underlying must cross this level for the buyer to make a profit.'],
                    ['Are charges included?', 'No. Brokerage, STT, exchange charges and GST reduce the P&L slightly.'],
                ],
            ],
            'option-premium' => [
                'name' => 'Option Premium & Greeks Calculator',
                'short' => 'Black-Scholes fair value of a call or put, with Delta, Gamma, Theta, Vega and Rho.',
                'icon' => 'sigma',
                'group' => 'fno',
                'about' => 'The Black-Scholes model prices a European option from the spot price, strike, days to expiry, risk-free rate and implied volatility (IV). The Greeks show how the premium reacts: Delta to a ₹1 move in the underlying, Gamma to changes in Delta, Theta to one day passing, Vega to a 1% change in IV and Rho to a 1% change in interest rates.',
                'formula' => 'Call = S·N(d₁) − K·e^(−rT)·N(d₂) · Put = K·e^(−rT)·N(−d₂) − S·N(−d₁) · d₁ = [ln(S/K) + (r + σ²/2)T] ÷ (σ√T), d₂ = d₁ − σ√T',
                'faqs' => [
                    ['Why does the market premium differ from this value?', 'Market prices reflect the IV traders are paying, dividends and demand. Enter the IV quoted on the option chain to get a value close to the traded price.'],
                    ['Are Indian index options European?', 'Yes. NSE index options (Nifty, Bank Nifty) and stock options are European-style and can only be exercised at expiry, which suits the Black-Scholes model.'],
                ],
            ],
            'hedging' => [
                'name' => 'Portfolio Hedging Calculator',
                'short' => 'How many index futures lots to sell to hedge your stock portfolio.',
                'icon' => 'shield-check',
                'group' => 'fno',
                'about' => 'A portfolio with a beta of 1.2 tends to move 1.2% for every 1% move in the index. To protect it from a fall, sell index futures worth portfolio value × beta. This calculator converts that into lots of the index future and shows the margin you would need.',
                'formula' => 'Lots to sell = (Portfolio value × Beta) ÷ (Futures price × Lot size) · Margin = Lots × Contract value × Margin %',
                'faqs' => [
                    ['Why round the lots?', 'Futures trade only in whole lots, so the hedge is rarely exact. Rounding down leaves part of the portfolio unhedged; rounding up slightly over-hedges it.'],
                ],
            ],
            'beta' => [
                'name' => 'Stock Beta Calculator',
                'short' => 'Measure how volatile a stock is compared with the market.',
                'icon' => 'activity',
                'group' => 'fno',
                'about' => 'Beta compares a stock’s returns with the market’s returns over the same periods. Paste matching lists of periodic returns (for example, monthly % returns of the stock and of Nifty 50). A beta above 1 means the stock tends to swing more than the market, below 1 means it is more defensive.',
                'formula' => 'Beta = Covariance(stock, market) ÷ Variance(market) · Correlation = Covariance ÷ (σ stock × σ market)',
                'faqs' => [
                    ['How many data points should I use?', 'Use at least 12 monthly or 52 weekly returns for a meaningful estimate. Both lists must cover exactly the same periods.'],
                ],
            ],
            'income-tax' => [
                'name' => 'Income Tax Calculator (FY 2025-26)',
                'short' => 'Compare tax under the new and old regime and see which saves more.',
                'icon' => 'landmark',
                'group' => 'trading',
                'about' => 'Enter your annual income and, for the old regime, your deductions such as 80C, 80D, HRA and home-loan interest. The calculator applies FY 2025-26 (AY 2026-27) slabs, the standard deduction for salaried people, the Section 87A rebate (no tax up to ₹12 lakh taxable income in the new regime, ₹5 lakh in the old) and 4% health & education cess.',
                'formula' => 'New regime: 0–4L nil, 4–8L 5%, 8–12L 10%, 12–16L 15%, 16–20L 20%, 20–24L 25%, above 24L 30% · Old regime: 0–2.5L nil, 2.5–5L 5%, 5–10L 20%, above 10L 30% · + 4% cess',
                'faqs' => [
                    ['Which regime should I choose?', 'The new regime is usually better unless your deductions (80C, 80D, HRA, home-loan interest and so on) are large. The calculator shows both so you can compare.'],
                    ['Is surcharge included?', 'No. Surcharge applies only above ₹50 lakh income. Capital gains on shares are taxed separately; use the Capital Gains calculator for those.'],
                ],
            ],
            'break-even' => [
                'name' => 'Break-even Calculator',
                'short' => 'Units and sales you need to cover your costs.',
                'icon' => 'scale',
                'group' => 'trading',
                'about' => 'A business breaks even when revenue covers both fixed costs (rent, salaries) and variable costs (materials per unit). Each unit sold contributes selling price minus variable cost towards the fixed costs; the break-even point is where those contributions add up to the fixed costs.',
                'formula' => 'Break-even units = Fixed costs ÷ (Selling price − Variable cost per unit) · Break-even sales = Units × Selling price',
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
                'icon' => 'badge-percent',
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
