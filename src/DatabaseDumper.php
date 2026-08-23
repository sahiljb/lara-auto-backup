<?php

namespace SahilJB\LaraAutoBackup;

use RuntimeException;
use Symfony\Component\Process\Process;

class DatabaseDumper
{
    public function __construct(private array $dbConfig, private ?string $binaryPath = null)
    {
    }

    /**
     * Dump the database to $target and return the path actually written.
     */
    public function dumpTo(string $target, bool $compress): string
    {
        $driver = $this->dbConfig['driver'] ?? null;

        $path = $compress ? $target . '.gz' : $target;

        [$command, $env] = match ($driver) {
            'mysql', 'mariadb' => $this->mysqlCommand(),
            'pgsql' => $this->pgsqlCommand(),
            'sqlite' => $this->sqliteCommand(),
            default => throw new RuntimeException("Unsupported database driver [{$driver}] for backup."),
        };

        $shell = $this->quote($command) . ($compress ? ' | gzip' : '') . ' > ' . escapeshellarg($path);

        $process = Process::fromShellCommandline($shell, null, $env, null, null);
        $process->run();

        if (! $process->isSuccessful()) {
            @unlink($path);
            throw new RuntimeException('Database dump failed: ' . trim($process->getErrorOutput()));
        }

        if (! file_exists($path) || filesize($path) === 0) {
            @unlink($path);
            throw new RuntimeException('Database dump produced an empty file.');
        }

        return $path;
    }

    private function mysqlCommand(): array
    {
        $command = [
            $this->binary('mysqldump'),
            '--single-transaction',
            '--quick',
            '--skip-lock-tables',
            '--routines',
            '--triggers',
            '--events',
            '--default-character-set=' . ($this->dbConfig['charset'] ?? 'utf8mb4'),
            '--host=' . ($this->dbConfig['host'] ?? '127.0.0.1'),
            '--port=' . ($this->dbConfig['port'] ?? 3306),
            '--user=' . ($this->dbConfig['username'] ?? 'root'),
            $this->dbConfig['database'],
        ];

        // Passed via env so the password never appears in the process list.
        return [$command, ['MYSQL_PWD' => (string) ($this->dbConfig['password'] ?? '')]];
    }

    private function pgsqlCommand(): array
    {
        $command = [
            $this->binary('pg_dump'),
            '--no-owner',
            '--no-acl',
            '--host=' . ($this->dbConfig['host'] ?? '127.0.0.1'),
            '--port=' . ($this->dbConfig['port'] ?? 5432),
            '--username=' . ($this->dbConfig['username'] ?? 'postgres'),
            $this->dbConfig['database'],
        ];

        return [$command, ['PGPASSWORD' => (string) ($this->dbConfig['password'] ?? '')]];
    }

    private function sqliteCommand(): array
    {
        return [['cat', $this->dbConfig['database']], []];
    }

    private function binary(string $name): string
    {
        return $this->binaryPath ? rtrim($this->binaryPath, '/') . '/' . $name : $name;
    }

    private function quote(array $command): string
    {
        return implode(' ', array_map('escapeshellarg', $command));
    }
}
