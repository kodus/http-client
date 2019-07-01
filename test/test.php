<?php

use Kodus\Http\HttpClient;
use function mindplay\testies\configure;
use function mindplay\testies\eq;
use function mindplay\testies\expect;
use function mindplay\testies\run;
use function mindplay\testies\test;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Client\ClientExceptionInterface;

require dirname(__DIR__) . "/vendor/autoload.php";

$http = new Psr17Factory();

$client = new HttpClient($http, $http);

test(
    "can create client",
    function () use ($http) {
        $client = new HttpClient($http, $http);
    }
);

test(
    "can perform GET request",
    function () use ($http, $client) {
        $expected_params = [
            "foo1" => "bar1",
            "foo2" => "bar2",
        ];

        $query = http_build_query($expected_params);

        $response = $client->sendRequest(
            $http->createRequest("GET", "https://postman-echo.com/get?{$query}")
        );

        eq($response->getProtocolVersion(), "1.1");

        eq($response->getStatusCode(), 200);

        $result = json_decode($response->getBody()->__toString(), true);

        eq($result["args"], $expected_params, "server received query params");
    }
);

test(
    "can parse GZIP response",
    function () use ($http, $client) {
        $response = $client->sendRequest(
            $http->createRequest("GET", "https://postman-echo.com/gzip")
                ->withHeader("Accept-Encoding", "gzip")
        );

        eq($response->getStatusCode(), 200);

        eq($response->getHeaderLine("Content-Type"), "application/json; charset=utf-8");

        $result = json_decode($response->getBody()->__toString(), true);

        eq($result["gzipped"], true, "gzipped response body was correctly decoded");
    }
);

test(
    "should throw for unsupported Content-Encoding",
    function () use ($http, $client) {
        expect(
            ClientExceptionInterface::class,
            "'deflate' encoding is unsupported by this client",
            function () use ($http, $client) {
                $client->sendRequest(
                    $http->createRequest("GET", "https://postman-echo.com/deflate")
                        ->withHeader("Accept-Encoding", "deflate")
                );
            },
            "/unsupported Content-Encoding: deflate/"
        );
    }
);

test(
    "can perform plain-text POST request",
    function () use ($http, $client) {
        $expected_body = "This is expected to be sent back as part of response body.";

        $response = $client->sendRequest(
            $http->createRequest("POST", "https://postman-echo.com/post")
                ->withHeader("Content-Type", "text/plain")
                ->withBody($http->createStream($expected_body))
        );

        eq($response->getStatusCode(), 200);

        $result = json_decode($response->getBody()->__toString(), true);

        eq($result["data"], $expected_body, "server received POST body");
    }
);

test(
    "can perform JSON POST request",
    function () use ($http, $client) {
        $expected_data = [
            "foo1" => 123,
            "foo2" => 456,
        ];

        $body = $http->createStream(
            json_encode($expected_data)
        );

        $response = $client->sendRequest(
            $http->createRequest("POST", "https://postman-echo.com/post")
                ->withHeader("Content-Type", "application/json")
                ->withBody($body)
        );

        eq($response->getStatusCode(), 200);

        $result = json_decode($response->getBody()->__toString(), true);

        eq($result["data"], $expected_data, "server received JSON data");
    }
);

test(
    "can perform x-www-form POST request",
    function () use ($http, $client) {
        $expected_data = [
            "foo1" => "bar1",
            "foo2" => "bar2",
        ];

        $body = $http->createStream(
            http_build_query($expected_data)
        );

        $response = $client->sendRequest(
            $http->createRequest("POST", "https://postman-echo.com/post")
                ->withHeader("Content-Type", "application/x-www-form-urlencoded")
                ->withBody($body)
        );

        eq($response->getStatusCode(), 200);

        $result = json_decode($response->getBody()->__toString(), true);

        eq($result["form"], $expected_data, "server received x-www-form POST data");
    }
);

test(
    "can send headers",
    function () use ($http, $client) {
        $response = $client->sendRequest(
            $http->createRequest("GET", "https://postman-echo.com/headers")
                ->withHeader("X-Hello", "Hello World")
        );

        eq($response->getStatusCode(), 200);

        $result = json_decode($response->getBody()->__toString(), true);

        eq($result["headers"]["x-hello"], "Hello World", "server received custom header");
    }
);

test(
    "can receive headers",
    function () use ($http, $client) {
        $response = $client->sendRequest(
            $http->createRequest("GET", "https://postman-echo.com/response-headers?x-hello=one&x-hello=two")
        );

        eq($response->getStatusCode(), 200);

        eq(
            $response->getHeaderLine("Content-Type"),
            "application/json; charset=utf-8",
            "Response model was populated with headers"
        );

        eq(
            $response->getHeader("X-Hello"),
            ["one", "two"],
            "Can parse multi-line header"
        );
    }
);

configure()->enableCodeCoverage(__DIR__ . "/clover.xml", [dirname(__DIR__) . "/src"]);

exit(run());
