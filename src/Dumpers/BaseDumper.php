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

    public function dump(array $config, string $targetPath, array $options = []): void
    {
        $command = array_merge(
            $this->command($config, $options),
            array_map('strval', $options['dump_options'] ?? [])
        );

        $shell = $this->escape($command)
            . (($options['compress'] ?? false) ? ' | gzip' : '')
            . ' > ' . escapeshellarg($targetPath);

        $process = Process::fromShellCommandline(
            $shell,
            null,
            $this->environment($config),
            null,
            $options['timeout'] ?? null
        );

        $process->run();

        if (! $process->isSuccessful()) {
            @unlink($targetPath);

            throw BackupFailed::dumpFailed($config['database'] ?? '', trim($process->getErrorOutput()));
        }

        if (! is_file($targetPath) || filesize($targetPath) === 0) {
            @unlink($targetPath);

            throw BackupFailed::emptyDump($config['database'] ?? '');
        }
    }

    public function extension(): string
    {
        return 'sql';
    }

    /**
     * Resolve a binary, honouring the configured binary directory.
     */
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
