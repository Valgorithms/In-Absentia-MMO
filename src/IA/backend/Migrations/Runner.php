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

namespace BackendPhp\Migrations;

use BackendPhp\Database\ConnectionPool;

class Runner
{
    protected ConnectionPool $pool;
    protected string $migrationsPath;

    public function __construct(ConnectionPool $pool, string $migrationsPath)
    {
        $this->pool = $pool;
        $this->migrationsPath = $migrationsPath;
    }

    public function runPending(): void
    {
        $pdo = $this->pool->getConnection();
        $pdo->beginTransaction();
        try {
            // ensure migrations table (use portable SQL depending on driver)
            $driver = $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);
            if ($driver === 'sqlite') {
                $pdo->exec("CREATE TABLE IF NOT EXISTS migrations (id TEXT PRIMARY KEY, ran_at TEXT DEFAULT (datetime('now')))");
            } else {
                $pdo->exec('CREATE TABLE IF NOT EXISTS migrations (id TEXT PRIMARY KEY, ran_at TIMESTAMPTZ DEFAULT now())');
            }

            $files = glob(rtrim($this->migrationsPath, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'*.sql');
            foreach ($files as $f) {
                $id = basename($f);
                $stmt = $pdo->prepare('SELECT 1 FROM migrations WHERE id = :id');
                $stmt->execute(['id' => $id]);
                if ($stmt->fetchColumn()) {
                    continue;
                }

                $sql = file_get_contents($f);
                $pdo->exec($sql);
                $ins = $pdo->prepare('INSERT INTO migrations (id) VALUES (:id)');
                $ins->execute(['id' => $id]);
            }

            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }
}
