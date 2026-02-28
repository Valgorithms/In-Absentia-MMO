<?php

declare(strict_types=1);

namespace BackendPhp\Api\Repositories;

use BackendPhp\Api\Endpoint;
use BackendPhp\Api\AbstractRepository;

final class World extends AbstractRepository
{
    public function time(): array
    {
        return $this->request('GET', Endpoint::WORLD_TIME);
    }

    public function zones(): array
    {
        return $this->request('GET', Endpoint::WORLD_ZONES);
    }

    public function zone(string $id): array
    {
        $path = $this->bindPath(Endpoint::WORLD_ZONE, ['id' => $id]);
        return $this->request('GET', $path);
    }

    public function buildings(string $zoneId): array
    {
        $path = $this->bindPath(Endpoint::WORLD_ZONE_BUILDINGS, ['id' => $zoneId]);
        return $this->request('GET', $path);
    }

    public function stockpile(string $zoneId): array
    {
        $path = $this->bindPath(Endpoint::WORLD_ZONE_STOCKPILE, ['id' => $zoneId]);
        return $this->request('GET', $path);
    }

    public function mana(string $zoneId): array
    {
        $path = $this->bindPath(Endpoint::WORLD_ZONE_MANA, ['id' => $zoneId]);
        return $this->request('GET', $path);
    }

    public function treasury(string $zoneId): array
    {
        $path = $this->bindPath(Endpoint::WORLD_ZONE_TREASURY, ['id' => $zoneId]);
        return $this->request('GET', $path);
    }

    public function events(): array
    {
        return $this->request('GET', Endpoint::WORLD_EVENTS);
    }

    public function nextRaid(): array
    {
        return $this->request('GET', Endpoint::WORLD_RAIDS_NEXT);
    }

    public function save(array $data): array
    {
        throw new \BadMethodCallException('Save not supported on World repository');
    }

    public function update(string $id, array $data): array
    {
        throw new \BadMethodCallException('Update not supported on World repository');
    }

    public function delete(string $id): bool
    {
        throw new \BadMethodCallException('Delete not supported on World repository');
    }
}
