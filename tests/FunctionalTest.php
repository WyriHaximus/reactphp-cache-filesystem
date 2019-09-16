<?php declare(strict_types=1);

namespace WyriHaximus\Tests\React\Cache;

use React\EventLoop\Factory;
use React\EventLoop\LoopInterface;
use React\Filesystem\Filesystem as ReactFilesystem;
use WyriHaximus\AsyncTestUtilities\AsyncTestCase;
use WyriHaximus\React\Cache\Filesystem;

/**
 * @internal
 */
final class FunctionalTest extends AsyncTestCase
{
    /** @var Filesystem */
    protected $filesystem;

    /** @var LoopInterface */
    private $loop;

    protected function setUp(): void
    {
        parent::setUp();

        $this->loop = Factory::create();
        $this->filesystem = new Filesystem(ReactFilesystem::create($this->loop), $this->getTmpDir());
    }

    public function testHasNot(): void
    {
        $fileName = 'file.name';

        self::assertFalse($this->await($this->filesystem->has($fileName), $this->loop));
    }

    public function testCannotDeleteNonExistingItem(): void
    {
        $fileName = 'file.name';

        self::assertFalse($this->await($this->filesystem->delete($fileName), $this->loop));
    }

    public function testWriteSingleItemToCacheAndVerifyItsExistenceBeforeRemovingIt(): void
    {
        $directory = 'dir' . \DIRECTORY_SEPARATOR;
        $fileName = $directory . 'file.name';
        $fileContents = 'file.contents';

        self::assertDirectoryNotExists($this->getTmpDir() . $directory);
        self::assertFileNotExists($this->getTmpDir() . $fileName);
        self::assertFalse($this->await($this->filesystem->has($fileName), $this->loop));

        self::assertTrue($this->await($this->filesystem->set($fileName, $fileContents), $this->loop));

        self::assertDirectoryExists($this->getTmpDir() . $directory);
        self::assertFileExists($this->getTmpDir() . $fileName);
        self::assertTrue($this->await($this->filesystem->has($fileName), $this->loop));

        self::assertSame($fileContents, $this->await($this->filesystem->get($fileName), $this->loop));

        self::assertTrue($this->await($this->filesystem->delete($fileName), $this->loop));

        self::assertDirectoryExists($this->getTmpDir() . $directory);
        self::assertFileNotExists($this->getTmpDir() . $fileName);
        self::assertFalse($this->await($this->filesystem->has($fileName), $this->loop));
    }

    public function testMultiOperations(): void
    {
        $directory = 'dar' . \DIRECTORY_SEPARATOR;
        $files = [
            $directory . 'a' => 'f',
            $directory . 'b' => 'e',
            $directory . 'c' => 'd',
        ];

        foreach ($files as $fileName =>  $fileContents) {
            self::assertDirectoryNotExists($this->getTmpDir() . $directory);
            self::assertFileNotExists($this->getTmpDir() . $fileName);
            self::assertFalse($this->await($this->filesystem->has($fileName), $this->loop));
        }

        self::assertTrue($this->await($this->filesystem->setMultiple($files), $this->loop));

        foreach ($files as $fileName =>  $fileContents) {
            self::assertDirectoryExists($this->getTmpDir() . $directory);
            self::assertFileExists($this->getTmpDir() . $fileName);
            self::assertTrue($this->await($this->filesystem->has($fileName), $this->loop));
        }

        foreach ($this->await($this->filesystem->getMultiple(\array_keys($files)), $this->loop) as $key => $value) {
            self::assertSame($files[$key], $value);
        }

        self::assertTrue($this->await($this->filesystem->deleteMultiple(\array_keys($files)), $this->loop));

        foreach ($files as $fileName =>  $fileContents) {
            self::assertDirectoryExists($this->getTmpDir() . $directory);
            self::assertFileNotExists($this->getTmpDir() . $fileName);
            self::assertFalse($this->await($this->filesystem->has($fileName), $this->loop));
        }
    }

    public function testMultiOperationsUsingClearInsteadOfDelete(): void
    {
        $directory = 'dar' . \DIRECTORY_SEPARATOR;
        $files = [
            $directory . 'a' => 'f',
            $directory . 'b' => 'e',
            $directory . 'c' => 'd',
        ];

        foreach ($files as $fileName =>  $fileContents) {
            self::assertDirectoryNotExists($this->getTmpDir() . $directory);
            self::assertFileNotExists($this->getTmpDir() . $fileName);
            self::assertFalse($this->await($this->filesystem->has($fileName), $this->loop));
        }

        self::assertTrue($this->await($this->filesystem->setMultiple($files), $this->loop));

        foreach ($files as $fileName =>  $fileContents) {
            self::assertDirectoryExists($this->getTmpDir() . $directory);
            self::assertFileExists($this->getTmpDir() . $fileName);
            self::assertTrue($this->await($this->filesystem->has($fileName), $this->loop));
        }

        foreach ($this->await($this->filesystem->getMultiple(\array_keys($files)), $this->loop) as $key => $value) {
            self::assertSame($files[$key], $value);
        }

        self::assertTrue($this->await($this->filesystem->clear(), $this->loop));

        foreach ($files as $fileName =>  $fileContents) {
            self::assertDirectoryExists($this->getTmpDir() . $directory);
            self::assertFileNotExists($this->getTmpDir() . $fileName);
            self::assertFalse($this->await($this->filesystem->has($fileName), $this->loop));
        }
    }
}
