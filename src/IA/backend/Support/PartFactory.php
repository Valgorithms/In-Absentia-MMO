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

use BackendPhp\Parts\Contract;
use BackendPhp\Parts\Character;

final class PartFactory
{
    protected static $discordFactory = null;

    public static function setDiscordFactory($factory): void
    {
        self::$discordFactory = $factory;
    }

    public static function create(string $type, array $attributes = []): object
    {
        $map = [
            'contract' => Contract::class,
            'character' => Character::class,
        ];

        $class = $map[$type] ?? $type;
        if (! class_exists($class)) {
            throw new \InvalidArgumentException("Unknown part class: {$class}");
        }

        // If a Discord factory is registered and the class is a Discord Part,
        // delegate creation to the Discord factory so it gets the Discord client.
        if (self::$discordFactory !== null) {
            try {
                // If class is subclass of Discord\Parts\Part or namespaced under Discord\Parts
                if (is_subclass_of($class, \Discord\Parts\Part::class) || str_starts_with($class, 'Discord\\Parts\\')) {
                    return self::$discordFactory->part($class, $attributes, false);
                }
            } catch (\Throwable $e) {
                // Fall back to direct instantiation on error
            }
        }

        // If it's a Discord Part class but we don't have a Discord factory registered,
        // create a minimal Discord stub instance to satisfy the constructor typehint
        // so tests and non-discord environments can still instantiate parts.
        if (is_subclass_of($class, \Discord\Parts\Part::class) || str_starts_with($class, 'Discord\\Parts\\')) {
            // Create instance without invoking DiscordPart constructor to avoid
            // requiring a full Discord client in tests or non-discord environments.
            $ref = new \ReflectionClass($class);
            $instance = $ref->newInstanceWithoutConstructor();

            // If the PartTrait fill method is available, use it to populate attributes.
            if (method_exists($instance, 'fill')) {
                $instance->fill($attributes);
            } else {
                // Fallback: set protected attributes property directly via a bound closure
                if ($ref->hasProperty('attributes')) {
                    $propName = 'attributes';
                    $setter = function ($val) use ($propName) {
                        $this->$propName = $val;
                    };
                    $setter = \Closure::bind($setter, $instance, $class);
                    $setter($attributes);
                }
            }

            // Mark as not created
            if ($ref->hasProperty('created')) {
                $propName = 'created';
                $setter = function ($val) use ($propName) {
                    $this->$propName = $val;
                };
                $setter = \Closure::bind($setter, $instance, $class);
                $setter(false);
            }

            return $instance;
        }

        return new $class($attributes);
    }
}
