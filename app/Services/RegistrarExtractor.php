<?php

namespace App\Services;

use DOMDocument;
use DOMXPath;
use Illuminate\Support\Str;

/**
 * Reads the IPO registrar (RTA) from a source IPO page: first from the details table
 * ("Registrar" | "MUFG Intime India Pvt. Ltd."), then from the allotment sentence
 * ("... allotment status using the registrar, Bigshare Services Pvt. Ltd. or ...").
 */
class RegistrarExtractor
{
    /** Keyword => registrar name as listed in config('ipodarbar.registrars'). */
    private const KNOWN = [
        'kfin' => 'KFin Technologies',
        'karvy' => 'KFin Technologies',
        'intime' => 'MUFG Intime (Link Intime)',
        'mufg' => 'MUFG Intime (Link Intime)',
        'bigshare' => 'Bigshare Services',
        'skyline' => 'Skyline Financial Services',
        'maashitla' => 'Maashitla Securities',
        'purva' => 'Purva Sharegistry',
        'cameo' => 'Cameo Corporate Services',
        'integrated registry' => 'Integrated Registry Management',
    ];

    public function fromHtml(string $html): ?string
    {
        return $this->fromTable($html) ?? $this->fromSentence($html);
    }

    public function normalize(string $raw): ?string
    {
        $name = Str::squish(str_replace(["\u{00A0}", "\u{200B}"], ' ', html_entity_decode($raw, ENT_QUOTES | ENT_HTML5)));
        $name = trim($name, " \t:.,-");

        if ($name === '' || mb_strlen($name) > 80 || preg_match('/\b(such as|e\.g\.|website|awaited|n\/a)\b/i', $name)) {
            return null;
        }

        $lower = Str::lower($name);
        foreach (self::KNOWN as $keyword => $known) {
            if (str_contains($lower, $keyword)) {
                return $known;
            }
        }

        $name = trim(preg_replace('/\s*\b(private|pvt\.?)?\s*(limited|ltd\.?)$/i', '', $name), ' .,');

        return $name === '' ? null : Str::limit($name, 60, '');
    }

    private function fromTable(string $html): ?string
    {
        $dom = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $loaded = $dom->loadHTML('<?xml encoding="utf-8"?>'.$html, LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (! $loaded) {
            return null;
        }

        foreach ((new DOMXPath($dom))->query('//tr[count(td|th) >= 2]') as $row) {
            $cells = (new DOMXPath($dom))->query('./td|./th', $row);
            $label = Str::squish(str_replace("\u{00A0}", ' ', $cells->item(0)->textContent));

            if (preg_match('/^(IPO\s+)?Registrar(\s+Name)?\s*:?$/i', $label)) {
                return $this->normalize($cells->item(1)->textContent);
            }
        }

        return null;
    }

    private function fromSentence(string $html): ?string
    {
        $text = Str::squish(str_replace("\u{00A0}", ' ', html_entity_decode(strip_tags(
            preg_replace('#<(script|style)\b[^>]*>.*?</\1>#is', ' ', $html)
        ), ENT_QUOTES | ENT_HTML5)));

        if (preg_match('/using the registrar,?\s+(.+?)\s+(?:or|and)\b/i', $text, $m)) {
            return $this->normalize($m[1]);
        }

        return null;
    }
}
