<?php

namespace Bangnokia\LaravelBunnyStorage;

use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\ServiceProvider;
use League\Flysystem\Filesystem;
use League\Flysystem\PathPrefixing\PathPrefixedAdapter;

class BunnyStorageServiceProvider extends ServiceProvider
{
    public function boot()
    {
        Storage::extend('bunny', function ($app, $config) {
            $root = $config['root'] ?? '';
            $pullZoneUrl = $config['pull_zone'] ?? '';
            $tokenAuthKey = $config['token_auth_key'] ?? '';

            if ($pullZoneUrl && $root) {
                $pullZoneUrl = rtrim($pullZoneUrl, '/').'/'.ltrim($root, '/');
            }

            $adapter = new BunnyStorageAdapter(
                new BunnyStorageClient(
                    $config['storage_zone'],
                    $config['api_key'],
                    $config['region'],
                ),
                $pullZoneUrl
            );

            $adapter->setTokenAuthKey($tokenAuthKey);

            if ($root) {
                $pathPrefixedAdapter = new PathPrefixedAdapter($adapter, $root);
                $filesystem = new Filesystem($pathPrefixedAdapter, $config);
            } else {
                $filesystem = new Filesystem($adapter, $config);
            }

            return new FilesystemAdapter(
                $filesystem,
                $adapter,
                $config
            );
        });
    }
}
