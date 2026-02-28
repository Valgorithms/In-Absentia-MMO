<?php

declare(strict_types=1);

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
