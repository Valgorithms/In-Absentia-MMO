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

class Service
{
    protected Repository $repo;

    public function __construct(Repository $repo)
    {
        $this->repo = $repo;
    }

    /**
     * Create a contract — returns a Promise resolving to the created data.
     */
    public function create(array $data): PromiseInterface
    {
        // Validate and reserve resources using repo (sync here)
        $result = ['status' => 'created', 'data' => $data];

        return resolve($result);
    }
}
