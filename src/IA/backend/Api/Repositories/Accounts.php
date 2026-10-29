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

namespace BackendPhp\Api\Repositories;

use BackendPhp\Api\Endpoint;
use BackendPhp\Api\AbstractRepository;

final class Accounts extends AbstractRepository
{
    public function fetch(string $id): array|null
    {
        return $this->request('GET', Endpoint::ACCOUNT);
    }

    public function skillpoints(): array
    {
        return $this->request('GET', Endpoint::ACCOUNT_SKILLPOINTS);
    }

    public function library(): array
    {
        return $this->request('GET', Endpoint::ACCOUNT_LIBRARY);
    }

    public function save(array $data): array
    {
        throw new \BadMethodCallException('Save not supported on Account repository');
    }

    public function update(string $id, array $data): array
    {
        throw new \BadMethodCallException('Update not supported on Account repository');
    }

    public function delete(string $id): bool
    {
        throw new \BadMethodCallException('Delete not supported on Account repository');
    }
}
