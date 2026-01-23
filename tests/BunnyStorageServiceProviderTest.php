<?php

namespace Bangnokia\LaravelBunnyStorage\Tests;

use Bangnokia\LaravelBunnyStorage\BunnyStorageAdapter;
use Bangnokia\LaravelBunnyStorage\BunnyStorageServiceProvider;
use Illuminate\Filesystem\FilesystemAdapter;
use League\Flysystem\Filesystem;
use Orchestra\Testbench\TestCase;

class BunnyStorageServiceProviderTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [BunnyStorageServiceProvider::class];
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Configure the test environment
        config([
            'filesystems.disks.bunny' => [
                'driver' => 'bunny',
                'storage_zone' => 'test-zone',
                'api_key' => 'test-api-key',
                'token_auth_key' => 'test-auth-key',
                'region' => 'ny',
                'hostname' => null,
                'directory' => null,
            ],
        ]);
    }

    public function test_it_registers_bunny_storage_driver()
    {
        $filesystem = $this->app['filesystem'];
        $disk = $filesystem->disk('bunny');

        $this->assertInstanceOf(FilesystemAdapter::class, $disk);

        $reflection = new \ReflectionClass($disk);
        $property = $reflection->getProperty('driver');
        // Use PHP 8+ compatible reflection approach
        $property->setValue($disk, $driver = $property->getValue($disk));

        $this->assertInstanceOf(Filesystem::class, $driver);

        // In Flysystem v3, we need to use reflection to get the adapter
        $filesystemReflection = new \ReflectionClass($driver);
        $adapterProperty = $filesystemReflection->getProperty('adapter');
        // Use PHP 8+ compatible approach
        $adapter = $adapterProperty->getValue($driver);

        $this->assertInstanceOf(BunnyStorageAdapter::class, $adapter);
    }
}
