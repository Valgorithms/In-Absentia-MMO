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
use BackendPhp\Contract\Contract;

test('contract lifecycle transitions: activate -> pause -> resolve', function () {
    $c = new Contract('c1');
    Assert::assertEquals(Contract::STATUS_PENDING, $c->getStatus());

    $c->activate();
    Assert::assertEquals(Contract::STATUS_ACTIVE, $c->getStatus());

    $c->pause('STAMINA_DEPLETED');
    Assert::assertEquals(Contract::STATUS_PAUSED, $c->getStatus());
    Assert::assertEquals('STAMINA_DEPLETED', $c->getPauseReason());

    $c->activate();
    Assert::assertEquals(Contract::STATUS_ACTIVE, $c->getStatus());

    $c->resolve();
    Assert::assertEquals(Contract::STATUS_RESOLVED, $c->getStatus());
});
