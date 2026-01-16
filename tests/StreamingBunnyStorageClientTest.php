<?php

namespace Bangnokia\LaravelBunnyStorage\Tests;

use Bangnokia\LaravelBunnyStorage\StreamingBunnyStorageClient;
use GuzzleHttp\Psr7\Request;
use PHPUnit\Framework\TestCase;

class StreamingBunnyStorageClientTest extends TestCase
{
    private function createClient(): StreamingBunnyStorageClient
    {
        return new StreamingBunnyStorageClient(
            'test-zone',
            'test-api-key',
            'ny'
        );
    }

    public function test_uploadStream_creates_put_request_with_stream_body()
    {
        $client = $this->createClient();

        $reflection = new \ReflectionClass($client);
        $method = $reflection->getMethod('getUploadStreamRequest');
        $method->setAccessible(true);

        $stream = fopen('php://memory', 'r+');
        fwrite($stream, 'test content');
        rewind($stream);

        $request = $method->invoke($client, 'test/path.txt', $stream);

        $this->assertInstanceOf(Request::class, $request);
        $this->assertEquals('PUT', $request->getMethod());
        $this->assertEquals('test-api-key', $request->getHeaderLine('AccessKey'));
        $this->assertEquals('application/octet-stream', $request->getHeaderLine('Content-Type'));

        fclose($stream);
    }

    public function test_uploadStream_normalizes_path()
    {
        $client = $this->createClient();

        $reflection = new \ReflectionClass($client);
        $method = $reflection->getMethod('getUploadStreamRequest');
        $method->setAccessible(true);

        $stream = fopen('php://memory', 'r+');

        $request = $method->invoke($client, '/leading/slash/path.txt', $stream);
        $uri = $request->getUri();

        $this->assertStringNotStartsWith('//', (string) $uri);
        $this->assertStringNotStartsWith('/', parse_url((string) $uri, PHP_URL_PATH));

        fclose($stream);
    }

    public function test_uploadStream_constructs_correct_url()
    {
        $client = $this->createClient();

        $reflection = new \ReflectionClass($client);
        $method = $reflection->getMethod('getUploadStreamRequest');
        $method->setAccessible(true);

        $stream = fopen('php://memory', 'r+');

        $request = $method->invoke($client, 'test/file.bin', $stream);
        $uri = $request->getUri();

        $this->assertEquals('https://ny.storage.bunnycdn.com', $uri->getScheme() . '://' . $uri->getHost());
        $this->assertStringContainsString('test-zone', (string) $uri);

        fclose($stream);
    }

    public function test_it_supports_ny_region()
    {
        $client = new StreamingBunnyStorageClient('zone', 'key', 'ny');
        $reflection = new \ReflectionClass($client);
        $method = $reflection->getMethod('getUploadStreamRequest');
        $method->setAccessible(true);

        $stream = fopen('php://memory', 'r+');
        $request = $method->invoke($client, 'file.txt', $stream);
        $uri = $request->getUri();

        $this->assertEquals('ny.storage.bunnycdn.com', $uri->getHost());

        fclose($stream);
    }

    public function test_it_supports_uk_region()
    {
        $client = new StreamingBunnyStorageClient('zone', 'key', 'uk');
        $reflection = new \ReflectionClass($client);
        $method = $reflection->getMethod('getUploadStreamRequest');
        $method->setAccessible(true);

        $stream = fopen('php://memory', 'r+');
        $request = $method->invoke($client, 'file.txt', $stream);
        $uri = $request->getUri();

        $this->assertEquals('uk.storage.bunnycdn.com', $uri->getHost());

        fclose($stream);
    }
}
