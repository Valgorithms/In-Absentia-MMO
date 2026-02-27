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

// Start servers
$http = new Http($loop);
$ws = new WebSocket($loop);

$http->listen('0.0.0.0', 8080);
$ws->listen('0.0.0.0', 8081);

echo "Backend PHP server starting on HTTP :8080 and WS :8081\n";

$loop->run();
