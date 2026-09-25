<?php

use Illuminate\Http\Request;

/*
 * Vercel entry point (vercel-php runtime). The deployment filesystem is read-only
 * except /tmp, so storage, caches and the SQLite database live there. Each new
 * instance starts from the bundled database snapshot and refreshes it from the API.
 */

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

define('LARAVEL_START', microtime(true));

require __DIR__.'/../vendor/autoload.php';

$app = require_once __DIR__.'/../bootstrap/app.php';
$app->useStoragePath("{$tmp}/storage");

$app->handleRequest(Request::capture());
