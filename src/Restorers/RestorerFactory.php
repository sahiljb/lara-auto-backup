<?php

namespace SahilJB\LaraAutoBackup\Restorers;

use Illuminate\Contracts\Container\Container;
use SahilJB\LaraAutoBackup\Exceptions\BackupFailed;

class RestorerFactory
{
    /** @var array<string, callable(array): Restorer> */
    private array $custom = [];

    public function __construct(private Container $container, private array $restorers = [])
    {
    }

    /**
     * Register a restorer for a driver at runtime:
     *
     *     Backup::extendRestorer('mysql', fn () => new MyRestorer);
     */
    public function extend(string $driver, callable $resolver): static
    {
        $this->custom[$driver] = $resolver;

        return $this;
    }

    public function make(array $connectionConfig): Restorer
    {
        $driver = $connectionConfig['driver'] ?? '';

        if (isset($this->custom[$driver])) {
            return ($this->custom[$driver])($connectionConfig);
        }

        if (! isset($this->restorers[$driver])) {
            throw BackupFailed::unsupportedRestoreDriver($driver);
        }

        $restorer = $this->restorers[$driver];

        return is_string($restorer) ? $this->container->make($restorer) : $restorer;
    }
}
