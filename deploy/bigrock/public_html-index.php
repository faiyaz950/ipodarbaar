<?php

/*
 * Option B only (public_html can't be a symlink): copy this file to public_html/index.php
 * and set APP_PUBLIC_PATH=/home/<user>/public_html in the app's .env. It boots the app
 * kept outside the web root (default: ~/ipodarbar).
 */

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

$appPath = dirname(__DIR__).'/ipodarbar';

if (file_exists($maintenance = $appPath.'/storage/framework/maintenance.php')) {
    require $maintenance;
}

require $appPath.'/vendor/autoload.php';

/** @var Application $app */
$app = require_once $appPath.'/bootstrap/app.php';

$app->handleRequest(Request::capture());
