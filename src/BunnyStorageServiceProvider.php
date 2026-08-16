<?php

namespace Bangnokia\LaravelBunnyStorage;

use GuzzleHttp\ClientInterface;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;
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
            $uploadOptions = $config['upload_options'] ?? [];
            $uploadClient = null;

            if (array_key_exists('upload_client', $config)) {
                $binding = $config['upload_client'];

                if (! is_string($binding) || trim($binding) === '') {
                    throw new InvalidArgumentException(
                        'Bunny [upload_client] configuration must be a container binding or class name.'
                    );
                }

                $uploadClient = $app->make($binding);

                if (! $uploadClient instanceof ClientInterface) {
                    throw new InvalidArgumentException(
                        "Bunny upload client [{$binding}] must implement ".ClientInterface::class.'.'
                    );
                }
            }

            if (! is_array($uploadOptions)) {
                throw new InvalidArgumentException('Bunny [upload_options] configuration must be an array.');
            }

            if ($pullZoneUrl && $root) {
                $pullZoneUrl = rtrim($pullZoneUrl, '/').'/'.ltrim($root, '/');
            }

            $adapter = new BunnyStorageAdapter(
                new BunnyStorageClient(
                    $config['storage_zone'],
                    $config['api_key'],
                    $config['region'],
                    $uploadOptions,
                    $uploadClient,
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
