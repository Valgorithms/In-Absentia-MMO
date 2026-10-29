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
use function React\Promise\reject;

class Service
{
    protected Repository $repo;

    public function __construct(Repository $repo)
    {
        $this->repo = $repo;
    }

    /**
     * Contract creation remains unsupported until persistence and reservations exist.
     */
    public function create(array $data): PromiseInterface
    {
        return reject(new \BadMethodCallException('Contract creation is not implemented'));
    }
}
