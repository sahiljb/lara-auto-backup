<?php

namespace SahilJB\LaraAutoBackup;

use Illuminate\Contracts\Filesystem\Factory as FilesystemFactory;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Event;
use SahilJB\LaraAutoBackup\Dumpers\DumperFactory;
use SahilJB\LaraAutoBackup\Events\BackupCompleted;
use SahilJB\LaraAutoBackup\Events\BackupFailed as BackupFailedEvent;
use SahilJB\LaraAutoBackup\Events\BackupStarted;
use SahilJB\LaraAutoBackup\Exceptions\BackupFailed;
use Throwable;

class BackupManager
{
    /** @var null|callable(string): void */
    private $reporter = null;

    public function __construct(
        private DumperFactory $dumpers,
        private FilesystemFactory $filesystem,
        private array $config,
    ) {
    }

    /**
     * Receive progress messages — used by the console command to print output.
     *
     * @param  callable(string): void  $reporter
     */
    public function onProgress(callable $reporter): static
    {
        $this->reporter = $reporter;

        return $this;
    }

    /**
     * Register a custom dumper for a database driver.
     */
    public function extend(string $driver, callable $resolver): static
    {
        $this->dumpers->extend($driver, $resolver);

        return $this;
    }

    /**
     * Back up every configured connection.
     *
     * @param  array<string, mixed>  $overrides  Per-run config overrides.
     * @return array<int, BackupResult>
     */
    public function run(array $overrides = []): array
    {
        $options = $this->options($overrides);

        $results = [];

        foreach ($this->connections($options) as $connection) {
            $results[] = $this->backupConnection($connection, $options);
        }

        if (($options['retention']['days'] ?? 0) > 0) {
            $this->prune($options);
        }

        return $results;
    }

    /**
     * Back up a single connection.
     */
    public function backupConnection(string $connection, array $overrides = []): BackupResult
    {
        $options = isset($overrides['__resolved']) ? $overrides : $this->options($overrides);

        $dbConfig = config("database.connections.{$connection}");

        if (! $dbConfig) {
            throw BackupFailed::unknownConnection($connection);
        }

        $database = (string) ($dbConfig['database'] ?? $connection);

        Event::dispatch(new BackupStarted($connection, $database));

        $startedAt = microtime(true);
        $localPath = null;

        try {
            $dumper = $this->dumpers->make($dbConfig);

            $filename = $this->filename($connection, $database, $dumper->extension(), $options);
            $localPath = $this->temporaryDirectory($options) . DIRECTORY_SEPARATOR . $filename;

            $this->report("Dumping [{$connection}]…");
            $dumper->dump($dbConfig, $localPath, $options);

            $size = (int) filesize($localPath);
            $remotePath = $this->remotePath($filename, $options);
            $disks = $options['disks'];

            foreach ($disks as $disk) {
                $this->report("Uploading {$filename} to [{$disk}]…");
                $this->upload($disk, $localPath, $remotePath);
            }

            $result = new BackupResult(
                connection: $connection,
                database: $database,
                filename: $filename,
                remotePath: $remotePath,
                size: $size,
                disks: $disks,
                duration: round(microtime(true) - $startedAt, 2),
            );

            Event::dispatch(new BackupCompleted($result));

            return $result;
        } catch (Throwable $e) {
            Event::dispatch(new BackupFailedEvent($connection, $e));

            throw $e;
        } finally {
            if ($localPath && ($options['delete_local_after_upload'] ?? true)) {
                @unlink($localPath);
            }
        }
    }

