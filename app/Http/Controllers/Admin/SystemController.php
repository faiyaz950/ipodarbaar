<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\SystemTestMail;
use App\Services\IpoSyncService;
use App\Services\TelegramService;
use App\Support\SchedulerHeartbeat;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;
use Throwable;

/**
 * Hosting health and one-click maintenance for shared hosting without a shell.
 */
class SystemController extends Controller
{
    public function index(Migrator $migrator, TelegramService $telegram): View
    {
        $ran = $migrator->repositoryExists() ? $migrator->getRepository()->getRan() : [];
        $pending = array_diff(array_keys($migrator->getMigrationFiles(database_path('migrations'))), $ran);

        return view('admin.system', [
            'checks' => $this->checks(),
            'pendingMigrations' => array_values($pending),
            'lastSynced' => IpoSyncService::lastSyncedAt(),
            'lastBeat' => SchedulerHeartbeat::lastBeatAt(),
            'cronAlive' => SchedulerHeartbeat::isAlive(),
            'queue' => [
                'pending' => DB::table('jobs')->count(),
                'failed' => DB::table('failed_jobs')->count(),
            ],
            'mailer' => config('mail.default'),
            'telegramReady' => $telegram->configured(),
            'cronLine' => '* * * * * cd '.base_path().' && '.$this->cliPhp().' artisan schedule:run >> /dev/null 2>&1',
        ]);
    }

    public function migrate(): RedirectResponse
    {
        try {
            Artisan::call('migrate', ['--force' => true]);
        } catch (Throwable $e) {
            return back()->with('error', 'Migration failed: '.$e->getMessage());
        }

        return back()->with('status', 'Migrations are up to date.');
    }

    /**
     * Rebuilds config, route, view and event caches. The application cache (sync time,
     * settings, news) is deliberately left alone.
     */
    public function optimize(): RedirectResponse
    {
        foreach (['config:clear', 'route:clear', 'view:clear', 'event:clear', 'optimize'] as $command) {
            Artisan::call($command);
        }

        return back()->with('status', 'Caches rebuilt.');
    }

    public function testMail(Request $request): RedirectResponse
    {
        try {
            Mail::to($request->user())->send(new SystemTestMail);
        } catch (Throwable $e) {
            return back()->with('error', 'Email failed: '.$e->getMessage());
        }

        return back()->with('status', 'Test email sent to '.$request->user()->email.'.');
    }

    public function testTelegram(TelegramService $telegram): RedirectResponse
    {
        try {
            $telegram->sendMessage('✅ <b>IPO Darbaar</b>: test message from the admin panel.');
        } catch (Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', 'Test message posted to the Telegram channel.');
    }

    /**
     * @return array<int, array{label: string, ok: bool, detail: string, required: bool}>
     */
    private function checks(): array
    {
        $gd = function_exists('gd_info') ? gd_info() : [];
        $disabled = array_map('trim', explode(',', (string) ini_get('disable_functions')));
        $writable = fn (string $path): bool => is_dir($path) ? is_writable($path) : is_writable(dirname($path));

        return [
            ['label' => 'PHP 8.3+', 'ok' => version_compare(PHP_VERSION, '8.3.0', '>='), 'detail' => PHP_VERSION, 'required' => true],
            ['label' => 'Database connection', 'ok' => $this->databaseReachable(), 'detail' => config('database.default'), 'required' => true],
            ['label' => 'pdo_mysql', 'ok' => extension_loaded('pdo_mysql'), 'detail' => 'MySQL driver', 'required' => config('database.default') === 'mysql'],
            ['label' => 'GD + WebP', 'ok' => function_exists('imagewebp'), 'detail' => 'Logo thumbnails', 'required' => false],
            ['label' => 'GD FreeType', 'ok' => (bool) ($gd['FreeType Support'] ?? false), 'detail' => 'Share-card images', 'required' => false],
            ['label' => 'proc_open', 'ok' => function_exists('proc_open') && ! in_array('proc_open', $disabled, true), 'detail' => 'Scheduler runs commands', 'required' => true],
            ['label' => 'storage/ writable', 'ok' => is_writable(storage_path()), 'detail' => storage_path(), 'required' => true],
            ['label' => 'public/logos writable', 'ok' => $writable(public_path('logos')), 'detail' => public_path('logos'), 'required' => false],
            ['label' => 'Debug mode off', 'ok' => ! config('app.debug') || ! app()->isProduction(), 'detail' => 'APP_DEBUG', 'required' => true],
            ['label' => 'HTTPS app URL', 'ok' => str_starts_with((string) config('app.url'), 'https://') || ! app()->isProduction(), 'detail' => (string) config('app.url'), 'required' => false],
        ];
    }

    private function databaseReachable(): bool
    {
        try {
            DB::connection()->getPdo();

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Web requests run under PHP-FPM; cron needs the matching CLI binary next to it.
     */
    private function cliPhp(): string
    {
        $binary = PHP_BINARY;

        if (str_contains(basename($binary), 'fpm')) {
            $candidate = dirname($binary, 2).'/bin/php';

            return is_file($candidate) ? $candidate : 'php';
        }

        return $binary;
    }
}
