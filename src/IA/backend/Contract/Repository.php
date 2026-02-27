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

namespace BackendPhp\Contract;

use React\Promise\PromiseInterface;
use function React\Promise\resolve;

class Repository
{
    public function create(array $payload): PromiseInterface
    {
        // In a real async implementation this would perform an async DB insert.
        return resolve($payload + ['id' => bin2hex(random_bytes(8))]);
    }
}
