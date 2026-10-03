<?php

/*
 * Vercel entrypoint for SheManagerGame (vercel-php runtime).
 *
 * Every request is rewritten here by vercel.json, then handed to Laravel's
 * normal front controller (public/index.php) — exactly like `php artisan serve`.
 */

// The PHP runtime reports SCRIPT_NAME as /api/index.php; Laravel derives its
// base URL from it and would strip /api from every path (all routes 404).
// Reset it so Laravel sees the real URLs.
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['PHP_SELF'] = '/index.php';

// Vercel Functions run on a read-only filesystem (only /tmp is writable), so
// point every file-based cache at /tmp and log to stderr (Vercel Function Logs).
// Each is guarded so an explicit env var set in the Vercel dashboard wins.
if (!getenv('APP_CONFIG_CACHE'))    putenv('APP_CONFIG_CACHE=/tmp/config.php');
if (!getenv('APP_ROUTES_CACHE'))    putenv('APP_ROUTES_CACHE=/tmp/routes-v7.php');
if (!getenv('APP_EVENTS_CACHE'))    putenv('APP_EVENTS_CACHE=/tmp/events.php');
if (!getenv('APP_PACKAGES_CACHE'))  putenv('APP_PACKAGES_CACHE=/tmp/packages.php');
if (!getenv('APP_SERVICES_CACHE'))  putenv('APP_SERVICES_CACHE=/tmp/services.php');
if (!getenv('VIEW_COMPILED_PATH'))  putenv('VIEW_COMPILED_PATH=/tmp/views');
if (!getenv('LOG_CHANNEL'))         putenv('LOG_CHANNEL=stderr');
if (!getenv('SESSION_DRIVER'))      putenv('SESSION_DRIVER=database');
if (!getenv('CACHE_STORE'))         putenv('CACHE_STORE=database');
if (!getenv('QUEUE_CONNECTION'))    putenv('QUEUE_CONNECTION=sync');
if (!getenv('VERCEL_STORAGE_PATH')) putenv('VERCEL_STORAGE_PATH=/tmp/storage');

require __DIR__.'/../public/index.php';
