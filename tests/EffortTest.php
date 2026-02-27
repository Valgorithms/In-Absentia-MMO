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
use BackendPhp\Domain\EffortCalculator;

test('efficiency calculation returns relevant_stat / reference_value', function () {
    $eff = EffortCalculator::calculateEfficiency(12.0, 10.0);
    Assert::assertEquals(1.2, $eff);
});

test('generates effort = stamina_drained * efficiency', function () {
    $effort = EffortCalculator::generateEffort(5.0, 1.2);
    Assert::assertEquals(6.0, $effort);
});

test('calculateEfficiency throws on invalid reference value', function () {
    $this->expectException(InvalidArgumentException::class);
    EffortCalculator::calculateEfficiency(10, 0);
});

test('generateEffort throws on negative inputs', function () {
    $this->expectException(InvalidArgumentException::class);
    EffortCalculator::generateEffort(-1, 1);
});
