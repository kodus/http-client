# `kodus/http-client`

Minimalist [PSR-18](https://www.php-fig.org/psr/psr-18/) HTTP Client.

[![PHP Version](https://img.shields.io/badge/php-7.1%2B-blue.svg)](https://packagist.org/packages/kodus/http-client)
[![Build Status](https://travis-ci.org/kodus/http-client.svg?branch=master)](https://travis-ci.org/kodus/http-client)
[![Code Coverage](https://scrutinizer-ci.com/g/kodus/http-client/badges/coverage.png?b=master)](https://scrutinizer-ci.com/g/kodus/http-client/?branch=master)

 * No dependencies beyond [PSR-17](https://www.php-fig.org/psr/psr-17/) HTTP Factory implementations
 * Streaming response: suitable for fetching large responses.
 * Accepts and decodes `gzip` encoded response content.

Note that this client does not follow redirects: PSR-18 doesn't specify - but this is a client, not a browser,
and it's designed to be bootstrapped as a service instance: some dependents may need to know the status-code of
the actual response; automatically following redirects makes that impossible.

## Usage

Basic example using [`nyholm/psr7`](https://packagist.org/packages/nyholm/psr7):

```php
use Kodus\Http\HttpClient;
use Nyholm\Psr7\Factory\Psr17Factory;

// Bootstrap the client:

$http = new Psr17Factory();

$client = new HttpClient($http, $http);

// Perform a request:

$response = $client->sendRequest(
    $http->createRequest("GET", "https://postman-echo.com/get?foo=bar")
);
```

Please refer to [PSR-18 documentation](https://www.php-fig.org/psr/psr-18/) for details.
