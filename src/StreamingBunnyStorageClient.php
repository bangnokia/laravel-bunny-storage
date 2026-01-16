<?php

namespace Bangnokia\LaravelBunnyStorage;

use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Psr7\Request;
use PlatformCommunity\Flysystem\BunnyCDN\BunnyCDNClient as BaseClient;
use PlatformCommunity\Flysystem\BunnyCDN\Exceptions\BunnyCDNException;

class StreamingBunnyStorageClient extends BaseClient
{
    private static ?\ReflectionMethod $requestMethod = null;
    private static ?\ReflectionMethod $createRequestMethod = null;

    public function uploadStream(string $path, $stream): mixed
    {
        $this->validateStream($stream);
        $this->resetStreamPosition($stream);

        try {
            $request = $this->getUploadStreamRequest($path, $stream);

            return $this->invokeRequest($request);
        } catch (GuzzleException $e) {
            throw new BunnyCDNException($e->getMessage());
        }
    }

    private function validateStream($stream): void
    {
        if (!is_resource($stream)) {
            throw new BunnyCDNException('Stream must be a valid resource');
        }

        $meta = stream_get_meta_data($stream);
        if (!in_array($meta['type'], ['STDIO', 'TEMP', 'MEMORY'])) {
            throw new BunnyCDNException('Invalid stream type: ' . $meta['type']);
        }
    }

    private function resetStreamPosition($stream): void
    {
        if (stream_get_meta_data($stream)['seekable']) {
            rewind($stream);
        }
    }

    private function invokeRequest(Request $request): mixed
    {
        if (self::$requestMethod === null) {
            $reflection = new \ReflectionClass(BaseClient::class);
            self::$requestMethod = $reflection->getMethod('request');
            self::$requestMethod->setAccessible(true);
        }

        return self::$requestMethod->invoke($this, $request);
    }

    private function getUploadStreamRequest(string $path, $stream): Request
    {
        $path = ltrim($path, '/');

        return $this->invokeCreateRequest(
            $path,
            'PUT',
            [
                'Content-Type' => 'application/octet-stream',
                'Content-Length' => $this->getStreamSize($stream),
            ],
            $stream
        );
    }

    private function invokeCreateRequest(string $path, string $method, array $headers, $body): Request
    {
        if (self::$createRequestMethod === null) {
            $reflection = new \ReflectionClass(BaseClient::class);
            self::$createRequestMethod = $reflection->getMethod('createRequest');
            self::$createRequestMethod->setAccessible(true);
        }

        return self::$createRequestMethod->invoke($this, $path, $method, $headers, $body);
    }

    private function getStreamSize($stream): string
    {
        $stats = fstat($stream);
        if ($stats === false) {
            return '0';
        }

        $size = $stats['size'];
        if ($size < 0) {
            return '0';
        }

        return (string) $size;
    }
}
