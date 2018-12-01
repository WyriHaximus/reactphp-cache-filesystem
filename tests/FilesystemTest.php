<?php declare(strict_types=1);

namespace WyriHaximus\Tests\React\Cache;

use ApiClients\Tools\TestUtilities\TestCase;
use React\EventLoop\Factory;
use React\Filesystem\FilesystemInterface as ReactFilesystem;
use React\Filesystem\Node\DirectoryInterface;
use React\Filesystem\Node\FileInterface;
use React\Promise\FulfilledPromise;
use React\Promise\PromiseInterface;
use React\Promise\RejectedPromise;
use WyriHaximus\React\Cache\Filesystem;
use function Clue\React\Block\await;

/**
 * @internal
 */
final class FilesystemTest extends TestCase
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
        $file->getContents()->shouldBeCalled()->willReturn(new FulfilledPromise($value));
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
        $file->exists()->shouldBeCalled()->shouldBeCalled()->willReturn(new RejectedPromise());
        $file->getContents()->shouldNotBeCalled();
        $promise = (new Filesystem($this->filesystem->reveal(), $prefix))->get($key);
        $this->assertInstanceOf(PromiseInterface::class, $promise);
        $this->assertInstanceOf(RejectedPromise::class, $promise);
    }

    public function testSet(): void
    {
        $prefix = 'root:';
        $key = 'key';
        $value = 'value';
        $file = $this->prophesize(FileInterface::class);
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
        (new Filesystem($this->filesystem->reveal(), $prefix))->remove($key);
    }

    public function testRemoveNonExistant(): void
    {
        $prefix = 'root:';
        $key = 'key';
        $file = $this->prophesize(FileInterface::class);
        $file->exists()->shouldBeCalled()->willReturn(new RejectedPromise());
        $this->filesystem->file($prefix . $key)->shouldBeCalled()->willReturn($file->reveal());
        (new Filesystem($this->filesystem->reveal(), $prefix))->remove($key);
    }
}
