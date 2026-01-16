<?php

namespace Bangnokia\LaravelBunnyStorage\Tests;

use Bangnokia\LaravelBunnyStorage\BunnyStorageServiceProvider;
use Bangnokia\LaravelBunnyStorage\StreamingBunnyStorageAdapter;
use Bangnokia\LaravelBunnyStorage\StreamingBunnyStorageClient;
use Orchestra\Testbench\TestCase;

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

    public function test_it_uses_streaming_client()
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

        while ($adapterReflection && ! $adapterReflection->hasProperty('client')) {
            $adapterReflection = $adapterReflection->getParentClass();
        }

        $this->assertNotFalse($adapterReflection);

        $clientProperty = $adapterReflection->getProperty('client');
        $clientProperty->setAccessible(true);
        $client = $clientProperty->getValue($adapter);

        $this->assertInstanceOf(StreamingBunnyStorageClient::class, $client);
    }

    public function test_write_accepts_stream_resources()
    {
        $client = $this->createMockClient();
        $adapter = new StreamingBunnyStorageAdapter($client, '');

        $stream = fopen('php://memory', 'r+');
        fwrite($stream, 'test content');
        rewind($stream);

        $client->expects($this->once())
            ->method('uploadStream')
            ->with('test-path.txt', $stream);

        $adapter->write('test-path.txt', $stream, new \League\Flysystem\Config());

        fclose($stream);
    }

    public function test_writeStream_accepts_stream_resources()
    {
        $client = $this->createMockClient();
        $adapter = new StreamingBunnyStorageAdapter($client, '');

        $stream = fopen('php://memory', 'r+');
        fwrite($stream, 'test content');
        rewind($stream);

        $client->expects($this->once())
            ->method('uploadStream')
            ->with('test-path.txt', $stream);

        $adapter->writeStream('test-path.txt', $stream, new \League\Flysystem\Config());

        fclose($stream);
    }

    public function test_write_falls_back_to_parent_for_strings()
    {
        $client = $this->createMockClient();
        $adapter = new StreamingBunnyStorageAdapter($client, '');

        $content = 'string content';

        $client->expects($this->never())
            ->method('uploadStream');

        $client->expects($this->once())
            ->method('upload')
            ->with('test-path.txt', $content);

        $adapter->write('test-path.txt', $content, new \League\Flysystem\Config());
    }

    private function createMockClient()
    {
        return $this->getMockBuilder(StreamingBunnyStorageClient::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['uploadStream', 'upload'])
            ->getMock();
    }
}
