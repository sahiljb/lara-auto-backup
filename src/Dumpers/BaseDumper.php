<?php

namespace SahilJB\LaraAutoBackup\Dumpers;

use SahilJB\LaraAutoBackup\Exceptions\BackupFailed;
use Symfony\Component\Process\Process;

abstract class BaseDumper implements Dumper
{
    /**
     * The command to execute, as an array of arguments.
     *
     * @return array<int, string>
     */
    abstract protected function command(array $config, array $options): array;

    /**
     * Environment variables for the process — used to pass credentials without
     * exposing them in the process list.
     *
     * @return array<string, string>
     */
    protected function environment(array $config): array
    {
        return [];
    }

    public function extension(): string
    {
        return 'sql';
    }

    public function dump(array $config, string $targetPath, array $options = []): void
    {
        $command = array_merge(
            $this->command($config, $options),
            array_map('strval', $options['dump_options'] ?? [])
        );

        // The dump is streamed straight into the (optionally gzipped) target
        // file. Compressing in PHP rather than piping through gzip keeps this
        // working on Windows, where no such binary exists.
        $compress = (bool) ($options['compress'] ?? false);
        $handle = $compress ? gzopen($targetPath, 'wb9') : fopen($targetPath, 'wb');

        if ($handle === false) {
            throw BackupFailed::dumpFailed($config['database'] ?? '', "Unable to write to [{$targetPath}].");
        }

        $process = new Process(
            $command,
            null,
            $this->environment($config),
            null,
            $options['timeout'] ?? null
        );

        $errors = '';

        try {
            $process->run(function (string $type, string $buffer) use ($handle, $compress, &$errors) {
                if ($type === Process::ERR) {
                    $errors .= $buffer;

                    return;
                }

                $compress ? gzwrite($handle, $buffer) : fwrite($handle, $buffer);
            });
        } finally {
            $compress ? gzclose($handle) : fclose($handle);
        }

        if (! $process->isSuccessful()) {
            @unlink($targetPath);

            throw BackupFailed::dumpFailed($config['database'] ?? '', trim($errors));
        }

        if (! is_file($targetPath) || filesize($targetPath) === 0) {
            @unlink($targetPath);

            throw BackupFailed::emptyDump($config['database'] ?? '');
        }
    }

    /**
     * Resolve a binary, honouring the configured binary directory.
     */
    protected function binary(string $name, array $options): string
    {
        $path = $options['dump_binary_path'] ?? null;

        return $path ? rtrim($path, '/\\') . DIRECTORY_SEPARATOR . $name : $name;
    }
}
