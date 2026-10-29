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

final class Market extends AbstractRepository
{
    public function listings(): array
    {
        return $this->request('GET', Endpoint::MARKET_LISTINGS);
    }

    public function listItem(array $data): array
    {
        return $this->request('POST', Endpoint::MARKET_LIST, $data);
    }

    public function buy(array $data): array
    {
        return $this->request('POST', Endpoint::MARKET_BUY, $data);
    }

    public function history(): array
    {
        return $this->request('GET', Endpoint::MARKET_HISTORY);
    }

    public function save(array $data): array
    {
        return $this->listItem($data);
    }

    public function update(string $id, array $data): array
    {
        throw new \BadMethodCallException('Update not supported on Market repository');
    }

    public function delete(string $id): bool
    {
        throw new \BadMethodCallException('Delete not supported on Market repository');
    }
}
