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

namespace BackendPhp\Server;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use React\Http\Message\Response;
use React\Promise\PromiseInterface;

/** Explicitly opt a route into receiving the request before positional path parameters. */
final class RequestHandlerAdapter
{
    private $handler;

    public function __construct(callable $handler)
    {
        $this->handler = $handler;
    }

    public function __invoke(ServerRequestInterface $request, ...$parameters): ResponseInterface|PromiseInterface
    {
        try {
            $result = ($this->handler)($request, ...$parameters);
        } catch (\BadMethodCallException $error) {
            return $this->unsupported();
        }

        if ($result instanceof PromiseInterface) {
            return $result->then(
                fn ($response) => $this->toResponse($response),
                function (\Throwable $error) {
                    if ($error instanceof \BadMethodCallException) {
                        return $this->unsupported();
                    }

                    throw $error;
                }
            );
        }

        return $this->toResponse($result);
    }

    private function toResponse($result): ResponseInterface
    {
        if ($result instanceof ResponseInterface) {
            return $result;
        }
        if (is_array($result)) {
            return Response::json($result);
        }

        throw new \UnexpectedValueException('Request handlers must return a response or array');
    }

    private function unsupported(): ResponseInterface
    {
        return Response::json(['error' => 'Operation is not implemented'])->withStatus(501);
    }
}
