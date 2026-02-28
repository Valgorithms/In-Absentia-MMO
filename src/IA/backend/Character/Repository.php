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

namespace BackendPhp\Character;

use React\Promise\PromiseInterface;
use function React\Promise\resolve;

class Repository
{
    public function find(string $id): PromiseInterface
    {
        // Placeholder: resolve null if not found. Replace with async DB call.
        return resolve(null);
    }
}
