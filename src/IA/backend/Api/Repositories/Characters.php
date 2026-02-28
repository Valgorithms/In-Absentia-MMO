<?php

declare(strict_types=1);

namespace BackendPhp\Api\Repositories;

use BackendPhp\Api\Endpoint;
use BackendPhp\Api\AbstractRepository;

final class Characters extends AbstractRepository
{
    public function list(array $params = []): array
    {
        $path = $this->withQuery(Endpoint::CHARACTERS, $params);
        return $this->request('GET', $path);
    }

    public function fetch(string $id): array
    {
        $path = $this->bindPath(Endpoint::CHARACTER, ['id' => $id]);
        return $this->request('GET', $path);
    }

    public function save(array $data): array
    {
        if (! empty($data[$this->discrim])) {
            $path = $this->bindPath(Endpoint::CHARACTER, [$this->discrim => $data[$this->discrim]]);
            return $this->request('PATCH', $path, $data);
        }

        return $this->request('POST', Endpoint::CHARACTERS, $data);
    }

    public function retire(string $id): array
    {
        $path = $this->bindPath(Endpoint::CHARACTER, ['id' => $id]);
        return $this->request('DELETE', $path);
    }

    public function stats(string $id): array
    {
        $path = $this->bindPath(Endpoint::CHARACTER_STATS, ['id' => $id]);
        return $this->request('GET', $path);
    }

    public function expertise(string $id): array
    {
        $path = $this->bindPath(Endpoint::CHARACTER_EXPERTISE, ['id' => $id]);
        return $this->request('GET', $path);
    }

    public function knowledge(string $id): array
    {
        $path = $this->bindPath(Endpoint::CHARACTER_KNOWLEDGE, ['id' => $id]);
        return $this->request('GET', $path);
    }

    public function inventory(string $id, ?string $type = null): array
    {
        $path = $this->bindPath(Endpoint::CHARACTER_INVENTORY, ['id' => $id]);
        if ($type !== null) {
            $path = $this->withQuery($path, ['type' => $type]);
        }
        return $this->request('GET', $path);
    }

    public function contracts(string $id): array
    {
        $path = $this->bindPath(Endpoint::CHARACTER_CONTRACTS, ['id' => $id]);
        return $this->request('GET', $path);
    }

    public function wallet(string $id): array
    {
        $path = $this->bindPath(Endpoint::CHARACTER_WALLET, ['id' => $id]);
        return $this->request('GET', $path);
    }

    public function researchJournal(string $id, ?string $knowledgeId = null): array
    {
        $path = $this->bindPath(Endpoint::CHARACTER_RESEARCH_JOURNAL, ['id' => $id]);
        if ($knowledgeId !== null) {
            $path .= '/' . $knowledgeId;
        }
        return $this->request('GET', $path);
    }

    public function researchDiscoveries(string $id): array
    {
        $path = $this->bindPath(Endpoint::CHARACTER_RESEARCH_DISCOVERIES, ['id' => $id]);
        return $this->request('GET', $path);
    }

    

    public function update(string $id, array $data): array
    {
        // No generic update endpoint defined; repositories that support patching
        // should override this.
        throw new \BadMethodCallException('Update not supported on Character repository');
    }

    public function delete(string $id): bool
    {
        $this->retire($id);
        return true;
    }
}
