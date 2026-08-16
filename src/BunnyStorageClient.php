<?php

namespace Bangnokia\LaravelBunnyStorage;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use InvalidArgumentException;
use PlatformCommunity\Flysystem\BunnyCDN\BunnyCDNClient;
use PlatformCommunity\Flysystem\BunnyCDN\BunnyCDNRegion;
use PlatformCommunity\Flysystem\BunnyCDN\Exceptions\BunnyCDNException;

class BunnyStorageClient extends BunnyCDNClient
{
    private const DEFAULT_UPLOAD_OPTIONS = [
        'connect_timeout' => 5,
        'timeout' => 3600,
        'expect' => true,
    ];

    private ?ClientInterface $uploadClient;

    /** @var array{connect_timeout: int|float, timeout: int|float, expect: bool} */
    private array $uploadOptions;

    public function __construct(
        string $storage_zone_name,
        string $api_key,
        string $region = BunnyCDNRegion::FALKENSTEIN,
        array $upload_options = [],
        ?ClientInterface $upload_client = null
    ) {
        parent::__construct($storage_zone_name, $api_key, $region);

        $this->uploadOptions = $this->validatedUploadOptions($upload_options);
        $this->uploadClient = $upload_client;
    }

    /**
     * @throws BunnyCDNException
     */
    public function upload(string $path, $contents): mixed
    {
        $request = $this->getUploadRequest($path, $contents);

        try {
            $response = ($this->uploadClient ?? $this->guzzleClient)->send(
                $request,
                array_merge($this->uploadOptions, [
                    'allow_redirects' => false,
                    'http_errors' => false,
                ])
            );
        } catch (GuzzleException $exception) {
            throw new BunnyCDNException(
                $exception->getMessage(),
                (int) $exception->getCode(),
                $exception
            );
        }

        if ($response->getStatusCode() < 200 || $response->getStatusCode() >= 300) {
            throw new BunnyCDNException(
                'Bunny upload failed with HTTP status '.$response->getStatusCode().'.'
            );
        }

        $responseContents = $response->getBody()->getContents();

        return json_decode($responseContents, true) ?? $responseContents;
    }

    /**
     * @param  array<string, mixed>  $uploadOptions
     * @return array{connect_timeout: int|float, timeout: int|float, expect: bool}
     */
    private function validatedUploadOptions(array $uploadOptions): array
    {
        $unsupportedOptions = array_diff(
            array_keys($uploadOptions),
            array_keys(self::DEFAULT_UPLOAD_OPTIONS)
        );

        if ($unsupportedOptions !== []) {
            throw new InvalidArgumentException(
                'Unsupported Bunny upload option(s): '.implode(', ', $unsupportedOptions)
            );
        }

        foreach (['connect_timeout', 'timeout'] as $option) {
            if (! array_key_exists($option, $uploadOptions)) {
                continue;
            }

            $value = $uploadOptions[$option];

            if ((! is_int($value) && ! is_float($value)) || $value <= 0 || ! is_finite((float) $value)) {
                throw new InvalidArgumentException(
                    "Bunny upload option [{$option}] must be a finite number greater than zero."
                );
            }
        }

        if (array_key_exists('expect', $uploadOptions) && ! is_bool($uploadOptions['expect'])) {
            throw new InvalidArgumentException('Bunny upload option [expect] must be a boolean.');
        }

        return array_replace(self::DEFAULT_UPLOAD_OPTIONS, $uploadOptions);
    }
}
