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

namespace BackendPhp\Research;

use Psr\Http\Message\ServerRequestInterface;
use React\Promise\PromiseInterface;
use React\Http\Message\Response;
use function React\Promise\resolve;

class Handler
{
    protected Service $service;

    public function __construct(Service $service)
    {
        $this->service = $service;
    }

    public function trial(ServerRequestInterface $request): PromiseInterface
    {
        $data = json_decode((string) $request->getBody(), true);
        if (json_last_error() !== JSON_ERROR_NONE || ! is_array($data)) {
            return resolve(Response::json(['error' => 'Invalid JSON body'])->withStatus(400));
        }

        return $this->service->trial($data);
    }
}
