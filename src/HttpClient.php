<?php

namespace Kodus\Http;

use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use function array_shift;
use function fopen;
use function implode;
use function preg_match;
use function stream_context_create;
use function stream_filter_append;
use function stream_get_meta_data;

class HttpClient implements ClientInterface
{
    /**
     * @var ResponseFactoryInterface
     */
    private $response_factory;

    /**
     * @var string|null
     */
    private $proxy;

    /**
     * @var StreamFactoryInterface
     */
    private $stream_factory;

    /**
     * @param ResponseFactoryInterface $response_factory
     * @param StreamFactoryInterface   $stream_factory
     * @param string|null              $proxy optional proxy server for outgoing HTTP requests (e.g. "tcp://proxy.example.com:5100")
     */
    public function __construct(
        ResponseFactoryInterface $response_factory,
        StreamFactoryInterface $stream_factory,
        ?string $proxy = null
    ) {
        $this->proxy = $proxy;
        $this->response_factory = $response_factory;
        $this->stream_factory = $stream_factory;
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $headers = [];

        foreach ($request->getHeaders() as $name => $values) {
            foreach ($values as $value) {
                $headers[] = "{$name}: {$value}";
            }
        }

        $uri = $request->getUri()->__toString();

        $method = strtoupper($request->getMethod());

        $context = stream_context_create([
            "http" => [
                // http://docs.php.net/manual/en/context.http.php
                "method"        => $method,
                "header"        => implode("\r\n", $headers),
                "content"       => $request->getBody()->__toString(),
                "ignore_errors" => true,
                "proxy"         => $this->proxy,
            ],
        ]);

        $stream = @fopen($uri, "r", false, $context);

        if ($stream === false) {
            throw new HttpClientException("unable to open resource: {$method} {$uri}");
        }

        $headers = stream_get_meta_data($stream)['wrapper_data'];

        $status_line = array_shift($headers);

        if (preg_match('{^HTTP\/(?<version>\S*)\s(?<code>\d{3})\s*(?<reasonPhrase>.*)}', $status_line, $match) !== 1) {
            throw new HttpClientException("invalid HTTP status line: {$status_line}");
        }

        $version = $match["version"];
        $code = (int) $match["code"];
        $reasonPhrase = $match["reasonPhrase"];

        $response = $this->response_factory
            ->createResponse($code, $reasonPhrase)
            ->withProtocolVersion($version);

        foreach ($headers as $header) {
            if (preg_match('{^(?<name>[^\:]+)\:(?<value>.*)}', $header, $match) !== 1) {
                throw new HttpClientException("malformed header value: {$header}");
            }

            $response = $response->withAddedHeader($match["name"], trim($match["value"]));
        }

        if ($response->hasHeader("Content-Encoding")) {
            $encoding = $response->getHeaderLine("Content-Encoding");

            switch ($encoding) {
                case "gzip":
                    stream_filter_append($stream, "zlib.inflate", STREAM_FILTER_READ, ["window" => 30]);
                    break;

                default:
                    throw new HttpClientException("unsupported Content-Encoding: {$encoding}");
            }
        }

        return $response->withBody(
            $this->stream_factory->createStreamFromResource($stream)
        );
    }
}
