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
    protected $discordFactory = null;

    /** @var null|self Global compatibility instance */
    protected static ?self $globalInstance = null;

    public function __construct($discordFactory = null)
    {
        $this->discordFactory = $discordFactory;
    }

    public function getDiscordFactory()
    {
        return $this->discordFactory;
    }

    /**
     * Compatibility shim: accept the previous static setter which provided
     * the Discord factory. This sets a global PartFactory instance used by
     * existing static call sites.
     */
    public static function setDiscordFactory($factory): void
    {
        self::$globalInstance = new self($factory);
    }

    /**
     * Static facade kept for backwards compatibility. Delegates to the
     * injectable instance (global if configured) so existing call sites
     * continue to work.
     */
    public static function create(string $type, array $attributes = []): object
    {
        $instance = self::$globalInstance ?? new self(null);

        return $instance->createInstance($type, $attributes);
    }

    /**
     * Instance-based creation method. New code should call this on an
     * injected `PartFactory` instance.
     */
    public function createInstance(string $type, array $attributes = []): object
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
        if ($this->discordFactory !== null) {
            try {
                if (is_subclass_of($class, \Discord\Parts\Part::class) || str_starts_with($class, 'Discord\\Parts\\')) {
                    return $this->discordFactory->part($class, $attributes, false);
                }
            } catch (\Throwable $e) {
                // Fall back to direct instantiation on error
            }
        }

        // If it's a Discord Part class but we don't have a Discord factory registered,
        // create a minimal Discord stub instance to satisfy the constructor typehint
        // so tests and non-discord environments can still instantiate parts.
        if (is_subclass_of($class, \Discord\Parts\Part::class) || str_starts_with($class, 'Discord\\Parts\\')) {
            $ref = new \ReflectionClass($class);
            $instance = $ref->newInstanceWithoutConstructor();

            if (method_exists($instance, 'fill')) {
                $instance->fill($attributes);
            } else {
                if ($ref->hasProperty('attributes')) {
                    $propName = 'attributes';
                    $setter = function ($val) use ($propName) {
                        $this->$propName = $val;
                    };
                    $setter = \Closure::bind($setter, $instance, $class);
                    $setter($attributes);
                }
            }

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
