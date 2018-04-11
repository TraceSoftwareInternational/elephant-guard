<?php

namespace TraceSoftware\Middleware\ElephantGuard;

use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;
use Tuupola\Http\Factory\ServerRequestFactory;

class SessionTokenAuthenticatorTest extends TestCase
{
    public function getParametersToTest() : array
    {
        return [
            'wrong TTL type' => [
                [
                    SessionTokenAuthenticator::TOKEN_HEADER_NAME => 'X-CustomToken',
                    SessionTokenAuthenticator::VALUES_TO_CHECK_IN_SESSION => ['value1' => 'expectedValue'],
                    SessionTokenAuthenticator::TTL => 'TESTSTRING',
                    SessionTokenAuthenticator::SESSION_EXPIRE_KEY => 'expiration',
                    SessionTokenAuthenticator::SESSION_TOKEN_KEY => 'token'
                ],
                true,
                RuntimeException::class
            ],
            'valid parameters' => [
                [
                    SessionTokenAuthenticator::TOKEN_HEADER_NAME => 'X-CustomToken',
                    SessionTokenAuthenticator::VALUES_TO_CHECK_IN_SESSION => ['value1' => 'expectedValue'],
                    SessionTokenAuthenticator::TTL => 1 * 60 * 60,
                    SessionTokenAuthenticator::SESSION_EXPIRE_KEY => 'expiration',
                    SessionTokenAuthenticator::SESSION_TOKEN_KEY => 'token'
                ],
                false,
                null
            ],
            'wrong parameters count' => [
                [
                    SessionTokenAuthenticator::TOKEN_HEADER_NAME => 'X-CustomToken',
                    SessionTokenAuthenticator::VALUES_TO_CHECK_IN_SESSION => ['value1' => 'expectedValue'],
                    SessionTokenAuthenticator::SESSION_TOKEN_KEY => 'token'
                ],
                true,
                RuntimeException::class
            ]
        ];
    }

    /**
     * @param array $parameters an array to be passed to SessionTokenAuthenticator
     * @param bool $shouldThrow will the arrow result in throwing an exception ?
     * @param string $expectedException name of the exception
     *
     * @dataProvider getParametersToTest
     */
    public function testParameters(array $parameters, bool $shouldThrow, ?string $expectedException)
    {
        if ($shouldThrow) {
            $this->expectException($expectedException);
        }

        $emptyArray = [];

        $authenticator = new SessionTokenAuthenticator($parameters, $emptyArray);
        $this->assertInstanceOf(SessionTokenAuthenticator::class, $authenticator);
    }

