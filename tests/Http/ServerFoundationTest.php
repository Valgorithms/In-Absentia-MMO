<?php

/*
 * This file is a part of the In Absentia project.
 *
 * Copyright (c) 2026-present Valithor Obsidion <valithor@discordphp.org>
 *
 * This file is subject to the MIT license that is bundled
 * with this source code in the LICENSE.md file.
 */

use BackendPhp\Api\Repositories\Accounts;
use BackendPhp\Server\Http;
use BackendPhp\Support\Container;
use React\Http\Message\Response;
use React\Http\Message\ServerRequest;
use Sharkk\Router\Router;

test('default HTTP router returns a response without a configured route', function () {
    $response = (new Http())->handle(new ServerRequest('GET', 'http://localhost/missing'));
    expect($response->getStatusCode())->toBe(404);
});

test('HTTP wrapper honors the installed router lookup contract', function () {
    $router = new Router();
    $router->get('/hello/:name', fn ($name) => Response::json(['hello' => $name]));
    $server = new Http(null, $router);
    $response = $server->handle(new ServerRequest('GET', 'http://localhost/hello/fixture?ignored=true'));
    expect(json_decode((string) $response->getBody(), true))->toBe(['hello' => 'fixture']);
    expect($server->handle(new ServerRequest('POST', 'http://localhost/hello/fixture'))->getStatusCode())->toBe(405);
});

test('HTTP wrapper preserves custom dispatch routers', function () {
    $request = new ServerRequest('GET', 'http://localhost/custom');
    $router = new class {
        public $received;

        public function dispatch($request)
        {
            $this->received = $request;

            return new Response(204);
        }
    };
    expect((new Http(null, $router))->handle($request)->getStatusCode())->toBe(204);
    expect($router->received)->toBe($request);
});

test('account API repository is autoloadable through its public class name', function () {
    $repository = new Accounts(new Container());
    expect($repository->fetch('fixture'))->toBe(['method' => 'GET', 'path' => '/api/v1/account']);
});
