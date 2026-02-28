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
use BackendPhp\Domain\ExpertiseCalculator;

test('weighted expertise computes correctly', function () {
    $contributors = [
        ['effort' => 10.0, 'expertise' => 2.5],
        ['effort' => 90.0, 'expertise' => 0.8],
    ];

    $weighted = ExpertiseCalculator::weightedExpertise($contributors);
    // weighted = 0.1*2.5 + 0.9*0.8 = 0.25 + 0.72 = 0.97
    Assert::assertEqualsWithDelta(0.97, $weighted, 0.0001);
});

test('weighted expertise returns 0 for zero effort', function () {
    $weighted = ExpertiseCalculator::weightedExpertise([]);
    Assert::assertEquals(0.0, $weighted);
});
