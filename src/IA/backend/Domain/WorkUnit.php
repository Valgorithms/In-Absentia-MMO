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

final class WorkUnit
{
    public static function fromActiveSeconds(int $seconds): float
    {
        if ($seconds < 0) {
            throw new \InvalidArgumentException('seconds must be non-negative');
        }

        return $seconds / 60.0;
    }
}
