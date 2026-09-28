<?php

namespace Tests\Unit;

use App\Services\IpoPageParser;
use App\Services\RegistrarExtractor;
use Tests\TestCase;

class IpoPageParserTest extends TestCase
{
    public function test_it_reads_issue_details_company_sections_and_financials(): void
    {
        $page = (new IpoPageParser(new RegistrarExtractor))->parse($this->page());

        $this->assertSame('Bigshare Services', $page['registrar']);
        $this->assertSame(1600, $page['lot_size']);
        $this->assertSame("Acme runs outdoor advertising across railway stations and airports in India.\n\nIt has also expanded into metro advertising and in-store branding.", $page['about']);
        $this->assertEquals([
            'face_value' => 10.0,
            'fresh_issue_cr' => 16.0,
            'ofs_cr' => 2.5,
            'promoter_holding_pre' => 100.0,
            'promoter_holding_post' => 73.61,
            'roe' => 36.44,
            'debt_equity' => 0.44,
            'objects' => "Purchase of media assets. ~ Rs. 4.21 Cr.\nGeneral corporate purposes.",
            'strengths' => 'Presence across many advertising formats.',
            'weaknesses' => 'Depends on concession agreements.',
            'promoters' => 'Shashi Kumar, Seema Devi',
            'lead_managers' => 'Hem Securities Ltd.',
            'eps' => 8.61,
            'pe_pre' => 8.59,
            'pe_post' => 11.67,
        ], $page['detail']);
        $this->assertSame([
            ['period' => 'FY26', 'period_end' => '2026-03-31', 'total_assets' => 31.28, 'revenue' => 46.76, 'pat' => 5.56, 'net_worth' => -1.5],
            ['period' => 'Sep 2025', 'period_end' => '2025-09-30', 'total_assets' => 22.37, 'revenue' => 18.0, 'pat' => 2.1, 'net_worth' => 1.2],
        ], $page['financials']);
    }

    public function test_financials_in_lakhs_are_converted_to_crore(): void
    {
        $html = '<h2>Company Financials</h2><p>(Amount in Lakhs)</p><table><tr><th>Period</th><th>31 Mar 2026</th></tr>'
            .'<tr><td>Total Income</td><td>4,676.00</td></tr><tr><td>Profit After Tax</td><td>556</td></tr></table>';

        $page = (new IpoPageParser(new RegistrarExtractor))->parse($html);

        $this->assertSame(46.76, $page['financials'][0]['revenue']);
        $this->assertSame(5.56, $page['financials'][0]['pat']);
    }

    public function test_a_page_without_details_yields_nothing(): void
    {
        $page = (new IpoPageParser(new RegistrarExtractor))->parse('<h2>Acme IPO GMP</h2><p>GMP has not started yet.</p>');

        $this->assertNull($page['lot_size']);
        $this->assertSame([], $page['detail']);
        $this->assertSame([], $page['financials']);
    }

    private function page(): string
    {
        return <<<'HTML'
            <script>{"text": "How to check allotment? Visit the Registrar's website."}</script>
            <h2>Acme IPO- Company Analysis</h2>
            <p>Acme runs outdoor advertising across railway stations and airports in India.</p>
            <p>It has also expanded into metro advertising and in-store branding.</p>
            <h3>Company Financials</h3>
            <p>(Amount in Cr)</p>
            <table>
                <tr><th>Particulars</th><th>31 Mar 2026</th><th>30 Sep 2025</th></tr>
                <tr><td>Assets</td><td>31.28</td><td>22.37</td></tr>
                <tr><td>Total Income</td><td>46.76</td><td>18.00</td></tr>
                <tr><td>Profit After Tax</td><td>5.56</td><td>2.10</td></tr>
                <tr><td>Net Worth</td><td>(1.50)</td><td>1.20</td></tr>
            </table>
            <h3>Cash Flows</h3>
            <table><tr><th>Net Cash Flow</th><th>31 Mar 2026</th></tr><tr><td>Total Income</td><td>999</td></tr></table>
            <h3>The Objective of the Issue</h3>
            <ul><li>Purchase of media assets. ~ Rs. 4.21 Cr.</li><li>General corporate purposes.</li></ul>
            <h3>Valuation</h3>
            <table><tr><td>KPI</td><td>Value</td></tr><tr><td>ROE</td><td>36.44%</td></tr><tr><td>Debt/Equity</td><td>0.44</td></tr></table>
            <table><tr><td>Valuation Metric</td><td>Pre IPO</td><td>Post IPO</td></tr><tr><td>EPS (₹)</td><td>8.61</td><td>6.34</td></tr><tr><td>P/E (x)</td><td>8.59</td><td>11.67</td></tr></table>
            <h3>IPO's Strengths</h3><ul><li>Presence across many advertising formats.</li></ul>
            <h3>IPO's Weaknesses</h3><ul><li>Depends on concession agreements.</li></ul>
            <h3>Acme IPO Summary</h3>
            <table>
                <tr><td>Face Value</td><td>Rs. 10 per Share</td></tr>
                <tr><td>Lot Size</td><td>1600 Shares</td></tr>
                <tr><td>Offer for Sale</td><td>3,00,000 Shares (Rs. 250 Lakh)</td></tr>
                <tr><td>Fresh Issue</td><td>21,95,200 Shares (Rs. 16 Cr)</td></tr>
                <tr><td>Registrar&nbsp;</td><td>Bigshare Services Pvt. Ltd.</td></tr>
            </table>
            <h3>Promoters And Management of Acme Ltd.</h3>
            <ul><li>Shashi Kumar</li><li>Seema Devi.</li></ul>
            <table><tr><td>Pre-Issue Promoter Shareholding</td><td>100%</td></tr><tr><td>Post-Issue Promoter Shareholding</td><td>73.61%</td></tr></table>
            <h3>IPO Lead Managers</h3>
            <p>Hem Securities Ltd.</p>
            <h3>Frequently Asked Questions</h3>
            HTML;
    }
}
