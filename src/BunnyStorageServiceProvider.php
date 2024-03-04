<?php

namespace Bangnokia\LaravelBunnyStorage;

use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\ServiceProvider;
use League\Flysystem\Filesystem;
use PlatformCommunity\Flysystem\BunnyCDN\BunnyCDNAdapter;
use PlatformCommunity\Flysystem\BunnyCDN\BunnyCDNClient;

class BunnyStorageServiceProvider extends ServiceProvider
{
    public function boot()
    {
        Storage::extend('bunny', function($app, $config) {
            $adapter = new BunnyCDNAdapter(
                new BunnyCDNClient(
                    $config['storage_zone'],
                    $config['api_key'],
                    $config['region']
                ),
                $config['pull_zone']
            );

            return new FilesystemAdapter(
                new Filesystem($adapter, $config),
                $adapter,
                $config
            );
        });
    }
}