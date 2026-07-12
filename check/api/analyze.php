<?php
/**
 * check/api/analyze.php – Orchestrierung des Studien-Checks.
 * Erwartet POST mit JSON { "url": "..." } (optional { "abstract": "..." }).
 * Antwortet immer mit JSON.
 */

declare(strict_types=1);
@set_time_limit(120);
header('Content-Type: application/json; charset=utf-8');

$cfg = require __DIR__ . '/../../lib/config.php';
require_once __DIR__ . '/../../lib/db.php';
require_once __DIR__ . '/../lib/extract.php';
require_once __DIR__ . '/../lib/badge.php';
require_once __DIR__ . '/../lib/analyze_llm.php';
require_once __DIR__ . '/../lib/store.php';

function sc_fail(string $code, string $msg, int $http = 200, bool $allowManual = false): void {
    http_response_code($http);
    $out = ['ok' => false, 'error' => $code, 'message' => $msg];
    if ($allowManual) $out['allow_manual'] = true;
    echo json_encode($out, JSON_UNESCAPED_UNICODE);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    sc_fail('method', 'Nur POST erlaubt.', 405);
}

// Eingabe lesen (JSON oder Form)
$input = json_decode(file_get_contents('php://input'), true);
$url = trim((string)($input['url'] ?? $_POST['url'] ?? ''));
$manualAbstract = trim((string)($input['abstract'] ?? $_POST['abstract'] ?? ''));

// Eingabe-Hardening
if ($url === '' || strlen($url) > $cfg['max_link_len']) {
    sc_fail('input', 'Bitte einen gültigen Link einfügen.');
}
$scheme = parse_url($url, PHP_URL_SCHEME);
if ($scheme !== 'https') {
    sc_fail('input', 'Nur https-Links werden akzeptiert.');
}

try {
    $db = sc_db($cfg);
} catch (Throwable $e) {
    sc_fail('server', 'Datenbank nicht erreichbar.', 500);
}

$iph = sc_ip_hash($cfg);

// Rate-Limit: günstige Hits zuerst
if (!sc_rate_ok($db, 'check-hit:' . $iph, (int)$cfg['rate_hits_per_hour'])) {
    sc_fail('rate_limit', 'Zu viele Anfragen. Bitte später erneut versuchen.', 429);
}

// Extraktion
$ex = sc_extract($url, $cfg['contact_mailto'], (int)$cfg['http_timeout']);

// Manuell eingefügter Abstract rettet no_abstract / no_doi
if ($manualAbstract !== '' && in_array($ex['error'], ['no_abstract', 'no_doi'], true)) {
    $ex['abstract']   = mb_substr($manualAbstract, 0, (int)$cfg['max_abstract_chars']);
    $ex['datenbasis'] = 'abstract';
    $ex['error']      = null;
    $ex['manual']     = true;
}

if ($ex['error'] === 'no_doi') {
    sc_fail('no_doi', 'Diese Studie konnte nicht automatisch ausgelesen werden.', 200, true);
}
if ($ex['error'] === 'nonresearch') {
    $typ = $ex['article_type'] ? ('„' . $ex['article_type'] . '“') : 'ein redaktioneller Beitrag';
    echo json_encode([
        'ok'             => true,
        'not_applicable' => true,
        'reason'         => 'nonresearch',
        'article_type'   => $ex['article_type'],
        'meta'           => [
            'title'   => $ex['title'],
            'journal' => $ex['journal'],
            'doi'     => $ex['doi'],
            'source'  => $url,
        ],
        'message' => 'Dieser Beitrag ist ' . $typ . ' – also kein Forschungsartikel mit '
                   . 'eigenen empirischen Ergebnissen. Ein Belastbarkeits-Check der '
                   . 'Aussagenreichweite ist hier nicht sinnvoll, weil es keine Studienergebnisse '
                   . 'gibt, deren Tragweite man prüfen könnte.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}
if ($ex['error'] === 'no_abstract') {
    sc_fail('no_abstract', 'Es ließ sich kein Abstract automatisch auslesen – das passiert oft bei '
        . 'sehr neuen Artikeln oder Verlagen, die den Abruf blockieren.', 200, true);
}

// Badge (deterministisch)
$badge = sc_badge($ex);

// Cache-Key: DOI, sonst Hash der URL (verhindert NULL-Key)
$cacheKey = $ex['doi'] ?: ('url:' . sha1($url));

// Cache-Lookup
$cached = sc_cache_get($db, $cacheKey);
if ($cached !== null) {
    // auch bei Cache-Treffer ins Archiv (Upsert, idempotent)
    sc_archive_save($db, $cacheKey, $cached);
    echo json_encode($cached, JSON_UNESCAPED_UNICODE);
    exit;
}

// LLM-Rate-Limit + Tages-Notbremse
if (!sc_rate_ok($db, 'check-llm:' . $iph, (int)$cfg['rate_per_ip_per_hour'])) {
    sc_fail('rate_limit', 'Stündliches Analyse-Limit erreicht. Bitte später erneut versuchen.', 429);
}
if (!sc_daily_cap_ok($db, (int)$cfg['daily_llm_cap'])) {
    sc_fail('capacity', 'Das heutige Analyse-Kontingent ist aufgebraucht. Bitte morgen erneut versuchen.', 503);
}

// LLM-Analyse
$llm = sc_llm_analyze($cfg, $ex);
if (!$llm['ok']) {
    sc_fail('analysis', 'Die Analyse ist fehlgeschlagen. Bitte später erneut versuchen.');
}

// Zusammenführen
$result = [
    'ok'         => true,
    'analysis'   => $llm['data'],
    'badge'      => $badge,
    'datenbasis' => $ex['datenbasis'],
    'manual'     => !empty($ex['manual']),
    'meta'       => [
        'title'   => $ex['title'],
        'journal' => $ex['journal'],
        'doi'     => $ex['doi'],
        'source'  => $url,
    ],
];

sc_cache_set($db, $cfg, $cacheKey, $result, (bool)$ex['is_preprint']);
sc_archive_save($db, $cacheKey, $result);

echo json_encode($result, JSON_UNESCAPED_UNICODE);
