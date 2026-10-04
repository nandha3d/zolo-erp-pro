<?php

namespace App\Services\Deployment;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Process\Process;
use ZipArchive;

/** Operator-only instance backup. Every archive entry is encrypted; archives never enter public storage. */
class BackupService
{
    public function create(bool $localOnly = false): array
    {
        $password = $this->password();
        $disk = config('deployment.backup_disk');
        if (!$localOnly && !$disk) throw new RuntimeException('Configure an off-site backup disk or explicitly choose a local rehearsal.');
        if (DB::transactionLevel()) throw new RuntimeException('Backup must run outside a posting transaction.');
        $root = $this->privateRoot(config('deployment.backup_root'));
        $name = 'erp-backup-'.Str::uuid();
        $dump = $root.'/'.$name.'.database'; $archive = $root.'/'.$name.'.zip';
        $connection = DB::connection(); $driver = $connection->getDriverName();
        $zip = new ZipArchive();
        try {
            if ($driver === 'sqlite') {
                $connection->statement('VACUUM INTO '.$connection->getPdo()->quote($dump));
            } elseif ($driver === 'mysql') {
                $this->mysqlProcess(config('deployment.mysql_dump_binary'), $connection->getConfig(), [
                    '--single-transaction', '--set-gtid-purged=OFF', '--skip-add-drop-table', '--routines', '--triggers', '--events', '--hex-blob', '--no-tablespaces', '--result-file='.$dump,
                    $connection->getDatabaseName(),
                ])->mustRun();
            } else throw new RuntimeException('Backup supports SQLite rehearsals and MySQL deployments.');
            $files = ['database' => ['sha256' => hash_file('sha256', $dump)]];
            if ($zip->open($archive, ZipArchive::CREATE | ZipArchive::EXCL) !== true) throw new RuntimeException('Cannot create private backup archive.');
            $zip->setPassword($password);
            $zip->addFile($dump, 'database');
            foreach (config('deployment.upload_roots') as $index => $directory) {
                if (!is_dir($directory)) continue;
                $base = realpath($directory);
                if ($this->inside($root, $base)) throw new RuntimeException('Upload roots must not contain the backup directory.');
                $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($base, \FilesystemIterator::SKIP_DOTS));
                foreach ($iterator as $file) {
                    if ($file->isLink()) throw new RuntimeException('Review symlinked uploads before backup.');
                    if (!$file->isFile()) continue;
                    $relative = substr($file->getPathname(), strlen($base) + 1);
                    if (basename($relative) === '.env') throw new RuntimeException('Secret environment files must not be stored as uploads.');
                    $entry = 'uploads/'.$index.'/'.str_replace('\\', '/', $relative);
                    $files[$entry] = ['sha256' => hash_file('sha256', $file->getPathname())];
                    $zip->addFile($file->getPathname(), $entry);
                }
            }
            $tables = $driver === 'mysql' ? count($connection->select('SHOW TABLES'))
                : count($connection->select("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'"));
            $manifest = ['version' => 1, 'driver' => $driver, 'created_at' => now()->toIso8601String(), 'table_count' => $tables, 'files' => $files];
            $zip->addFromString('manifest.json', json_encode($manifest, JSON_THROW_ON_ERROR));
            for ($i = 0; $i < $zip->numFiles; $i++) {
                if (!$zip->setEncryptionIndex($i, ZipArchive::EM_AES_256, $password)) throw new RuntimeException('AES archive encryption is unavailable.');
            }
            if (!$zip->close()) throw new RuntimeException('Backup archive did not finish.');
            chmod($archive, 0600);
            // Verify every encrypted entry before declaring success or retiring an older backup.
            $this->verify($archive);
            $offsite = null;
            if (!$localOnly) {
                $offsite = 'erp-backups/'.$name.'.zip';
                $stream = fopen($archive, 'rb');
                try {
                    if (!Storage::disk($disk)->put($offsite, $stream, ['visibility' => 'private'])) throw new RuntimeException('Off-site backup upload failed.');
                } finally { fclose($stream); }
                $stream = Storage::disk($disk)->readStream($offsite);
                if (!is_resource($stream)) throw new RuntimeException('Cannot verify off-site backup.');
                try { $hash = hash_init('sha256'); hash_update_stream($hash, $stream); $remoteHash = hash_final($hash); }
                finally { fclose($stream); }
                if (!hash_equals(hash_file('sha256', $archive), $remoteHash)) throw new RuntimeException('Off-site backup checksum differs.');
            }
            $receipt = ['version' => 1, 'archive' => basename($archive), 'archive_sha256' => hash_file('sha256', $archive),
                'completed_at' => now()->toIso8601String(), 'offsite_verified' => !$localOnly, 'offsite_path' => $offsite, 'table_count' => $tables];
            file_put_contents($archive.'.receipt.json', json_encode($receipt, JSON_THROW_ON_ERROR)); chmod($archive.'.receipt.json', 0600);
            if (!$localOnly) $this->retain($root);
            Log::info('erp.backup_completed', ['archive_sha256' => $receipt['archive_sha256'], 'offsite_verified' => !$localOnly]);
            return $receipt + ['path' => $archive];
        } finally {
            if (is_file($dump)) unlink($dump);
        }
    }

    public function verify(string $archive): array
    {
        $zip = new ZipArchive();
        if ($zip->open($archive) !== true) throw new RuntimeException('Cannot open backup archive.');
        try {
            $zip->setPassword($this->password());
            $json = $zip->getFromName('manifest.json');
            if ($json === false) throw new RuntimeException('Backup password or manifest is invalid.');
            $manifest = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
            if (($manifest['version'] ?? null) !== 1 || !in_array($manifest['driver'] ?? '', ['sqlite', 'mysql'], true)
                || !isset($manifest['files']['database']) || $zip->numFiles !== count($manifest['files']) + 1) throw new RuntimeException('Unexpected backup manifest.');
            foreach (['manifest.json' => null] + $manifest['files'] as $name => $file) {
                if (($name !== 'manifest.json' && $name !== 'database' && !preg_match('#\Auploads/[0-9]+/(?!/)[^\\\\]+\z#D', $name))
                    || str_contains($name, ':') || str_contains($name, "\0")
                    || array_intersect(['..', '.', ''], explode('/', $name))) throw new RuntimeException('Unsafe archive entry.');
                $stat = $zip->statName($name);
                if (($stat['encryption_method'] ?? null) !== ZipArchive::EM_AES_256) throw new RuntimeException('Backup contains an unencrypted entry.');
                if ($file === null) continue;
                $stream = $zip->getStream($name);
                if (!is_resource($stream)) throw new RuntimeException('Cannot decrypt backup entry.');
                try { $hash = hash_init('sha256'); hash_update_stream($hash, $stream); $actual = hash_final($hash); }
                finally { fclose($stream); }
                if (!hash_equals($file['sha256'], $actual)) throw new RuntimeException('Backup entry checksum differs.');
            }
            return $manifest;
        } finally { $zip->close(); }
    }

    public function restoreRehearsal(string $archive): array
    {
        $manifest = $this->verify($archive);
        $root = $this->privateRoot(config('deployment.restore_root')).'/'.Str::uuid();
        mkdir($root, 0700);
        $zip = new ZipArchive(); $zip->open($archive); $zip->setPassword($this->password());
        try {
            foreach ($manifest['files'] as $name => $file) {
                $target = $root.'/'.$name;
                if (!is_dir(dirname($target))) mkdir(dirname($target), 0700, true);
                $input = $zip->getStream($name); $output = fopen($target, 'xb');
                try { stream_copy_to_stream($input, $output); } finally { fclose($input); fclose($output); }
                chmod($target, 0600);
                if (!hash_equals($file['sha256'], hash_file('sha256', $target))) throw new RuntimeException('Restored file checksum differs.');
            }
        } finally { $zip->close(); }
        if ($manifest['driver'] === 'sqlite') {
            $pdo = new \PDO('sqlite:'.$root.'/database');
            if ($pdo->query('PRAGMA integrity_check')->fetchColumn() !== 'ok') throw new RuntimeException('Restored SQLite integrity check failed.');
            $tables = (int) $pdo->query("SELECT COUNT(*) FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'")->fetchColumn();
        } else {
            $config = config('deployment.restore_mysql');
            if (!preg_match('/\Azolo_test_restore_[a-z0-9_]+\z/D', $config['database'] ?? '')
                || $config['database'] === DB::connection()->getDatabaseName()) throw new RuntimeException('Restore requires a separate zolo_test_restore_* database.');
            config(['database.connections.erp_restore' => $config]); DB::purge('erp_restore');
            $connection = DB::connection('erp_restore');
            foreach ($connection->select('SHOW GRANTS FOR CURRENT_USER') as $grant) {
                $values = (array) $grant; $grant = reset($values);
                if (!preg_match('/GRANT (.+) ON (.+) TO /i', $grant, $match)
                    || ($match[1] !== 'USAGE' && $match[2] !== '`'.$config['database'].'`.*')) throw new RuntimeException('Restore user must have privileges only on the rehearsal database.');
            }
            if ($connection->select('SHOW TABLES')) throw new RuntimeException('Restore rehearsal database must be empty; no tables are dropped.');
            $input = fopen($root.'/database', 'rb');
            try { $this->mysqlProcess(config('deployment.mysql_binary'), $config, ['--database='.$config['database']])->setInput($input)->mustRun(); }
            finally { fclose($input); }
            $tables = count($connection->select('SHOW TABLES'));
        }
        if ($tables !== $manifest['table_count']) throw new RuntimeException('Restored table count differs.');
        $proof = ['archive_sha256' => hash_file('sha256', $archive), 'restored_at' => now()->toIso8601String(), 'table_count' => $tables,
            'files_verified' => count($manifest['files']), 'driver' => $manifest['driver'], 'root' => $root];
        file_put_contents($archive.'.restore.json', json_encode($proof, JSON_THROW_ON_ERROR)); chmod($archive.'.restore.json', 0600);
        return $proof;
    }

    public function latestReceipt(): ?array
    {
        $rows = [];
        foreach (glob(config('deployment.backup_root').'/*.zip.receipt.json') ?: [] as $file) {
            $receipt = json_decode(file_get_contents($file), true);
            $archive = substr($file, 0, -strlen('.receipt.json'));
            if (($receipt['version'] ?? null) === 1 && is_file($archive) && hash_equals($receipt['archive_sha256'] ?? '', hash_file('sha256', $archive))) {
                $proof = is_file($archive.'.restore.json') ? json_decode(file_get_contents($archive.'.restore.json'), true) : null;
                $receipt['restore_verified'] = $proof && ($proof['archive_sha256'] ?? null) === $receipt['archive_sha256'];
                $rows[] = $receipt;
            }
        }
        usort($rows, fn ($a, $b) => strcmp($b['completed_at'], $a['completed_at']));
        return $rows[0] ?? null;
    }

    private function password(): string
    {
        $password = config('deployment.backup_password');
        if (!is_string($password) || strlen($password) < 32) throw new RuntimeException('A backup password of at least 32 characters is required. Store it separately from backups.');
        return $password;
    }

    private function privateRoot(string $path): string
    {
        $probe = $path;
        while (!is_dir($probe)) { $parent = dirname($probe); if ($parent === $probe) throw new RuntimeException('Private directory is invalid.'); $probe = $parent; }
        $publicRoots = [public_path(), storage_path('app/public')];
        foreach ($publicRoots as $publicRoot) {
            if ($this->inside($path, $publicRoot) || $this->inside(realpath($probe), realpath($publicRoot) ?: $publicRoot)) {
                throw new RuntimeException('Backup and restore files must be outside public storage.');
            }
        }
        if (!is_dir($path) && !mkdir($path, 0700, true)) throw new RuntimeException('Private backup directory is unavailable.');
        $root = realpath($path);
        if (!$root) throw new RuntimeException('Private backup directory is unavailable.');
        foreach ($publicRoots as $publicRoot) if ($this->inside($root, realpath($publicRoot) ?: $publicRoot)) {
            throw new RuntimeException('Backup and restore files must be outside public storage.');
        }
        if (!chmod($root, 0700)) throw new RuntimeException('Private directory permissions are unavailable.');
        return $root;
    }

    private function inside(string $path, string $parent): bool
    {
        $path = strtolower(str_replace('\\', '/', $path)); $parent = rtrim(strtolower(str_replace('\\', '/', $parent)), '/');
        return $path === $parent || str_starts_with($path, $parent.'/');
    }

    private function mysqlProcess(string $binary, array $config, array $arguments): Process
    {
        return (new Process([$binary, '--host='.$config['host'], '--port='.($config['port'] ?? 3306), '--user='.$config['username'], ...$arguments],
            null, ['MYSQL_PWD' => $config['password'] ?? '']))->setTimeout(3600)->disableOutput();
    }

    private function retain(string $root): void
    {
        foreach (glob($root.'/erp-backup-*.zip.receipt.json') ?: [] as $file) {
            $receipt = json_decode(file_get_contents($file), true); $archive = substr($file, 0, -strlen('.receipt.json'));
            if (($receipt['offsite_verified'] ?? false) && isset($receipt['completed_at']) && strtotime($receipt['completed_at']) < now()->subDays(config('deployment.backup_retention_days'))->timestamp
                && is_file($archive) && hash_equals($receipt['archive_sha256'] ?? '', hash_file('sha256', $archive))) {
                unlink($archive); unlink($file);
                if (is_file($archive.'.restore.json')) unlink($archive.'.restore.json');
            }
        }
    }
}
