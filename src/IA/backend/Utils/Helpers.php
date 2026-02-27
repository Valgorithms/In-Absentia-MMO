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

namespace BackendPhp\Utils;

class Helpers
{
    public static function env(string $key, $default = null)
    {
        $val = getenv($key);

        return $val === false ? $default : $val;
    }
}
