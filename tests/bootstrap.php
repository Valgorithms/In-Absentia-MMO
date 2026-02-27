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

$autoload = __DIR__.'/../vendor/autoload.php';
if (! file_exists($autoload)) {
    fwrite(STDERR, "Composer autoload not found. Run `composer install` first.\n");
    exit(1);
}

require $autoload;

// Optional test-time settings
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Provide a lightweight test Discord factory so tests can create Discord Part
// subclasses without constructing a full Discord client. This mirrors the
// approach used by DiscordPHP tests where a factory/stub is provided.
\BackendPhp\Support\PartFactory::setDiscordFactory(new class {
    public function part(string $class, array $data = [], bool $created = false)
    {
        $ref = new \ReflectionClass($class);
        $instance = $ref->newInstanceWithoutConstructor();

        if (method_exists($instance, 'fill')) {
            $instance->fill($data);
        } elseif ($ref->hasProperty('attributes')) {
            $prop = $ref->getProperty('attributes');
            $prop->setAccessible(true);
            $prop->setValue($instance, $data);
        }

        if ($ref->hasProperty('created')) {
            $prop = $ref->getProperty('created');
            $prop->setAccessible(true);
            $prop->setValue($instance, $created);
        }

        return $instance;
    }
});
