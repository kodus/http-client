<?php

namespace Kodus\Http;

use Psr\Http\Client\ClientExceptionInterface;
use RuntimeException;

class HttpClientException extends RuntimeException implements ClientExceptionInterface
{
    // nothing here.
}
