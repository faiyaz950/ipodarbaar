<?php

namespace App\Support;

use App\Models\Ipo;
use Illuminate\Support\Carbon;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

/**
 * Search copy for IPO pages. What people search for changes over an IPO's life
 * ("GMP" and "subscription" while it is open, "allotment status" after it closes,
 * "listing price" once it lists), so titles, descriptions and FAQs follow the stage.
 */
class IpoSeo
{
    private const DESCRIPTION_LIMIT = 160;

    public static function title(Ipo $ipo): string
    {
        $name = $ipo->name;

        return match ($ipo->status()) {
            'upcoming' => $ipo->open_date
                ? "{$name} IPO GMP, Date, Price Band & Lot Size"
                : "{$name} IPO Date, Price Band & GMP",
            'open' => "{$name} IPO GMP Today & Subscription Status",
            'closed' => $ipo->listing_date?->isToday()
                ? "{$name} IPO Listing Today: GMP & Listing Price"
                : "{$name} IPO Allotment Status & GMP",
            default => $ipo->listing_price
                ? "{$name} IPO Listing Price & Allotment Status"
                : "{$name} IPO Listing Date & Allotment Status",
        };
    }

    public static function description(Ipo $ipo, ?Carbon $allotment = null): string
    {
        $name = $ipo->name;
        $sentences = [];

        if ($ipo->status() === 'listed' && $ipo->listingGainPercent() !== null) {
            $gain = $ipo->listingGainPercent();
            $sentences[] = "{$name} IPO listed at ₹".Ipo::num($ipo->listing_price)
                .($ipo->listing_date ? ' on '.$ipo->listing_date->format('j M Y') : '')
                .', a '.($gain >= 0 ? '' : '-').number_format(abs($gain), 1).'% '.($gain >= 0 ? 'gain' : 'loss')
                .' over the issue price of ₹'.Ipo::num($ipo->price).'.';
        } elseif ($ipo->hasGmp() && $ipo->status() !== 'listed') {
            $sentences[] = "{$name} IPO GMP today is ".($ipo->gmp >= 0 ? '₹' : '-₹').Ipo::num(abs($ipo->gmp))
                .($ipo->gmpPercent() !== null ? ' ('.number_format($ipo->gmpPercent(), 1).'%)' : '')
                .($ipo->estListingPrice() ? ', expected listing ₹'.Ipo::num($ipo->estListingPrice()) : '').'.';
        }

        $facts = [];
        if ($ipo->price) {
            $facts[] = 'price band '.$ipo->priceBand();
        }
        if ($ipo->lot_size) {
            $facts[] = 'lot '.number_format($ipo->lot_size).' shares';
        }
        if ($minimum = $ipo->lotTable()[0]['amount'] ?? null) {
            $facts[] = 'min ₹'.Ipo::num($minimum);
        }
        // The description always opens with the company name.
        $leadsWithName = $sentences !== [];
        if ($facts) {
            $sentences[] = $leadsWithName
                ? Str::ucfirst(implode(', ', $facts)).'.'
                : "{$name} IPO ".implode(', ', $facts).'.';
        } elseif (! $leadsWithName) {
            $sentences[] = "{$name} IPO.";
        }

        $sentences[] = match ($ipo->status()) {
            'upcoming' => $ipo->open_date
                ? 'Opens '.$ipo->open_date->format('j M').($ipo->close_date ? ', closes '.$ipo->close_date->format('j M') : '').'.'
                : 'Dates awaited.',
            'open' => 'Closes '.($ipo->close_date ?? $ipo->open_date)->format('j M').'. Check live subscription status.',
            'closed' => ($allotment ? 'Allotment '.$allotment->format('j M').', ' : '')
                .'listing '.($ipo->listing_date?->format('j M') ?? 'TBA').'. Check allotment status by PAN.',
            default => 'Check allotment status and listing details.',
        };

        $sentences[] = $ipo->exchange
            ? $ipo->typeLabel().' IPO on '.$ipo->exchange.'.'
            : ($ipo->isMainboard() ? 'Mainboard IPO on BSE and NSE.' : 'SME IPO.');
        if ($ipo->issue_size) {
            $sentences[] = 'Issue size ₹'.Ipo::num($ipo->issue_size).' Cr.';
        }

        return self::fit($sentences);
    }

