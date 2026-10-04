<?php

namespace App\Http\Controllers;
use App\Http\Requests\InstallationRequest;
use App\Traits\ENVFilePutContent;
use Exception;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

class InstallController extends Controller
{
    use ENVFilePutContent;

    public function installStep1()
    {
        return view('install.step_1');
    }

    public function installStep2()
    {
        return view('install.step_2');
    }
    public function installStep3()
    {
        return view('install.step_3');
    }

    public function installProcess(InstallationRequest $request)
    {
        // Owner confirmed on 2026-10-04 that the SalePro license permits this fork/rebranding
        // and removal of purchase-code verification. See PHASE_4C_AUTHORITATIVE_CUTOVER.md.

        $envPath = base_path('.env');
        if (!file_exists($envPath))
            return redirect()->back()->withErrors(['errors' => ['.env file does not exist.']]);
        elseif (!is_readable($envPath))
            return redirect()->back()->withErrors(['errors' => ['.env file is not readable.']]);
        elseif (!is_writable($envPath))
            return redirect()->back()->withErrors(['errors' => ['.env file is not writable.']]);
        else {
            try {
                $this->envSetDatabaseCredentials($request);
                self::switchToNewDatabaseConnection($request);

                // Gunakan Laravel Migration dan Seeder
                Artisan::call('migrate', ['--force' => true]);
                Artisan::call('db:seed', ['--force' => true]);

                self::optimizeClear();
                return redirect(url('/install/step-4'));

            } catch (Exception $e) {

                return redirect()->back()->withErrors(['errors' => [$e->getMessage()]]);
            }
        }
    }

protected function envSetDatabaseCredentials($request): void
    {
        $this->dataWriteInENVFile('APP_URL', url('/'));
        $this->dataWriteInENVFile('DB_HOST', $request->db_host);
        $this->dataWriteInENVFile('DB_DATABASE', $request->db_name);
        $this->dataWriteInENVFile('DB_USERNAME', $request->db_username);
        $this->dataWriteInENVFile('DB_PASSWORD', $request->db_password);
    }

    public function switchToNewDatabaseConnection($request): void
    {
        DB::purge('mysql');
        Config::set('database.connections.mysql.host', $request->db_host);
        Config::set('database.connections.mysql.database', $request->db_name);
        Config::set('database.connections.mysql.username', $request->db_username);
        Config::set('database.connections.mysql.password', $request->db_password);
    }

    protected static function importCentralDatabase($dbdata): void
    {
        DB::unprepared($dbdata);
    }

    protected static function optimizeClear(): void
    {
        Artisan::call('optimize:clear');
    }

    public function installStep4()
    {
        // Buat file lock untuk menandakan instalasi sudah selesai
        $lockFile = storage_path('installed.lock');
        if (!file_exists($lockFile)) {
            file_put_contents($lockFile, 'Installed on: ' . now()->toDateTimeString());
        }

        return view('install.step_4');
    }

    /**
     * Cek apakah aplikasi sudah terinstall
     */
    public static function isInstalled(): bool
    {
        return file_exists(storage_path('installed.lock'));
    }

}
