<?php

namespace Bangnokia\LaravelBunnyStorage;

use Carbon\CarbonInterface;
use League\Flysystem\Config;
use PlatformCommunity\Flysystem\BunnyCDN\BunnyCDNAdapter;

class BunnyStorageAdapter extends BunnyCDNAdapter
{
    public function getUrl(string $path): string
    {
        return parent::publicUrl($path, new Config);
    }

    public function getTemporaryUrl(string $path, CarbonInterface $carbon, array $options): string
    {
        return parent::temporaryUrl($path, $carbon->toDateTimeImmutable(), new Config($options));
    }
}
