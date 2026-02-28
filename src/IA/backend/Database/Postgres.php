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

namespace BackendPhp\Database;

class Postgres
{
    public static function dsn(string $host, int $port, string $db): string
    {
        return sprintf('pgsql:host=%s;port=%d;dbname=%s', $host, $port, $db);
    }
}
