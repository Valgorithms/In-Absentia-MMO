<?php

/*
 * This file is a part of the In Absentia project.
 *
 * Copyright (c) 2026-present Valithor Obsidion <valithor@discordphp.org>
 *
 * This file is subject to the MIT license that is bundled
 * with this source code in the LICENSE.md file.
 */

use BackendPhp\Support\Config;
use BackendPhp\Database\ConnectionPool;
use BackendPhp\Migrations\Runner as MigrationRunner;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\SkippedTestSuiteError;

test('migration runner applies SQL files using in-memory sqlite', function () {
    $migrationsPath = __DIR__.'/integration_migrations';

    // Try to create a test PDO connection; skip if it fails in this environment
    try {
        new \PDO('sqlite::memory:');
    } catch (\PDOException $e) {
        throw new SkippedTestSuiteError('Unable to create PDO sqlite connection; skipping integration migration test');
    }

    $config = new Config([
        'db' => [
            'dsn' => 'sqlite::memory:',
            'user' => null,
            'pass' => null,
        ],
    ]);

    $pool = new ConnectionPool($config);
    $runner = new MigrationRunner($pool, $migrationsPath);

    // Should run without exceptions
    $runner->runPending();

    $pdo = $pool->getConnection();
    $stmt = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='example_table'");
    $found = $stmt->fetchColumn();
    Assert::assertEquals('example_table', $found);
});
