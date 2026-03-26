<?php
/**
 * Nexus Forum — Addon System
 *
 * Two hook patterns:
 *
 *  AddonManager::fire($hook, $data)
 *    FILTER pattern — each callback receives the return value of the previous.
 *    Use for transforming a single value (e.g. render_post_content: HTML string in/out).
 *
 *  AddonManager::collect($hook, $context)
 *    COLLECTOR pattern — every callback receives the same $context array.
 *    Non-empty string returns are accumulated and joined.
 *    Use for output hooks where multiple addons each contribute HTML
 *    (e.g. render_post_footer, render_topic_header, before_page_head).
 */
if (!defined('NEXUS')) exit('Forbidden');

class AddonManager {
    private static array  $hooks   = [];
    private static array  $loaded  = [];
    private static ?array $active  = null;

    /* ── Boot all active addons ─────────────────────────────── */
    public static function boot(): void {
        foreach (self::activeList() as $slug) {
            $main = ROOT . '/addons/' . $slug . '/main.php';
            if (file_exists($main) && !isset(self::$loaded[$slug])) {
                try {
                    require_once $main;
                    self::$loaded[$slug] = true;
                } catch (Throwable $e) {
                    error_log("Addon [$slug] boot error: " . $e->getMessage());
                }
            }
        }
    }

    /* ── Register a hook callback ────────────────────────────── */
    public static function on(string $hook, callable $cb, int $priority = 10): void {
        self::$hooks[$hook][$priority][] = $cb;
    }

    /* ── FILTER: chain callbacks, each receives previous return ─ */
    public static function fire(string $hook, mixed $data = null): mixed {
        if (empty(self::$hooks[$hook])) return $data;
        $buckets = self::$hooks[$hook];
        ksort($buckets);
        foreach ($buckets as $callbacks) {
            foreach ($callbacks as $cb) {
                try {
                    $result = $cb($data);
                    if ($result !== null) $data = $result;
                } catch (Throwable $e) {
                    error_log("Addon hook [$hook] filter error: " . $e->getMessage());
                }
            }
        }
        return $data;
    }

    /* ── COLLECTOR: all callbacks receive same $context ──────── */
    /* Each callback should return '' or an HTML string to append. */
    public static function collect(string $hook, mixed $context = null): string {
        if (empty(self::$hooks[$hook])) return '';
        $buckets = self::$hooks[$hook];
        ksort($buckets);
        $output = '';
        foreach ($buckets as $callbacks) {
            foreach ($callbacks as $cb) {
                try {
                    $result = $cb($context);
                    if (is_string($result) && $result !== '') {
                        $output .= $result;
                    }
                } catch (Throwable $e) {
                    error_log("Addon hook [$hook] collect error: " . $e->getMessage());
                }
            }
        }
        return $output;
    }

    public static function hasHook(string $hook): bool {
        return !empty(self::$hooks[$hook]);
    }

    /* ── Active addon list ──────────────────────────────────── */
    public static function activeList(): array {
        if (self::$active !== null) return self::$active;
        try {
            $val = DB::val("SELECT value FROM settings WHERE `key`='active_addons'");
            self::$active = $val ? (json_decode($val, true) ?: []) : [];
        } catch (Throwable $e) {
            self::$active = [];
        }
        return self::$active;
    }

    /* ── Scan all installed addons ──────────────────────────── */
    public static function all(): array {
        $dir    = ROOT . '/addons';
        $active = self::activeList();
        $addons = [];
        if (!is_dir($dir)) return [];
        foreach (scandir($dir) as $slug) {
            if ($slug[0] === '.') continue;
            $manifest = $dir . '/' . $slug . '/nexus-addon.json';
            if (!file_exists($manifest)) continue;
            $info = json_decode(file_get_contents($manifest), true);
            if (!$info || empty($info['name'])) continue;
            $addons[$slug] = array_merge([
                'slug' => $slug, 'name' => $slug, 'description' => '',
                'version' => '1.0.0', 'author' => 'Unknown', 'url' => '',
                'hooks' => [], 'requires' => [],
            ], $info, [
                'slug'   => $slug,
                'active' => in_array($slug, $active),
            ]);
        }
        return $addons;
    }

    /* ── Activate / deactivate ──────────────────────────────── */
    public static function activate(string $slug): bool {
        $active = self::activeList();
        if (in_array($slug, $active)) return true;
        $install = ROOT . '/addons/' . $slug . '/install.php';
        if (file_exists($install)) {
            try { require $install; } catch (Throwable $e) {
                error_log("Addon [$slug] install error: " . $e->getMessage());
                return false;
            }
        }
        $active[] = $slug;
        self::saveActive($active);
        self::$active = $active;
        return true;
    }

    public static function deactivate(string $slug): bool {
        $active = self::activeList();
        if (!in_array($slug, $active)) return true;
        $uninstall = ROOT . '/addons/' . $slug . '/uninstall.php';
        if (file_exists($uninstall)) {
            try { require $uninstall; } catch (Throwable $e) {
                error_log("Addon [$slug] uninstall error: " . $e->getMessage());
            }
        }
        $active = array_values(array_filter($active, fn($s) => $s !== $slug));
        self::saveActive($active);
        self::$active = $active;
        return true;
    }

    private static function saveActive(array $active): void {
        DB::upsert('settings', 'key', 'value', 'active_addons', json_encode(array_values($active)));
    }
}

/* ── Global convenience helpers ─────────────────────────────── */

/** FILTER hook — transforms a value through all callbacks */
function addon_hook(string $hook, mixed $data = null): mixed {
    return AddonManager::fire($hook, $data);
}

/** COLLECTOR hook — every callback gets same $context, returns accumulated HTML */
function addon_collect(string $hook, mixed $context = null): string {
    return AddonManager::collect($hook, $context);
}

/** Register a callback on a hook */
function addon_on(string $hook, callable $cb, int $priority = 10): void {
    AddonManager::on($hook, $cb, $priority);
}
