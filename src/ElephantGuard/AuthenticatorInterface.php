<?php
declare(strict_types=1);

namespace TraceSoftware\Middleware\ElephantGuard;

use Psr\Http\Message\ServerRequestInterface;

interface AuthenticatorInterface
{
    public function __invoke(ServerRequestInterface $request): bool;

    public function getLastError(): string;
}
