<?php

namespace SahilJB\LaraAutoBackup;

use Illuminate\Contracts\Support\Arrayable;
use SahilJB\LaraAutoBackup\Support\FileSize;

/**
 * @implements Arrayable<string, mixed>
 */
class BackupResult implements Arrayable
{
    /**
     * @param  array<int, string>  $disks
     */
    public function __construct(
        public readonly string $connection,
        public readonly string $database,
        public readonly string $filename,
        public readonly string $remotePath,
        public readonly int $size,
        public readonly array $disks,
        public readonly float $duration,
    ) {
    }

    /**
     * Human-readable archive size, e.g. "12.4 MB".
     */
    public function humanSize(): string
    {
        return FileSize::human($this->size);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'connection' => $this->connection,
            'database' => $this->database,
            'filename' => $this->filename,
            'remote_path' => $this->remotePath,
            'size' => $this->size,
            'disks' => $this->disks,
            'duration' => $this->duration,
        ];
    }
}
