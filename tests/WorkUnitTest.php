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
use BackendPhp\Domain\WorkUnit;

test('1 minute (60s) equals 1 WU', function () {
    $wu = WorkUnit::fromActiveSeconds(60);
    Assert::assertEquals(1.0, $wu);
});

test('two minutes equals 2 WU', function () {
    $wu = WorkUnit::fromActiveSeconds(120);
    Assert::assertEquals(2.0, $wu);
});

test('fromActiveSeconds throws on negative seconds', function () {
    $this->expectException(InvalidArgumentException::class);
    WorkUnit::fromActiveSeconds(-5);
});
