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

use React\EventLoop\LoopInterface;
use React\Socket\TcpServer;
use React\Http\HttpServer as ReactHttpServer;
use Psr\Http\Message\ServerRequestInterface;
use React\EventLoop\Loop;
use Sharkk\Router\Router as SharkkRouter;

class Http
{
    protected LoopInterface $loop;
    protected ReactHttpServer $server;
    protected TcpServer $socket;
    /** @var mixed Router implementation (sharkk/router or local) */
    protected $router;

    public function __construct(?LoopInterface $loop = null, $router = null)
    {
        $this->loop = $loop ?? Loop::get();

        if ($router !== null) {
            $this->router = $router;
        } else {
            $this->router = new SharkkRouter();
        }

        $this->server = new ReactHttpServer(function (ServerRequestInterface $request) {
            return $this->router->dispatch($request);
        });
    }

    public function listen(string $host, int $port): void
    {
        $this->socket = new TcpServer("{$host}:{$port}", $this->loop);
        $this->server->listen($this->socket);
    }
}
