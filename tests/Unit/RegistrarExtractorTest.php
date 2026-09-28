<?php

namespace Tests\Unit;

use App\Services\RegistrarExtractor;
use PHPUnit\Framework\TestCase;

class RegistrarExtractorTest extends TestCase
{
    public function test_it_reads_the_registrar_row_of_the_details_table(): void
    {
        $html = '<table><tr><td><p>Issue Type&nbsp;</p></td><td><p>Book Built Issue IPO</p></td></tr>'
            .'<tr><td><p dir="ltr">Registrar&nbsp;</p></td><td><p dir="ltr">MUFG Intime India Pvt. Ltd.&nbsp;</p></td></tr></table>'
            .'<p>Go to the Registrar\'s website (e.g. KFintech, Link Intime, Bigshare).</p>';

        $this->assertSame('MUFG Intime (Link Intime)', (new RegistrarExtractor)->fromHtml($html));
    }

    public function test_it_falls_back_to_the_allotment_sentence(): void
    {
        $html = '<script>{"text": "visit the Registrar\'s website, such as KFintech"}</script>'
            .'<p>Investors can check Omara IPO allotment status using the registrar, Bigshare Services Pvt. Ltd. or using BSE Website- <a href="#">BSE</a></p>';

        $this->assertSame('Bigshare Services', (new RegistrarExtractor)->fromHtml($html));
    }

    public function test_generic_mentions_of_registrars_are_ignored(): void
    {
        $html = '<p>Go to the Registrar\'s website (e.g. KFintech, Link Intime, Bigshare).</p><p>Kfintech IPO allotment status</p>';

        $this->assertNull((new RegistrarExtractor)->fromHtml($html));
    }

    public function test_unknown_registrars_keep_their_name_without_the_company_suffix(): void
    {
        $html = '<table><tr><td>Registrar</td><td>Mas Services Private Limited</td></tr></table>';

        $this->assertSame('Mas Services', (new RegistrarExtractor)->fromHtml($html));
    }
}
