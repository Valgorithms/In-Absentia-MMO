<?php

declare(strict_types=1);

namespace BackendPhp\Api;

interface RepositoryInterface
{
    /**
     * Refresh or fetch latest collection data from the backing API/service.
     * Mirrors DiscordPHP's `freshen()` naming.
     *
     * @param array $params
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