    public function getOptionsAndSessions()
    {
        return [
            'valid' => [
                (new ServerRequestFactory)
                    ->createServerRequest("GET", "https://example.com/randomRoute")
                    ->withAddedHeader('X-CustomToken', __CLASS__),
                [
                    SessionTokenAuthenticator::TOKEN_HEADER_NAME => 'X-CustomToken',
                    SessionTokenAuthenticator::VALUES_TO_CHECK_IN_SESSION => ['value1' => 'expectedValue'],
                    SessionTokenAuthenticator::TTL => 1 * 60 * 60,
                    SessionTokenAuthenticator::SESSION_EXPIRE_KEY => 'expiration',
                    SessionTokenAuthenticator::SESSION_TOKEN_KEY => 'token'
                ],
                [
                    'expiration' => time() + 2 * 60 * 60,
                    'value1' => 'expectedValue',
                    'token' => __CLASS__
                ],
                true
            ],
            'missing header in request' => [
                (new ServerRequestFactory)->createServerRequest("GET", "https://example.com/randomRoute"),
                [
                    SessionTokenAuthenticator::TOKEN_HEADER_NAME => 'X-CustomToken',
                    SessionTokenAuthenticator::VALUES_TO_CHECK_IN_SESSION => ['value1' => 'expectedValue'],
                    SessionTokenAuthenticator::TTL => 1 * 60 * 60,
                    SessionTokenAuthenticator::SESSION_EXPIRE_KEY => 'expiration',
                    SessionTokenAuthenticator::SESSION_TOKEN_KEY => 'token'
                ],
                [
                    'expiration' => time() + 2 * 60 * 60,
                    'value1' => 'expectedValue',
                    'token' => __CLASS__
                ],
                false
            ],
            'missing expiration in session' => [
                (new ServerRequestFactory)
                    ->createServerRequest("GET", "https://example.com/randomRoute")
                    ->withAddedHeader('X-CustomToken', __CLASS__),
                [
                    SessionTokenAuthenticator::TOKEN_HEADER_NAME => 'X-CustomToken',
                    SessionTokenAuthenticator::VALUES_TO_CHECK_IN_SESSION => ['value1' => 'expectedValue'],
                    SessionTokenAuthenticator::TTL => 1 * 60 * 60,
                    SessionTokenAuthenticator::SESSION_EXPIRE_KEY => 'expiration',
                    SessionTokenAuthenticator::SESSION_TOKEN_KEY => 'token'
                ],
                [
                    'value1' => 'expectedValue',
                    'token' => __CLASS__
                ],
                false
            ],
            'session has expired' => [
                (new ServerRequestFactory)
                    ->createServerRequest("GET", "https://example.com/randomRoute")
                    ->withAddedHeader('X-CustomToken', __CLASS__),
                [
                    SessionTokenAuthenticator::TOKEN_HEADER_NAME => 'X-CustomToken',
                    SessionTokenAuthenticator::VALUES_TO_CHECK_IN_SESSION => ['value1' => 'expectedValue'],
                    SessionTokenAuthenticator::TTL => 1 * 60 * 60,
                    SessionTokenAuthenticator::SESSION_EXPIRE_KEY => 'expiration',
                    SessionTokenAuthenticator::SESSION_TOKEN_KEY => 'token'
                ],
                [
                    'expiration' => time() - 2 * 60 * 60,
                    'value1' => 'expectedValue',
                    'token' => __CLASS__
                ],
                false
            ],
            'missing token in session' => [
                (new ServerRequestFactory)
                    ->createServerRequest("GET", "https://example.com/randomRoute")
                    ->withAddedHeader('X-CustomToken', __CLASS__),
                [
                    SessionTokenAuthenticator::TOKEN_HEADER_NAME => 'X-CustomToken',
                    SessionTokenAuthenticator::VALUES_TO_CHECK_IN_SESSION => ['value1' => 'expectedValue'],
                    SessionTokenAuthenticator::TTL => 1 * 60 * 60,
                    SessionTokenAuthenticator::SESSION_EXPIRE_KEY => 'expiration',
                    SessionTokenAuthenticator::SESSION_TOKEN_KEY => 'token'
                ],
                [
                    'expiration' => time() + 2 * 60 * 60,
                    'value1' => 'expectedValue',
                ],
                false
            ],
            '' => [
                (new ServerRequestFactory)
                    ->createServerRequest("GET", "https://example.com/randomRoute")
                    ->withAddedHeader('X-CustomToken', __CLASS__),
                [
                    SessionTokenAuthenticator::TOKEN_HEADER_NAME => 'X-CustomToken',
                    SessionTokenAuthenticator::VALUES_TO_CHECK_IN_SESSION => ['value1' => 'expectedValue'],
                    SessionTokenAuthenticator::TTL => 1 * 60 * 60,
                    SessionTokenAuthenticator::SESSION_EXPIRE_KEY => 'expiration',
                    SessionTokenAuthenticator::SESSION_TOKEN_KEY => 'token'
                ],
                [
                    'expiration' => time() + 2 * 60 * 60,
                    'value1' => 'expectedValue',
                    'token' => __CLASS__
                ],
                true
            ],
            'wrong token in session' => [
                (new ServerRequestFactory)
                    ->createServerRequest("GET", "https://example.com/randomRoute")
                    ->withAddedHeader('X-CustomToken', __CLASS__),
                [
                    SessionTokenAuthenticator::TOKEN_HEADER_NAME => 'X-CustomToken',
                    SessionTokenAuthenticator::VALUES_TO_CHECK_IN_SESSION => ['value1' => 'expectedValue'],
                    SessionTokenAuthenticator::TTL => 1 * 60 * 60,
                    SessionTokenAuthenticator::SESSION_EXPIRE_KEY => 'expiration',
                    SessionTokenAuthenticator::SESSION_TOKEN_KEY => 'token'
                ],
                [
                    'expiration' => time() + 2 * 60 * 60,
                    'value1' => 'expectedValue',
                    'token' => 'hello'
                ],
                false
            ],
            'missing arbitrary values' => [
                (new ServerRequestFactory)
                    ->createServerRequest("GET", "https://example.com/randomRoute")
                    ->withAddedHeader('X-CustomToken', __CLASS__),
                [
                    SessionTokenAuthenticator::TOKEN_HEADER_NAME => 'X-CustomToken',
                    SessionTokenAuthenticator::VALUES_TO_CHECK_IN_SESSION => ['value1' => 'expectedValue'],
                    SessionTokenAuthenticator::TTL => 1 * 60 * 60,
                    SessionTokenAuthenticator::SESSION_EXPIRE_KEY => 'expiration',
                    SessionTokenAuthenticator::SESSION_TOKEN_KEY => 'token'
                ],
                [
                    'expiration' => time() + 2 * 60 * 60,
                    'token' => __CLASS__
                ],
                false
            ],
            'wrong arbitrary values' => [
                (new ServerRequestFactory)
                    ->createServerRequest("GET", "https://example.com/randomRoute")
                    ->withAddedHeader('X-CustomToken', __CLASS__),
                [
                    SessionTokenAuthenticator::TOKEN_HEADER_NAME => 'X-CustomToken',
                    SessionTokenAuthenticator::VALUES_TO_CHECK_IN_SESSION => ['value1' => 'expectedValue'],
                    SessionTokenAuthenticator::TTL => 1 * 60 * 60,
                    SessionTokenAuthenticator::SESSION_EXPIRE_KEY => 'expiration',
                    SessionTokenAuthenticator::SESSION_TOKEN_KEY => 'token'
                ],
                [
                    'expiration' => time() + 2 * 60 * 60,
                    'value1' => 'notTheGoodValue',
                    'token' => __CLASS__
                ],
                false
            ]
        ];
    }

    /**
     * @dataProvider getOptionsAndSessions
     *
     * @param ServerRequestInterface $request
     * @param array $authenticatorOptions
     * @param array $session
     * @param bool $expectedResult
     */
    public function testAuthentication(
        ServerRequestInterface $request,
        array $authenticatorOptions,
        array $session,
        bool $expectedResult
    ) {
        $authenticator = new SessionTokenAuthenticator($authenticatorOptions, $session);

        $this->assertEquals($expectedResult, $authenticator($request));

        if ($expectedResult === false) {
            $this->assertNotNull($authenticator->getLastError());
        }
    }
}
