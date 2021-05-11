<?php declare(strict_types=1);

namespace WyriHaximus\Tests\React\Cache;

use WyriHaximus\React\Cache\CacheItem;
use function Clue\React\Block\await;
use React\EventLoop\Factory;
use React\Filesystem\FilesystemInterface as ReactFilesystem;
use React\Filesystem\Node\DirectoryInterface;
use React\Filesystem\Node\FileInterface;
use React\Promise\FulfilledPromise;
use React\Promise\PromiseInterface;
use function React\Promise\reject;
use React\Promise\RejectedPromise;
use function React\Promise\resolve;
use WyriHaximus\AsyncTestUtilities\AsyncTestCase;
use WyriHaximus\React\Cache\Filesystem;

/**
 * @internal
 */
final class FilesystemTest extends AsyncTestCase
{
    /**
     * @var ReactFilesystem
     */
    protected $filesystem;

    protected function setUp(): void
    {
        parent::setUp();
        $this->filesystem = $this->prophesize(ReactFilesystem::class);
    }

    public function testGet(): void
    {
        $prefix = 'root:';
        $key = 'key';
        $value = 'value';
        $file = $this->prophesize(FileInterface::class);
        $this->filesystem->file($prefix . $key)->shouldBeCalled()->willReturn($file->reveal());
        $file->exists()->shouldBeCalled()->willReturn(new FulfilledPromise());
        $file->getContents()->shouldBeCalled()->willReturn(new FulfilledPromise(serialize(new CacheItem($value))));
        $promise = (new Filesystem($this->filesystem->reveal(), $prefix))->get($key);
        $this->assertInstanceOf(PromiseInterface::class, $promise);
        $result = await($promise, Factory::create());
        $this->assertSame($value, $result);
    }

    public function testGetNonExistant(): void
    {
        $prefix = 'root:';
        $key = 'key';
        $file = $this->prophesize(FileInterface::class);
        $this->filesystem->file($prefix . $key)->shouldBeCalled()->willReturn($file->reveal());
        $file->exists()->shouldBeCalled()->shouldBeCalled()->willReturn(reject(new \Exception('Doesn\'t exist')));
        $file->getContents()->shouldNotBeCalled();
        $promise = (new Filesystem($this->filesystem->reveal(), $prefix))->get($key);
        $this->assertInstanceOf(PromiseInterface::class, $promise);
        $this->assertInstanceOf(FulfilledPromise::class, $promise);
        self::assertSame(null, $this->await($promise));
    }

    public function testSet(): void
    {
        $prefix = 'root:';
        $key = 'key';
        $value = 'value';
        $file = $this->prophesize(FileInterface::class);
        $file->putContents(serialize(new CacheItem($value)))->shouldBeCalled()->willReturn(resolve(true));
        $this->filesystem->file($prefix . $key)->shouldBeCalled()->willReturn($file->reveal());
        (new Filesystem($this->filesystem->reveal(), $prefix))->set($key, $value);
    }

    public function testSetMakeDirectory(): void
    {
        $prefix = '/tmp/';
        $key = 'path/to/key';
        $dirKey = 'path/to';
        $value = 'value';
        $file = $this->prophesize(FileInterface::class);
        $file->putContents(serialize(new CacheItem($value)))->shouldBeCalled()->willReturn(resolve(true));
        $dir = $this->prophesize(DirectoryInterface::class);
        $dir->createRecursive()->shouldBeCalled()->willReturn(new FulfilledPromise());
        $this->filesystem->file($prefix . $key)->shouldBeCalled()->willReturn($file->reveal());
        $this->filesystem->dir($prefix . $dirKey)->shouldBeCalled()->willReturn($dir->reveal());
        (new Filesystem($this->filesystem->reveal(), $prefix))->set($key, $value);
    }

    public function testRemove(): void
    {
        $prefix = 'root:';
        $key = 'key';
        $file = $this->prophesize(FileInterface::class);
        $file->exists()->shouldBeCalled()->willReturn(new FulfilledPromise());
        $this->filesystem->file($prefix . $key)->shouldBeCalled()->willReturn($file->reveal());
        (new Filesystem($this->filesystem->reveal(), $prefix))->delete($key);
    }

    public function testRemoveNonExistant(): void
    {
        $prefix = 'root:';
        $key = 'key';
        $file = $this->prophesize(FileInterface::class);
        $file->exists()->shouldBeCalled()->willReturn(new RejectedPromise());
        $this->filesystem->file($prefix . $key)->shouldBeCalled()->willReturn($file->reveal());
        (new Filesystem($this->filesystem->reveal(), $prefix))->delete($key);
    }
}
