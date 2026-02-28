<?php

declare(strict_types=1);

namespace BackendPhp\Api\Repositories;

use BackendPhp\Api\Endpoint;
use BackendPhp\Api\AbstractRepository;

final class Governance extends AbstractRepository
{
    public function offices(): array
    {
        return $this->request('GET', Endpoint::GOVERNANCE_OFFICES);
    }

    public function elections(): array
    {
        return $this->request('GET', Endpoint::GOVERNANCE_ELECTIONS);
    }

    public function vote(string $electionId, array $data): array
    {
        $path = $this->bindPath(Endpoint::GOVERNANCE_ELECTION_VOTE, ['id' => $electionId]);
        return $this->request('POST', $path, $data);
    }

    public function policies(): array
    {
        return $this->request('GET', Endpoint::GOVERNANCE_POLICIES);
    }

    public function propose(array $data): array
    {
        return $this->request('POST', Endpoint::GOVERNANCE_PROPOSE_POLICY, $data);
    }

    public function save(array $data): array
    {
        return $this->propose($data);
    }

    public function update(string $id, array $data): array
    {
        throw new \BadMethodCallException('Update not supported on Governance repository');
    }

    public function delete(string $id): bool
    {
        throw new \BadMethodCallException('Delete not supported on Governance repository');
    }
}
