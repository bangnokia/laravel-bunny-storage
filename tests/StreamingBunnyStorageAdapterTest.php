<?php

namespace Bangnokia\LaravelBunnyStorage\Tests;

use Bangnokia\LaravelBunnyStorage\StreamingBunnyStorageAdapter;
use Orchestra\Testbench\TestCase;
use PlatformCommunity\Flysystem\BunnyCDN\BunnyCDNClient;
use PlatformCommunity\Flysystem\BunnyCDN\BunnyCDNRegion;

class StreamingBunnyStorageAdapterTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [BunnyStorageServiceProvider::class];
    }

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'filesystems.disks.bunny' => [
                'driver' => 'bunny',
                'storage_zone' => 'test-zone',
                'api_key' => 'test-api-key',
                'region' => 'ny',
            ]
        ]);
    }

    public function test_it_registers_streaming_adapter()
    {
        $filesystem = $this->app['filesystem'];
        $disk = $filesystem->disk('bunny');

        $reflection = new \ReflectionClass($disk);
        $property = $reflection->getProperty('driver');
        $property->setValue($disk, $driver = $property->getValue($disk));

        $filesystemReflection = new \ReflectionClass($driver);
        $adapterProperty = $filesystemReflection->getProperty('adapter');
        $adapter = $adapterProperty->getValue($driver);

        $this->assertInstanceOf(StreamingBunnyStorageAdapter::class, $adapter);
    }

    public function test_it_uses_bunny_cdn_client()
    {
        $filesystem = $this->app['filesystem'];
        $disk = $filesystem->disk('bunny');

        $reflection = new \ReflectionClass($disk);
        $property = $reflection->getProperty('driver');
        $property->setValue($disk, $driver = $property->getValue($disk));

        $filesystemReflection = new \ReflectionClass($driver);
        $adapterProperty = $filesystemReflection->getProperty('adapter');
        $adapter = $adapterProperty->getValue($driver);

        $adapterReflection = new \ReflectionClass($adapter);
        $clientProperty = $adapterReflection->getProperty('client');
        $client = $clientProperty->getValue($adapter);

        $this->assertInstanceOf(BunnyCDNClient::class, $client);
    }

    public function test_write_accepts_stream_resources()
    {
        $disk = $this->app['filesystem']->disk('bunny');

        $stream = fopen('php://memory', 'r+');
        fwrite($stream, 'test content');
        rewind($stream);

        $disk->put('test-path.txt', $stream);
        fclose($stream);

        $this->assertFileExists('test-path.txt');
    }

    public function test_writeStream_accepts_stream_resources()
    {
        $disk = $this->app['filesystem']->disk('bunny');

        $stream = fopen('php://memory', 'r+');
        fwrite($stream, 'test content');
        rewind($stream);

        $disk->writeStream('test-path.txt', $stream);
        fclose($stream);

        $this->assertFileExists('test-path.txt');
    }

    public function test_write_falls_back_to_parent_for_strings()
    {
        $disk = $this->app['filesystem']->disk('bunny');

        $content = 'string content';

        $disk->write('test-path.txt', $content);

        $this->assertFileExists('test-path.txt');
    }

    public function test_writeStream_falls_back_to_parent_for_non_resources()
    {
        $disk = $this->app['filesystem']->disk('bunny');

        $content = 'string content';

        $disk->writeStream('test-path.txt', $content);

        $this->assertFileExists('test-path.txt');
    }

    public function test_file_is_stored_correctly_on_bunny()
    {
        $disk = $this->app['filesystem']->disk('bunny');

        $stream = fopen('php://memory', 'r+');
        fwrite($stream, 'test content for bunny');
        rewind($stream);

        $disk->put('test/bunny-file.txt', $stream);
        fclose($stream);

        $this->assertTrue($disk->exists('test/bunny-file.txt'));
    }
}
