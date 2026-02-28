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

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\SignatureInvalidException;

class TokenService
{
    protected string $secret;
    protected string $algo;

    public function __construct(string $secret, string $algo = 'HS256')
    {
        $this->secret = $secret;
        $this->algo = $algo;
    }

    /**
     * Create a signed JWT.
     *
     * @param array    $payload Claims to include in the token (will be merged with exp/iat)
     * @param int|null $ttl     Seconds until expiry. Null = no exp claim.
     */
    public function createToken(array $payload, ?int $ttl = 3600): string
    {
        $now = time();
        $claims = $payload;
        $claims['iat'] = $now;

        if ($ttl !== null) {
            $claims['exp'] = $now + $ttl;
        }

        return JWT::encode($claims, $this->secret, $this->algo);
    }

    /**
     * Verify and decode a JWT. Returns claims array on success or null on failure.
     */
    public function verifyToken(string $token): ?array
    {
        try {
            $decoded = JWT::decode($token, new Key($this->secret, $this->algo));

            return json_decode(json_encode($decoded), true);
        } catch (ExpiredException|SignatureInvalidException $e) {
            return null;
        } catch (\Throwable $e) {
            return null;
        }
    }
}
