<?php

namespace Bangnokia\LaravelBunnyStorage;

use PlatformCommunity\Flysystem\BunnyCDN\BunnyCDNClient;
use PlatformCommunity\Flysystem\BunnyCDN\BunnyCDNRegion;

class BunnyStorageClient extends BunnyCDNClient
{
    private string $root;

    public function __construct(string $storage_zone_name, string $api_key, string $region = BunnyCDNRegion::DEFAULT, $root = '')
    {
        parent::__construct($storage_zone_name, $api_key, $region);

        $this->root = $root;
    }

    protected function appendRoot(string $path): string
    {
        return $this->root . '/' . $path;
    }

    #[\Override]
    public function list(string $path): array
    {
        $path = $this->appendRoot($path);

        return parent::list($path);
    }

    #[\Override]
    public function download(string $path): string
    {
        $path = $this->appendRoot($path);

        return parent::download($path);
    }

    #[\Override]
    public function stream(string $path)
    {
        $path = $this->appendRoot($path);

        return parent::stream($path);
    }

    #[\Override]
    public function upload(string $path, $contents): mixed
    {
        $path = $this->appendRoot($path);

        return parent::upload($path, $contents);
    }

    #[\Override]
    public function make_directory(string $path): mixed
    {
        $path = $this->appendRoot($path);

        return parent::make_directory($path);
    }

    #[\Override]
    public function delete(string $path): mixed
    {
        $path = $this->appendRoot($path);

        return parent::delete($path);
    }
}