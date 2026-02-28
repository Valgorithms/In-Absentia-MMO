<?php

declare(strict_types=1);

namespace BackendPhp\Api\Repositories;

use BackendPhp\Api\Endpoint;
use BackendPhp\Api\AbstractRepository;

final class Contracts extends AbstractRepository
{
    public function create(array $data): array
    {
        return $this->request('POST', Endpoint::CONTRACTS, $data);
    }

    public function fetch(string $id): array
    {
        $path = $this->bindPath(Endpoint::CONTRACT, ['id' => $id]);
        return $this->request('GET', $path);
    }

    public function cancel(string $id): array
    {
        $path = $this->bindPath(Endpoint::CONTRACT, ['id' => $id]);
        return $this->request('DELETE', $path);
    }

    public function pause(string $id): array
    {
        $path = $this->bindPath(Endpoint::CONTRACT, ['id' => $id]);
        return $this->request('PATCH', $path, ['action' => 'pause']);
    }

    public function resume(string $id): array
    {
        $path = $this->bindPath(Endpoint::CONTRACT, ['id' => $id]);
        return $this->request('PATCH', $path, ['action' => 'resume']);
    }

    public function contributions(string $id): array
    {
        $path = $this->bindPath(Endpoint::CONTRACT_CONTRIBUTIONS, ['id' => $id]);
        return $this->request('GET', $path);
    }

    public function contribute(string $id, string $charId): array
    {
        $path = $this->bindPath(Endpoint::CONTRACT_CONTRIBUTE, ['id' => $id]);
        return $this->request('POST', $path, ['character_id' => $charId]);
    }

    public function withdraw(string $id): array
    {
        $path = $this->bindPath(Endpoint::CONTRACT_WITHDRAW, ['id' => $id]);
        return $this->request('DELETE', $path);
    }

    public function approve(string $id, string $charId): array
    {
        $path = $this->bindPath(Endpoint::CONTRACT_APPROVE, ['id' => $id, 'charId' => $charId]);
        return $this->request('POST', $path);
    }

    public function invite(string $id, string $charId): array
    {
        $path = $this->bindPath(Endpoint::CONTRACT_INVITE, ['id' => $id, 'charId' => $charId]);
        return $this->request('POST', $path);
    }

    public function submitTrial(string $id, array $params): array
    {
        $path = $this->bindPath(Endpoint::CONTRACT_TRIAL, ['id' => $id]);
        return $this->request('POST', $path, $params);
    }

    public function trials(string $id): array
    {
        $path = $this->bindPath(Endpoint::CONTRACT_TRIALS, ['id' => $id]);
        return $this->request('GET', $path);
    }

    public function save(array $data): array
    {
        return $this->create($data);
    }

    public function update(string $id, array $data): array
    {
        // Use pause/resume semantics via PATCH action
        if (isset($data['action']) && $data['action'] === 'pause') {
            return $this->pause($id);
        }
        if (isset($data['action']) && $data['action'] === 'resume') {
            return $this->resume($id);
        }

        throw new \BadMethodCallException('Update not supported for given payload on Contract repository');
    }

    public function delete(string $id): bool
    {
        $this->cancel($id);
        return true;
    }
}
