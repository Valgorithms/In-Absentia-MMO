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

namespace BackendPhp\Support;

class Container
{
    protected array $items = [];

    public function set(string $id, $value): void
    {
        $this->items[$id] = $value;
    }

    public function get(string $id)
    {
        if (! array_key_exists($id, $this->items)) {
            return null;
        }

        $val = $this->items[$id];
        if (is_callable($val)) {
            // lazy factory: replace with resolved value
            $this->items[$id] = $val($this);

            return $this->items[$id];
        }

        return $val;
    }

    public function has(string $id): bool
    {
        return array_key_exists($id, $this->items);
    }
}
