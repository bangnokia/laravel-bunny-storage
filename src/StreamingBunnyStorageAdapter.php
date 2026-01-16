<?php

namespace Bangnokia\LaravelBunnyStorage;

use League\Flysystem\Config;
use PlatformCommunity\Flysystem\BunnyCDN\BunnyCDNAdapter as BaseAdapter;

class StreamingBunnyStorageAdapter extends BaseAdapter
{
    public function __construct(StreamingBunnyStorageClient $client, string $pullZoneUrl = '')
    {
        parent::__construct($client, $pullZoneUrl);
        $this->client = $client;
    }

    public function writeStream($path, $contents, Config $config): void
    {
        if (is_resource($contents)) {
            $this->client->uploadStream($path, $contents);
        } else {
            parent::writeStream($path, $contents, $config);
        }
    }

    public function write($path, $contents, Config $config): void
    {
        if (is_resource($contents)) {
            $this->client->uploadStream($path, $contents);
        } else {
            parent::write($path, $contents, $config);
        }
    }
}
