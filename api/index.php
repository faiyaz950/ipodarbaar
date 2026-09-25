<?php

use Illuminate\Http\Request;

/*
 * Vercel entry point (vercel-php runtime). The deployment filesystem is read-only
 * except /tmp, so storage, caches and the SQLite database live there. Each new
 * instance starts from the bundled database snapshot and refreshes it from the API.
 */

ini_set('display_errors', '0');

// Variables imported into Vercel from .env.example can be blank; a blank value would
// override the config defaults (e.g. APP_TIMEZONE='' breaks date_default_timezone_set).
foreach (array_merge(getenv(), $_ENV, $_SERVER) as $key => $value) {
    if ($value === '' && is_string($key) && preg_match('/^[A-Z][A-Z0-9_]*$/', $key)) {
        putenv($key);
        unset($_ENV[$key], $_SERVER[$key]);
    }
}

$tmp = '/tmp/ipodarbar';

foreach (['storage/framework/views', 'storage/framework/cache/data', 'storage/framework/sessions', 'storage/logs'] as $dir) {
    if (! is_dir("{$tmp}/{$dir}")) {
        mkdir("{$tmp}/{$dir}", 0755, true);
    }
}

if (! is_file("{$tmp}/database.sqlite")) {
    copy(__DIR__.'/../database/vercel.sqlite', "{$tmp}/database.sqlite");
}

$setEnv = function (string $key, string $value, bool $force = true): void {
    if (! $force && getenv($key) !== false) {
        return;
    }
    putenv("{$key}={$value}");
    $_ENV[$key] = $_SERVER[$key] = $value;
};

$setEnv('APP_ENV', 'production', false);
$setEnv('APP_DEBUG', 'false', false);
$setEnv('DB_CONNECTION', 'sqlite');
$setEnv('DB_DATABASE', "{$tmp}/database.sqlite");
$setEnv('CACHE_STORE', 'database');
$setEnv('SESSION_DRIVER', 'cookie');
$setEnv('LOG_CHANNEL', 'stderr');
$setEnv('QUEUE_CONNECTION', 'sync');
foreach (['CONFIG', 'EVENTS', 'PACKAGES', 'ROUTES', 'SERVICES'] as $cache) {
    $setEnv("APP_{$cache}_CACHE", "{$tmp}/".strtolower($cache).'.php');
}

// TLS ends at Vercel's edge; without this, asset URLs would be generated as http://.
if (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https') {
    $_SERVER['HTTPS'] = 'on';
}

$diag = str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/__diag-7f3a9c');
if ($diag) {
    $setEnv('LOG_CHANNEL', 'single');
    $setEnv('LOG_LEVEL', 'debug');
}

define('LARAVEL_START', microtime(true));

require __DIR__.'/../vendor/autoload.php';

$app = require_once __DIR__.'/../bootstrap/app.php';
$app->useStoragePath("{$tmp}/storage");

if ($diag) {
    $key = (string) getenv('APP_KEY');
    $raw = str_starts_with($key, 'base64:') ? base64_decode(substr($key, 7), true) : $key;
    $log = "{$tmp}/storage/logs/laravel.log";
    @unlink($log);
    try {
        $status = $app->make(Illuminate\Contracts\Http\Kernel::class)->handle(Request::create('/'))->getStatusCode();
    } catch (Throwable $e) {
        $status = get_class($e).': '.$e->getMessage();
    }
    header('Content-Type: text/plain', true, 200);
    echo json_encode([
        'php' => PHP_VERSION,
        'pdo_drivers' => PDO::getAvailableDrivers(),
        'sqlite3' => extension_loaded('sqlite3'),
        'app_key_set' => $key !== '',
        'app_key_base64_prefix' => str_starts_with($key, 'base64:'),
        'app_key_bytes' => $raw === false ? 'invalid base64' : strlen($raw),
        'db_copied' => is_file("{$tmp}/database.sqlite"),
        'home_status' => $status,
    ], JSON_PRETTY_PRINT)."\n\n";
    echo is_file($log) ? substr(file_get_contents($log), 0, 4000) : 'no log';
    exit;
}

$app->handleRequest(Request::capture());
