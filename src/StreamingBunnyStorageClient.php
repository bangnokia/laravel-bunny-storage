<?php

namespace Bangnokia\LaravelBunnyStorage;

use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Psr7\Request;
use PlatformCommunity\Flysystem\BunnyCDN\BunnyCDNClient as BaseClient;
use PlatformCommunity\Flysystem\BunnyCDN\Exceptions\BunnyCDNException;
use Psr\Http\Client\ClientExceptionInterface;

class StreamingBunnyStorageClient extends BaseClient
{
    public function uploadStream(string $path, $stream): mixed
    {
        try {
            $request = $this->getUploadStreamRequest($path, $stream);
            
            return $this->request($request);
        } catch (GuzzleException $e) {
            throw new BunnyCDNException($e->getMessage());
        }
    }

    private function getUploadStreamRequest(string $path, $stream): Request
    {
        return $this->createRequest(
            $path,
            'PUT',
            [
                'Content-Type' => 'application/octet-stream',
            ],
            $stream
        );
    }
}
