<?php

declare(strict_types=1);

/*
 * This file is a part of the In Absentia project.
 *
 * Copyright (c) 2026-present Valithor Obsidion <valithor@discordphp.org>
 *
 * This file is subject to the MIT license that is bundled
 * with this source code in the LICENSE.md file.
 */

namespace BackendPhp\Domain;

final class EffortCalculator
{
    public static function calculateEfficiency(float $relevantStat, float $referenceValue): float
    {
        if ($referenceValue <= 0) {
            throw new \InvalidArgumentException('referenceValue must be > 0');
        }

        return $relevantStat / $referenceValue;
    }

    public static function generateEffort(float $staminaDrained, float $efficiency): float
    {
        if ($staminaDrained < 0 || $efficiency < 0) {
            throw new \InvalidArgumentException('Values must be non-negative');
        }

        return $staminaDrained * $efficiency;
    }
}
