<?php
declare(strict_types=1);

namespace TraceSoftware\Middleware;

use Closure;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use TraceSoftware\Middleware\ElephantGuard\AuthenticatorInterface;
use TraceSoftware\Middleware\ElephantGuard\RequestPathRule;
use Tuupola\Http\Factory\ResponseFactory;
use Tuupola\Middleware\DoublePassTrait;

final class ElephantGuard implements MiddlewareInterface
{
    use DoublePassTrait;

    private $rules;

    private $options = [
        "authenticator" => null,
        "path" => ["/"],
        "ignore" => [],
        "before" => null,
        "after" => null,
        "error" => null
    ];

    public function __construct($options = [])
    {
        /* Setup stack for rules */
        $this->rules = new \SplStack;

        /* Store passed in options overwriting any defaults */
        $this->hydrate($options);

        /* There must be an authenticator passed via options */
        if ($this->options["authenticator"] === null) {
            throw new \RuntimeException("Authenticator must be supplied");
        }

        if (($this->options["authenticator"] instanceof AuthenticatorInterface) === false) {
            throw new \RuntimeException("Authenticator must implements AuthenticatorInterface");
        }

        $this->rules->push(new RequestPathRule([
            "path" => $this->options["path"],
            "ignore" => $this->options["ignore"]
        ]));
    }

    /**
     * Process a request in PSR-15 style and return a response.
     * @param ServerRequestInterface $request
     * @param RequestHandlerInterface $handler
     * @return ResponseInterface
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        /* If rules say we should not authenticate call next and return. */
        if (false === $this->shouldAuthenticate($request)) {
            return $handler->handle($request);
        }

        /* Check if user authenticates. */
        if (false === $this->options["authenticator"]($request)) {
            $response = (new ResponseFactory)->createResponse(401);

            return $this->processError($response, [
                "message" => "Authenticator rejected this request",
                "authenticatorError" => $this->options["authenticator"]->getLastError()
            ]);
        }

        /* Modify $request before calling next middleware. */
        if (is_callable($this->options["before"])) {
            $before_request = $this->options["before"]($request);
            if ($before_request instanceof ServerRequestInterface) {
                $request = $before_request;
            }
        }

        /* Everything ok, call next middleware. */
        $response = $handler->handle($request);

        /* Modify $response before returning. */
        if (is_callable($this->options["after"])) {
            $after_response = $this->options["after"]($response);
            if ($after_response instanceof ResponseInterface) {
                return $after_response;
            }
        }

        return $response;
    }

    /**
     * Hydrate all options from given array.
     * @param array $data
     */
    private function hydrate(array $data = []): void
    {
        foreach ($data as $key => $value) {
            /* https://github.com/facebook/hhvm/issues/6368 */
            $key = str_replace(".", " ", $key);
            $method = lcfirst(ucwords($key));
            $method = str_replace(" ", "", $method);
            if (method_exists($this, $method)) {
                /* Try to use setter */
                call_user_func([$this, $method], $value);
            } else {
                /* Or fallback to setting option directly */
                $this->options[$key] = $value;
            }
        }
    }

    /**
     * Test if current request should be authenticated.
     * @param ServerRequestInterface $request
     * @return bool
     */
    private function shouldAuthenticate(ServerRequestInterface $request): bool
    {
        /* If any of the rules in stack return false will not authenticate */
        foreach ($this->rules as $callable) {
            if (false === $callable($request)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Execute the error handler.
     * @param ResponseInterface $response
     * @param array $arguments
     * @return ResponseInterface
     */
    private function processError(ResponseInterface $response, array $arguments): ResponseInterface
    {
        if (is_callable($this->options["error"])) {
            $handler_response = $this->options["error"]($response, $arguments);
            if ($handler_response instanceof ResponseInterface) {
                return $handler_response;
            }
        }
        return $response;
    }

    /**
     * Set path which middleware ignores.
     * @param array $ignore
     */
    private function ignore(array $ignore): void
    {
        $this->options["ignore"] = $ignore;
    }

    /**
     * Set the authenticator.
     * @param callable $authenticator
     */
    private function authenticator(callable $authenticator): void
    {
        $this->options["authenticator"] = $authenticator;
    }

    /**
     * Set the handler which is called before other middlewares.
     * @param Closure $before
     */
    private function before(Closure $before): void
    {
        $this->options["before"] = $before->bindTo($this);
    }

    /**
     * Set the handler which is called after other middlewares.
     * @param Closure $after
     */
    private function after(Closure $after): void
    {
        $this->options["after"] = $after->bindTo($this);
    }

    /**
     * Set path where middleware should bind to.
     * @param array $path
     */
    private function path($path): void
    {
        $this->options["path"] = (array) $path;
    }

    /**
     * Set the handler which is if authentication fails.
     * @param callable $error
     */
    private function error(callable $error): void
    {
        $this->options["error"] = $error;
    }
}
