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
        Storage::extend('bunny', function($app, $config) {
            $root = $config['root'] ?? '';
            $pullZoneUrl = $config['pull_zone'] ?? '';

            if ($pullZoneUrl && $root) {
                $pullZoneUrl = rtrim($pullZoneUrl, '/') . '/' . ltrim($root, '/');
            }

            $adapter = new BunnyStorageAdapter(
                new BunnyStorageClient(
                    $config['storage_zone'],
                    $config['api_key'],
                    $config['region'],
                ),
                $pullZoneUrl
            );

            $pathPrefixedAdapter =  new PathPrefixedAdapter($adapter, $root);

            return new FilesystemAdapter(
                new Filesystem($pathPrefixedAdapter, $config),
                $pathPrefixedAdapter,
                $config
            );
        });
    }
}