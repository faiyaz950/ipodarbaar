<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;

/**
 * Admin-editable site settings (ads, analytics, brokers, notification switches).
 * Values saved in the database win; otherwise the defaults under
 * `ipodarbar.settings` (which may read .env) apply. Secrets never live here.
 */
class Settings
{
    private const CACHE_KEY = 'settings:all';

    /**
     * @return array<string, mixed>
     */
    public function stored(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, fn (): array => Setting::query()->pluck('value', 'key')->all());
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $stored = $this->stored();

        if (array_key_exists($key, $stored) && $stored[$key] !== null) {
            return $stored[$key];
        }

        return Arr::get(config('ipodarbar.settings', []), $key, $default);
    }

    public function enabled(string $key): bool
    {
        return (bool) $this->get($key, false);
    }

    /** Placements are visible unless an admin hid them; the global "ads.enabled" switch still applies. */
    public function adSlotVisible(string $slot): bool
    {
        return (bool) $this->get('ads.visible.'.$slot, true);
    }

    /**
     * @param  array<string, mixed>  $values  Flat keys such as "ads.enabled".
     */
    public function set(array $values): void
    {
        foreach ($values as $key => $value) {
            Setting::query()->updateOrCreate(['key' => $key], ['value' => $value]);
        }

        Cache::forget(self::CACHE_KEY);
    }
}
