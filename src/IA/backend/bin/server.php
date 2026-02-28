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

require __DIR__.'/../../../vendor/autoload.php';

use BackendPhp\Server\Http;
use BackendPhp\Server\WebSocket;
use BackendPhp\Support\Container;
use BackendPhp\Support\DiscordServiceProvider;
use BackendPhp\Support\MonologFactory;
use React\EventLoop\Loop;

$loop = Loop::get();

// Create a simple container and register basic services
$container = new Container();
$container->set('loop', fn ($c) => $loop);
$container->set('logger', fn ($c) => MonologFactory::create('in-absentia'));
$container->set('config', fn ($c) => [
    'discord' => [
        'token' => getenv('DISCORD_TOKEN') ?: '',
    ],
]);

// Register Discord services (factory + client) and wire PartFactory
(new DiscordServiceProvider())->register($container);

// Ensure container exposes an HTTP service and a generic `factory` alias
$container->set('http', function ($c) { return new Http($c->get('loop')); });
$container->set('factory', function ($c) { return $c->get('part.factory'); });

// Register API repositories in the container (factory bindings)
$container->set('repositories.auth', function ($c) { return new \BackendPhp\Api\Repositories\Auth($c); });
$container->set('repositories.account', function ($c) { return new \BackendPhp\Api\Repositories\Accounts($c); });
$container->set('repositories.character', function ($c) { return new \BackendPhp\Api\Repositories\Characters($c); });
$container->set('repositories.contract', function ($c) { return new \BackendPhp\Api\Repositories\Contracts($c); });
$container->set('repositories.knowledge', function ($c) { return new \BackendPhp\Api\Repositories\Knowledge($c); });
$container->set('repositories.world', function ($c) { return new \BackendPhp\Api\Repositories\World($c); });
$container->set('repositories.market', function ($c) { return new \BackendPhp\Api\Repositories\Market($c); });
$container->set('repositories.governance', function ($c) { return new \BackendPhp\Api\Repositories\Governance($c); });

// Start servers
$http = new Http($loop);
$ws = new WebSocket($loop);

$http->listen('0.0.0.0', 8080);
$ws->listen('0.0.0.0', 8081);

echo "Backend PHP server starting on HTTP :8080 and WS :8081\n";

$loop->run();
