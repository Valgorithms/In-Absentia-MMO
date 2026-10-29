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
use React\Http\Message\Response;

class Middleware
{
    protected TokenService $tokens;

    public function __construct(TokenService $tokens)
    {
        $this->tokens = $tokens;
    }

    public function handle(ServerRequestInterface $request, callable $next)
    {
        $auth = $request->getHeaderLine('Authorization');
        if (preg_match('/^Bearer\s+(\S+)$/i', $auth, $m)) {
            $token = $m[1];
            $payload = $this->tokens->verifyToken($token);
            if ($payload !== null
                && is_string($payload['sub'] ?? null)
                && $payload['sub'] !== ''
                && is_int($payload['exp'] ?? null)
                && $payload['exp'] > time()
            ) {
                return $next($request->withAttribute('auth', $payload));
            }
        }

        return Response::json(['error' => 'Unauthorized'])->withStatus(401);
    }
}
