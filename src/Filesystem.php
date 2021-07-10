<?php declare(strict_types=1);

namespace WyriHaximus\React\Cache;

use React\Filesystem\Node\DirectoryInterface;
use React\Cache\CacheInterface;
use React\Filesystem\FilesystemInterface as ReactFilesystem;
use React\Filesystem\Node\FileInterface;
use React\Filesystem\Node\NodeInterface;
use React\Filesystem\Node\NotExistInterface;
use React\Promise\Promise;
use React\Promise\PromiseInterface;
use Throwable;
use function React\Promise\all;
use function React\Promise\reject;
use function React\Promise\resolve;

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
     * @var bool
     */
    private $supportsHighResolution;

    /**
     * filesystem constructor.
     * @param ReactFilesystem $filesystem
     * @param string          $path
     */
    public function __construct(ReactFilesystem $filesystem, string $path)
    {
        $this->filesystem = $filesystem;
        $this->path = $path;
        // prefer high-resolution timer, available as of PHP 7.3+
        $this->supportsHighResolution = \function_exists('hrtime');
    }

    /**
     * @param  string           $key
     * @param  null|mixed       $default
     */
    public function get($key, $default = null): PromiseInterface
    {
        return $this->has($key)->then(function (bool $has) use ($key, $default) {
            if ($has === true) {
                return $this->getFile($key)
                    ->then(static function (FileInterface $file) {
                        return $file->getContents();
                    })
                    ->then(static function (string $contents) {
                        return unserialize($contents);
                    })
                    ->then(
                        function (CacheItem $cacheItem) use ($key, $default) {
                            if ($cacheItem->hasExpired($this->now())) {
                                return $this->getFile($key)
                                    ->then(static fn (FileInterface $file): PromiseInterface => $file->unlink())
                                    ->then(
                                        function () use ($default) {
                                            return resolve($default);
                                        }
                                    );
                            }
                            return resolve($cacheItem->data());
                        }
                    );
            }

            return resolve($default);
        });
    }

    /**
     * @param string     $key
     * @param mixed      $value
     * @param ?float     $ttl
     */
    public function set($key, $value, $ttl = null): PromiseInterface
    {
        return $this->getFile($key)->then(function (FileInterface $file) use ($value, $ttl): PromiseInterface {
            return $this->putContents($file, $value, $ttl);
        });
    }

    /**
     * @param string $key
     */
    public function delete($key): PromiseInterface
    {
        return $this->has($key)->then(function (bool $has) use ($key): PromiseInterface {
            return $has === false ? resolve(false) : $this->getFile($key)->then(static function (FileInterface $file): PromiseInterface {
                return $file->unlink();
            });
        })->then(null, static fn (): bool => false);
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

        return all($promises)->then(static function ($results): bool {
            foreach ($results as $result) {
                if ($result === false) {
                    return false;
                }
            }

            return true;
        });
    }

    public function deleteMultiple(array $keys): PromiseInterface
    {
        $promises = [];
        foreach ($keys as $key) {
            $promises[$key] = $this->delete($key);
        }

        return all($promises)->then(static function ($results): bool {
            foreach ($results as $result) {
                if ($result === false) {
                    return false;
                }
            }

            return true;
        });
    }

    public function clear(): PromiseInterface
    {
        return $this->clearDir($this->path)->then(static fn (): bool => true);
    }

    private function clearDir(string $path): PromiseInterface
    {
        return $this->filesystem->detect($path)->then(function (\React\Filesystem\Node\DirectoryInterface $directory) {
            return $directory->ls();
        })->then(function (array $nodes): PromiseInterface {
            $promises = [];
            foreach ($nodes as $node) {
                assert($node instanceof NodeInterface);
                if ($node instanceof DirectoryInterface) {
                    $promises[] = $this->clearDir($node->path() . $node->name());
                }
                if ($node instanceof FileInterface) {
                    $promises[] = $node->unlink();
                }
            }

            return all($promises)->then(static fn (array $nodes): bool => true);
        });
    }

    public function has($key): PromiseInterface
    {
        return $this->filesystem->detect($this->path . $key)->then(static fn (NodeInterface $node): bool => $node instanceof FileInterface, static fn (): bool => false);
    }

    private function putContents(FileInterface $file, $value, $ttl): PromiseInterface
    {
        $item = new CacheItem($value, is_null($ttl) ? null : ($this->now() + $ttl));
        return $file->putContents(serialize($item))->then(function () {
            return resolve(true);
        }, function () {
            return resolve(false);
        });
    }

    /**
     * @param $key
     * @return PromiseInterface<FileInterface>
     */
    private function getFile($key): PromiseInterface
    {
        return $this->filesystem->detect($this->path . $key)->then(static function (NodeInterface $node): PromiseInterface {
            if ($node instanceof NotExistInterface) {
                return $node->createFile();
            }

            return resolve($node);
        });
    }

    /**
     * @return float
     */
    private function now()
    {
        return $this->supportsHighResolution ? \hrtime(true) * 1e-9 : \microtime(true);
    }
}
