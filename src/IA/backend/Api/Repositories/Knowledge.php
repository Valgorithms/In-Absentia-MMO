<?php

declare(strict_types=1);

namespace BackendPhp\Api\Repositories;

use BackendPhp\Api\Endpoint;
use BackendPhp\Api\AbstractRepository;

final class Knowledge extends AbstractRepository
{
    public function list(): array
    {
        return $this->request('GET', Endpoint::KNOWLEDGE);
    }

    public function fetch(string $id): array
    {
        $path = $this->bindPath(Endpoint::KNOWLEDGE_ITEM, ['id' => $id]);
        return $this->request('GET', $path);
    }

    public function researchPaths(string $id, string $charId): array
    {
        $path = $this->bindPath(Endpoint::KNOWLEDGE_RESEARCH_PATHS, ['id' => $id]);
        $path = $this->withQuery($path, ['character_id' => $charId]);
        return $this->request('GET', $path);
    }

    public function save(array $data): array
    {
        throw new \BadMethodCallException('Save not supported on Knowledge repository');
    }

    public function update(string $id, array $data): array
    {
        throw new \BadMethodCallException('Update not supported on Knowledge repository');
    }

    public function delete(string $id): bool
    {
        throw new \BadMethodCallException('Delete not supported on Knowledge repository');
    }
}
