<?php

declare(strict_types=1);

namespace BackendPhp\Api\Repositories;

use BackendPhp\Api\Endpoint;
use BackendPhp\Api\AbstractRepository;

final class Auth extends AbstractRepository
{
    public function login(string $email, string $password): array
    {
        return $this->request('POST', Endpoint::AUTH_LOGIN, ['email' => $email, 'password' => $password]);
    }

    public function register(string $email, string $password): array
    {
        return $this->request('POST', Endpoint::AUTH_REGISTER, ['email' => $email, 'password' => $password]);
    }

    public function refresh(string $token): array
    {
        return $this->request('POST', Endpoint::AUTH_REFRESH, ['token' => $token]);
    }

    public function save(array $data): array
    {
        // Map to register when creating via generic interface
        return $this->register($data['email'] ?? '', $data['password'] ?? '');
    }

    public function update(string $id, array $data): array
    {
        throw new \BadMethodCallException('Update not supported on Auth repository');
    }

    public function delete(string $id): bool
    {
        throw new \BadMethodCallException('Delete not supported on Auth repository');
    }
}
