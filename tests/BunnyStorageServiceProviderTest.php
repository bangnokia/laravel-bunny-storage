<?php

namespace Bangnokia\LaravelBunnyStorage\Tests;

use Bangnokia\LaravelBunnyStorage\BunnyStorageAdapter;
use Bangnokia\LaravelBunnyStorage\BunnyStorageClient;
use Bangnokia\LaravelBunnyStorage\BunnyStorageServiceProvider;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\Response;
use Illuminate\Filesystem\FilesystemAdapter;
use InvalidArgumentException;
use League\Flysystem\Filesystem;
use Orchestra\Testbench\TestCase;
use PlatformCommunity\Flysystem\BunnyCDN\BunnyCDNAdapter;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

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

    public function test_it_forwards_cache_safe_upload_configuration(): void
    {
        $uploadClient = new RecordingUploadClient;
        $this->app->instance('bunny.upload-client', $uploadClient);

        $diskConfig = [
            'driver' => 'bunny',
            'storage_zone' => 'test-zone',
            'api_key' => 'test-api-key',
            'token_auth_key' => 'test-auth-key',
            'region' => 'ny',
            'upload_options' => [
                'connect_timeout' => 2,
                'timeout' => 5,
                'expect' => false,
            ],
            'upload_client' => 'bunny.upload-client',
        ];

        /** @var array<string, mixed> $cachedConfig */
        $cachedConfig = eval('return '.var_export($diskConfig, true).';');
        config(['filesystems.disks.bunny' => $cachedConfig]);

        $disk = $this->app['filesystem']->disk('bunny');
        $adapter = $this->adapterFromDisk($disk);
        $clientProperty = new \ReflectionProperty(BunnyCDNAdapter::class, 'client');
        $client = $clientProperty->getValue($adapter);

        $this->assertInstanceOf(BunnyStorageClient::class, $client);

        $uploadClientProperty = new \ReflectionProperty(BunnyStorageClient::class, 'uploadClient');
        $this->assertSame($uploadClient, $uploadClientProperty->getValue($client));

        $uploadOptionsProperty = new \ReflectionProperty(BunnyStorageClient::class, 'uploadOptions');
        $this->assertSame([
            'connect_timeout' => 2,
            'timeout' => 5,
            'expect' => false,
        ], $uploadOptionsProperty->getValue($client));

        $disk->put('contracts/example.pdf', 'pdf contents');

        $this->assertCount(1, $uploadClient->requests);
        $this->assertSame(5, $uploadClient->requests[0]['options']['timeout']);
    }

    public function test_it_rejects_a_non_string_upload_client_configuration(): void
    {
        config(['filesystems.disks.bunny.upload_client' => new RecordingUploadClient]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('must be a container binding or class name');

        $this->app['filesystem']->disk('bunny');
    }

    public function test_it_rejects_an_upload_client_that_does_not_implement_the_interface(): void
    {
        $this->app->instance('bunny.invalid-upload-client', new \stdClass);
        config(['filesystems.disks.bunny.upload_client' => 'bunny.invalid-upload-client']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('must implement '.ClientInterface::class);

        $this->app['filesystem']->disk('bunny');
    }

    public function test_it_rejects_non_array_upload_options(): void
    {
        config(['filesystems.disks.bunny.upload_options' => 'timeout=5']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('must be an array');

        $this->app['filesystem']->disk('bunny');
    }

    private function adapterFromDisk(FilesystemAdapter $disk): BunnyStorageAdapter
    {
        $diskReflection = new \ReflectionClass($disk);
        $driverProperty = $diskReflection->getProperty('driver');
        $driver = $driverProperty->getValue($disk);

        $filesystemReflection = new \ReflectionClass($driver);
        $adapterProperty = $filesystemReflection->getProperty('adapter');

        return $adapterProperty->getValue($driver);
    }
}

class RecordingUploadClient implements ClientInterface
{
    /** @var array<int, array{request: RequestInterface, options: array<string, mixed>}> */
    public array $requests = [];

    public function send(RequestInterface $request, array $options = []): ResponseInterface
    {
        $this->requests[] = compact('request', 'options');

        return new Response(201);
    }

    public function sendAsync(RequestInterface $request, array $options = []): PromiseInterface
    {
        throw new \LogicException('Asynchronous requests are not used for uploads.');
    }

    public function request(string $method, $uri, array $options = []): ResponseInterface
    {
        throw new \LogicException('Request shortcuts are not used for uploads.');
    }

    public function requestAsync(string $method, $uri, array $options = []): PromiseInterface
    {
        throw new \LogicException('Asynchronous requests are not used for uploads.');
    }

    public function getConfig(?string $option = null)
    {
        return null;
    }
}
