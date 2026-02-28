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

namespace BackendPhp\Account;

use React\Promise\PromiseInterface;
use function React\Promise\resolve;

class Service
{
    protected Repository $repo;

    public function __construct(Repository $repo)
    {
        $this->repo = $repo;
    }

    public function getAccount(?array $auth): PromiseInterface
    {
        // Return account object for authenticated user
        $res = ['id' => $auth['sub'] ?? null, 'banked' => 0];

        return resolve($res);
    }
}
