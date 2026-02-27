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

namespace BackendPhp\Contract;

use BackendPhp\Support\PartFactory;
use BackendPhp\Parts\Contract;

final class PartRepository
{
    public function hydrateFromRow(array $row): Contract
    {
        // Convert DB row keys (snake_case) to attributes as-is
        return PartFactory::create('contract', $row);
    }
}
