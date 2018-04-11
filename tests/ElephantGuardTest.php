<?php

namespace TraceSoftware\Middleware\ElephantGuard;

use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Test\FalseAuthenticator;
use Test\TrueAuthenticator;
use TraceSoftware\Middleware\ElephantGuard;
use Tuupola\Http\Factory\ResponseFactory;
use Tuupola\Http\Factory\ServerRequestFactory;

class HttpBasicAuthenticationTest extends TestCase
{
    public function testShouldFailWithoutAuthenticator()
    {
        $this->expectException("RuntimeException");
        $this->expectExceptionMessageRegExp("/^Authenticator/");

        new ElephantGuard([
            "path" => "/api"
        ]);
    }

    public function testShouldFailWithWrongAuthenticator()
    {
        $this->expectException("RuntimeException");
        $this->expectExceptionMessageRegExp("/^Authenticator/");

        new ElephantGuard([
            "path" => "/api",
            "authenticator" => new class {
                public function __invoke(ServerRequestInterface $r)
                {
                    return "Hello";
                }
            }
        ]);
    }

    public function testIgnoredRoute()
    {
        $request = (new ServerRequestFactory)->createServerRequest("GET", "https://example.com/BackEnd/public");

        $response = (new ResponseFactory)->createResponse();

        $auth = new ElephantGuard([
            "authenticator" => new FalseAuthenticator(),
            "path" => ["/BackEnd"],
            "ignore" => [
                "/public"
            ]
        ]);

        $next = function (ServerRequestInterface $request, ResponseInterface $response) {
            $response->getBody()->write("Success");
            return $response->withStatus(302);
        };

        $response = $auth($request, $response, $next);

        $this->assertEquals(302, $response->getStatusCode());
        $this->assertEquals("Success", $response->getBody());
    }

    public function testAuthorizedRoute()
    {
        $request = (new ServerRequestFactory)->createServerRequest("GET", "https://example.com/BackEnd/guardedRoute");

        $response = (new ResponseFactory)->createResponse();

        $auth = new ElephantGuard([
            "authenticator" => new TrueAuthenticator(),
            "path" => "/BackEnd"
        ]);

        $next = function (ServerRequestInterface $request, ResponseInterface $response) {
            $response->getBody()->write("Success");
            return $response;
        };

        $response = $auth($request, $response, $next);

        $this->assertEquals("Success", $response->getBody());
    }

    public function testMultiplePaths()
    {
        $requestArray = [
            (new ServerRequestFactory)->createServerRequest("GET", "https://example.com/api/guardedRoute"),
            (new ServerRequestFactory)->createServerRequest("GET", "https://example.com/private/guardedRoute")
        ];

        $response = (new ResponseFactory)->createResponse();

        $auth = new ElephantGuard([
            "authenticator" => new TrueAuthenticator(),
            "path" => ["/api", "/private"]
        ]);

        foreach ($requestArray as $request) {
            $next = function (ServerRequestInterface $request, ResponseInterface $response) {
                return $response->withStatus(503);
            };


            $response = $auth($request, $response, $next);

            $this->assertEquals(503, $response->getStatusCode());
        }
    }

    public function testRejectedByAuthenticator()
    {
        $request = (new ServerRequestFactory)->createServerRequest("GET", "https://example.com/BackEnd/route");

        $response = (new ResponseFactory)->createResponse();

        $auth = new ElephantGuard([
            "authenticator" => new FalseAuthenticator(),
            "path" => "/BackEnd",
            "error" => function (ResponseInterface $response, array $arguments) use ($request) {
                $this->assertEquals(401, $response->getStatusCode());
                $this->assertArrayHasKey("message", $arguments);

                return $response;
            }
        ]);

        $next = function (ServerRequestInterface $request, ResponseInterface $response) {
            return $response;
        };

        $response = $auth($request, $response, $next);

        $this->assertEquals(401, $response->getStatusCode());
    }

    public function testSimpleError()
    {
        $request = (new ServerRequestFactory)->createServerRequest("GET", "https://example.com/BackEnd/route");

        $response = (new ResponseFactory)->createResponse();

        $auth = new ElephantGuard([
            "authenticator" => new FalseAuthenticator(),
            "path" => "/BackEnd"
        ]);

        $next = function (ServerRequestInterface $request, ResponseInterface $response) {
            return $response;
        };

        $response = $auth($request, $response, $next);

        $this->assertEquals(401, $response->getStatusCode());
    }

    public function testBeforeOption()
    {
        $request = (new ServerRequestFactory)->createServerRequest("GET", "https://example.com/BackEnd/guardedRoute");

        $response = (new ResponseFactory)->createResponse();

        $auth = new ElephantGuard([
            "authenticator" => new TrueAuthenticator(),
            "path" => "/BackEnd",
            "before" => function (ServerRequestInterface $request) {
                return $request->withAddedHeader("x-test-value", "string");
            }
        ]);

        $next = function (ServerRequestInterface $request, ResponseInterface $response) {
            $this->assertEquals("string", $request->getHeader("x-test-value")[0]);

            return $response;
        };

        $auth($request, $response, $next);
    }

    public function testAfterFunction()
    {
        $request = (new ServerRequestFactory)->createServerRequest("GET", "https://example.com/BackEnd/guardedRoute");

        $response = (new ResponseFactory)->createResponse();

        $auth = new ElephantGuard([
            "authenticator" => new TrueAuthenticator(),
            "path" => "/BackEnd",
            "after" => function (ResponseInterface $response) {
                return $response->withAddedHeader("x-test-value", "string");
            }
        ]);

        $next = function (ServerRequestInterface $request, ResponseInterface $response) {
            return $response;
        };

        $response = $auth($request, $response, $next);

        $this->assertEquals("string", $response->getHeader("x-test-value")[0]);
    }

    public function testRoutesMatching()
    {
        $request = (new ServerRequestFactory)->createServerRequest("GET", "https://example.com/BackEnd/getEntity/1");

        $response = (new ResponseFactory)->createResponse();

        $auth = new ElephantGuard([
            "authenticator" => new FalseAuthenticator(),
            "path" => "/BackEnd",
            "ignore" => [
                "/getEntity"
            ]
        ]);

        $next = function (ServerRequestInterface $request, ResponseInterface $response) {
            return $response;
        };

        $response = $auth($request, $response, $next);

        $this->assertEquals(401, $response->getStatusCode());
    }

    public function testRoutesOutsidePath()
    {
        $request = (new ServerRequestFactory)->createServerRequest("GET", "https://example.com/anotherAPI/get/1");

        $response = (new ResponseFactory)->createResponse();

        $auth = new ElephantGuard([
            "authenticator" => new TrueAuthenticator(),
            "path" => "/BackEnd",
        ]);

        $next = function (ServerRequestInterface $request, ResponseInterface $response) {
            return $response->withStatus(404);
        };

        $response = $auth($request, $response, $next);

        $this->assertEquals(404, $response->getStatusCode());
    }
}
