<?php

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// Determine if the application is in maintenance mode...
// DIPERBAIKI (CATATAN-struktur-repo-baru): sebelumnya '/../storage/...'
// (warisan asumsi index.php di dalam public/, storage satu level di atas).
// Sekarang index.php di root repo, storage ada di core/storage — path
// lama menunjuk SATU LEVEL DI ATAS ROOT REPO (di luar proyek), membuat
// maintenance-mode diam-diam tidak pernah terdeteksi.
if (file_exists($maintenance = __DIR__.'/core/storage/framework/maintenance.php')) {
    require $maintenance;
}

// Register the Composer autoloader...
require __DIR__.'/core/vendor/autoload.php';

// Bootstrap Laravel and handle the request...
/** @var Application $app */
$app = require_once __DIR__.'/core/bootstrap/app.php';
$app->usePublicPath(__DIR__);   // benar — public path baru = root repo

$kernel = $app->make(Kernel::class);
$response = $kernel->handle($request = Request::capture())->send();
$kernel->terminate($request, $response);
