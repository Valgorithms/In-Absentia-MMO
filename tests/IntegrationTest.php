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

test('connection pool reuses configured connections and surfaces database errors', function () {
    foreach (['sqlite::memory:', ['dsn' => 'sqlite::memory:', 'user' => null, 'pass' => null]] as $db) {
        $pool = new ConnectionPool(new Config(['db' => $db]));
        $pdo = $pool->getConnection();
        expect($pool->getConnection())->toBe($pdo);
        expect($pdo->getAttribute(PDO::ATTR_ERRMODE))->toBe(PDO::ERRMODE_EXCEPTION);
        expect(fn () => $pdo->exec('not valid SQL'))->toThrow(PDOException::class);
    }
    expect(fn () => (new ConnectionPool(new Config(['db' => 'missing-driver:'])))->getConnection())->toThrow(PDOException::class);
});

test('migrations run in filename order once and failed batches roll back', function () {
    $path = sys_get_temp_dir().DIRECTORY_SEPARATOR.'ia-migrations-'.bin2hex(random_bytes(8));
    mkdir($path);
    try {
        // Write in reverse order so directory insertion order differs from application order.
        file_put_contents($path.'/002_insert.sql', "INSERT INTO ordered VALUES ('second');");
        file_put_contents($path.'/001_create.sql', 'CREATE TABLE ordered (value TEXT);');
        $pool = new ConnectionPool(new Config(['db' => 'sqlite::memory:']));
        $runner = new MigrationRunner($pool, $path);
        $runner->runPending();
        $runner->runPending();
        $pdo = $pool->getConnection();
        expect($pdo->query('SELECT COUNT(*) FROM ordered')->fetchColumn())->toBe(1);
        expect($pdo->query('SELECT COUNT(*) FROM migrations')->fetchColumn())->toBe(2);
        file_put_contents($path.'/003_create.sql', 'CREATE TABLE rolled_back (id TEXT);');
        file_put_contents($path.'/004_fail.sql', 'not valid SQL');
        expect(fn () => $runner->runPending())->toThrow(PDOException::class);
        expect($pdo->query("SELECT COUNT(*) FROM sqlite_master WHERE name='rolled_back'")->fetchColumn())->toBe(0);
        expect($pdo->query('SELECT COUNT(*) FROM migrations')->fetchColumn())->toBe(2);
    } finally {
        foreach (glob($path.'/*.sql') as $file) {
            unlink($file);
        }
        rmdir($path);
    }
});
