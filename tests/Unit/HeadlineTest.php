<?php

namespace Tests\Unit;

use App\Support\Headline;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class HeadlineTest extends TestCase
{
    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function shoutingHeadlines(): array
    {
        return [
            'acronym and currency' => ['NSE FINALLY GOES PUBLIC! A ₹22,562 CRORE MILESTONE FOR INDIA', 'NSE Finally Goes Public! A ₹22,562 Crore Milestone for India'],
            'possessive acronym, mixed-case brand' => ['UPI’S BIG EXPANSION! PhonePe TO HIRE 20,000+ STAFF!', 'UPI’s Big Expansion! PhonePe to Hire 20,000+ Staff!'],
            'plural acronym already mixed' => ['IPO MARKET UPDATE: 2 MAINBOARD IPOs OPEN, 2 CLOSE TODAY!', 'IPO Market Update: 2 Mainboard IPOs Open, 2 Close Today!'],
            'hyphenated number' => ['BANK STRIKE DEFERRED! 5-DAY BANKING WEEK TALKS TO CONTINUE', 'Bank Strike Deferred! 5-Day Banking Week Talks to Continue'],
            'partly shouting' => ['BLOODBATH ON D’STREET! Sensex CRASHES 1,247 Points, Nifty Slips Below 23,100', 'Bloodbath on D’Street! Sensex Crashes 1,247 Points, Nifty Slips Below 23,100'],
            'company acronym' => ['INDIA HITS AN ENERGY BREAKTHROUGH! ONGC DISCOVERS NATURAL GAS OFF ODISHA COAST', 'India Hits an Energy Breakthrough! ONGC Discovers Natural Gas Off Odisha Coast'],
            'question and article' => ['₹4 LAKH CRORE WIPED OUT! WHY DID THE INDIAN STOCK MARKET FALL?', '₹4 Lakh Crore Wiped Out! Why Did the Indian Stock Market Fall?'],
            'straight apostrophe' => ['CRUDE OIL ABOVE $100! GLOBAL TENSIONS COULD PUT PRESSURE ON INDIA\'S OIL BILL', 'Crude Oil Above $100! Global Tensions Could Put Pressure on India\'s Oil Bill'],
            'plural acronym and multiple' => ['QIBS LEAD DEMAND AT 12.68X AS NSE IPO CLOSES', 'QIBs Lead Demand at 12.68x as NSE IPO Closes'],
            'small word after colon' => ['PRE-IPO BUZZ: A LOOK AT Q2 FY27 NUMBERS', 'Pre-IPO Buzz: A Look at Q2 FY27 Numbers'],
            'IT as sector' => ['IT STOCKS RALLY AS US FED HOLDS RATES', 'IT Stocks Rally as US Fed Holds Rates'],
            'it as pronoun' => ['WHERE DOES IT GO FROM HERE?', 'Where Does It Go From Here?'],
            'hinglish particles' => ['IPO MARKET MEIN DHAMAAL! 2 IPOs OPEN, 2 CLOSE TODAY!', 'IPO Market mein Dhamaal! 2 IPOs Open, 2 Close Today!'],
        ];
    }

    #[DataProvider('shoutingHeadlines')]
    public function test_shouting_headlines_become_title_case(string $headline, string $expected): void
    {
        $this->assertSame($expected, Headline::normalizeCase($headline));
    }

    public function test_headlines_in_normal_case_are_left_alone(): void
    {
        foreach ([
            'NSE shares fall below IPO price amid broader market crash',
            '₹22,562 Crore NSE IPO Gets Strong Response: QIBs Lead Demand at 12.68x Subscription',
            'IPO Listing Alert | 17 September 2026',
            'India’s permanent UNSC seat bid gets backing from four European nations',
            'NSE IPO',
        ] as $headline) {
            $this->assertSame($headline, Headline::normalizeCase($headline));
        }
    }
}
