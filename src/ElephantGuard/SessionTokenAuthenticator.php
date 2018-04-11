<?php

namespace TraceSoftware\Middleware\ElephantGuard;

use Psr\Http\Message\ServerRequestInterface;
use ReflectionClass;
use RuntimeException;

class SessionTokenAuthenticator implements AuthenticatorInterface
{

    /**
     * Name of the header in the request object that contains the token value
     */
    const TOKEN_HEADER_NAME = 'HEADER_NAME';

    /**
     * Name of the key in the session object to check expiration
     */
    const SESSION_EXPIRE_KEY = 'EXPIRE_KEY';

    /**
     * Name of the key in the session object where the token value is stored
     */
    const SESSION_TOKEN_KEY = 'TOKEN_KEY';

    /**
     * Array of values to test in session object
     *     Keys are the indexes values in session
     *     Values are the expected values to be found in session
     */
    const VALUES_TO_CHECK_IN_SESSION = 'ARBITRARY_VALUES';

    /**
     * The maximum session lifespan, in seconds
     */
    const TTL = 'TTL';

    private $options = [];

    /**
     * @var array the current session array
     */
    private $session = [];

    /**
     * @var string last error encountered
     */
    private $lastError = "";

    public function __construct(array $options, array &$session)
    {
        $this->verifyParameters($options);

        $this->options = $options;
        $this->session = $session;
    }

    public function __invoke(ServerRequestInterface $request): bool
    {
        $headerValuesArray = $request->getHeader($this->options[self::TOKEN_HEADER_NAME]);

        $requestToken = array_shift($headerValuesArray);

        if ($requestToken === null) {
            $this->lastError = "No token in request";
            return false;
        }

        if (array_key_exists($this->options[self::SESSION_EXPIRE_KEY], $this->session) === false) {
            $this->lastError = "No expiration on session";
            return false;
        }

        if (time() - $this->session[$this->options[self::SESSION_EXPIRE_KEY]] > $this->options[self::TTL]) {
            $this->lastError = "Session has expired";
            return false;
        }

        if (array_key_exists($this->options[self::SESSION_TOKEN_KEY], $this->session) === false) {
            $this->lastError = "No token in session";
            return false;
        }

        if ($this->session[$this->options[self::SESSION_TOKEN_KEY]] !== $requestToken) {
            $this->lastError = "Token in request is not the same in session";
            return false;
        }

        foreach ($this->options[self::VALUES_TO_CHECK_IN_SESSION] as $key => $expectedValue) {
            if (array_key_exists($key, $this->session) === false) {
                $this->lastError = "$key not found in session";
                return false;
            }

            if ($this->session[$key] !== $expectedValue) {
                $this->lastError = "The expected value in \$_SESSION[$key] was not found";
                return false;
            }
        }

        return true;
    }

    /**
     * Validating options count and integrity
     * @param array $options
     * @throws \ReflectionException
     */
    private function verifyParameters(array $options)
    {
        $reflectionInfo = new ReflectionClass(__CLASS__);

        $constants = $reflectionInfo->getConstants();

        if (count($options) !== count($constants)) {
            throw new RuntimeException("Wrong option number");
        }

        foreach ($constants as $name => $value) {
            if (array_key_exists($value, $options) === false) {
                throw new RuntimeException("$name was not found in options...");
            }
        }

        if (is_int($options[self::TTL]) == false) {
            throw new RuntimeException("TTL must be an integer");
        }
    }

    /**
     * @return string
     */
    public function getLastError() : string
    {
        return $this->lastError;
    }
}
