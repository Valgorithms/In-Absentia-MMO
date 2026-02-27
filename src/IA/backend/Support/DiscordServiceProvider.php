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

use Discord\Discord;
use Discord\Factory\Factory as DiscordFactory;

final class DiscordServiceProvider extends ServiceProvider
{
    public function register(Container $container): void
    {
        // discord client
        $container->set('discord', function ($c) {
            $cfg = $c->get('config')['discord'] ?? [];
            $options = [
                'token' => $cfg['token'] ?? '',
                'loop' => $c->get('loop'),
                'logger' => $c->get('logger'),
            ];

            return new Discord($options);
        });

        // discord factory
        $container->set('discord.factory', function ($c) {
            return new DiscordFactory($c->get('discord'));
        });

        // Eagerly bind our PartFactory to use the Discord factory so parts created
        // through PartFactory will use the Discord client where appropriate.
        $factory = $container->get('discord.factory');
        \BackendPhp\Support\PartFactory::setDiscordFactory($factory);
    }
}
