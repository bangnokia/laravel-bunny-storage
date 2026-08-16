<?php

namespace Bangnokia\LaravelBunnyStorage\Tests;

use ArrayObject;
use Bangnokia\LaravelBunnyStorage\BunnyStorageClient;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Promise\Create;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use PlatformCommunity\Flysystem\BunnyCDN\Exceptions\BunnyCDNException;
use Psr\Http\Message\RequestInterface;

class BunnyStorageClientTest extends TestCase
{
    public function test_it_forwards_configured_upload_options(): void
    {
        [$client, $transactions] = $this->recordingClient([
            'connect_timeout' => 2,
            'timeout' => 5,
            'expect' => false,
        ]);

        $client->upload('contracts/example.pdf', 'pdf contents');

        $this->assertCount(1, $transactions);
        $this->assertSame(2, $transactions[0]['options']['connect_timeout']);
        $this->assertSame(5, $transactions[0]['options']['timeout']);
        $this->assertFalse($transactions[0]['options']['expect']);
        $this->assertSame('', $transactions[0]['request']->getHeaderLine('Expect'));
    }

    public function test_it_preserves_upstream_upload_defaults(): void
    {
        [$client, $transactions] = $this->recordingClient();

        $client->upload('contracts/example.pdf', 'pdf contents');

        $this->assertCount(1, $transactions);
        $this->assertSame(5, $transactions[0]['options']['connect_timeout']);
        $this->assertSame(3600, $transactions[0]['options']['timeout']);
        $this->assertTrue($transactions[0]['options']['expect']);
        $this->assertSame('100-Continue', $transactions[0]['request']->getHeaderLine('Expect'));
    }

    public function test_it_uses_an_injected_upload_client_interface(): void
    {
        $uploadClient = $this->createMock(ClientInterface::class);
        $uploadClient->expects($this->once())
            ->method('send')
            ->with(
                $this->callback(fn (RequestInterface $request): bool => $request->getMethod() === 'PUT'),
                $this->callback(fn (array $options): bool => $options['timeout'] === 7)
            )
            ->willReturn(new Response(201, [], '{"stored":true}'));

        $fallbackClient = new Client([
            'handler' => HandlerStack::create(
                fn () => Create::promiseFor(new Response(201, [], 'fallback'))
            ),
        ]);

        $client = new BunnyStorageClient(
            'test-zone',
            'test-api-key',
            'ny',
            ['timeout' => 7],
            $uploadClient
        );
        $client->guzzleClient = $fallbackClient;

        $this->assertSame(['stored' => true], $client->upload('contracts/example.pdf', 'pdf contents'));
    }

    public function test_it_honors_the_inherited_public_guzzle_client_when_no_upload_client_is_injected(): void
    {
        $transactions = new ArrayObject;
        $handler = function (RequestInterface $request, array $options) use ($transactions) {
            $transactions[] = compact('request', 'options');

            return Create::promiseFor(new Response(201));
        };
        $replacementClient = new Client(['handler' => HandlerStack::create($handler)]);
        $client = new BunnyStorageClient('test-zone', 'test-api-key', 'ny');

        // Setting this to null fails safely before the fix instead of using the
        // real client that the upstream constructor initially creates.
        $uploadClientProperty = new \ReflectionProperty(BunnyStorageClient::class, 'uploadClient');
        $uploadClientProperty->setValue($client, null);
        $client->guzzleClient = $replacementClient;

        $client->upload('contracts/example.pdf', 'pdf contents');

        $this->assertCount(1, $transactions);
    }

    #[DataProvider('unsupportedUploadOptions')]
    public function test_it_rejects_unsupported_upload_options(array $uploadOptions): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported Bunny upload option');

