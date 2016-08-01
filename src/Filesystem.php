<?php

namespace WyriHaximus\React\Cache;

use React\Cache\CacheInterface;
use React\Filesystem\Filesystem as ReactFilesystem;
use React\Filesystem\Node\FileInterface;
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
        $file = $this->getFile($key);
        return $file->exists()->then(function () use ($file) {
            return $file->getContents();
        });
    }

    /**
     * @param string $key
     * @param mixed $value
     */
    public function set($key, $value)
    {
        $file = $this->getFile($key);
        if (strpos($key, DIRECTORY_SEPARATOR) === false) {
            $file->putContents($value);
            return;
        }

        $path = explode(DIRECTORY_SEPARATOR, $key);
        array_pop($path);
        $path = implode(DIRECTORY_SEPARATOR, $path);

        $this->filesystem->dir($this->path . $path)->createRecursive()->then(function () use ($file, $value) {
            $file->putContents($value);
        });
    }

    /**
     * @param string $key
     */
    public function remove($key)
    {
        $file = $this->getFile($key);
        $file->exists()->then(function () use ($file) {
            $file->remove();
        });
    }

    /**
     * @param $key
     * @return FileInterface
     */
    protected function getFile($key)
    {
        return $this->filesystem->file($this->path . $key);
    }
}
