<?php

// Local-only router for an explicitly exported disposable fixture; never a configured database.
if (PHP_SAPI !== 'cli-server' || !in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true)) { http_response_code(403); exit; }
$root = dirname(__DIR__, 2);
$scratch = realpath($root.'/scratch');
$fixture = realpath(trim(file_get_contents($root.'/scratch/operations-browser-path.txt')));
if (!$fixture || !$scratch || !str_starts_with($fixture, $scratch.DIRECTORY_SEPARATOR) || !str_ends_with($fixture, '.sqlite')) {
    throw new RuntimeException('Export a disposable operations browser fixture first.');
}
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if (str_starts_with($uri, '/css/') || str_starts_with($uri, '/js/')) return false;
require $root.'/scratch/operations-bootstrap.php';
$app = require $root.'/bootstrap/app.php';
$request = Illuminate\Http\Request::capture(); $app->instance('request', $request);
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$app->afterBootstrapping(Illuminate\Foundation\Bootstrap\LoadConfiguration::class, function () use ($fixture, $scratch) {
    config(['app.env' => 'testing', 'app.key' => 'base64:'.base64_encode(str_repeat('x', 32)),
        'database.default' => 'sqlite', 'database.connections.sqlite.database' => $fixture,
        'session.driver' => 'file', 'session.files' => $scratch.'/operations-sessions', 'cache.default' => 'array',
        'queue.default' => 'sync', 'operations.enabled' => true, 'commercial.enabled' => true, 'debugbar.enabled' => false]);
});
$kernel->bootstrap();
if (!is_dir($scratch.'/operations-sessions')) mkdir($scratch.'/operations-sessions');
Illuminate\Support\Facades\DB::purge('sqlite');
Carbon\CarbonImmutable::setTestNow('2026-10-03 06:00:00 UTC');
// Fixture-only gate override; capability configuration and dependencies are still exercised.
$app->bind(App\Services\Platform\CapabilityService::class, fn () => new class extends App\Services\Platform\CapabilityService {
    protected function optionalActivationReady(): bool { return true; }
});
Illuminate\Support\Facades\Auth::setUser(App\Models\User::findOrFail(1));
$response = $kernel->handle($request); $response->send(); $kernel->terminate($request, $response);
