<?php

// Loopback-only authenticated server for an exported disposable browser fixture.
if (PHP_SAPI !== 'cli-server' || !in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true)) {
    http_response_code(403); exit;
}
$root = dirname(__DIR__, 2); $scratch = realpath($root.'/scratch');
$fixture = realpath(trim(file_get_contents($root.'/scratch/compliance-browser-path.txt')));
if (!$fixture || !$scratch || !str_starts_with($fixture, $scratch.DIRECTORY_SEPARATOR) || !str_ends_with($fixture, '.sqlite')) {
    throw new RuntimeException('Export an isolated compliance fixture first.');
}
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if (str_starts_with($uri, '/css/') || str_starts_with($uri, '/js/')) return false;
require $root.'/vendor/autoload.php'; $app = require $root.'/bootstrap/app.php';
$request = Illuminate\Http\Request::capture(); $app->instance('request', $request);
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$app->afterBootstrapping(Illuminate\Foundation\Bootstrap\LoadConfiguration::class, function () use ($fixture, $scratch) {
    config(['app.env' => 'testing', 'app.key' => 'base64:'.base64_encode(str_repeat('x', 32)),
        'database.default' => 'sqlite', 'database.connections.sqlite.database' => $fixture,
        'session.driver' => 'file', 'session.files' => $scratch.'/compliance-browser-sessions', 'cache.default' => 'array',
        'queue.default' => 'sync', 'commercial.enabled' => true, 'compliance.enabled' => true]);
});
$kernel->bootstrap(); if (!is_dir($scratch.'/compliance-browser-sessions')) mkdir($scratch.'/compliance-browser-sessions');
Illuminate\Support\Facades\DB::purge('sqlite'); Carbon\CarbonImmutable::setTestNow('2026-10-04 06:00:00 UTC');
$app->bind(App\Services\Platform\CapabilityService::class, fn () => new class extends App\Services\Platform\CapabilityService {
    public function enabled(string $key, App\Services\Platform\CompanyContext|int|null $company = null): bool { return true; }
});
Illuminate\Support\Facades\Auth::setUser(App\Models\User::findOrFail(1));
$response = $kernel->handle($request); $response->send(); $kernel->terminate($request, $response);
