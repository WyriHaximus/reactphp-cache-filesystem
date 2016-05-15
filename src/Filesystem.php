<?php

namespace WyriHaximus\React\Cache;

use React\Cache\CacheInterface;
use React\Filesystem\Filesystem as ReactFilesystem;
use React\Promise\PromiseInterface;

class Filesystem implements CacheInterface
{
    /**
     * @var ReactFilesystem
     */
    protected $filesystem;

    /**
     * @var string
     */
    protected $path;

    /**
     * filesystem constructor.
     * @param ReactFilesystem $filesystem
     * @param string $path
     */
    public function __construct(ReactFilesystem $filesystem, $path)
    {
        $this->filesystem = $filesystem;
        $this->path = $path;
    }

    /**
     * @param string $key
     * @return PromiseInterface
     */
    public function get($key)
    {
        return $this->filesystem->file($this->path . $key)->exists()->then(function () use ($key) {
            return $this->filesystem->file($this->path . $key)->getContents();
        });
    }

    /**
     * @param string $key
     * @param mixed $value
     */
    public function set($key, $value)
    {
        $this->filesystem->file($this->path . $key)->putContents($value);
    }

    /**
     * @param string $key
     */
    public function remove($key)
    {
        $this->filesystem->file($this->path . $key)->remove();
    }
}
