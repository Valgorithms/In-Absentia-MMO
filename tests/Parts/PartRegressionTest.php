<?php

/*
 * This file is a part of the In Absentia project.
 *
 * Copyright (c) 2026-present Valithor Obsidion <valithor@discordphp.org>
 *
 * This file is subject to the MIT license that is bundled
 * with this source code in the LICENSE.md file.
 */

use BackendPhp\Contract\PartRepository;
use BackendPhp\Parts\Character;
use BackendPhp\Parts\Contract;

test('contract part repository obeys PSR-4 and hydrates nested parts', function () {
    $part = (new PartRepository())->hydrateFromRow(['id' => 'c1', 'owner' => ['id' => 'p1']]);
    expect($part)->toBeInstanceOf(Contract::class);
    expect($part->owner)->toBeInstanceOf(Character::class);
    expect($part->owner->id)->toBe('p1');
});

test('part setters retain mutators and serialize timestamps as strings', function () {
    $date = '2026-02-27T12:30:00+00:00';
    foreach ([[new Character(['id' => 'p1']), ['created_at', 'updated_at']], [new Contract(['id' => 'c1']), ['started_at', 'resolved_at']]] as [$part, $fields]) {
        expect($part->isDirty())->toBeFalse();
        foreach ($fields as $field) {
            $part->$field = $date;
            expect($part->$field)->toBeInstanceOf(DateTimeInterface::class);
            expect($part->jsonSerialize()[$field])->toBe($date);
            expect(json_decode(json_encode($part), true)[$field])->toBe($date);
        }
        expect($part->isDirty())->toBeTrue();
        $part->syncOriginal();
        expect($part->isDirty())->toBeFalse();
    }
    $contract = new Contract(['id' => 'c1']);
    $contract->owner = ['id' => 'p1', 'created_at' => $date];
    expect($contract->owner)->toBeInstanceOf(Character::class);
    expect($contract->jsonSerialize()['owner']['created_at'])->toBe($date);
});

test('invalid part dates fail explicitly and preserve previous values', function () {
    foreach ([[new Character(), ['created_at', 'updated_at']], [new Contract(), ['started_at', 'resolved_at']]] as [$part, $fields]) {
        foreach ($fields as $field) {
            $part->$field = '2026-02-27T12:30:00+00:00';
            $before = $part->$field;
            expect(fn () => $part->$field = 'definitely not a date')->toThrow(InvalidArgumentException::class);
            expect($part->$field)->toBe($before);
            $part->$field = null;
            expect($part->$field)->toBeNull();
        }
    }
});
