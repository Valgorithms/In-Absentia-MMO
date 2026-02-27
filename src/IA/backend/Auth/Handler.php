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

class Handler
{
    public function login(ServerRequestInterface $request)
    {
        return ['token' => 'DEV-TOKEN'];
    }

    public function register(ServerRequestInterface $request)
    {
        return ['status' => 'ok'];
    }
}
