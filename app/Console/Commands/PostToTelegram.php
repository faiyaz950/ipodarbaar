<?php

namespace App\Console\Commands;

use App\Models\Ipo;
use App\Models\NotificationLog;
use App\Services\IpoDigestBuilder;
use App\Services\TelegramService;
use App\Support\Settings;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Sleep;
use Throwable;

#[Signature('darbaar:telegram {type : digest (morning events), gmp (evening board) or new (newly announced IPOs)}')]
#[Description('Post IPO updates to the Telegram channel (each message is sent only once)')]
class PostToTelegram extends Command
{
    private const CHANNEL = 'telegram';

    /** New-IPO alerts per run, so a big sync never floods the channel. */
    private const MAX_NEW_PER_RUN = 5;

    public function handle(TelegramService $telegram, IpoDigestBuilder $builder, Settings $settings): int
    {
        $type = (string) $this->argument('type');
        $setting = ['digest' => 'telegram.digest', 'gmp' => 'telegram.gmp', 'new' => 'telegram.new_ipo'][$type] ?? null;

        if ($setting === null) {
            $this->error('Type must be digest, gmp or new.');

            return self::FAILURE;
        }
        if (! $telegram->configured()) {
            $this->line('Telegram is not configured; skipping.');

            return self::SUCCESS;
        }
        if (! $settings->enabled($setting)) {
            $this->line("Telegram {$type} posts are switched off in Admin → Settings.");

            return self::SUCCESS;
        }

        return match ($type) {
            'digest' => $this->postOnce($telegram, 'digest:'.today()->toDateString(), $this->digestMessage($builder)),
            'gmp' => $this->postOnce($telegram, 'gmp:'.today()->toDateString(), $this->gmpMessage($builder)),
            'new' => $this->postNewIpos($telegram),
        };
    }

    private function postOnce(TelegramService $telegram, string $key, ?string $message): int
    {
        if ($message === null) {
            $this->line('Nothing to post today.');

            return self::SUCCESS;
        }
        if (! NotificationLog::claim(self::CHANNEL, $key)) {
            $this->line("Already posted ({$key}).");

            return self::SUCCESS;
        }

        try {
            $telegram->sendMessage($message);
        } catch (Throwable $e) {
            NotificationLog::release(self::CHANNEL, $key);
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("Posted {$key}.");

        return self::SUCCESS;
    }

    private function postNewIpos(TelegramService $telegram): int
    {
        $candidates = Ipo::active()->where('created_at', '>=', now()->subDays(3))->orderBy('open_date')->get();

        // First run: treat everything already in the database as announced instead of flooding the channel.
        if (! NotificationLog::query()->where('channel', self::CHANNEL)->where('key', 'like', 'new:%')->exists()) {
            Ipo::active()->pluck('id')->each(fn (int $id): bool => NotificationLog::claim(self::CHANNEL, 'new:'.$id));
            $this->line('Marked current IPOs as announced; future new IPOs will be posted.');

            return self::SUCCESS;
        }

        $posted = 0;
        foreach ($candidates as $ipo) {
            if ($posted >= self::MAX_NEW_PER_RUN || ! NotificationLog::claim(self::CHANNEL, 'new:'.$ipo->id)) {
                continue;
            }

            try {
                $shareImage = $ipo->shareImageUrl();
                $shareImage
                    ? $telegram->sendPhoto($shareImage, $this->newIpoMessage($ipo))
                    : $telegram->sendMessage($this->newIpoMessage($ipo), showLinkPreview: true);
                $posted++;
                Sleep::sleep(1);
            } catch (Throwable $e) {
                NotificationLog::release(self::CHANNEL, 'new:'.$ipo->id);
                $this->error($e->getMessage());

                return self::FAILURE;
            }
        }

        $this->info("Posted {$posted} new IPO alert(s).");

        return self::SUCCESS;
    }

    private function digestMessage(IpoDigestBuilder $builder): ?string
    {
        $day = $builder->day(today());
        $blocks = array_filter([
            $this->block('🟢 <b>Opening today</b>', $day['opening']),
            $this->block('⏳ <b>Last day to apply</b>', $day['closing']),
            $this->block('🎯 <b>Allotment today</b> (tentative)', $day['allotment'], showGmp: false),
            $this->block('🚀 <b>Listing today</b>', $day['listing']),
            $this->block('📅 <b>Opening tomorrow</b>', $day['tomorrow']),
        ]);

        if ($blocks === []) {
            return null;
        }

        return '🔔 <b>IPO Darbaar · '.e(today()->format('D, j M'))."</b>\n\n"
            .implode("\n\n", $blocks)
            ."\n\n👉 ".e(route('ipos.index'));
    }

    private function gmpMessage(IpoDigestBuilder $builder): ?string
    {
        $board = $builder->gmpBoard();
        if ($board->isEmpty()) {
            return null;
        }

        $lines = $board->map(function (array $row): string {
            $ipo = $row['ipo'];
            $change = $row['change'];
            $trend = $change ? ' '.($change > 0 ? '▲' : '▼').Ipo::num(abs($change)) : '';

            return '• <a href="'.e($ipo->url()).'">'.e($ipo->name).'</a> ('.e($ipo->typeLabel()).'): '
                .($ipo->gmp > 0 ? '+' : '').'₹'.Ipo::num($ipo->gmp)
                .' ('.number_format($ipo->gmpPercent() ?? 0, 1).'%)'.$trend;
        });

        return '📊 <b>IPO GMP board · '.e(now()->format('j M, g:i A'))."</b>\n\n"
            .$lines->implode("\n")
            ."\n\n<i>GMP is unofficial and indicative only.</i>\n👉 ".e(route('ipos.gmp'));
    }

    private function newIpoMessage(Ipo $ipo): string
    {
        $lines = ['🆕 <b>New IPO: '.e($ipo->name).'</b>', e($ipo->typeLabel().' · '.$ipo->exchangeLabel())];

        if ($ipo->open_date) {
            $lines[] = '📅 Opens '.e($ipo->open_date->format('j M'))
                .($ipo->close_date ? ', closes '.e($ipo->close_date->format('j M')) : '')
                .($ipo->listing_date ? ' · Lists '.e($ipo->listing_date->format('j M')) : '');
        }
        $lines[] = '💰 Price band '.e($ipo->priceBand()).($ipo->issue_size ? ' · Issue ₹'.Ipo::num($ipo->issue_size).' Cr' : '');
        if ($ipo->hasGmp()) {
            $lines[] = '📈 GMP '.($ipo->gmp > 0 ? '+' : '').'₹'.Ipo::num($ipo->gmp).' ('.number_format($ipo->gmpPercent() ?? 0, 1).'%)';
        }
        $lines[] = '👉 '.e($ipo->url());

        return implode("\n", $lines);
    }

    /**
     * @param  Collection<int, Ipo>  $ipos
     */
    private function block(string $title, Collection $ipos, bool $showGmp = true): ?string
    {
        if ($ipos->isEmpty()) {
            return null;
        }

        return $title."\n".$ipos->map(function (Ipo $ipo) use ($showGmp): string {
            $gmp = $showGmp && $ipo->hasGmp()
                ? ' · GMP '.($ipo->gmp > 0 ? '+' : '').'₹'.Ipo::num($ipo->gmp).' ('.number_format($ipo->gmpPercent() ?? 0, 1).'%)'
                : '';

            return '• <a href="'.e($ipo->url()).'">'.e($ipo->name).'</a> ('.e($ipo->typeLabel()).') · '.e($ipo->priceBand()).$gmp;
        })->implode("\n");
    }
}
