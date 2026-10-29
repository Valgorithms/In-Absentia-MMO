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
use BackendPhp\Api\Endpoint;
use BackendPhp\Server\Http;
use BackendPhp\Server\RequestHandlerAdapter;
use BackendPhp\Support\Container;
use Psr\Http\Message\ServerRequestInterface;
use React\Http\Message\Response;
use React\Http\Message\ServerRequest;
use Sharkk\Router\Router;

function exchangeBackendRequest(callable $createMock, Http $server, string $wire): string
{
    $accept = null;
    $socket = $createMock(\React\Socket\ServerInterface::class);
    $socket->method('on')->willReturnCallback(function ($event, $callback) use (&$accept, $socket) {
        if ($event === 'connection') {
            $accept = $callback;
        }

        return $socket;
    });
    $events = new \Evenement\EventEmitter();
    $connection = $createMock(\React\Socket\ConnectionInterface::class);
    $connection->method('emit')->willReturnCallback(function ($event, array $arguments = []) use ($events) {
        $events->emit($event, $arguments);
    });
    foreach (['on', 'once', 'removeListener'] as $method) {
        $connection->method($method)->willReturnCallback(function ($event, $callback) use ($events, $connection, $method) {
            $events->$method($event, $callback);

            return $connection;
        });
    }
    $connection->method('getRemoteAddress')->willReturn('tcp://127.0.0.1:12345');
    $connection->method('getLocalAddress')->willReturn('tcp://127.0.0.1:8080');
    $connection->method('isReadable')->willReturn(true);
    $connection->method('isWritable')->willReturn(true);
    $output = '';
    $connection->method('write')->willReturnCallback(function ($data) use (&$output) {
        $output .= $data;

        return true;
    });
    $connection->method('end')->willReturnCallback(function ($data = null) use (&$output) {
        $output .= $data ?? '';
    });
    $server->listenOn($socket);
    $accept($connection);
    $events->emit('data', [$wire]);

    return $output;
}

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

test('request-aware route receives HTTP headers query body and path parameters', function () {
    $router = new Router();
    $router->post('/fixture/:id', new RequestHandlerAdapter(function (ServerRequestInterface $request, string $id) {
        return ['id' => $id, 'header' => $request->getHeaderLine('X-Fixture'), 'query' => $request->getQueryParams(), 'body' => (string) $request->getBody()];
    }));
    $wire = "POST /fixture/char-0?view=stats HTTP/1.1\r\nHost: localhost\r\nX-Fixture: preserved\r\nContent-Length: 7\r\nConnection: close\r\n\r\n{\"x\":1}";
    $response = exchangeBackendRequest(fn ($class) => $this->createMock($class), new Http(null, $router), $wire);
    expect($response)->toStartWith('HTTP/1.1 200');
    expect(json_decode(explode("\r\n\r\n", $response, 2)[1], true))->toBe(['id' => 'char-0', 'header' => 'preserved', 'query' => ['view' => 'stats'], 'body' => '{"x":1}']);
});

test('real HTTP boundary preserves validation and rejects unsupported creation', function () {
    $handler = new \BackendPhp\Contract\Handler(new \BackendPhp\Contract\Service(new \BackendPhp\Contract\Repository()));
    $router = new Router();
    $router->post('/fixture/contracts', new RequestHandlerAdapter([$handler, 'create']));
    foreach (['{' => 400, '{}' => 501] as $body => $status) {
        $wire = "POST /fixture/contracts HTTP/1.1\r\nHost: localhost\r\nContent-Length: ".strlen($body)."\r\nConnection: close\r\n\r\n".$body;
        $response = exchangeBackendRequest(fn ($class) => $this->createMock($class), new Http(null, $router), $wire);
        expect($response)->toStartWith('HTTP/1.1 '.$status);
        expect(json_decode(explode("\r\n\r\n", $response, 2)[1], true))->toHaveKey('error');
    }
});

test('fixture auth middleware works across HTTP and production auth routes stay absent', function () {
    $tokens = new \BackendPhp\Auth\TokenService(str_repeat('fixture-only-', 4));
    $middleware = new \BackendPhp\Auth\Middleware($tokens);
    $calls = 0;
    $router = new Router();
    $router->get('/fixture/private', new RequestHandlerAdapter(function ($request) use ($middleware, &$calls) {
        return $middleware->handle($request, function ($authenticated) use (&$calls) {
            $calls++;

            return Response::json(['sub' => $authenticated->getAttribute('auth')['sub']]);
        });
    }));
    $server = new Http(null, $router);
    foreach (['DEV-TOKEN' => 401, $tokens->createToken(['sub' => 'fixture']) => 200] as $token => $status) {
        $response = exchangeBackendRequest(fn ($class) => $this->createMock($class), $server, "GET /fixture/private HTTP/1.1\r\nHost: localhost\r\nAuthorization: Bearer ".$token."\r\nConnection: close\r\n\r\n");
        expect($response)->toStartWith('HTTP/1.1 '.$status);
    }
    expect($calls)->toBe(1);
    foreach ([Endpoint::ACCOUNT, Endpoint::AUTH_LOGIN, Endpoint::AUTH_REGISTER] as $path) {
        expect(exchangeBackendRequest(fn ($class) => $this->createMock($class), new Http(), 'GET '.$path." HTTP/1.1\r\nHost: localhost\r\nConnection: close\r\n\r\n"))->toStartWith('HTTP/1.1 404');
    }
});

test('request-aware character route resolves handler data into an HTTP response', function () {
    $handler = new \BackendPhp\Character\Handler(new \BackendPhp\Character\Service(new \BackendPhp\Character\Repository()));
    $router = new Router();
    $router->get('/fixture/characters/:id', new RequestHandlerAdapter(function (ServerRequestInterface $request, string $id) use ($handler) {
        return $handler->getStats($request->withAttribute('character_id', $id));
    }));
    $response = exchangeBackendRequest(fn ($class) => $this->createMock($class), new Http(null, $router), "GET /fixture/characters/char-0 HTTP/1.1\r\nHost: localhost\r\nConnection: close\r\n\r\n");
    expect($response)->toStartWith('HTTP/1.1 200');
    expect(json_decode(explode("\r\n\r\n", $response, 2)[1], true))->toBe(['id' => 'char-0', 'stats' => []]);
});

test('HTTP adapter reports unsupported operations and preserves real failures', function () {
    $router = new Router();
    $router->get('/fixture/unsupported', new RequestHandlerAdapter(function ($request) {
        throw new BadMethodCallException('private fixture detail');
    }));
    $router->get('/fixture/failure', new RequestHandlerAdapter(function ($request) {
        throw new RuntimeException('private fixture detail');
    }));
    $router->get('/fixture/invalid', new RequestHandlerAdapter(fn ($request) => true));
    foreach (['unsupported' => 501, 'failure' => 500, 'invalid' => 500] as $path => $status) {
        $response = exchangeBackendRequest(fn ($class) => $this->createMock($class), new Http(null, $router), 'GET /fixture/'.$path." HTTP/1.1\r\nHost: localhost\r\nConnection: close\r\n\r\n");
        expect($response)->toStartWith('HTTP/1.1 '.$status);
        expect($response)->not->toContain('private fixture detail');
    }
});
