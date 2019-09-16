<?php declare(strict_types=1);

namespace WyriHaximus\React\Cache;

use React\Cache\CacheInterface;
use React\Filesystem\FilesystemInterface as ReactFilesystem;
use React\Filesystem\Node\FileInterface;
use React\Filesystem\Node\NodeInterface;
use function React\Promise\all;
use React\Promise\Promise;
use React\Promise\PromiseInterface;
use function React\Promise\reject;
use function React\Promise\resolve;
use Throwable;

final class Filesystem implements CacheInterface
{
    /**
     * @var ReactFilesystem
     */
    private $filesystem;

    /**
     * @var string
     */
    private $path;

    /**
     * filesystem constructor.
     * @param ReactFilesystem $filesystem
     * @param string          $path
     */
    public function __construct(ReactFilesystem $filesystem, string $path)
    {
        $this->filesystem = $filesystem;
        $this->path = $path;
    }

    /**
     * @param  string           $key
     * @param  null|mixed       $default
     * @return PromiseInterface
     */
    public function get($key, $default = null): PromiseInterface
    {
        return $this->has($key)->then(function (bool $has) use ($key, $default) {
            if ($has === true) {
                return $this->getFile($key)->getContents();
            }

            return resolve($default);
        });
    }

    /**
     * @param string     $key
     * @param mixed      $value
     * @param null|mixed $ttl
     */
    public function set($key, $value, $ttl = null): PromiseInterface
    {
        $file = $this->getFile($key);
        if (\strpos($key, \DIRECTORY_SEPARATOR) === false) {
            return $this->putContents($file, $value);
        }

        $path = \explode(\DIRECTORY_SEPARATOR, $key);
        \array_pop($path);
        $path = \implode(\DIRECTORY_SEPARATOR, $path);

        $dir = $this->filesystem->dir($this->path . $path);

        return $dir->createRecursive()->then(null, function (Throwable $error) {
            if ($error->getMessage() === 'mkdir(): File exists') {
                return resolve(true);
            }

            return reject($error);
        })->then(function () use ($file, $value): PromiseInterface {
            return $this->putContents($file, $value);
        });
    }

    /**
     * @param string $key
     */
    public function delete($key): PromiseInterface
    {
        return $this->has($key)->then(function () use ($key): PromiseInterface {
            return $this->getFile($key)->remove();
        })->then(function () {
            return resolve(true);
        }, function () {
            return resolve(false);
        });
    }

    public function getMultiple(array $keys, $default = null): PromiseInterface
    {
        $promises = [];
        foreach ($keys as $key) {
            $promises[$key] = $this->get($key, $default);
        }

        return all($promises);
    }

    public function setMultiple(array $values, $ttl = null): PromiseInterface
    {
        $promises = [];
        foreach ($values as $key => $value) {
            $promises[$key] = $this->set($key, $value, $ttl);
        }

        return all($promises)->then(function ($results) {
            foreach ($results as $result) {
                if ($result === false) {
                    return resolve(false);
                }
            }

            return resolve(true);
        });
    }

    public function deleteMultiple(array $keys): PromiseInterface
    {
        $promises = [];
        foreach ($keys as $key) {
            $promises[$key] = $this->delete($key);
        }

        return all($promises)->then(function ($results) {
            foreach ($results as $result) {
                if ($result === false) {
                    return resolve(false);
                }
            }

            return resolve(true);
        });
    }

    public function clear(): PromiseInterface
    {
        return (new Promise(function ($resolve, $reject): void {
            $stream = $this->filesystem->dir($this->path)->lsRecursiveStreaming();
            $stream->on('data', function (NodeInterface $node) use ($reject): void {
                if ($node instanceof FileInterface === false) {
                    return;
                }

                $node->remove()->then(null, $reject);
            });
            $stream->on('error', $reject);
            $stream->on('close', $resolve);
        }))->then(function () {
            return resolve(true);
        });
    }

    public function has($key): PromiseInterface
    {
        return $this->getFile($key)->exists()->then(function () {
            return resolve(true);
        }, function () {
            return resolve(false);
        });
    }

    private function putContents(FileInterface $file, $value): PromiseInterface
    {
        return $file->putContents($value)->then(function () {
            return resolve(true);
        }, function () {
            return resolve(false);
        });
    }

    /**
     * @param $key
     * @return FileInterface
     */
    private function getFile($key): FileInterface
    {
        return $this->filesystem->file($this->path . $key);
    }
}
