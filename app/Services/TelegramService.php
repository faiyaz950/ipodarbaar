<?php

namespace App\Services;

use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/**
 * Posts to the site's Telegram channel through the Bot API.
 */
class TelegramService
{
    private const CAPTION_LIMIT = 1024;

    public function configured(): bool
    {
        return filled(config('services.telegram.bot_token')) && filled(config('services.telegram.channel_id'));
    }

    /**
     * @return array<string, mixed>
     */
    public function sendMessage(string $html, bool $showLinkPreview = false): array
    {
        return $this->call('sendMessage', [
            'text' => $html,
            'link_preview_options' => ['is_disabled' => ! $showLinkPreview],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function sendPhoto(string $photoUrl, string $captionHtml): array
    {
        return $this->call('sendPhoto', [
            'photo' => $photoUrl,
            'caption' => mb_substr($captionHtml, 0, self::CAPTION_LIMIT),
        ]);
    }

    /**
     * Sends are not retried on timeouts (Telegram has no idempotency key, so a retry could
     * post twice); only an explicit 429 "slow down" is retried. Errors never include the token.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function call(string $method, array $payload): array
    {
        if (! $this->configured()) {
            throw new RuntimeException('Telegram is not configured (TELEGRAM_BOT_TOKEN / TELEGRAM_CHANNEL_ID).');
        }

        $token = (string) config('services.telegram.bot_token');

        try {
            return Http::connectTimeout(5)
                ->timeout(15)
                ->retry([1000, 3000], 0, fn (Throwable $e): bool => $e instanceof RequestException && $e->response->status() === 429)
                ->post("https://api.telegram.org/bot{$token}/{$method}", $payload + [
                    'chat_id' => config('services.telegram.channel_id'),
                    'parse_mode' => 'HTML',
                ])
                ->throw()
                ->json();
        } catch (Throwable $e) {
            throw new RuntimeException('Telegram '.$method.' failed: '.str_replace($token, '***', $e->getMessage()), previous: null);
        }
    }
}
