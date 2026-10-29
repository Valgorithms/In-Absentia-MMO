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

namespace BackendPhp\Server;

use React\EventLoop\Loop;
use React\EventLoop\LoopInterface;

class WebSocket
{
    protected LoopInterface $loop;

    public function __construct(?LoopInterface $loop = null)
    {
        $this->loop = $loop ?? Loop::get();
    }

    public function listen(string $host, int $port): void
    {
        // For a minimal skeleton we don't spin a full Ratchet server here.
        // Implementation note: use Ratchet or ReactPHP WebSocket libraries.
    }
}
