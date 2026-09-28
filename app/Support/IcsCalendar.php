<?php

namespace App\Support;

use App\Models\Ipo;
use Illuminate\Support\Carbon;

/**
 * Builds iCalendar (.ics) files of IPO dates for Apple Calendar, Outlook and calendar
 * subscriptions, plus "add to Google Calendar" links for single dates.
 */
class IcsCalendar
{
    /** Timeline steps worth a calendar entry, with the verb used in the event title. */
    private const STEPS = [
        'IPO Opens' => ['key' => 'opens', 'title' => '%s IPO opens'],
        'IPO Closes' => ['key' => 'closes', 'title' => '%s IPO closes (bids till 5 PM)'],
        'Basis of Allotment' => ['key' => 'allotment', 'title' => '%s IPO allotment status'],
        'Listing Date' => ['key' => 'listing', 'title' => '%s IPO listing'],
    ];

    /** @var list<list<string>> */
    private array $events = [];

    public function __construct(private string $name) {}

    /**
     * Opening, closing, allotment and listing dates of an IPO.
     *
     * @return list<array{key: string, label: string, title: string, date: Carbon, tentative: bool}>
     */
    public static function ipoEvents(Ipo $ipo): array
    {
        $events = [];
        foreach ($ipo->timeline() as $step) {
            $meta = self::STEPS[$step['label']] ?? null;
            if ($meta === null || $step['date'] === null) {
                continue;
            }
            $events[] = [
                'key' => $meta['key'],
                'label' => $step['label'],
                'title' => sprintf($meta['title'], $ipo->name).($step['tentative'] ? ' (tentative)' : ''),
                'date' => $step['date']->copy(),
                'tentative' => $step['tentative'],
            ];
        }

        return $events;
    }

    public static function googleLink(Ipo $ipo, array $event): string
    {
        return 'https://calendar.google.com/calendar/render?'.http_build_query([
            'action' => 'TEMPLATE',
            'text' => $event['title'],
            'dates' => $event['date']->format('Ymd').'/'.$event['date']->copy()->addDay()->format('Ymd'),
            'details' => self::details($ipo),
            'ctz' => 'Asia/Kolkata',
        ]);
    }

    public function addIpo(Ipo $ipo): self
    {
        foreach (self::ipoEvents($ipo) as $event) {
            $this->events[] = [
                'BEGIN:VEVENT',
                'UID:'.$ipo->slug.'-'.$event['key'].'@'.parse_url((string) config('app.url'), PHP_URL_HOST),
                'DTSTAMP:'.now('UTC')->format('Ymd\THis\Z'),
                'DTSTART;VALUE=DATE:'.$event['date']->format('Ymd'),
                'DTEND;VALUE=DATE:'.$event['date']->copy()->addDay()->format('Ymd'),
                'SUMMARY:'.self::escape($event['title']),
                'DESCRIPTION:'.self::escape(self::details($ipo)),
                'URL:'.$ipo->url(),
                'TRANSP:TRANSPARENT',
                'END:VEVENT',
            ];
        }

        return $this;
    }

    public function render(): string
    {
        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//IPO Darbaar//IPO Calendar//EN',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'X-WR-CALNAME:'.self::escape($this->name),
            'X-WR-TIMEZONE:Asia/Kolkata',
            // Calendar apps that subscribe to the feed refresh it every six hours.
            'REFRESH-INTERVAL;VALUE=DURATION:PT6H',
            'X-PUBLISHED-TTL:PT6H',
        ];
        foreach ($this->events as $event) {
            array_push($lines, ...$event);
        }
        $lines[] = 'END:VCALENDAR';

        return implode("\r\n", array_map(self::fold(...), $lines))."\r\n";
    }

    private static function details(Ipo $ipo): string
    {
        return $ipo->typeLabel().' IPO · Price band '.$ipo->priceBand()
            .($ipo->lot_size ? ' · Lot '.number_format($ipo->lot_size).' shares' : '')
            .'. Latest GMP, allotment status and listing details: '.$ipo->url();
    }

    private static function escape(string $text): string
    {
        return str_replace(['\\', ';', ',', "\r\n", "\n"], ['\\\\', '\\;', '\\,', '\\n', '\\n'], $text);
    }

    /** Lines longer than 75 bytes continue on the next line after a space (RFC 5545). */
    private static function fold(string $line): string
    {
        $parts = [];
        // Continuation lines start with a space, so they carry one byte less.
        while (strlen($line) > ($limit = $parts === [] ? 75 : 74)) {
            $chunk = mb_strcut($line, 0, $limit, 'UTF-8');
            $parts[] = $chunk;
            $line = substr($line, strlen($chunk));
        }
        $parts[] = $line;

        return implode("\r\n ", $parts);
    }
}
