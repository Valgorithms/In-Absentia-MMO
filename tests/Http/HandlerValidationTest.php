<?php

/*
 * This file is a part of the In Absentia project.
 *
 * Copyright (c) 2026-present Valithor Obsidion <valithor@discordphp.org>
 *
 * This file is subject to the MIT license that is bundled
 * with this source code in the LICENSE.md file.
 */

use BackendPhp\Character\Handler as CharacterHandler;
use BackendPhp\Character\Repository as CharacterRepository;
use BackendPhp\Character\Service as CharacterService;
use BackendPhp\Contract\Handler as ContractHandler;
use BackendPhp\Contract\Repository as ContractRepository;
use BackendPhp\Contract\Service as ContractService;
use BackendPhp\Research\Handler as ResearchHandler;
use BackendPhp\Research\Repository as ResearchRepository;
use BackendPhp\Research\Service as ResearchService;
use React\Http\Message\Response;
use React\Http\Message\ServerRequest;

test('JSON handlers reject malformed and scalar bodies with 400 responses', function () {
    $handlers = [
        [new ContractHandler(new ContractService(new ContractRepository())), 'create'],
        [new ResearchHandler(new ResearchService(new ResearchRepository())), 'trial'],
    ];
    foreach ($handlers as [$handler, $method]) {
        foreach (['', '{', 'null', 'true', '123', '"text"'] as $body) {
            $resolved = false;
            $request = new ServerRequest('POST', 'http://localhost/', [], $body);
            $handler->$method($request)->then(function ($response) use (&$resolved) {
                $resolved = true;
                expect($response)->toBeInstanceOf(Response::class);
                expect($response->getStatusCode())->toBe(400);
            });
            expect($resolved)->toBeTrue();
        }
    }
});

test('JSON handlers preserve valid array and object bodies', function () {
    $handlers = [
        [new ContractHandler(new ContractService(new ContractRepository())), 'create', 'data'],
        [new ResearchHandler(new ResearchService(new ResearchRepository())), 'trial', 'params'],
    ];
    foreach ($handlers as [$handler, $method, $field]) {
        foreach (['{}', '[]', '{"type":"research"}', '[1,2]'] as $body) {
            $resolved = false;
            $handler->$method(new ServerRequest('POST', 'http://localhost/', [], $body))->then(function ($result) use (&$resolved, $body, $field) {
                $resolved = true;
                expect($result[$field])->toBe(json_decode($body, true));
            });
            expect($resolved)->toBeTrue();
        }
    }
});

test('character handler validates route identity without coercion', function () {
    $handler = new CharacterHandler(new CharacterService(new CharacterRepository()));
    foreach ([null, '', 1, [], false] as $id) {
        $resolved = false;
        $request = (new ServerRequest('GET', 'http://localhost/'))->withAttribute('character_id', $id);
        $handler->getStats($request)->then(function ($response) use (&$resolved) {
            $resolved = true;
            expect($response->getStatusCode())->toBe(400);
        });
        expect($resolved)->toBeTrue();
    }
    $handler->getStats((new ServerRequest('GET', 'http://localhost/'))->withAttribute('character_id', 'char-0'))->then(function ($result) {
        expect($result['id'])->toBe('char-0');
    });
});
