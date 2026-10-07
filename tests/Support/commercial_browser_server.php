<?php

// Run only with `php -S 127.0.0.1:PORT -t public tests/Support/commercial_browser_server.php`.
// This router authenticates a fixture actor against an exported scratch SQLite database.
if (PHP_SAPI !== 'cli-server' || !in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true)) {
    http_response_code(403); exit;
}
$root = dirname(__DIR__, 2);
$fixture = realpath(trim(file_get_contents($root.'/scratch/commercial-browser-path.txt')));
$scratch = realpath($root.'/scratch');
if (!$fixture || !$scratch || !str_starts_with($fixture, $scratch.DIRECTORY_SEPARATOR) || !str_ends_with($fixture, '.sqlite')) {
    throw new RuntimeException('Export a disposable commercial browser fixture first.');
}
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if (str_starts_with($uri, '/css/') || str_starts_with($uri, '/js/')) { return false; }
if (is_file($root.'/scratch/phase6-bootstrap.php')) {
    (static function ($path) { require $path; })($root.'/scratch/phase6-bootstrap.php');
} else {
    require $root.'/vendor/autoload.php';
}
$app = require $root.'/bootstrap/app.php';
$request = Illuminate\Http\Request::capture();
$app->instance('request', $request);
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$app->afterBootstrapping(Illuminate\Foundation\Bootstrap\LoadConfiguration::class, function () use ($fixture, $scratch) {
    config(['app.env' => 'testing', 'app.key' => 'base64:'.base64_encode(str_repeat('x', 32)),
        'database.default' => 'sqlite', 'database.connections.sqlite.database' => $fixture,
        'session.driver' => 'file', 'session.files' => $scratch.'/browser-sessions', 'cache.default' => 'array', 'queue.default' => 'sync',
        'commercial.enabled' => true, 'compliance.enabled' => false, 'operations.enabled' => false]);
});
$kernel->bootstrap();
if (!is_dir($scratch.'/browser-sessions')) { mkdir($scratch.'/browser-sessions'); }
Illuminate\Support\Facades\DB::purge('sqlite');
Carbon\CarbonImmutable::setTestNow('2026-10-03 06:00:00 UTC');
// Test-only capability adapter. Production gates remain unchanged.
$app->bind(App\Services\Platform\CapabilityService::class, fn () => new class extends App\Services\Platform\CapabilityService {
    public function enabled(string $key, App\Services\Platform\CompanyContext|int|null $company = null): bool { return true; }
    public function forNavigation(App\Services\Platform\CompanyContext|int|null $company = null): array { return ['sales.fast_counter', 'purchases.fast_entry']; }
});
$app->bind(App\Http\Middleware\Common::class, fn () => new class extends App\Http\Middleware\Common {
    public function handle(Illuminate\Http\Request $request, Closure $next) {
        Tests\Support\CommandCenterViewFixture::share();
        return $next($request);
    }
});
Illuminate\Support\Facades\Auth::setUser(App\Models\User::findOrFail(1));
$response = $kernel->handle($request);
$response->send();
$kernel->terminate($request, $response);
