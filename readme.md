<img src="https://bunny.net/static/bunnynet-dark-d6a41260b1e4b665cb2dc413e3eb84ca.svg">

# Bunny Storage for Laravel

This package is just a wrapper for Laravel of the [flysystem-bunnycdn](https://github.com/PlatformCommunity/flysystem-bunnycdn) package for simple integration with Laravel.

## Installation
```bash
composer require bangnokia/laravel-bunny-storage
```

## Configuration

This package automatically register the service provider and the storage disk for the driver `bunny`. You can configure the disk in `config/filesystems.php`:

```php
'bunny' => [
    'driver' => 'bunny',
    'storage_zone' => env('BUNNY_STORAGE_ZONE'),
    'api_key' => env('BUNNY_API_KEY'),
    'region' => env('BUNNY_REGION', \PlatformCommunity\Flysystem\BunnyCDN\BunnyCDNRegion::DEFAULT),
    'pull_zone' => env('BUNNY_PULL_ZONE', ''), // optional if you want to access the file publicly
    'root' => '', // optional, you could set a specific folder for upload like '/uploads'
],
```

and remember to add the environment variables in your `.env` file:

```dotenv
BUNNY_STORAGE_ZONE=your-storage-zone
BUNNY_API_KEY=your-api-key
#BUNNY_REGION=your-region
#BUNNY_PULL_ZONE=https://your-pull-zone-url
```


## Usage

```php
Storage::disk('bunny')->put('index.html', '<html>Hello World</html>');

return response(Storage::disk('bunny')->get('index.html'));
```


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