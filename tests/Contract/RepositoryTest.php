<?php

/*
 * This file is a part of the In Absentia project.
 *
 * Copyright (c) 2026-present Valithor Obsidion <valithor@discordphp.org>
 *
 * This file is subject to the MIT license that is bundled
 * with this source code in the LICENSE.md file.
 */

use BackendPhp\Contract\Repository;

test('contract creation assigns a server id and preserves other input', function () {
    $resolved = false;
    (new Repository())->create(['id' => 'client-id', 'type' => 'research'])->then(function ($result) use (&$resolved) {
        $resolved = true;
        expect($result['id'])->toMatch('/^[a-f0-9]{16}$/');
        expect($result['id'])->not->toBe('client-id');
        expect($result['type'])->toBe('research');
    });
    expect($resolved)->toBeTrue();
});
