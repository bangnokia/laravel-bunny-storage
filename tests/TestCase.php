<?php

namespace Bangnokia\LaravelBunnyStorage\Tests;

use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Get package providers.
     *
     * @param  \Illuminate\Foundation\Application  $app
     * @return array
     */
    protected function getPackageProviders($app)
    {
        return ['Bangnokia\LaravelBunnyStorage\BunnyStorageServiceProvider'];
    }

    /**
     * Define environment setup.
     *
     * @param  \Illuminate\Foundation\Application  $app
     * @return void
     */
    protected function getEnvironmentSetUp($app)
    {
        // Setup default database to use sqlite :memory:
        $app['config']->set('filesystems.disks.bunny', [
            'driver' => 'bunny',
            'storage_zone' => 'test-zone',
            'api_key' => 'test-api-key',
            'region' => 'ny',
            'hostname' => null,
            'directory' => null,
        ]);
    }
}
