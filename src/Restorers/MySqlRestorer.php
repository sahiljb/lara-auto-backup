<?php

namespace SahilJB\LaraAutoBackup\Restorers;

class MySqlRestorer extends BaseRestorer
{
    protected function command(array $config, array $options): array
    {
        $command = [
            $this->binary('mysql', $options),
            '--default-character-set=' . ($config['charset'] ?? 'utf8mb4'),
            '--host=' . ($config['host'] ?? '127.0.0.1'),
            '--port=' . ($config['port'] ?? 3306),
            '--user=' . ($config['username'] ?? 'root'),
        ];

        if (! empty($config['unix_socket'])) {
            $command[] = '--socket=' . $config['unix_socket'];
        }

        $command[] = $config['database'];

        return $command;
    }

    protected function environment(array $config): array
    {
        return ['MYSQL_PWD' => (string) ($config['password'] ?? '')];
    }
}
