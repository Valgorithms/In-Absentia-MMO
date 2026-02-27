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
use BackendPhp\Reservation\Service as ReservationService;

test('reservation succeeds when enough inventory', function () {
    $svc = new ReservationService(['iron_ingot' => 5]);
    $ok = $svc->reserve('iron_ingot', 3);
    Assert::assertTrue($ok);
    Assert::assertEquals(2, $svc->available('iron_ingot'));
});

test('reservation fails when insufficient', function () {
    $svc = new ReservationService(['iron_ingot' => 2]);
    $ok = $svc->reserve('iron_ingot', 3);
    Assert::assertFalse($ok);
    Assert::assertEquals(2, $svc->available('iron_ingot'));
});
