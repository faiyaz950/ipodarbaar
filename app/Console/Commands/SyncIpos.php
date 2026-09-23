<?php

namespace App\Console\Commands;

use App\Models\Ipo;
use App\Services\IpoSyncService;
use App\Services\LogoService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('ipo:sync {--full : Sync every page instead of only the most recent IPOs} {--no-logos : Skip building logo thumbnails}')]
#[Description('Sync IPO data from the IPO API into the local database')]
class SyncIpos extends Command
{
    public function handle(IpoSyncService $sync, LogoService $logos): int
    {
        $full = (bool) $this->option('full');
        $this->info($full ? 'Running full IPO sync…' : 'Syncing recent IPOs…');

        try {
            $result = $sync->sync(
                $full ? null : (int) config('ipodarbar.ipo_api.recent_pages', 3),
                fn (int $page, int $fetched, int $total) => $this->line("  page {$page}: {$fetched}/{$total}")
            );
        } catch (Throwable $e) {
            $this->error('Sync failed: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info("Done. {$result['fetched']} IPOs synced across {$result['pages']} page(s).");

        if (! $this->option('no-logos')) {
            $made = $logos->warm(Ipo::active()->get());
            $this->line("  {$made} new logo thumbnail(s) built.");
        }

        return self::SUCCESS;
    }
}
