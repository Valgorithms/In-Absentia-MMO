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

final class Contract
{
    public const STATUS_PENDING = 'PENDING';
    public const STATUS_ACTIVE = 'ACTIVE';
    public const STATUS_PAUSED = 'PAUSED';
    public const STATUS_RESOLVED = 'RESOLVED';

    protected string $id;
    protected string $status = self::STATUS_PENDING;
    protected ?string $pauseReason = null;

    public function __construct(string $id)
    {
        $this->id = $id;
    }

    public function activate(): void
    {
        if ($this->status === self::STATUS_PENDING || $this->status === self::STATUS_PAUSED) {
            $this->status = self::STATUS_ACTIVE;
            $this->pauseReason = null;
        }
    }

    public function pause(string $reason): void
    {
        if ($this->status === self::STATUS_ACTIVE) {
            $this->status = self::STATUS_PAUSED;
            $this->pauseReason = $reason;
        }
    }

    public function resolve(): void
    {
        $this->status = self::STATUS_RESOLVED;
        $this->pauseReason = null;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getPauseReason(): ?string
    {
        return $this->pauseReason;
    }
}
