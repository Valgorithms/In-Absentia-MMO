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

class TokenService
{
    public function createToken(array $payload): string
    {
        return base64_encode(json_encode($payload));
    }

    public function verifyToken(string $token): ?array
    {
        return json_decode(base64_decode($token), true) ?: null;
    }
}
