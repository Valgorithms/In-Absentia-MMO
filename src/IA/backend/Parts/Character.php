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

namespace BackendPhp\Parts;

use Discord\Discord;
use Discord\Parts\Part as DiscordPart;

final class Character extends DiscordPart implements \JsonSerializable
{
    // maintain compatibility with previous local Part API
    protected $fillable = ['id', 'created_at', 'updated_at'];
    protected $original = [];

    public function __construct($discordOrAttributes = [], array $attributes = [], bool $created = false)
    {
        if ($discordOrAttributes instanceof Discord) {
            parent::__construct($discordOrAttributes, $attributes, $created);

            return;
        }

        $attrs = is_array($discordOrAttributes) ? $discordOrAttributes : [];
        if (! empty($attributes)) {
            $attrs = $attributes;
        }

        $this->fill($attrs);
        $this->syncOriginal();
        $this->created = false;
    }

    public function getOriginal(): array
    {
        return $this->original;
    }

    public function isDirty(): bool
    {
        return ($this->attributes ?? []) !== $this->original;
    }

    public function syncOriginal(): void
    {
        $this->original = $this->attributes ?? [];
    }

    public function jsonSerialize(): array
    {
        $out = [];
        foreach ($this->getRawAttributes() as $k => $v) {
            if ($v instanceof DiscordPart) {
                $out[$k] = $v->jsonSerialize();
            } elseif (is_array($v)) {
                $out[$k] = array_map(fn ($x) => $x instanceof DiscordPart ? $x->jsonSerialize() : $x, $v);
            } else {
                $out[$k] = $v;
            }
        }

        return $out;
    }

    public function __set(string $name, $value): void
    {
        $this->attributes[$name] = $value;
    }

    public function __isset(string $name): bool
    {
        return isset($this->attributes[$name]);
    }

    protected function setCreatedAtAttribute($value): void
    {
        if (is_string($value)) {
            try {
                $this->attributes['created_at'] = new \Carbon\Carbon($value);

                return;
            } catch (\Throwable $e) {
                // fallthrough
            }
        }

        $this->attributes['created_at'] = $value;
    }

    protected function setUpdatedAtAttribute($value): void
    {
        if (is_string($value)) {
            try {
                $this->attributes['updated_at'] = new \Carbon\Carbon($value);

                return;
            } catch (\Throwable $e) {
                // fallthrough
            }
        }

        $this->attributes['updated_at'] = $value;
    }
}