    /**
     * Delete archives that fall outside the retention window.
     *
     * @return int Number of archives deleted.
     */
    public function prune(array $overrides = []): int
    {
        $options = isset($overrides['__resolved']) ? $overrides : $this->options($overrides);

        $days = (int) ($options['retention']['days'] ?? 0);

        if ($days <= 0) {
            return 0;
        }

        $keepAtLeast = max(0, (int) ($options['retention']['keep_at_least'] ?? 0));
        $cutoff = now()->subDays($days)->getTimestamp();
        $deleted = 0;

        foreach ($options['disks'] as $disk) {
            $storage = $this->filesystem->disk($disk);
            $directory = trim((string) $options['path'], '/');

            $files = collect($storage->files($directory))
                ->map(fn (string $file) => ['path' => $file, 'time' => $storage->lastModified($file)])
                ->sortByDesc('time')
                ->values();

            // The newest `keep_at_least` archives are always retained, so a
            // paused scheduler can never leave the bucket empty.
            foreach ($files->slice($keepAtLeast) as $file) {
                if ($file['time'] < $cutoff) {
                    $storage->delete($file['path']);
                    $deleted++;
                }
            }
        }

        if ($deleted > 0) {
            $this->report("Pruned {$deleted} backup(s) older than {$days} day(s).");
        }

        return $deleted;
    }

    /**
     * List the archives currently stored on a disk, newest first.
     *
     * @return array<int, array{path: string, size: int, last_modified: int}>
     */
    public function list(?string $disk = null, array $overrides = []): array
    {
        $options = $this->options($overrides);
        $disk ??= Arr::first($options['disks']);

        $storage = $this->filesystem->disk($disk);
        $directory = trim((string) $options['path'], '/');

        return collect($storage->files($directory))
            ->map(fn (string $file) => [
                'path' => $file,
                'size' => $storage->size($file),
                'last_modified' => $storage->lastModified($file),
            ])
            ->sortByDesc('last_modified')
            ->values()
            ->all();
    }

    private function upload(string $disk, string $localPath, string $remotePath): void
    {
        $stream = fopen($localPath, 'rb');

        if ($stream === false) {
            throw BackupFailed::uploadFailed($disk, 'Unable to read the local dump file.');
        }

        try {
            // Streamed so large databases never get loaded into memory.
            $ok = $this->filesystem->disk($disk)->writeStream($remotePath, $stream);

            if ($ok === false) {
                throw BackupFailed::uploadFailed($disk, 'The disk rejected the write.');
            }
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }
    }

    /**
     * @return array<int, string>
     */
    private function connections(array $options): array
    {
        $connections = array_filter((array) ($options['connections'] ?? []));

        return $connections ?: [(string) config('database.default')];
    }

    private function filename(string $connection, string $database, string $extension, array $options): string
    {
        $name = strtr((string) $options['filename_format'], [
            '{app}' => $this->sanitize((string) config('app.name', 'laravel')),
            '{database}' => $this->sanitize(pathinfo($database, PATHINFO_FILENAME) ?: $connection),
            '{connection}' => $this->sanitize($connection),
            '{timestamp}' => now()->format((string) $options['timestamp_format']),
        ]);

        return $name . '.' . $extension . (($options['compress'] ?? false) ? '.gz' : '');
    }

    /**
     * Reduce a value to characters that are safe in a filename on any disk.
     */
    private function sanitize(string $value): string
    {
        $value = preg_replace('/[^A-Za-z0-9_.-]+/', '-', $value) ?? '';

        return trim($value, '-') ?: 'backup';
    }

    private function remotePath(string $filename, array $options): string
    {
        $directory = trim((string) $options['path'], '/');

        return $directory === '' ? $filename : $directory . '/' . $filename;
    }

    private function temporaryDirectory(array $options): string
    {
        $path = rtrim((string) $options['temp_path'], '/\\');

        if (! is_dir($path) && ! @mkdir($path, 0700, true) && ! is_dir($path)) {
            throw BackupFailed::temporaryDirectory($path);
        }

        return $path;
    }

    /**
     * Merge per-run overrides over the package config.
     *
     * @return array<string, mixed>
     */
    private function options(array $overrides): array
    {
        $options = array_merge($this->config, array_filter(
            $overrides,
            static fn ($value) => $value !== null && $value !== []
        ));

        $options['disks'] = array_values(array_filter((array) ($options['disks'] ?? [])));
        $options['connections'] = array_values(array_filter((array) ($options['connections'] ?? [])));
        $options['retention'] = array_merge(
            $this->config['retention'] ?? [],
            (array) ($overrides['retention'] ?? [])
        );
        $options['__resolved'] = true;

        return $options;
    }

    private function report(string $message): void
    {
        if ($this->reporter) {
            ($this->reporter)($message);
        }
    }
}
