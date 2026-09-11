<?php

namespace SahilJB\LaraAutoBackup\Restorers;

use SahilJB\LaraAutoBackup\Exceptions\BackupFailed;
use Symfony\Component\Process\Process;

abstract class BaseRestorer implements Restorer
{
    /**
     * The client command that reads SQL on stdin.
     *
     * @return array<int, string>
     */
    abstract protected function command(array $config, array $options): array;

    /**
     * @return array<string, string>
     */
    protected function environment(array $config): array
    {
        return [];
    }

    public function restore(array $config, string $archivePath, array $options = []): void
    {
        if (! is_file($archivePath)) {
            throw BackupFailed::restoreFailed($config['database'] ?? '', "Archive [{$archivePath}] not found.");
        }

        // Decompress on the fly so a large archive is never expanded to disk.
        $reader = str_ends_with($archivePath, '.gz') ? 'gunzip -c' : 'cat';

        $shell = $reader . ' ' . escapeshellarg($archivePath)
            . ' | ' . $this->escape($this->command($config, $options));

        $process = Process::fromShellCommandline(
            $shell,
            null,
            $this->environment($config),
            null,
            $options['timeout'] ?? null
        );

        $process->run();

        if (! $process->isSuccessful()) {
            throw BackupFailed::restoreFailed($config['database'] ?? '', trim($process->getErrorOutput()));
        }
    }

    protected function binary(string $name, array $options): string
    {
        $path = $options['dump_binary_path'] ?? null;

        return $path ? rtrim($path, '/\\') . DIRECTORY_SEPARATOR . $name : $name;
    }

    /**
     * @param  array<int, string>  $command
     */
    protected function escape(array $command): string
    {
        return implode(' ', array_map('escapeshellarg', $command));
    }
}
