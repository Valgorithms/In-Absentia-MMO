<?php

/*
 * This file is a part of the In Absentia project.
 *
 * Copyright (c) 2026-present Valithor Obsidion <valithor@discordphp.org>
 *
 * This file is subject to the MIT license that is bundled
 * with this source code in the LICENSE.md file.
 */

use BackendPhp\Api\Repositories\Characters;
use BackendPhp\Api\Repositories\Contracts;
use BackendPhp\Contract\Repository;
use BackendPhp\Contract\Service;
use BackendPhp\Support\Container;
use function React\Async\await;

test('API deletion cannot report a successful mutation without a transport', function () {
    $characters = new Characters(new Container());
    $contracts = new Contracts(new Container());
    expect(fn () => $characters->delete('fixture'))->toThrow(BadMethodCallException::class);
    expect(fn () => $contracts->delete('fixture'))->toThrow(BadMethodCallException::class);
    expect($characters->retire('fixture'))->toBe(['method' => 'DELETE', 'path' => '/api/v1/characters/fixture']);
    expect($contracts->cancel('fixture'))->toBe(['method' => 'DELETE', 'path' => '/api/v1/contracts/fixture']);
});

test('contract creation rejects unsupported persistence and reservation work', function () {
    $repository = new class extends Repository {
        public int $calls = 0;

        public function create(array $payload): \React\Promise\PromiseInterface
        {
            $this->calls++;

            return parent::create($payload);
        }
    };
    expect(fn () => await((new Service($repository))->create(['type' => 'research'])))->toThrow(BadMethodCallException::class);
    expect($repository->calls)->toBe(0);
});
