<?php

namespace SahilJB\LaraAutoBackup\Dumpers;

class MySqlDumper extends BaseDumper
{
    protected function command(array $config, array $options): array
    {
        $database = $config['database'];

        $command = [
            $this->binary('mysqldump', $options),
            '--single-transaction',
            '--quick',
            '--skip-lock-tables',
            '--routines',
            '--triggers',
            '--events',
            '--default-character-set=' . ($config['charset'] ?? 'utf8mb4'),
            '--host=' . ($config['host'] ?? '127.0.0.1'),
            '--port=' . ($config['port'] ?? 3306),
            '--user=' . ($config['username'] ?? 'root'),
        ];

        if (! empty($config['unix_socket'])) {
            $command[] = '--socket=' . $config['unix_socket'];
        }

        foreach ($options['exclude_tables'] ?? [] as $table) {
            $command[] = "--ignore-table={$database}." . trim($table);
        }

        $command[] = $database;

        foreach ($options['only_tables'] ?? [] as $table) {
            $command[] = trim($table);
        }

        return $command;
    }

    protected function environment(array $config): array
    {
        // Passing the password via MYSQL_PWD keeps it out of the process list.
        return ['MYSQL_PWD' => (string) ($config['password'] ?? '')];
    }
}
