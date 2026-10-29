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

namespace BackendPhp\Api;

interface RepositoryInterface
{
    /**
     * Refresh or fetch latest collection data from the backing API/service.
     * Mirrors DiscordPHP's `freshen()` naming.
     *
     * @param  array $params
     * @return array
     */
    public function freshen(array $params = []): array;

    /**
     * Fetch a single resource by id.
     */
    public function fetch(string $id): array|null;

    /**
     * Create a new resource.
     */
    public function save(array $data): array;

    /**
     * Update an existing resource.
     */
    public function update(string $id, array $data): array;

    /**
     * Delete a resource.
     */
    public function delete(string $id): bool;
}
