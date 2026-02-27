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

namespace BackendPhp\Database;

use BackendPhp\Support\Config;

class ConnectionPool
{
    protected array $pool = [];
    protected Config $config;

    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    public function getConnection(): \PDO
    {
        // Simple single-connection pool for sync operations
        if (! isset($this->pool['default'])) {
            $dbCfg = $this->config->get('db', []);
            if (is_array($dbCfg)) {
                $dsn = $dbCfg['dsn'] ?? '';
                $user = $dbCfg['user'] ?? null;
                $pass = $dbCfg['pass'] ?? null;
            } else {
                $dsn = (string) $dbCfg;
                $user = null;
                $pass = null;
            }
            $opts = [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION];
            // Try DSN-only construction first (works for sqlite and many drivers),
            // fall back to DSN+credentials+options if that fails.
            try {
                $this->pool['default'] = new \PDO($dsn);
                $this->pool['default']->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
            } catch (\PDOException $e) {
                $this->pool['default'] = new \PDO($dsn, $user, $pass, $opts);
            }
        }

        return $this->pool['default'];
    }
}
