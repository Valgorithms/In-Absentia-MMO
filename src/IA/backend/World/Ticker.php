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

namespace BackendPhp\World;

use React\EventLoop\Loop;
use React\EventLoop\LoopInterface;

class Ticker
{
    protected LoopInterface $loop;

    public function __construct(?LoopInterface $loop = null)
    {
        $this->loop = $loop ?? Loop::get();
    }

    public function start(): void
    {
        // fast tick
        $this->loop->addPeriodicTimer(1.0, function () {
            // process stamina regen, active contracts, etc.
        });

        // slow tick
        $this->loop->addPeriodicTimer(60.0, function () {
            // economic aggregates, decay, scheduled events
        });
    }
}
