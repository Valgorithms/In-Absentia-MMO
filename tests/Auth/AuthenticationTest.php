<?php

/*
 * This file is a part of the In Absentia project.
 *
 * Copyright (c) 2026-present Valithor Obsidion <valithor@discordphp.org>
 *
 * This file is subject to the MIT license that is bundled
 * with this source code in the LICENSE.md file.
 */

use BackendPhp\Auth\Handler;
use BackendPhp\Auth\Middleware;
use BackendPhp\Auth\TokenService;
use Firebase\JWT\JWT;
use React\Http\Message\Response;
use React\Http\Message\ServerRequest;
use function React\Async\await;

test('authentication rejects invalid bearer tokens without invoking downstream code', function () {
    $key = str_repeat('fixture-only-', 4);
    $tokens = new TokenService($key);
    $middleware = new Middleware($tokens);
    $now = time();
    $invalid = [
        '', 'DEV-TOKEN', base64_encode('{"sub":"other-account"}'),
        JWT::encode(['sub' => 'a', 'exp' => $now + 60], str_repeat('different-fixture-', 4), 'HS256'),
        JWT::encode(['sub' => 'a', 'exp' => $now - 60], $key, 'HS256'),
        JWT::encode(['sub' => 'a', 'exp' => $now], $key, 'HS256'),
        JWT::encode(['sub' => 'a', 'nbf' => $now + 120, 'exp' => $now + 300], $key, 'HS256'),
        JWT::encode(['sub' => 'a', 'exp' => $now + 60], str_repeat('fixture-only-', 6), 'HS512'),
        $tokens->createToken(['sub' => 'a'], null),
        JWT::encode(['sub' => 'a', 'exp' => null], $key, 'HS256'),
        JWT::encode(['sub' => 'a', 'exp' => (string) ($now + 60)], $key, 'HS256'),
        JWT::encode(['sub' => 'a', 'exp' => $now + 60.5], $key, 'HS256'),
        $tokens->createToken([]),
        $tokens->createToken(['sub' => '']),
        $tokens->createToken(['sub' => ['a']]),
        $tokens->createToken(['sub' => 12]),
    ];
    $valid = $tokens->createToken(['sub' => 'a']);
    [$header, $payload, $signature] = explode('.', $valid);
    $invalid[] = $header.'.'.JWT::urlsafeB64Encode('{"sub":"other-account","exp":'.($now + 60).'}').'.'.$signature;

    foreach ($invalid as $token) {
        $called = false;
        $request = new ServerRequest('GET', 'http://localhost/account', ['Authorization' => 'Bearer '.$token]);
        $response = $middleware->handle($request, function () use (&$called) {
            $called = true;

            return new Response(200);
        });
        expect($called)->toBeFalse();
        expect($response)->toBeInstanceOf(Response::class);
        expect($response->getStatusCode())->toBe(401);
    }
});

test('authentication passes verified identity claims unchanged', function () {
    $tokens = new TokenService(str_repeat('fixture-only-', 4));
    $token = $tokens->createToken(['sub' => 'account-0', 'roles' => ['player']]);
    $request = new ServerRequest('GET', 'http://localhost/account', ['Authorization' => 'Bearer '.$token]);
    $response = (new Middleware($tokens))->handle($request, function ($authenticated) use ($tokens, $token) {
        expect($authenticated->getAttribute('auth'))->toBe($tokens->verifyToken($token));

        return new Response(200);
    });
    expect($response->getStatusCode())->toBe(200);
});

test('unimplemented authentication endpoints never report success', function () {
    $handler = new Handler();
    $request = new ServerRequest('POST', 'http://localhost/auth', [], '{"email":"fixture@example.invalid","password":"fixture"}');
    foreach (['login', 'register'] as $method) {
        $response = $handler->$method($request);
        expect($response)->toBeInstanceOf(Response::class);
        expect($response->getStatusCode())->toBe(501);
        expect(json_decode((string) $response->getBody(), true))->not->toHaveKey('token');
    }
});

test('account access fails closed for missing or malformed identities', function () {
    $service = new \BackendPhp\Account\Service(new \BackendPhp\Account\Repository());
    $handler = new \BackendPhp\Account\Handler($service);
    foreach ([null, [], ['sub' => ''], ['sub' => ['a']], 'unverified'] as $auth) {
        $request = (new ServerRequest('GET', 'http://localhost/account'))->withAttribute('auth', $auth);
        expect(await($handler->getAccount($request)))->toBeNull();
    }
    expect(await($service->getAccount(['sub' => 'account-0']))['id'])->toBe('account-0');
});

test('token creation rejects weak signing keys and invalid JSON claims', function () {
    expect(fn () => (new TokenService('weak-fixture'))->createToken(['sub' => 'a']))->toThrow(DomainException::class);
    expect(fn () => (new TokenService(str_repeat('fixture-only-', 4)))->createToken(['sub' => "\xB1\x31"]))->toThrow(DomainException::class);
});
