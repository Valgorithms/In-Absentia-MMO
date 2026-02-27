<?php

declare(strict_types=1);

/*
 * This file is a part of the In Absentia project.
 *
 * Copyright (c) 2026-present Valithor Obsidion <valithor@discordphp.org>
 *
 * This file is subject to the MIT license that is bundled
 * with this source code in the LICENSE.md file.
 */

namespace BackendPhp\Auth;

use Psr\Http\Message\ServerRequestInterface;

class AuthMiddleware
{
    protected TokenService $tokens;

    public function __construct(TokenService $tokens)
    {
        $this->tokens = $tokens;
    }

    public function handle(ServerRequestInterface $request, callable $next)
    {
        $auth = $request->getHeaderLine('Authorization');
        if ($auth && preg_match('/^Bearer\s+(.*)$/', $auth, $m)) {
            $token = $m[1];
            $payload = $this->tokens->verifyToken($token);
            if ($payload) {
                return $next($request->withAttribute('auth', $payload));
            }
        }

        return [401, ['Content-Type' => 'application/json'], json_encode(['error' => 'Unauthorized'])];
    }
}
