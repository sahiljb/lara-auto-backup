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

        // Zlib's stream wrapper decompresses as the client reads, so there is
        // no gunzip dependency and the archive is never expanded to disk.
        $source = str_ends_with($archivePath, '.gz')
            ? 'compress.zlib://' . $archivePath
            : $archivePath;

        $input = fopen($source, 'rb');

        if ($input === false) {
            throw BackupFailed::restoreFailed($config['database'] ?? '', "Unable to read [{$archivePath}].");
        }

        try {
            $process = new Process(
                $this->command($config, $options),
                null,
                $this->environment($config),
                $input,
                $options['timeout'] ?? null
            );

            $process->run();

            if (! $process->isSuccessful()) {
                throw BackupFailed::restoreFailed($config['database'] ?? '', trim($process->getErrorOutput()));
            }
        } finally {
            if (is_resource($input)) {
                fclose($input);
            }
        }
    }

    protected function binary(string $name, array $options): string
    {
        $path = $options['dump_binary_path'] ?? null;

        return $path ? rtrim($path, '/\\') . DIRECTORY_SEPARATOR . $name : $name;
    }
}
