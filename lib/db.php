<?php
/**
 * Geteilte DB-Schicht: Verbindung, anonymisiertes Rate-Limit, Tagesbremse.
 * MySQL statt Redis (Shared-Hosting-tauglich). Genutzt von check/ und spin/.
 */

function sc_db(array $cfg): PDO {
    static $pdo = null;
    if ($pdo instanceof PDO) return $pdo;
    $d = $cfg['db'];
    $dsn = "mysql:host={$d['host']};dbname={$d['name']};charset={$d['charset']}";
    $pdo = new PDO($dsn, $d['user'], $d['pass'], [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
    return $pdo;
}

/**
 * Anonymisierte Besucher-Kennung fürs Rate-Limit.
 * SHA-256(IP + täglich rotierendes Salt) -> keine personenbezogene IP gespeichert.
 */
function sc_ip_hash(array $cfg): string {
    $ip   = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $salt = ($cfg['ip_salt_base'] ?? 'x') . '|' . date('Y-m-d');
    return hash('sha256', $ip . '|' . $salt);
}

/**
 * Stundenbasiertes Rate-Limit. Gibt true zurück, wenn ERLAUBT.
 * Grober als ein Token-Bucket, aber auf Shared Hosting robust.
 * $bucket z. B. "check-llm:<iphash>" oder "spin:<iphash>".
 */
function sc_rate_ok(PDO $db, string $bucket, int $limit): bool {
    $windowStart = (int)(floor(time() / 3600) * 3600);
    $db->prepare(
        'INSERT INTO rate_limit (bucket, window_start, cnt) VALUES (?, ?, 1)
         ON DUPLICATE KEY UPDATE cnt = cnt + 1'
    )->execute([$bucket, $windowStart]);
    $st = $db->prepare('SELECT cnt FROM rate_limit WHERE bucket = ? AND window_start = ?');
    $st->execute([$bucket, $windowStart]);
    $cnt = (int)$st->fetchColumn();
    return $cnt <= $limit;
}

/** Globale Tages-Notbremse über ALLE LLM-Calls beider Modi. true = erlaubt. */
function sc_daily_cap_ok(PDO $db, int $cap): bool {
    $day = date('Y-m-d');
    $db->prepare(
        'INSERT INTO llm_daily (day, cnt) VALUES (?, 1)
         ON DUPLICATE KEY UPDATE cnt = cnt + 1'
    )->execute([$day]);
    $st = $db->prepare('SELECT cnt FROM llm_daily WHERE day = ?');
    $st->execute([$day]);
    return (int)$st->fetchColumn() <= $cap;
}

/** Aufräumen alter Rate-Limit-Zeilen (per Cron aufrufbar, optional). */
function sc_cleanup(PDO $db): void {
    $db->prepare('DELETE FROM rate_limit WHERE window_start < ?')->execute([time() - 7200]);
    $db->prepare('DELETE FROM llm_daily WHERE day < ?')->execute([date('Y-m-d', time() - 7 * 86400)]);
}
