<?php

namespace SahilJB\LaraAutoBackup\Restorers;

class PostgresRestorer extends BaseRestorer
{
    protected function command(array $config, array $options): array
    {
        return [
            $this->binary('psql', $options),
            // Abort on the first error rather than leaving a half-restored database.
            '--set=ON_ERROR_STOP=1',
            '--quiet',
            '--host=' . ($config['host'] ?? '127.0.0.1'),
            '--port=' . ($config['port'] ?? 5432),
            '--username=' . ($config['username'] ?? 'postgres'),
            '--dbname=' . $config['database'],
        ];
    }

    protected function environment(array $config): array
    {
        return ['PGPASSWORD' => (string) ($config['password'] ?? '')];
    }
}