        $this->recordingClient($uploadOptions);
    }

    public static function unsupportedUploadOptions(): iterable
    {
        yield 'TLS verification' => [['verify' => false]];
        yield 'headers' => [['headers' => ['AccessKey' => 'overridden']]];
        yield 'authentication' => [['auth' => ['user', 'password']]];
        yield 'handler' => [['handler' => static fn () => null]];
        yield 'base URI' => [['base_uri' => 'https://example.invalid']];
        yield 'redirects' => [['allow_redirects' => true]];
    }

    #[DataProvider('invalidUploadOptions')]
    public function test_it_rejects_invalid_upload_option_values(array $uploadOptions): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->recordingClient($uploadOptions);
    }

    public static function invalidUploadOptions(): iterable
    {
        yield 'zero connect timeout' => [['connect_timeout' => 0]];
        yield 'negative timeout' => [['timeout' => -1]];
        yield 'string timeout' => [['timeout' => '5']];
        yield 'invalid expect value' => [['expect' => 'false']];
        yield 'negative expect threshold' => [['expect' => -1]];
    }

    public function test_it_uploads_string_contents_without_changing_the_body(): void
    {
        [$client, $transactions] = $this->recordingClient();

        $client->upload('contracts/example.pdf', 'pdf contents');

        $request = $transactions[0]['request'];
        $this->assertSame('PUT', $request->getMethod());
        $this->assertSame('application/octet-stream', $request->getHeaderLine('Content-Type'));
        $this->assertSame('pdf contents', (string) $request->getBody());
    }

    public function test_it_uploads_a_seekable_stream_without_changing_the_body(): void
    {
        $stream = fopen('php://temp', 'w+b');
        $this->assertIsResource($stream);
        fwrite($stream, 'streamed pdf contents');
        rewind($stream);

        [$client, $transactions] = $this->recordingClient();
        $client->upload('contracts/example.pdf', $stream);

        $request = $transactions[0]['request'];
        $this->assertTrue($request->getBody()->isSeekable());
        $this->assertSame('streamed pdf contents', (string) $request->getBody());
        $this->assertSame((string) strlen('streamed pdf contents'), $request->getHeaderLine('Content-Length'));

        fclose($stream);
    }

    public function test_it_preserves_the_original_transport_exception_and_does_not_retry(): void
    {
        $attempts = 0;
        $connectException = new ConnectException(
            'unexpected EOF while reading',
            new Request('PUT', 'https://example.invalid/contracts/example.pdf')
        );
        $handler = function () use (&$attempts, $connectException) {
            $attempts++;

            return Create::rejectionFor($connectException);
        };
        $httpClient = new Client(['handler' => HandlerStack::create($handler)]);
        $client = new BunnyStorageClient('test-zone', 'test-api-key', 'ny', [], $httpClient);
        $client->guzzleClient = $httpClient;

        try {
            $client->upload('contracts/example.pdf', 'pdf contents');
            $this->fail('The failed upload should throw a BunnyCDNException.');
        } catch (BunnyCDNException $exception) {
            $this->assertSame($connectException, $exception->getPrevious());
            $this->assertSame($connectException->getMessage(), $exception->getMessage());
        }

        $this->assertSame(1, $attempts);
    }

    public function test_it_fails_without_replaying_an_upload_when_bunny_redirects(): void
    {
        $transactions = new ArrayObject;
        $handler = function (RequestInterface $request, array $options) use ($transactions) {
            $transactions[] = compact('request', 'options');

            return Create::promiseFor(count($transactions) === 1
                ? new Response(307, ['Location' => 'https://redirect.example.invalid/upload'])
                : new Response(201));
        };
        $httpClient = new Client(['handler' => HandlerStack::create($handler)]);
        $client = new BunnyStorageClient('test-zone', 'test-api-key', 'ny', [], $httpClient);

        try {
            $client->upload('contracts/example.pdf', 'pdf contents');
            $this->fail('A redirect response should fail the upload.');
        } catch (BunnyCDNException $exception) {
            $this->assertSame('Bunny upload failed with HTTP status 307.', $exception->getMessage());
            $this->assertNull($exception->getPrevious());
        }

        $this->assertCount(1, $transactions);
        $this->assertFalse($transactions[0]['options']['allow_redirects']);
    }

    #[DataProvider('unsuccessfulHttpStatuses')]
    public function test_it_rejects_unsuccessful_http_responses(int $status): void
    {
        $handler = fn () => Create::promiseFor(new Response($status, [], 'provider response'));
        $httpClient = new Client(['handler' => HandlerStack::create($handler)]);
        $client = new BunnyStorageClient('test-zone', 'test-api-key', 'ny', [], $httpClient);

        $this->expectException(BunnyCDNException::class);
        $this->expectExceptionMessage("Bunny upload failed with HTTP status {$status}.");

        $client->upload('contracts/example.pdf', 'pdf contents');
    }

    public static function unsuccessfulHttpStatuses(): iterable
    {
        yield 'unauthorized' => [401];
        yield 'server error' => [500];
    }

    /**
     * @return array{BunnyStorageClient, ArrayObject<int, array{request: RequestInterface, options: array<string, mixed>}>}
     */
    private function recordingClient(array $uploadOptions = []): array
    {
        $transactions = new ArrayObject;
        $handler = function (RequestInterface $request, array $options) use ($transactions) {
            $transactions[] = compact('request', 'options');

            return Create::promiseFor(new Response(201));
        };
        $httpClient = new Client(['handler' => HandlerStack::create($handler)]);
        $client = new BunnyStorageClient('test-zone', 'test-api-key', 'ny', $uploadOptions, $httpClient);

        // This keeps the pre-fix regression run isolated from the real network too.
        $client->guzzleClient = $httpClient;

        return [$client, $transactions];
    }
}
