<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Admin → Settings: ads, analytics, broker links and notification switches.
 */
class SettingsController extends Controller
{
    public const AD_SLOTS = [
        'home_top' => 'Home: below the dashboard',
        'home_mid' => 'Home: inside the news section',
        'ipo_after_gmp' => 'IPO page: after the GMP card',
        'ipo_sidebar' => 'IPO page: sidebar',
        'article' => 'News article: after the story',
        'calculator' => 'Calculators: below the result',
        'list' => 'IPO lists: above pagination',
    ];

    public const MAX_BROKERS = 6;

    public function edit(Settings $settings): View
    {
        return view('admin.settings', [
            'settings' => $settings,
            'slots' => self::AD_SLOTS,
            'brokers' => array_pad(array_values((array) $settings->get('brokers', [])), self::MAX_BROKERS, []),
        ]);
    }

    public function update(Request $request, Settings $settings): RedirectResponse
    {
        $data = $request->validate([
            'ads_client' => ['nullable', 'string', 'regex:/^ca-pub-\d{10,20}$/'],
            'slots' => ['array'],
            'slots.*' => ['nullable', 'string', 'regex:/^\d{6,12}$/'],
            'ga4_id' => ['nullable', 'string', 'regex:/^G-[A-Z0-9]{4,15}$/'],
            'cf_token' => ['nullable', 'string', 'regex:/^[a-f0-9]{32}$/'],
            'brokers' => ['array', 'max:'.self::MAX_BROKERS],
            'brokers.*.name' => ['nullable', 'string', 'max:40', 'required_with:brokers.*.url'],
            'brokers.*.url' => ['nullable', 'url:https', 'max:500', 'required_with:brokers.*.name'],
            'brokers.*.tagline' => ['nullable', 'string', 'max:80'],
        ], [
            'ads_client.regex' => 'The AdSense publisher ID looks like ca-pub-1234567890123456.',
            'slots.*.regex' => 'Ad slot IDs are the 6–12 digit numbers from AdSense.',
            'ga4_id.regex' => 'The GA4 measurement ID looks like G-XXXXXXXXXX.',
            'cf_token.regex' => 'The Cloudflare token is the 32-character value from the beacon snippet.',
        ]);

        $brokers = collect($data['brokers'] ?? [])
            ->filter(fn (array $broker): bool => filled($broker['name'] ?? null) && filled($broker['url'] ?? null))
            ->map(fn (array $broker): array => [
                'name' => trim($broker['name']),
                'url' => trim($broker['url']),
                'tagline' => trim((string) ($broker['tagline'] ?? '')),
            ])
            ->values()
            ->all();

        $values = [
            'ads.client' => $data['ads_client'] ?? null,
            'analytics.ga4_id' => $data['ga4_id'] ?? null,
            'analytics.cf_token' => $data['cf_token'] ?? null,
            'brokers' => $brokers,
            'telegram.digest' => $request->boolean('telegram_digest'),
            'telegram.gmp' => $request->boolean('telegram_gmp'),
            'telegram.new_ipo' => $request->boolean('telegram_new_ipo'),
            'email.digest' => $request->boolean('email_digest'),
        ];
        foreach (array_keys(self::AD_SLOTS) as $slot) {
            $values['ads.slots.'.$slot] = $data['slots'][$slot] ?? null;
        }

        $settings->set($values);

        return redirect()->route('admin.settings')->with('status', 'Settings saved.');
    }

    /**
     * One-click show/hide: all ads when no slot is given, otherwise a single placement.
     */
    public function toggleAds(Request $request, Settings $settings): RedirectResponse
    {
        $data = $request->validate([
            'slot' => ['nullable', Rule::in(array_keys(self::AD_SLOTS))],
        ]);
        $slot = $data['slot'] ?? null;

        if ($slot === null) {
            $show = ! $settings->enabled('ads.enabled');
            $settings->set(['ads.enabled' => $show]);
            $message = $show ? 'Ads are now showing on the site.' : 'All ads are now hidden.';
        } else {
            $show = ! $settings->adSlotVisible($slot);
            $settings->set(['ads.visible.'.$slot => $show]);
            $message = self::AD_SLOTS[$slot].($show ? ': ad shown.' : ': ad hidden.');
        }

        return redirect()->route('admin.settings')->with('status', $message);
    }
}