    /**
     * Visible FAQ answered from the IPO's own data. Only questions we can answer are included.
     *
     * @return array<int, array{0: string, 1: string|HtmlString}>
     */
    public static function faqs(Ipo $ipo, ?Carbon $allotment = null): array
    {
        $name = $ipo->name;
        $faqs = [];

        if ($ipo->status() !== 'listed') {
            $faqs[] = ["What is {$name} IPO GMP today?", $ipo->hasGmp()
                ? 'As of '.now()->format('j M Y').", the {$name} IPO GMP is ".($ipo->gmp >= 0 ? '₹' : '-₹').Ipo::num(abs($ipo->gmp))
                    .($ipo->gmpPercent() !== null ? ', or '.number_format($ipo->gmpPercent(), 1).'% over the upper price band' : '')
                    .($ipo->estListingPrice() ? ', which points to a listing price of around ₹'.Ipo::num($ipo->estListingPrice()) : '')
                    .'. GMP is an unofficial grey-market indicator and can change quickly.'
                : ($ipo->status() === 'upcoming'
                    ? "The grey market premium for {$name} IPO is not available yet. It usually appears a few days before the issue opens; this page updates through the day."
                    : "No grey market premium has been reported for {$name} IPO so far. This page updates through the day if one appears.")];
        }

        if ($ipo->price) {
            $faqs[] = ["What is the price band of {$name} IPO?", "The {$name} IPO price band is {$ipo->priceBand()} per share"
                .($ipo->issue_size ? ' and the issue size is ₹'.Ipo::num($ipo->issue_size).' crore' : '').'.'];
        }

        if ($ipo->lot_size && ($lots = $ipo->lotTable())) {
            $min = $lots[0];
            $faqs[] = ["What is the lot size and minimum investment for {$name} IPO?", "One lot of {$name} IPO has ".number_format($ipo->lot_size)
                .' shares. The minimum application for retail investors is '.$min['lots'].' '.($min['lots'] === 1 ? 'lot' : 'lots')
                .' ('.number_format($min['shares']).' shares), which costs ₹'.Ipo::num($min['amount']).' at the upper price band.'];
        }

        if ($ipo->open_date) {
            $faqs[] = ["When does {$name} IPO open and close?", "{$name} IPO opens for subscription on ".$ipo->open_date->format('l, j F Y')
                .($ipo->close_date ? ' and closes on '.$ipo->close_date->format('l, j F Y') : '').'. Bids are accepted until 5 PM on the closing day.'];
        }

        if ($allotment) {
            $faqs[] = ["What is the {$name} IPO allotment date?", 'The basis of allotment for '.$name.' IPO is '
                .($allotment->isPast() && ! $allotment->isToday() ? 'expected to have been finalised on ' : 'expected on ')
                .$allotment->format('l, j F Y').', one working day after the issue closes (SEBI T+3 timeline).'];
        }

        $registrar = $ipo->registrarInfo();
        $faqs[] = ["How to check {$name} IPO allotment status?", new HtmlString(
            ($registrar
                ? 'Visit the website of the registrar, '.($registrar['url']
                    ? '<a href="'.e($registrar['url']).'" target="_blank" rel="noopener nofollow">'.e($registrar['name']).'</a>'
                    : e($registrar['name'])).', select '.e($name).' IPO and search by PAN or application number.'
                : 'Visit the registrar\'s allotment page, select '.e($name).' IPO and search by PAN or application number.')
            .' You can also check on the <a href="'.e(route('ipos.allotment')).'">IPO allotment status</a> page or on the BSE and NSE websites.'
        )];

        if ($ipo->listing_date) {
            $faqs[] = ["When is {$name} IPO listing date?", "{$name} shares ".($ipo->status() === 'listed' ? 'listed' : 'are expected to list')
                .' on '.$ipo->exchangeLabel().' on '.$ipo->listing_date->format('l, j F Y')
                .($ipo->listing_price ? ' at ₹'.Ipo::num($ipo->listing_price).' per share' : '').'.'];
        }

        $faqs[] = ["Is {$name} a mainboard or SME IPO?", "{$name} is ".($ipo->isMainboard()
            ? 'a mainboard IPO, listing on the main board of '.$ipo->exchangeLabel().'.'
            : 'an SME IPO, listing on the '.$ipo->exchangeLabel().' platform. Individual investors must apply for at least two lots in SME IPOs.')];

        return $faqs;
    }

    /**
     * Joins sentences while they fit the snippet length; never cuts a sentence in half.
     *
     * @param  array<int, string>  $sentences
     */
    private static function fit(array $sentences): string
    {
        $text = '';
        foreach ($sentences as $sentence) {
            $candidate = trim($text.' '.$sentence);
            if ($text !== '' && mb_strlen($candidate) > self::DESCRIPTION_LIMIT) {
                continue;
            }
            $text = $candidate;
        }

        return Str::limit($text, self::DESCRIPTION_LIMIT);
    }
}
