<?php

namespace App\Services;

use App\Models\Tenant;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class TenantBackupService
{
    /**
     * @return list<array{file: string, size: int, size_label: string, created_at: string}>
     */
    public function list(Tenant $tenant): array
    {
        $dir = $this->backupDir($tenant);
        if (! is_dir($dir)) {
            return [];
        }

        $items = [];
        $paths = array_merge(
            glob($dir.'/*.sql') ?: [],
            glob($dir.'/*.sql.gz') ?: [],
        );
        foreach ($paths as $path) {
            $file = basename($path);
            if (! $this->isValidFilename($file)) {
                continue;
            }

            $mtime = filemtime($path) ?: time();
            $size = filesize($path) ?: 0;

            $items[] = [
                'file' => $file,
                'size' => $size,
                'size_label' => $this->formatBytes($size),
                'created_at' => date('Y-m-d H:i:s', $mtime),
            ];
        }

        usort($items, fn (array $a, array $b) => strcmp($b['created_at'], $a['created_at']));

        return $items;
    }

    /**
     * @return array{file: string, size: int, size_label: string, created_at: string}
     */
    public function create(Tenant $tenant): array
    {
        $this->assertBackupSupported();

        $dir = $this->backupDir($tenant);
        File::ensureDirectoryExists($dir);

        $file = $this->makeFilename($tenant);
        $path = $dir.'/'.$file;

        if ($this->usesMysql()) {
            $sql = $this->dumpMysqlDatabase($tenant);
            file_put_contents($path, gzencode($sql, 9));
        } else {
            $this->copySqliteDatabase($tenant, $path);
        }

        clearstatcache(true, $path);
        $size = filesize($path) ?: 0;

        return [
            'file' => $file,
            'size' => $size,
            'size_label' => $this->formatBytes($size),
            'created_at' => date('Y-m-d H:i:s'),
        ];
    }

    public function download(Tenant $tenant, string $file): BinaryFileResponse
    {
        $path = $this->resolveBackupPath($tenant, $file);

        return response()->download($path, $file, [
            'Content-Type' => str_ends_with($file, '.gz') ? 'application/gzip' : 'application/sql',
        ]);
    }

    public function restore(Tenant $tenant, string $file): void
    {
        $this->assertBackupSupported();

        $path = $this->resolveBackupPath($tenant, $file);

        if ($this->usesMysql()) {
            $sql = $this->readSqlPayload($path);
            $this->importMysqlDatabase($tenant, $sql);
        } else {
            $this->restoreSqliteDatabase($tenant, $path);
        }
    }

    public function restoreUpload(Tenant $tenant, UploadedFile $upload): void
    {
        $this->assertBackupSupported();

        $original = strtolower($upload->getClientOriginalExtension());
        $name = strtolower($upload->getClientOriginalName());
        $allowed = in_array($original, ['sql', 'gz'], true)
            || str_ends_with($name, '.sql')
            || str_ends_with($name, '.sql.gz');

        if (! $allowed) {
            throw ValidationException::withMessages([
                'backup' => 'Upload a .sql or .sql.gz database backup file.',
            ]);
        }

        $tmp = $upload->getRealPath();
        if (! $tmp || ! is_file($tmp)) {
            throw ValidationException::withMessages([
                'backup' => 'Could not read the uploaded backup file.',
            ]);
        }

        if ($this->usesMysql()) {
            $sql = $this->readSqlPayload($tmp);
            $this->importMysqlDatabase($tenant, $sql);
        } else {
            $this->restoreSqliteDatabase($tenant, $tmp);
        }
    }

    protected function backupDir(Tenant $tenant): string
    {
        return storage_path('app/platform/tenant-backups/'.$tenant->id);
    }

    protected function databaseName(Tenant $tenant): string
    {
        return $tenant->database()->getName();
    }

    protected function makeFilename(Tenant $tenant): string
    {
        $code = preg_replace('/[^a-z0-9\-]+/', '-', strtolower($tenant->code)) ?: 'tenant';

        return $code.'_'.now()->format('Ymd_His').'.sql.gz';
    }

    protected function resolveBackupPath(Tenant $tenant, string $file): string
    {
        $file = basename($file);
        if (! $this->isValidFilename($file)) {
            abort(404);
        }

        $path = $this->backupDir($tenant).'/'.$file;
        if (! is_file($path)) {
            abort(404);
        }

        return $path;
    }

    protected function isValidFilename(string $file): bool
    {
        return (bool) preg_match('/^[a-z0-9\-]+_\d{8}_\d{6}\.sql(\.gz)?$/', $file);
    }

    protected function usesMysql(): bool
    {
        return in_array($this->driver(), ['mysql', 'mariadb'], true);
    }

    protected function driver(): string
    {
        $connection = config('database.default');
        $driver = config("database.connections.{$connection}.driver");

        if (in_array($driver, ['mysql', 'mariadb', 'sqlite'], true)) {
            return $driver;
        }

        $mysql = config('database.connections.mysql.driver');

        return in_array($mysql, ['mysql', 'mariadb'], true) ? $mysql : (string) $driver;
    }

    /**
     * @return array{host: string, port: string, username: string, password: string, unix_socket: string}
     */
    protected function mysqlCredentials(): array
    {
        $connection = config('database.default');
        if (! in_array(config("database.connections.{$connection}.driver"), ['mysql', 'mariadb'], true)) {
            $connection = 'mysql';
        }

        $config = config("database.connections.{$connection}");

        return [
            'host' => (string) ($config['host'] ?? '127.0.0.1'),
            'port' => (string) ($config['port'] ?? '3306'),
            'username' => (string) ($config['username'] ?? ''),
            'password' => (string) ($config['password'] ?? ''),
            'unix_socket' => (string) ($config['unix_socket'] ?? ''),
        ];
    }

    protected function assertBackupSupported(): void
    {
        if ($this->usesMysql()) {
            $which = Process::run(['which', 'mysqldump']);
            if (! $which->successful()) {
                throw ValidationException::withMessages([
                    'backup' => 'mysqldump is not available on this server.',
                ]);
            }

            $mysql = Process::run(['which', 'mysql']);
            if (! $mysql->successful()) {
                throw ValidationException::withMessages([
                    'backup' => 'mysql client is not available on this server.',
                ]);
            }

            return;
        }

        if ($this->driver() !== 'sqlite') {
            throw ValidationException::withMessages([
                'backup' => 'Backups are only supported for MySQL/MariaDB and SQLite tenant databases.',
            ]);
        }
    }

    protected function dumpMysqlDatabase(Tenant $tenant): string
    {
        $dbName = $this->databaseName($tenant);
        $defaults = $this->writeMysqlDefaultsFile();

        try {
            $command = [
                'mysqldump',
                '--defaults-extra-file='.$defaults,
                '--single-transaction',
                '--routines',
                '--triggers',
                '--set-gtid-purged=OFF',
                $dbName,
            ];

            $result = Process::timeout(600)->run($command);
            if (! $result->successful()) {
                throw ValidationException::withMessages([
                    'backup' => trim($result->errorOutput()) ?: 'Database backup failed.',
                ]);
            }

            return $result->output();
        } finally {
            @unlink($defaults);
        }
    }

    protected function importMysqlDatabase(Tenant $tenant, string $sql): void
    {
        if (trim($sql) === '') {
            throw ValidationException::withMessages([
                'backup' => 'Backup file is empty.',
            ]);
        }

        $dbName = $this->databaseName($tenant);
        $defaults = $this->writeMysqlDefaultsFile();

        try {
            $result = Process::timeout(1200)
                ->input($sql)
                ->run(['mysql', '--defaults-extra-file='.$defaults, $dbName]);

            if (! $result->successful()) {
                throw ValidationException::withMessages([
                    'backup' => trim($result->errorOutput()) ?: 'Database restore failed.',
                ]);
            }
        } finally {
            @unlink($defaults);
        }
    }

    protected function writeMysqlDefaultsFile(): string
    {
        $creds = $this->mysqlCredentials();
        $path = tempnam(sys_get_temp_dir(), 'dukan_mysql_');
        if ($path === false) {
            throw ValidationException::withMessages([
                'backup' => 'Could not create a temporary MySQL config file.',
            ]);
        }

        $lines = [
            '[client]',
            'user='.$creds['username'],
            'password='.$creds['password'],
        ];

        if ($creds['unix_socket'] !== '') {
            $lines[] = 'socket='.$creds['unix_socket'];
        } else {
            $lines[] = 'host='.$creds['host'];
            $lines[] = 'port='.$creds['port'];
        }

        file_put_contents($path, implode("\n", $lines)."\n");
        chmod($path, 0600);

        return $path;
    }

    protected function readSqlPayload(string $path): string
    {
        $contents = file_get_contents($path);
        if ($contents === false) {
            throw ValidationException::withMessages([
                'backup' => 'Could not read the backup file.',
            ]);
        }

        if (str_ends_with(strtolower($path), '.gz')) {
            $decoded = @gzdecode($contents);
            if ($decoded === false) {
                throw ValidationException::withMessages([
                    'backup' => 'Could not decompress the gzip backup file.',
                ]);
            }

            return $decoded;
        }

        return $contents;
    }

    protected function sqliteDatabasePath(Tenant $tenant): string
    {
        $name = $this->databaseName($tenant);
        if (is_file($name)) {
            return $name;
        }

        $candidate = database_path($name);
        if (is_file($candidate)) {
            return $candidate;
        }

        if (! str_ends_with($name, '.sqlite')) {
            $withExt = database_path($name.'.sqlite');
            if (is_file($withExt)) {
                return $withExt;
            }
        }

        throw ValidationException::withMessages([
            'backup' => 'Tenant SQLite database file could not be located.',
        ]);
    }

    protected function copySqliteDatabase(Tenant $tenant, string $destination): void
    {
        $source = $this->sqliteDatabasePath($tenant);
        if (! copy($source, $destination)) {
            throw ValidationException::withMessages([
                'backup' => 'Could not copy the SQLite database file.',
            ]);
        }
    }

    protected function restoreSqliteDatabase(Tenant $tenant, string $sourcePath): void
    {
        $destination = $this->sqliteDatabasePath($tenant);

        if (is_file($destination)) {
            @copy($destination, $destination.'.before-restore-'.now()->format('Ymd_His'));
        }

        if (! copy($sourcePath, $destination)) {
            throw ValidationException::withMessages([
                'backup' => 'Could not restore the SQLite database file.',
            ]);
        }
    }

    protected function formatBytes(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes.' B';
        }
        if ($bytes < 1024 * 1024) {
            return round($bytes / 1024, 1).' KB';
        }

        return round($bytes / (1024 * 1024), 2).' MB';
    }
}
