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

final class ExpertiseCalculator
{
    /**
     * contributors: array of ['effort' => float, 'expertise' => float].
     */
    public static function weightedExpertise(array $contributors): float
    {
        $totalEffort = 0.0;
        foreach ($contributors as $c) {
            $totalEffort += $c['effort'];
        }

        if ($totalEffort <= 0) {
            return 0.0;
        }

        $weighted = 0.0;
        foreach ($contributors as $c) {
            $weight = $c['effort'] / $totalEffort;
            $weighted += $weight * $c['expertise'];
        }

        return $weighted;
    }
}
