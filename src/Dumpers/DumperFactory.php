<?php

namespace SahilJB\LaraAutoBackup\Dumpers;

use Illuminate\Contracts\Container\Container;
use SahilJB\LaraAutoBackup\Exceptions\BackupFailed;

class DumperFactory
{
    /** @var array<string, callable(array): Dumper> */
    private array $custom = [];

    public function __construct(private Container $container, private array $dumpers = [])
    {
    }

    /**
     * Register a dumper for a driver at runtime, e.g. from a service provider:
     *
     *     Backup::extend('mysql', fn () => new MyDumper);
     */
    public function extend(string $driver, callable $resolver): static
    {
        $this->custom[$driver] = $resolver;

        return $this;
    }

    public function make(array $connectionConfig): Dumper
    {
        $driver = $connectionConfig['driver'] ?? '';

        if (isset($this->custom[$driver])) {
            return ($this->custom[$driver])($connectionConfig);
        }

        if (! isset($this->dumpers[$driver])) {
            throw BackupFailed::unsupportedDriver($driver);
        }

        $dumper = $this->dumpers[$driver];

        return is_string($dumper) ? $this->container->make($dumper) : $dumper;
    }
}
