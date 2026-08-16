<?php

namespace Bangnokia\LaravelBunnyStorage\Tests;

use Bangnokia\LaravelBunnyStorage\BunnyStorageAdapter;
use Bangnokia\LaravelBunnyStorage\BunnyStorageClient;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

class BunnyStorageAdapterTest extends TestCase
{
    public function test_it_generates_temporary_urls_from_minutes_or_a_date(): void
    {
        $adapter = new BunnyStorageAdapter(
            new BunnyStorageClient('test-zone', 'test-api-key', 'ny'),
            'https://cdn.example.com'
        );
        $adapter->setTokenAuthKey('test-token-key');

        $minutesUrl = $adapter->getTemporaryUrl('contracts/example.pdf', 5);
        $dateUrl = $adapter->getTemporaryUrl(
            'contracts/example.pdf',
            new DateTimeImmutable('2030-01-01T00:00:00Z')
        );

        $this->assertStringStartsWith(
            'https://cdn.example.com/contracts/example.pdf?token=',
            $minutesUrl
        );
        $this->assertStringContainsString('&expires=', $minutesUrl);
        $this->assertStringContainsString('&expires=1893456000', $dateUrl);
    }
}
