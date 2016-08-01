<?php

namespace WyriHaximus\Tests\React\Cache;

use Phake;
use React\EventLoop\Factory;
use React\Filesystem\Filesystem as ReactFilesystem;
use React\Filesystem\Node\DirectoryInterface;
use React\Filesystem\Node\FileInterface;
use React\Promise\FulfilledPromise;
use React\Promise\PromiseInterface;
use React\Promise\RejectedPromise;
use WyriHaximus\React\Cache\Filesystem;
use function Clue\React\Block\await;

class FilesystemTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var ReactFilesystem
     */
    protected $filesystem;

    public function setUp()
    {
        parent::setUp();
        $this->filesystem = Phake::mock(ReactFilesystem::class);
    }

    public function testGet()
    {
        $prefix = 'root:';
        $key = 'key';
        $value = 'value';
        $file = Phake::mock(FileInterface::class);
        Phake::when($this->filesystem)->file($prefix . $key)->thenReturn($file);
        Phake::when($file)->exists()->thenReturn(new FulfilledPromise());
        Phake::when($file)->getContents()->thenReturn(new FulfilledPromise($value));
        $promise = (new Filesystem($this->filesystem, $prefix))->get($key);
        $this->assertInstanceOf(PromiseInterface::class, $promise);
        $result = await($promise, Factory::create());
        $this->assertSame($value, $result);
        Phake::inOrder(
            Phake::verify($file)->exists(),
            Phake::verify($file)->getContents()
        );
    }

    public function testGetNonExistant()
    {
        $prefix = 'root:';
        $key = 'key';
        $file = Phake::mock(FileInterface::class);
        Phake::when($this->filesystem)->file($prefix . $key)->thenReturn($file);
        Phake::when($file)->exists()->thenReturn(new RejectedPromise());
        Phake::when($file)->getContents()->thenReturn(new RejectedPromise());
        $promise = (new Filesystem($this->filesystem, $prefix))->get($key);
        $this->assertInstanceOf(PromiseInterface::class, $promise);
        $this->assertInstanceOf(RejectedPromise::class, $promise);
        Phake::verify($file)->exists();
        Phake::verify($file, Phake::never())->get();
    }

    public function testSet()
    {
        $prefix = 'root:';
        $key = 'key';
        $value = 'value';
        $file = Phake::mock(FileInterface::class);
        Phake::when($this->filesystem)->file($prefix . $key)->thenReturn($file);
        (new Filesystem($this->filesystem, $prefix))->set($key, $value);
        Phake::verify($this->filesystem)->file($prefix . $key);
        Phake::verify($file)->putContents($value);
    }

    public function testSetMakeDirectory()
    {
        $prefix = '/tmp/';
        $key = 'path/to/key';
        $dirKey = 'path/to';
        $value = 'value';
        $file = Phake::mock(FileInterface::class);
        $dir = Phake::mock(DirectoryInterface::class);
        Phake::when($dir)->createRecursive()->thenReturn(new FulfilledPromise());
        Phake::when($this->filesystem)->file($prefix . $key)->thenReturn($file);
        Phake::when($this->filesystem)->dir($prefix . $dirKey)->thenReturn($dir);
        (new Filesystem($this->filesystem, $prefix))->set($key, $value);
        Phake::verify($this->filesystem)->file($prefix . $key);
        Phake::verify($this->filesystem)->dir($prefix . $dirKey);
        Phake::verify($file)->putContents($value);
    }

    public function testRemove()
    {
        $prefix = 'root:';
        $key = 'key';
        $file = Phake::mock(FileInterface::class);
        Phake::when($file)->exists()->thenReturn(new FulfilledPromise());
        Phake::when($this->filesystem)->file($prefix . $key)->thenReturn($file);
        (new Filesystem($this->filesystem, $prefix))->remove($key);
        Phake::verify($this->filesystem)->file($prefix . $key);
        Phake::verify($file)->remove();
    }

    public function testRemoveNonExistant()
    {
        $prefix = 'root:';
        $key = 'key';
        $file = Phake::mock(FileInterface::class);
        Phake::when($file)->exists()->thenReturn(new RejectedPromise());
        Phake::when($this->filesystem)->file($prefix . $key)->thenReturn($file);
        (new Filesystem($this->filesystem, $prefix))->remove($key);
        Phake::verify($this->filesystem)->file($prefix . $key);
        Phake::verify($file, Phake::never())->remove();
    }
}
