<?php

declare(strict_types=1);

namespace WyriHaximus\React\Cache;

/**
 * One item in a cache. Only used locally within the cache
 * @internal
 */
final class CacheItem
{
    /** @var mixed the data to be cached */
    private $data;

    /** @var float|null */
    private $expiresAt;

    /**
     * @param mixed $data
     * @param float|null $expiresAtTime
     */
    public function __construct($data, float $expiresAtTime = null)
    {
        $this->data      = $data;
        $this->expiresAt = $expiresAtTime;
    }

    public function expiresAt(): ?float
    {
        return $this->expiresAt;
    }

    /**
     * @param float|null $now current time
     */
    public function hasExpired(float $now = null): bool
    {
        return is_null($this->expiresAt) ? false : $now > $this->expiresAt;
    }

    /**
     * @return mixed
     */
    public function data()
    {
        return $this->data;
    }
}
