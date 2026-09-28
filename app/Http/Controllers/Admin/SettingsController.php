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

    public const SOCIAL_NETWORKS = [
        'telegram' => 'Telegram channel',
        'whatsapp' => 'WhatsApp channel',
        'x' => 'X (Twitter)',
        'youtube' => 'YouTube',
        'instagram' => 'Instagram',
        'facebook' => 'Facebook',
        'linkedin' => 'LinkedIn',
    ];

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
        // Accept a pasted <meta ... content="CODE"> tag as well as the bare code.
        foreach (['google_verification', 'bing_verification'] as $field) {
            if (preg_match('/content=["\']([^"\']+)["\']/', (string) $request->input($field), $m)) {
                $request->merge([$field => $m[1]]);
            }
        }

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
            'google_verification' => ['nullable', 'string', 'regex:/^[A-Za-z0-9_\-]{10,100}$/'],
            'bing_verification' => ['nullable', 'string', 'regex:/^[A-Fa-f0-9]{32}$/'],
            'social' => ['array'],
            'social.*' => ['nullable', 'url:https', 'max:300'],
        ], [
            'google_verification.regex' => 'Paste the code from Search Console (letters, numbers, - and _).',
            'bing_verification.regex' => 'The Bing code is 32 characters (0-9, A-F).',
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
            'seo.google_verification' => $data['google_verification'] ?? null,
            'seo.bing_verification' => $data['bing_verification'] ?? null,
        ];
        foreach (array_keys(self::SOCIAL_NETWORKS) as $network) {
            $values['social.'.$network] = $data['social'][$network] ?? null;
        }
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
