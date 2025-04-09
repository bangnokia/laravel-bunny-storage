<?php

namespace Bangnokia\LaravelBunnyStorage;

use PlatformCommunity\Flysystem\BunnyCDN\BunnyCDNAdapter;

class BunnyStorageAdapter extends BunnyCDNAdapter
{
    public function getUrl(?string $path = null): string
    {
        return parent::getUrl((string) $path);
    }
}