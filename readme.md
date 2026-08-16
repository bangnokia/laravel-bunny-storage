<img src="https://bunny.net/static/bunnynet-dark-d6a41260b1e4b665cb2dc413e3eb84ca.svg">

# Bunny Storage for Laravel

This package is just a wrapper for Laravel of the [flysystem-bunnycdn](https://github.com/PlatformCommunity/flysystem-bunnycdn) package for simple integration with Laravel.

## Installation
```bash
composer require bangnokia/laravel-bunny-storage
```

Laravel 10 and 11 remain available for legacy compatibility but are end of life.
Use a currently security-supported Laravel release for production when possible.

## Configuration

This package automatically register the service provider and the storage disk for the driver `bunny`. You can configure the disk in `config/filesystems.php`:

```php
'bunny' => [
    'driver' => 'bunny',
    'storage_zone' => env('BUNNY_STORAGE_ZONE'),
    'api_key' => env('BUNNY_API_KEY'),
    'region' => env('BUNNY_REGION', \PlatformCommunity\Flysystem\BunnyCDN\BunnyCDNRegion::DEFAULT),
    'token_auth_key' => env('BUNNY_TOKEN_AUTH_KEY'), // optional if you want to generate temporaryUrls
    'pull_zone' => env('BUNNY_PULL_ZONE', ''), // optional if you want to access the file publicly
    'root' => '', // optional, you could set a specific folder for upload like '/uploads'
    'upload_options' => [
        'connect_timeout' => 3,
        'timeout' => 50,
        'expect' => false,
    ], // optional; defaults are 5, 3600, and true
],
```

and remember to add the environment variables in your `.env` file:

```dotenv
BUNNY_STORAGE_ZONE=your-storage-zone-name
BUNNY_API_KEY=your-api-key (it's password in bunny)
#BUNNY_REGION=your-region (optional)
#BUNNY_PULL_ZONE="https://your-pull-zone-url" (optional if you want to access the file publicly)
#BUNNY_TOKEN_AUTH_KEY=your-key (optional, CDN > Security > Token authentication > Url token authentication key)
```


## Usage

```php
Storage::disk('bunny')->put('index.html', '<html>Hello World</html>');

return response(Storage::disk('bunny')->get('index.html'));
```

## Streaming Support

This package includes **streaming support** for large file uploads, which significantly reduces memory usage.

### Memory Efficient Uploads

When uploading large files (e.g., database backups, videos), the streaming adapter automatically:

1. Detects when a file stream/resource is provided
2. Streams the file directly to BunnyCDN without loading into memory
3. Reduces memory usage from O(file_size) to O(buffer_size)

### Example: Large File Upload

```php
// Efficient streaming - memory stays low (~8-16MB buffer)
$stream = fopen('/path/to/large-file.zip', 'r');
Storage::disk('bunny')->writeStream('backup.zip', $stream);
fclose($stream);

// Storage::disk('bunny')->put('backup.zip', $stream) also accepts an open stream.
```

## Upload Transport Configuration

The `upload_options` disk setting supports three Guzzle request options:
`connect_timeout`, `timeout`, and `expect`. Timeouts must be positive numbers and
`expect` must be a boolean. Other request options are rejected so disk
configuration cannot override TLS verification, credentials, headers, or the
request body.

Choose a timeout below your API or queue worker deadline, with enough headroom
for the application to handle a failed upload. Setting `expect` to `false`
disables the `Expect: 100-Continue` handshake. Existing disks keep the upstream
defaults listed in the example comment until these options are configured.

Uploads are attempted once. Redirects are not followed, and every non-2xx
response fails explicitly. The package does not automatically retry a failed PUT
because Bunny may have stored the object before the connection was lost, and a
stream may not be safe to replay. Reconcile the expected object before a bounded
application-level retry.

An upload-only Guzzle client can optionally be resolved from Laravel's container
using a class name or binding string:

```php
'upload_client' => App\Support\BunnyUploadClient::class,
```

Register the binding in an application service provider so `config:cache`
contains only the string. Use a dedicated client: it receives the Bunny
`AccessKey` header and uploaded file body, so request logging must be disabled.
Do not log or serialize transport exception request objects for the same reason.
Do not attach automatic retry or redirect middleware to the custom client.


## Regions
For a full region list, please visit the [BunnyCDN API documentation page](https://docs.bunny.net/reference/regionpublic_index).

`flysystem-bunnycdn` also comes with constants for each region located within `PlatformCommunity\Flysystem\BunnyCDN\BunnyCDNRegion`.

```php
# Europe
BunnyCDNRegion::FALKENSTEIN = 'de';
BunnyCDNRegion::STOCKHOLM = 'se';

# United Kingdom
BunnyCDNRegion::UNITED_KINGDOM = 'uk';

# USA
BunnyCDNRegion::NEW_YORK = 'ny';
BunnyCDNRegion::LOS_ANGELAS = 'la';

# SEA
BunnyCDNRegion::SINGAPORE = 'sg';

# Oceania
BunnyCDNRegion::SYDNEY = 'syd';

# Africa
BunnyCDNRegion::JOHANNESBURG = 'jh';

# South America
BunnyCDNRegion::BRAZIL = 'br';
```
