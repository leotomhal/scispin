<?php
/**
 * Studien-Check: Ergebnis-Cache (DOI) + durchsuchbares Archiv.
 * Nutzt die geteilte Verbindung aus lib/db.php.
 */

/** Cache-Treffer holen (NULL bei Miss oder Ablauf). */
function sc_cache_get(PDO $db, string $doi): ?array {
    $st = $db->prepare('SELECT result, expires_at FROM cache WHERE doi = ?');
    $st->execute([$doi]);
    $row = $st->fetch();
    if (!$row) return null;
    if ($row['expires_at'] !== null && (int)$row['expires_at'] < time()) {
        $db->prepare('DELETE FROM cache WHERE doi = ?')->execute([$doi]);
        return null;
    }
    $data = json_decode($row['result'], true);
    return is_array($data) ? $data : null;
}

/** Ergebnis cachen. Preprints mit kurzer TTL. */
function sc_cache_set(PDO $db, array $cfg, string $doi, array $result, bool $isPreprint): void {
    $now = time();
    $ttl = $isPreprint ? (int)$cfg['ttl_preprint'] : (int)$cfg['ttl_peerreview'];
    $expires = $ttl > 0 ? $now + $ttl : null;
    $st = $db->prepare(
        'REPLACE INTO cache (doi, result, is_preprint, created_at, expires_at)
         VALUES (?, ?, ?, ?, ?)'
    );
    $st->execute([$doi, json_encode($result, JSON_UNESCAPED_UNICODE), $isPreprint ? 1 : 0, $now, $expires]);
}

/**
 * Archiv (additiv). Speichert jede analysierte Studie dauerhaft mit durchsuchbaren
 * Spalten. Bewusst gekapselt: ein Fehler hier darf die Analyse-Antwort NIE brechen.
 */
function sc_archive_save(PDO $db, string $cacheKey, array $result): void {
    try {
        $a   = $result['analysis'] ?? [];
        $b   = $result['badge'] ?? [];
        $m   = $result['meta'] ?? [];
        $now = time();

        $st = $db->prepare(
            'INSERT INTO archive
               (cache_key, doi, title, journal, ampel, badge, is_preprint,
                datenbasis, core_statement, source, result, created_at, updated_at)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)
             ON DUPLICATE KEY UPDATE
               title=VALUES(title), journal=VALUES(journal), ampel=VALUES(ampel),
               badge=VALUES(badge), is_preprint=VALUES(is_preprint),
               datenbasis=VALUES(datenbasis), core_statement=VALUES(core_statement),
               source=VALUES(source), result=VALUES(result), updated_at=VALUES(updated_at)'
        );
        $st->execute([
            $cacheKey,
            $m['doi'] ?? null,
            $m['title'] ?? null,
            $m['journal'] ?? null,
            $a['ampel']['color'] ?? 'grau',
            $b['code'] ?? 'unklar',
            (($b['code'] ?? '') === 'preprint') ? 1 : 0,
            $result['datenbasis'] ?? 'abstract',
            $a['core_statement'] ?? null,
            $m['source'] ?? null,
            json_encode($result, JSON_UNESCAPED_UNICODE),
            $now,
            $now,
        ]);
    } catch (Throwable $e) {
        error_log('archive_save: ' . $e->getMessage());
    }
}
