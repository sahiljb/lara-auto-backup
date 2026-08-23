<?php

namespace SahilJB\LaraAutoBackup\Dumpers;

class PostgresDumper extends BaseDumper
{
    protected function command(array $config, array $options): array
    {
        $command = [
            $this->binary('pg_dump', $options),
            '--no-owner',
            '--no-acl',
            '--host=' . ($config['host'] ?? '127.0.0.1'),
            '--port=' . ($config['port'] ?? 5432),
            '--username=' . ($config['username'] ?? 'postgres'),
        ];

        foreach ($options['exclude_tables'] ?? [] as $table) {
            $command[] = '--exclude-table=' . trim($table);
        }

        foreach ($options['only_tables'] ?? [] as $table) {
            $command[] = '--table=' . trim($table);
        }

        $command[] = $config['database'];

        return $command;
    }

    protected function environment(array $config): array
    {
        return ['PGPASSWORD' => (string) ($config['password'] ?? '')];
    }
}
