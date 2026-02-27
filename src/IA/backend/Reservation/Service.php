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

namespace BackendPhp\Reservation;

final class Service
{
    /**
     * inventory: [itemType => quantity].
     */
    protected array $inventory = [];

    public function __construct(array $initialInventory = [])
    {
        $this->inventory = $initialInventory;
    }

    public function available(string $itemType): int
    {
        return $this->inventory[$itemType] ?? 0;
    }

    /**
     * Attempt to reserve quantity; atomic: either all reserved or none.
     */
    public function reserve(string $itemType, int $quantity): bool
    {
        if ($quantity <= 0) {
            return false;
        }

        $available = $this->available($itemType);
        if ($available < $quantity) {
            return false;
        }

        // reserve (consume) immediately for this simple model
        $this->inventory[$itemType] = $available - $quantity;

        return true;
    }

    public function release(string $itemType, int $quantity): void
    {
        $this->inventory[$itemType] = $this->available($itemType) + $quantity;
    }
}
