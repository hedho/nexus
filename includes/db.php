<?php
if (!defined('NEXUS')) exit('Forbidden');

/**
 * Database abstraction — supports SQLite3, MySQL, and MariaDB.
 * Driver is selected from DB_DRIVER constant (set by installer or config.php).
 *
 *   SQLite:  DB_DRIVER='sqlite'  (default, no credentials needed)
 *   MySQL:   DB_DRIVER='mysql'   + DB_HOST, DB_NAME, DB_USER, DB_PASS, DB_PORT
 *   MariaDB: DB_DRIVER='mysql'   (same driver as MySQL via PDO)
 */
class DB {
    private static ?PDO $pdo = null;

    /* ── Connect ──────────────────────────────────────────────── */
    public static function connect(): PDO {
        if (self::$pdo !== null) return self::$pdo;

        $driver = defined('DB_DRIVER') ? DB_DRIVER : 'sqlite';

        if ($driver === 'mysql') {
            $host    = defined('DB_HOST') ? DB_HOST : '127.0.0.1';
            $port    = defined('DB_PORT') ? DB_PORT : '3306';
            $name    = defined('DB_NAME') ? DB_NAME : 'nexus';
            $user    = defined('DB_USER') ? DB_USER : 'root';
            $pass    = defined('DB_PASS') ? DB_PASS : '';
            $charset = defined('DB_CHARSET') ? DB_CHARSET : 'utf8mb4';

            $dsn = "mysql:host={$host};port={$port};dbname={$name};charset={$charset}";
            self::$pdo = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::MYSQL_ATTR_FOUND_ROWS   => true,
            ]);
            self::$pdo->exec("SET SESSION sql_mode='STRICT_TRANS_TABLES,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO'");
        } else {
            // SQLite
            $path = DATA . '/forum.db';
            if (!is_dir(DATA)) mkdir(DATA, 0750, true);
            self::$pdo = new PDO('sqlite:' . $path, null, null, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
            self::$pdo->exec('PRAGMA foreign_keys = ON');
            self::$pdo->exec('PRAGMA journal_mode = WAL');
            self::$pdo->exec('PRAGMA synchronous = NORMAL');
            self::$pdo->exec('PRAGMA temp_store = MEMORY');
            self::$pdo->exec('PRAGMA mmap_size = 268435456');
        }

        return self::$pdo;
    }

    public static function driver(): string {
        return defined('DB_DRIVER') ? DB_DRIVER : 'sqlite';
    }

    public static function isMysql(): bool {
        return self::driver() === 'mysql';
    }

    /* ── Query helpers ────────────────────────────────────────── */
    public static function run(string $sql, array $p = []): PDOStatement {
        $s = self::connect()->prepare($sql);
        $s->execute($p);
        return $s;
    }

    public static function row(string $sql, array $p = []): ?array {
        $r = self::run($sql, $p)->fetch();
        return $r ?: null;
    }

    public static function rows(string $sql, array $p = []): array {
        return self::run($sql, $p)->fetchAll();
    }

    public static function insert(string $sql, array $p = []): int {
        self::run($sql, $p);
        return (int) self::connect()->lastInsertId();
    }

    public static function val(string $sql, array $p = []): mixed {
        $row = self::run($sql, $p)->fetch(PDO::FETCH_NUM);
        return $row ? $row[0] : null;
    }

    /**
     * Cross-driver INSERT IGNORE.
     * Silently skips if the row already exists (duplicate key).
     */
    public static function insertIgnore(string $table, array $cols, array $vals): void {
        $placeholders = implode(',', array_fill(0, count($vals), '?'));
        $colList      = implode(',', array_map(fn($c) => "`$c`", $cols));
        if (self::isMysql()) {
            self::run("INSERT IGNORE INTO `{$table}` ({$colList}) VALUES ({$placeholders})", $vals);
        } else {
            self::run("INSERT OR IGNORE INTO {$table} (" . implode(',', $cols) . ") VALUES ({$placeholders})", $vals);
        }
    }

    /**
     * Cross-driver upsert (INSERT ... ON DUPLICATE KEY UPDATE for MySQL,
     * INSERT OR REPLACE for SQLite).
     * Only works for simple single-column key tables like settings(key,value).
     */
    public static function upsert(string $table, string $keyCol, string $valCol, string $key, string $val): void {
        if (self::isMysql()) {
            self::run(
                "INSERT INTO `{$table}` (`{$keyCol}`,`{$valCol}`) VALUES (?,?) ON DUPLICATE KEY UPDATE `{$valCol}`=VALUES(`{$valCol}`)",
                [$key, $val]
            );
        } else {
            self::run(
                "INSERT OR REPLACE INTO {$table} ({$keyCol},{$valCol}) VALUES (?,?)",
                [$key, $val]
            );
        }
    }

    /* ── Schema: cross-driver table creation ─────────────────── */
    public static function init(): void {
        $mysql = self::isMysql();

        /* Helper: datetime default compatible with both drivers */
        $now   = $mysql ? "DEFAULT CURRENT_TIMESTAMP" : "DEFAULT (datetime('now'))";
        $auto  = $mysql ? "INT AUTO_INCREMENT"   : "INTEGER PRIMARY KEY AUTOINCREMENT";
        $pk    = $mysql ? "PRIMARY KEY"          : "";
        $eng   = $mysql ? "ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci" : "";

        $tables = [];

        /* settings */
        $tables[] = "CREATE TABLE IF NOT EXISTS settings (
            `key`   VARCHAR(100) PRIMARY KEY,
            `value` TEXT NOT NULL
        ) $eng";

        /* users */
        if ($mysql) {
            $tables[] = "CREATE TABLE IF NOT EXISTS users (
                id             INT AUTO_INCREMENT PRIMARY KEY,
                username       VARCHAR(30)  NOT NULL,
                email          VARCHAR(254) NOT NULL,
                password       VARCHAR(255) NOT NULL,
                avatar         TEXT,
                bio            TEXT,
                role           VARCHAR(20)  NOT NULL DEFAULT 'member',
                permissions    TEXT         NOT NULL DEFAULT '{}',
                post_count     INT          NOT NULL DEFAULT 0,
                topic_count    INT          NOT NULL DEFAULT 0,
                karma          INT          NOT NULL DEFAULT 0,
                suspended      TINYINT      NOT NULL DEFAULT 0,
                silenced       TINYINT      NOT NULL DEFAULT 0,
                location       VARCHAR(100) NOT NULL DEFAULT '',
                friends_hidden TINYINT      NOT NULL DEFAULT 0,
                joined_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
                last_seen      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uq_username (username),
                UNIQUE KEY uq_email (email)
            ) $eng";
        } else {
            $tables[] = "CREATE TABLE IF NOT EXISTS users (
                id             INTEGER PRIMARY KEY AUTOINCREMENT,
                username       TEXT    NOT NULL UNIQUE,
                email          TEXT    NOT NULL UNIQUE,
                password       TEXT    NOT NULL,
                avatar         TEXT,
                bio            TEXT,
                role           TEXT    NOT NULL DEFAULT 'member',
                permissions    TEXT    NOT NULL DEFAULT '{}',
                post_count     INTEGER NOT NULL DEFAULT 0,
                topic_count    INTEGER NOT NULL DEFAULT 0,
                karma          INTEGER NOT NULL DEFAULT 0,
                suspended      INTEGER NOT NULL DEFAULT 0,
                silenced       INTEGER NOT NULL DEFAULT 0,
                location       TEXT    NOT NULL DEFAULT '',
                friends_hidden INTEGER NOT NULL DEFAULT 0,
                joined_at      TEXT    NOT NULL DEFAULT (datetime('now')),
                last_seen      TEXT    NOT NULL DEFAULT (datetime('now'))
            )";
        }

        /* categories */
        if ($mysql) {
            $tables[] = "CREATE TABLE IF NOT EXISTS categories (
                id          INT AUTO_INCREMENT PRIMARY KEY,
                name        VARCHAR(100) NOT NULL,
                slug        VARCHAR(100) NOT NULL,
                description TEXT         NOT NULL DEFAULT '',
                color       VARCHAR(20)  NOT NULL DEFAULT '#3b82f6',
                icon        VARCHAR(10)  NOT NULL DEFAULT '💬',
                position    INT          NOT NULL DEFAULT 0,
                parent_id   INT,
                topic_count INT          NOT NULL DEFAULT 0,
                post_count  INT          NOT NULL DEFAULT 0,
                read_role   VARCHAR(20)  NOT NULL DEFAULT 'guest',
                post_role   VARCHAR(20)  NOT NULL DEFAULT 'member',
                reply_role  VARCHAR(20)  NOT NULL DEFAULT 'member',
                UNIQUE KEY uq_slug (slug)
            ) $eng";
        } else {
            $tables[] = "CREATE TABLE IF NOT EXISTS categories (
                id          INTEGER PRIMARY KEY AUTOINCREMENT,
                name        TEXT    NOT NULL,
                slug        TEXT    NOT NULL UNIQUE,
                description TEXT    NOT NULL DEFAULT '',
                color       TEXT    NOT NULL DEFAULT '#3b82f6',
                icon        TEXT    NOT NULL DEFAULT '💬',
                position    INTEGER NOT NULL DEFAULT 0,
                parent_id   INTEGER,
                topic_count INTEGER NOT NULL DEFAULT 0,
                post_count  INTEGER NOT NULL DEFAULT 0,
                read_role   TEXT    NOT NULL DEFAULT 'guest',
                post_role   TEXT    NOT NULL DEFAULT 'member',
                reply_role  TEXT    NOT NULL DEFAULT 'member'
            )";
        }

        /* topics */
        if ($mysql) {
            $tables[] = "CREATE TABLE IF NOT EXISTS topics (
                id           INT AUTO_INCREMENT PRIMARY KEY,
                title        VARCHAR(200) NOT NULL,
                slug         VARCHAR(220) NOT NULL,
                category_id  INT          NOT NULL,
                user_id      INT          NOT NULL,
                views        INT          NOT NULL DEFAULT 0,
                reply_count  INT          NOT NULL DEFAULT 0,
                pinned       TINYINT      NOT NULL DEFAULT 0,
                closed       TINYINT      NOT NULL DEFAULT 0,
                archived     TINYINT      NOT NULL DEFAULT 0,
                last_post_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
                created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uq_slug (slug),
                KEY ix_cat (category_id),
                KEY ix_user (user_id)
            ) $eng";
        } else {
            $tables[] = "CREATE TABLE IF NOT EXISTS topics (
                id           INTEGER PRIMARY KEY AUTOINCREMENT,
                title        TEXT    NOT NULL,
                slug         TEXT    NOT NULL UNIQUE,
                category_id  INTEGER NOT NULL,
                user_id      INTEGER NOT NULL,
                views        INTEGER NOT NULL DEFAULT 0,
                reply_count  INTEGER NOT NULL DEFAULT 0,
                pinned       INTEGER NOT NULL DEFAULT 0,
                closed       INTEGER NOT NULL DEFAULT 0,
                archived     INTEGER NOT NULL DEFAULT 0,
                last_post_at TEXT    NOT NULL DEFAULT (datetime('now')),
                created_at   TEXT    NOT NULL DEFAULT (datetime('now'))
            )";
        }

        /* posts */
        if ($mysql) {
            $tables[] = "CREATE TABLE IF NOT EXISTS posts (
                id          INT AUTO_INCREMENT PRIMARY KEY,
                topic_id    INT      NOT NULL,
                user_id     INT      NOT NULL,
                content     TEXT     NOT NULL,
                post_num    INT      NOT NULL,
                reply_to    INT,
                likes       INT      NOT NULL DEFAULT 0,
                edited      TINYINT  NOT NULL DEFAULT 0,
                edit_reason VARCHAR(200),
                deleted     TINYINT  NOT NULL DEFAULT 0,
                created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                KEY ix_topic (topic_id),
                KEY ix_user  (user_id)
            ) $eng";
        } else {
            $tables[] = "CREATE TABLE IF NOT EXISTS posts (
                id          INTEGER PRIMARY KEY AUTOINCREMENT,
                topic_id    INTEGER NOT NULL,
                user_id     INTEGER NOT NULL,
                content     TEXT    NOT NULL,
                post_num    INTEGER NOT NULL,
                reply_to    INTEGER,
                likes       INTEGER NOT NULL DEFAULT 0,
                edited      INTEGER NOT NULL DEFAULT 0,
                edit_reason TEXT,
                deleted     INTEGER NOT NULL DEFAULT 0,
                created_at  TEXT    NOT NULL DEFAULT (datetime('now')),
                updated_at  TEXT    NOT NULL DEFAULT (datetime('now'))
            )";
        }

        /* likes */
        if ($mysql) {
            $tables[] = "CREATE TABLE IF NOT EXISTS likes (
                post_id INT NOT NULL,
                user_id INT NOT NULL,
                PRIMARY KEY (post_id, user_id)
            ) $eng";
        } else {
            $tables[] = "CREATE TABLE IF NOT EXISTS likes (
                post_id INTEGER NOT NULL,
                user_id INTEGER NOT NULL,
                PRIMARY KEY (post_id, user_id)
            )";
        }

        /* tags + topic_tags */
        if ($mysql) {
            $tables[] = "CREATE TABLE IF NOT EXISTS tags (
                id          INT AUTO_INCREMENT PRIMARY KEY,
                name        VARCHAR(50) NOT NULL,
                topic_count INT         NOT NULL DEFAULT 0,
                UNIQUE KEY uq_name (name)
            ) $eng";
            $tables[] = "CREATE TABLE IF NOT EXISTS topic_tags (
                topic_id INT NOT NULL,
                tag_id   INT NOT NULL,
                PRIMARY KEY (topic_id, tag_id)
            ) $eng";
        } else {
            $tables[] = "CREATE TABLE IF NOT EXISTS tags (
                id          INTEGER PRIMARY KEY AUTOINCREMENT,
                name        TEXT    NOT NULL UNIQUE,
                topic_count INTEGER NOT NULL DEFAULT 0
            )";
            $tables[] = "CREATE TABLE IF NOT EXISTS topic_tags (
                topic_id INTEGER NOT NULL,
                tag_id   INTEGER NOT NULL,
                PRIMARY KEY (topic_id, tag_id)
            )";
        }

        /* notifications */
        if ($mysql) {
            $tables[] = "CREATE TABLE IF NOT EXISTS notifications (
                id         INT AUTO_INCREMENT PRIMARY KEY,
                user_id    INT         NOT NULL,
                type       VARCHAR(50) NOT NULL,
                payload    TEXT        NOT NULL,
                `read`     TINYINT     NOT NULL DEFAULT 0,
                created_at DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
                KEY ix_user (user_id)
            ) $eng";
        } else {
            $tables[] = "CREATE TABLE IF NOT EXISTS notifications (
                id         INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id    INTEGER NOT NULL,
                type       TEXT    NOT NULL,
                payload    TEXT    NOT NULL DEFAULT '{}',
                read       INTEGER NOT NULL DEFAULT 0,
                created_at TEXT    NOT NULL DEFAULT (datetime('now'))
            )";
        }

        /* friends */
        if ($mysql) {
            $tables[] = "CREATE TABLE IF NOT EXISTS friends (
                id         INT AUTO_INCREMENT PRIMARY KEY,
                user_id    INT         NOT NULL,
                friend_id  INT         NOT NULL,
                status     VARCHAR(20) NOT NULL DEFAULT 'pending',
                created_at DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uq_pair (user_id, friend_id),
                KEY ix_uid (user_id),
                KEY ix_fid (friend_id)
            ) $eng";
        } else {
            $tables[] = "CREATE TABLE IF NOT EXISTS friends (
                id         INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id    INTEGER NOT NULL,
                friend_id  INTEGER NOT NULL,
                status     TEXT    NOT NULL DEFAULT 'pending',
                created_at TEXT    NOT NULL DEFAULT (datetime('now')),
                UNIQUE(user_id, friend_id)
            )";
        }

        /* messages */
        if ($mysql) {
            $tables[] = "CREATE TABLE IF NOT EXISTS messages (
                id                  INT AUTO_INCREMENT PRIMARY KEY,
                sender_id           INT          NOT NULL,
                receiver_id         INT          NOT NULL,
                subject             VARCHAR(150) NOT NULL DEFAULT '',
                body                TEXT         NOT NULL,
                is_read             TINYINT      NOT NULL DEFAULT 0,
                deleted_by_sender   TINYINT      NOT NULL DEFAULT 0,
                deleted_by_receiver TINYINT      NOT NULL DEFAULT 0,
                created_at          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
                KEY ix_recv (receiver_id),
                KEY ix_send (sender_id)
            ) $eng";
        } else {
            $tables[] = "CREATE TABLE IF NOT EXISTS messages (
                id                  INTEGER PRIMARY KEY AUTOINCREMENT,
                sender_id           INTEGER NOT NULL,
                receiver_id         INTEGER NOT NULL,
                subject             TEXT    NOT NULL DEFAULT '',
                body                TEXT    NOT NULL,
                is_read             INTEGER NOT NULL DEFAULT 0,
                deleted_by_sender   INTEGER NOT NULL DEFAULT 0,
                deleted_by_receiver INTEGER NOT NULL DEFAULT 0,
                created_at          TEXT    NOT NULL DEFAULT (datetime('now'))
            )";
        }

        /* rate_events */
        if ($mysql) {
            $tables[] = "CREATE TABLE IF NOT EXISTS rate_events (
                id         INT AUTO_INCREMENT PRIMARY KEY,
                user_id    INT         NOT NULL,
                event_type VARCHAR(20) NOT NULL DEFAULT 'post',
                created_at DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
                KEY ix_rate (user_id, event_type, created_at)
            ) $eng";
        } else {
            $tables[] = "CREATE TABLE IF NOT EXISTS rate_events (
                id         INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id    INTEGER NOT NULL,
                event_type TEXT    NOT NULL DEFAULT 'post',
                created_at TEXT    NOT NULL DEFAULT (datetime('now'))
            )";
        }

        /* themes */
        if ($mysql) {
            $tables[] = "CREATE TABLE IF NOT EXISTS themes (
                id         INT AUTO_INCREMENT PRIMARY KEY,
                name       VARCHAR(100) NOT NULL,
                slug       VARCHAR(100) NOT NULL,
                css        LONGTEXT     NOT NULL DEFAULT '',
                is_active  TINYINT      NOT NULL DEFAULT 0,
                created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uq_slug (slug)
            ) $eng";
        } else {
            $tables[] = "CREATE TABLE IF NOT EXISTS themes (
                id         INTEGER PRIMARY KEY AUTOINCREMENT,
                name       TEXT    NOT NULL,
                slug       TEXT    NOT NULL UNIQUE,
                css        TEXT    NOT NULL DEFAULT '',
                is_active  INTEGER NOT NULL DEFAULT 0,
                created_at TEXT    NOT NULL DEFAULT (datetime('now'))
            )";
        }

        $pdo = self::connect();
        foreach ($tables as $sql) {
            $pdo->exec($sql);
        }

        /* SQLite-only indexes */
        if (!$mysql) {
            $pdo->exec("CREATE INDEX IF NOT EXISTS ix_topics_cat  ON topics(category_id)");
            $pdo->exec("CREATE INDEX IF NOT EXISTS ix_posts_topic ON posts(topic_id)");
            $pdo->exec("CREATE INDEX IF NOT EXISTS ix_notif_user  ON notifications(user_id)");
            $pdo->exec("CREATE INDEX IF NOT EXISTS ix_rate_user   ON rate_events(user_id, event_type, created_at)");
            $pdo->exec("CREATE INDEX IF NOT EXISTS ix_friends_u   ON friends(user_id)");
            $pdo->exec("CREATE INDEX IF NOT EXISTS ix_msg_recv    ON messages(receiver_id)");
        }

        /* ── Column migrations (safe for both SQLite + MySQL upgrades) ── */
        // Add columns that were introduced in later versions.
        // Uses IF NOT EXISTS logic compatible with both drivers.
        if ($mysql) {
            // MySQL: check information_schema then ALTER TABLE if column missing
            $dbName = defined('DB_NAME') ? DB_NAME : '';
            $existingCols = array_column(self::rows(
                "SELECT COLUMN_NAME FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'users'",
                [$dbName]
            ), 'COLUMN_NAME');
            $mysqlAdd = [
                'karma'          => "ALTER TABLE users ADD COLUMN karma INT NOT NULL DEFAULT 0",
                'permissions'    => "ALTER TABLE users ADD COLUMN permissions TEXT NOT NULL DEFAULT '{}'",
                'friends_hidden' => "ALTER TABLE users ADD COLUMN friends_hidden TINYINT NOT NULL DEFAULT 0",
                'suspended'      => "ALTER TABLE users ADD COLUMN suspended TINYINT NOT NULL DEFAULT 0",
                'silenced'       => "ALTER TABLE users ADD COLUMN silenced TINYINT NOT NULL DEFAULT 0",
                'topic_count'    => "ALTER TABLE users ADD COLUMN topic_count INT NOT NULL DEFAULT 0",
            ];
            foreach ($mysqlAdd as $col => $sql) {
                if (!in_array($col, $existingCols)) {
                    try { self::run($sql); } catch (\Throwable $e) { /* already exists */ }
                }
            }
        } else {
            // SQLite: use PRAGMA table_info
            $cols = array_column(self::rows("PRAGMA table_info(users)"), 'name');
            $sqliteAdd = [
                'karma'          => "ALTER TABLE users ADD COLUMN karma INTEGER NOT NULL DEFAULT 0",
                'permissions'    => "ALTER TABLE users ADD COLUMN permissions TEXT NOT NULL DEFAULT '{}'",
                'friends_hidden' => "ALTER TABLE users ADD COLUMN friends_hidden INTEGER NOT NULL DEFAULT 0",
                'suspended'      => "ALTER TABLE users ADD COLUMN suspended INTEGER NOT NULL DEFAULT 0",
                'silenced'       => "ALTER TABLE users ADD COLUMN silenced INTEGER NOT NULL DEFAULT 0",
                'topic_count'    => "ALTER TABLE users ADD COLUMN topic_count INTEGER NOT NULL DEFAULT 0",
            ];
            foreach ($sqliteAdd as $col => $sql) {
                if (!in_array($col, $cols)) {
                    try { self::run($sql); } catch (\Throwable $e) { /* already exists */ }
                }
            }
        }

        /* ── Categories: role permission columns ── */
        if ($mysql) {
            $dbName = defined('DB_NAME') ? DB_NAME : '';
            $catCols = array_column(self::rows(
                "SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=? AND TABLE_NAME='categories'",
                [$dbName]
            ), 'COLUMN_NAME');
            foreach (['read_role'=>"VARCHAR(20) NOT NULL DEFAULT 'guest'",'post_role'=>"VARCHAR(20) NOT NULL DEFAULT 'member'",'reply_role'=>"VARCHAR(20) NOT NULL DEFAULT 'member'"] as $col=>$def) {
                if (!in_array($col,$catCols)) { try{self::run("ALTER TABLE categories ADD COLUMN $col $def");}catch(\Throwable $e){} }
            }
        } else {
            $catCols = array_column(self::rows("PRAGMA table_info(categories)"),'name');
            foreach (['read_role'=>"TEXT NOT NULL DEFAULT 'guest'",'post_role'=>"TEXT NOT NULL DEFAULT 'member'",'reply_role'=>"TEXT NOT NULL DEFAULT 'member'"] as $col=>$def) {
                if (!in_array($col,$catCols)) { try{self::run("ALTER TABLE categories ADD COLUMN $col $def");}catch(\Throwable $e){} }
            }
        }

        /* ── Users: add location column ── */
        if ($mysql) {
            $dbName = defined('DB_NAME') ? DB_NAME : '';
            $uCols = array_column(self::rows(
                "SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=? AND TABLE_NAME='users'",
                [$dbName]
            ), 'COLUMN_NAME');
            if (!in_array('location', $uCols)) {
                try { self::run("ALTER TABLE users ADD COLUMN location VARCHAR(100) NOT NULL DEFAULT ''"); }
                catch (\Throwable $e) {}
            }
        } else {
            $uCols2 = array_column(self::rows("PRAGMA table_info(users)"), 'name');
            if (!in_array('location', $uCols2)) {
                try { self::run("ALTER TABLE users ADD COLUMN location TEXT NOT NULL DEFAULT ''"); }
                catch (\Throwable $e) {}
            }
        }

        /* ── Messages: add conversation_id for chat threading ── */
        // conversation_id = LEAST(sender_id, receiver_id) * 1000000 + GREATEST(...)
        // We store it as a VARCHAR key for easy lookup
        if ($mysql) {
            $dbName = defined('DB_NAME') ? DB_NAME : '';
            $msgCols = array_column(self::rows(
                "SELECT COLUMN_NAME FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'messages'", [$dbName]
            ), 'COLUMN_NAME');
            if (!in_array('conversation_id', $msgCols)) {
                try {
                    self::run("ALTER TABLE messages ADD COLUMN conversation_id VARCHAR(30) NOT NULL DEFAULT ''");
                    self::run("ALTER TABLE messages ADD INDEX ix_conv (conversation_id)");
                    // Back-fill existing rows
                    self::run("UPDATE messages SET conversation_id = CONCAT(LEAST(sender_id,receiver_id),'-',GREATEST(sender_id,receiver_id))");
                } catch (\Throwable $e) { /* ignore */ }
            }
        } else {
            $msgCols = array_column(self::rows("PRAGMA table_info(messages)"), 'name');
            if (!in_array('conversation_id', $msgCols)) {
                try {
                    self::run("ALTER TABLE messages ADD COLUMN conversation_id TEXT NOT NULL DEFAULT ''");
                    self::run("UPDATE messages SET conversation_id = MIN(sender_id,receiver_id)||'-'||MAX(sender_id,receiver_id)");
                } catch (\Throwable $e) { /* ignore */ }
            }
        }
    }


    /* ── Helpers ─────────────────────────────────────────────── */
    /** Cross-driver NOW() */
    public static function now(): string {
        return self::isMysql() ? 'NOW()' : "datetime('now')";
    }

    /** Cross-driver datetime comparison for rate limiting */
    public static function sinceSeconds(int $seconds): string {
        if (self::isMysql()) {
            return "DATE_SUB(NOW(), INTERVAL {$seconds} SECOND)";
        }
        return "datetime('now','-{$seconds} seconds')";
    }
}
