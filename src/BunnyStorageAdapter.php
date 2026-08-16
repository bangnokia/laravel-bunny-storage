<?php

namespace Bangnokia\LaravelBunnyStorage;

use DateTimeImmutable;
use DateTimeInterface;
use League\Flysystem\Config;
use PlatformCommunity\Flysystem\BunnyCDN\BunnyCDNAdapter;

class BunnyStorageAdapter extends BunnyCDNAdapter
{
    public function getUrl(string $path): string
    {
        return parent::publicUrl($path, new Config);
    }

    public function getTemporaryUrl(string $path, DateTimeInterface|int $expiration, array $options = []): string
    {
        if (method_exists(BunnyCDNAdapter::class, 'getTemporaryUrl')) {
            return parent::getTemporaryUrl($path, $expiration, $options);
        }

        $expiresAt = $expiration instanceof DateTimeInterface
            ? $expiration
            : (new DateTimeImmutable('now'))->modify('+'.$expiration.' minutes');

        return parent::temporaryUrl($path, $expiresAt, new Config($options));
    }
}
