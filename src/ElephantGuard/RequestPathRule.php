<?php
declare(strict_types=1);

namespace TraceSoftware\Middleware\ElephantGuard;

use Psr\Http\Message\ServerRequestInterface;
use Webmozart\Glob\Glob;

/**
 * Rule to decide by request path whether the request should be authenticated or not.
 */
final class RequestPathRule implements RuleInterface
{
    /**
     * Stores all the options passed to the rule.
     */
    private $options = [
        "path" => ["/"],
        "ignore" => []
    ];

    public function __construct($options = [])
    {
        $this->options = array_merge($this->options, $options);
    }

    public function __invoke(ServerRequestInterface $request): bool
    {
        $uri = $request->getRequestTarget();
        $uri = preg_replace("#/+#", "/", $uri);

        foreach ($this->options["ignore"] as $ignoredPath) {
            foreach ($this->options["path"] as $basePath) {
                $uriToCheck = 'http://'.$_SERVER['HTTP_HOST'].$uri;
                $glob = 'http://'.$_SERVER['HTTP_HOST'].$basePath.$ignoredPath;

                if (Glob::match($uriToCheck, $glob)) {
                    return false;
                }
            }
        }

        foreach ($this->options["path"] as $path) {
            $path = rtrim($path, "/");
            if (!!preg_match("@^{$path}(/.*)?$@", $uri)) {
                return true;
            }
        }

        return false;
    }
}
