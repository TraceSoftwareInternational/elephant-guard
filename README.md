# Elephant Guard

[![Software License](https://img.shields.io/badge/license-GPL%20v3.0-brightgreen.svg?style=flat-square)](LICENSE)
[![Build Status](https://img.shields.io/travis/TraceSoftwareInternational/elephant-guard/master.svg?style=flat-square)](https://travis-ci.org/TraceSoftwareInternational/elephant-guard)
[![Latest Version](https://img.shields.io/packagist/v/tracesoftwareinternational/elephant-guard.svg?style=flat-square)](https://packagist.org/packages/tracesoftwareinternational/elephant-guard)

This middleware is meant to apply a given [`AuthenticatorInterface`](src/ElephantGuard/AuthenticatorInterface.php) on a given set of routes. 

It can be used with all frameworks using PSR-7 or PSR-15 style middlewares. It has been tested with [Slim Framework](http://www.slimframework.com/).

## Credits

Huge thanks to [Mika Tuupola](https://github.com/tuupola) for his greatly structured [PSR-15](https://www.php-fig.org/psr/psr-15/) compliant middlewares that inspired us (thinking of [slim-jwt-auth](https://github.com/tuupola/slim-jwt-auth) and  [slim-basic-auth](https://github.com/tuupola/slim-basic-auth)), Elephant Guard contains some of his work, a lot of thanks Mika !

## Install

Install latest version using [composer](https://getcomposer.org/).

```
$ composer require tracesoftwareinternational/elephgant-guard
```

## Usage

Configuration options are passed as an array. Only mandatory parameter is `authenticator`.
 
For more information, please refer to [Parameters](#Parameters).

## Parameters
## Authenticator

The main purpose of this library is to test an incoming request against a class that implements [`AuthenticatorInterface`](src/ElephantGuard/AuthenticatorInterface.php)

By example, you could use a random based authentication :

```php
use TraceSoftware\Middleware\ElephantGuard\AuthenticatorInterface;
use TraceSoftware\Middleware\ElephantGuard

class RandomAuthenticator implements AuthenticatorInterface {
    public function __invoke(array $arguments) {
        return (bool)rand(0,1);
    }
    
    public function getLastError(): string
    {
        return "";
    }
}

$app = new Slim\App;

$app->add(new ElephantGuard([
    "path" => "/",
    "authenticator" => new RandomAuthenticator
]);
```

Same thing can also be accomplished with anonymous class declaration.

```php
use TraceSoftware\Middleware\ElephantGuard\AuthenticatorInterface;
use TraceSoftware\Middleware\ElephantGuard

$app = new Slim\App;

$app->add(new ElephantGuard([
    "path" => "/",
    "authenticator" => new class implements AuthenticatorInterface {
    
        public function __invoke(\Psr\Http\Message\ServerRequestInterface $request): bool
        {
            return (bool) rand(0,1);
        }
        
        public function getLastError(): string
        {
            return "";
        }
    }
]);
```

### Path

The optional `path` parameter allows you to specify the protected part of your website. It can be either a string or an array. You do not need to specify each URL. Instead think of `path` setting as a folder. In the example below everything starting with `/api` will be authenticated.

``` php
$app = new Slim\App;

$app->add(new \TraceSoftware\Middleware\ElephantGuard([
    "path" => "/api", /* or ["/admin", "/api"] */
    "authenticator" => new \MyCustomAuthenticator()
]));
```

### Ignore

With optional `ignore` parameter you can make exceptions to `path` parameter. In the example below everything starting with `/api` and `/admin`  will be authenticated with the exception of `/api/token` and `/admin/ping` which will not be authenticated.

``` php
$app = new Slim\App;

$app->add(new \TraceSoftware\Middleware\ElephantGuard([
    "path" => ["/api", "/admin"],
    "ignore" => [
        "/api/token", 
        "/admin/ping"
    ],
    "authenticator" => new \MyCustomAuthenticator()
]));
```

### Before

Before function is called only when authentication succeeds but before the next incoming middleware is called. You can use this to alter the request before passing it to the next incoming middleware in the stack. If it returns anything else than `\Psr\Http\Message\RequestInterface` the return value will be ignored.

```php
$app = new Slim\App;

$app->add(new Tuupola\Middleware\HttpBasicAuthentication([
    "path" => "/admin",
    "authenticator" => new \MyCustomAuthenticator()
    "before" => function ($request, $arguments) {
        return $request->withAttribute("user", $arguments["user"]);
    }
]));
```

### After

After function is called only when authentication succeeds and after the incoming middleware stack has been called. You can use this to alter the response before passing it next outgoing middleware in the stack. If it returns anything else than `\Psr\Http\Message\ResponseInterface` the return value will be ignored.

```php
$app = new Slim\App;

$app->add(new Tuupola\Middleware\HttpBasicAuthentication([
    "path" => "/admin",
    "authenticator" => new \MyCustomAuthenticator()
    "after" => function ($request, $response, $arguments) {
        return $response->withHeader("X-Brawndo", "plants crave");
    }
]));
```

## Setting response body when authentication fails

By default Elephant Guard returns an empty response body with 401 response and an array of "arguments". 

arguments will be an array containing two indexes : 
  - `message` : the reason why it failed
  - `authenticatorError` : the last error retrieved from the provided authenticator

You can return custom body using by providing an error handler. This is useful for example when you need additional information why authentication failed.

```php
$app = new Slim\App;

$app->add(new Tuupola\Middleware\HttpBasicAuthentication([
    "path" => "/api",
    "authenticator" => new \MyCustomAuthenticator()
    "error" => function ($response, array $arguments) {
        $data = [];
        $data["status"] = "error";
        $data["message"] = $arguments["message"];
        $data["additionalInformation"] = $arguments['authenticatorError'];
        return $response->write(json_encode($data, JSON_UNESCAPED_SLASHES));
    }
]));
```

## Testing

You can run tests manually with [composer](https://getcomposer.org/) :

``` bash
$ composer test
```

## License

The GNU General Public License v3.0 (GPL v3.0). Please see [License File](LICENSE) for more information.

