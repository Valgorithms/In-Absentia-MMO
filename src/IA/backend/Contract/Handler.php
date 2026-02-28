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

namespace BackendPhp\Contract;

use Psr\Http\Message\ServerRequestInterface;
use React\Promise\PromiseInterface;

class Handler
{
    protected Service $service;

    public function __construct(Service $service)
    {
        $this->service = $service;
    }

    public function create(ServerRequestInterface $request): PromiseInterface
    {
        return $this->service->create(json_decode((string) $request->getBody(), true));
    }
}
