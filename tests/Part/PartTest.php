<?php

/*
 * This file is a part of the In Absentia project.
 *
 * Copyright (c) 2026-present Valithor Obsidion <valithor@discordphp.org>
 *
 * This file is subject to the MIT license that is bundled
 * with this source code in the LICENSE.md file.
 */

use PHPUnit\Framework\Assert;
use BackendPhp\Parts\Contract;
use BackendPhp\Parts\Character;

test('part magic access and toArray nesting', function () {
    $row = [
        'id' => 'c1',
        'started_at' => '2026-02-27T10:00:00Z',
        'owner' => ['id' => 'char1', 'created_at' => '2026-02-01T00:00:00Z'],
    ];

    $contract = new Contract($row);
    Assert::assertInstanceOf(Contract::class, $contract);

    // owner should be a Character
    Assert::assertInstanceOf(Character::class, $contract->owner);

    $arr = $contract->jsonSerialize();
    Assert::assertArrayHasKey('owner', $arr);
    Assert::assertArrayHasKey('created_at', $arr['owner']);
});

test('dirty tracking works', function () {
    $c = new Contract(['id' => 'x']);
    Assert::assertFalse($c->isDirty());
    $c->foo = 'bar';
    Assert::assertTrue($c->isDirty());
    $c->syncOriginal();
    Assert::assertFalse($c->isDirty());
});
