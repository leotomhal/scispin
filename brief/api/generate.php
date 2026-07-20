<?php
/**
 * brief/api/generate.php – Endpunkt für EINE Phase der Kurzmeldung (5 Bits Outline).
 * POST { text: string, phase: 1|2, titel?, journal?, doi?, bits?: object }
 *   phase 1 → Gerüst: Bits 1–4 (oder Ablehnung, wenn keine Studie)
 *   phase 2 → Aufmacher: Lede + Headline + Kurzmeldung (braucht `bits` aus Phase 1)
 *             löst danach AUTOMATISCH einen Studien-Check-Lauf auf dem
 *             Original-Material aus (Selbstcheck, kein Extra-Request nötig).
 *
 * Antwort:
 *   Phase 1 Erfolg:   { ok:true, cached:bool, ist_studie:true, frage, methoden[],
 *                        methoden_recap, engpass, fortschritt, evidenz{...},
 *                        abstract_hype_warnung }
 *   Phase 1 Ablehnung:{ ok:true, cached:false, ist_studie:false, ablehnungsgrund }
 *   Phase 2:          { ok:true, cached:bool, lede, headline, kurzmeldung,
 *                        regel_hinweise[], studien_check{ampel,core_statement,...}|null }
 *   Fehler:           HTTP 4xx/5xx + { ok:false, error } (+ wo/typ im Debug)
 *
 * Aufbau bewusst parallel zu spin/api/generate.php gehalten.
 */

declare(strict_types=1);
ini_set('max_execution_time', 300);
set_time_limit(300);

if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

ini_set('display_errors', '0');
error_reporting(E_ALL);
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

$DEBUG = false;

function fail(int $status, string $msg, ?Throwable $e = null): void {
    global $DEBUG;
    http_response_code($status);
    $out = ['ok' => false, 'error' => $msg];
    if ($DEBUG && $e) {
        $out['wo']  = $e->getFile() . ':' . $e->getLine();
        $out['typ'] = get_class($e);
    }
    echo json_encode($out, JSON_UNESCAPED_UNICODE);
    exit;
}

register_shutdown_function(function () {
    $err = error_get_last();
    if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        global $DEBUG;
        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
        }
        $out = ['ok' => false, 'error' => 'Interner Fehler.'];
        if ($DEBUG) $out['fatal'] = $err['message'] . ' @ ' . $err['file'] . ':' . $err['line'];
        echo json_encode($out, JSON_UNESCAPED_UNICODE);
    }
});

class RateLimitException extends RuntimeException {}
class ApiException extends RuntimeException {}

try {
    $config = require __DIR__ . '/../../lib/config.php';
    require_once __DIR__ . '/../../lib/db.php';
    require_once __DIR__ . '/../../lib/llm.php';
    require __DIR__ . '/prompt.php';
    // Für die automatische Studien-Check-Stufe nach Phase 2 (Ampel auf dem
    // Original-Material) – teilt sich den Low-Level-Call aus lib/llm.php.
    require_once __DIR__ . '/../../check/lib/analyze_llm.php';
} catch (Throwable $e) {
    fail(500, 'Konfiguration konnte nicht geladen werden.', $e);
}
$DEBUG = !empty($config['debug']);

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    fail(405, 'Nur POST erlaubt.');
}

$in = json_decode(file_get_contents('php://input'), true);
if (!is_array($in) || !isset($in['text'], $in['phase'])) {
    fail(400, 'Erwartet: { text, phase }');
}
$text  = trim((string)$in['text']);
$phase = (int)$in['phase'];
$meta  = [
    'titel'   => trim((string)($in['titel']   ?? '')),
    'journal' => trim((string)($in['journal'] ?? '')),
    'doi'     => trim((string)($in['doi']     ?? '')),
];
$bits  = is_array($in['bits'] ?? null) ? $in['bits'] : [];

if ($text === '') {
    fail(400, 'Bitte einen Text eingeben.');
}
$maxChars = (int)($config['max_input_chars'] ?? 4000);
if (mb_strlen($text) > $maxChars) {
    fail(413, "Text zu lang (max. $maxChars Zeichen).");
}
if ($phase !== 1 && $phase !== 2) {
    fail(400, 'phase muss 1 oder 2 sein.');
}
if ($phase === 2 && (($bits['frage'] ?? '') === '')) {
    fail(400, 'Phase 2 braucht die Bits aus Phase 1.');
}

// ---------- DEMO-MODUS ----------
if (!empty($config['demo_mode'])) {
    try {
        $demo = require __DIR__ . '/demo_data.php';
    } catch (Throwable $e) {
        fail(500, 'Demo-Daten konnten nicht geladen werden.', $e);
    }
    $key = 'phase' . $phase;
    if (!isset($demo[$key])) {
        fail(404, 'Keine Demo-Daten für diese Phase.');
    }
    usleep(400000);
    echo json_encode(['ok' => true, 'cached' => false, 'demo' => true] + $demo[$key], JSON_UNESCAPED_UNICODE);
    exit;
}

// ---------- ECHTBETRIEB ----------
// Cache-Schlüssel: Hash des normalisierten Ausgangstexts (beide Phasen leiten
// sich aus demselben Material ab). Metadaten fließen mit ein.
$normalized = preg_replace('/\s+/u', ' ', $text . '|' . $meta['titel'] . '|' . $meta['journal'] . '|' . $meta['doi']);
$hash = hash('sha256', $normalized);

try {
    $pdo = sc_db($config);

    // Cache-Treffer für genau diese Phase?
    $sel = $pdo->prepare('SELECT payload FROM brief_cache WHERE input_hash = ? AND phase = ?');
    $sel->execute([$hash, $phase]);
    if ($row = $sel->fetch()) {
        $payload = json_decode($row['payload'], true);
        if (is_array($payload)) {
            echo json_encode(['ok' => true, 'cached' => true] + $payload, JSON_UNESCAPED_UNICODE);
            exit;
        }
    }

    // Limits nur bei echtem API-Bedarf: pro Besucher/Stunde + globale Tagesbremse.
    $iph = sc_ip_hash($config);
    if (!sc_rate_ok($pdo, 'brief:' . $iph, (int)($config['brief_rate_per_hour'] ?? 40))) {
        throw new RateLimitException('Zu viele Anfragen. Bitte in einer Stunde erneut versuchen.');
    }
    if (!sc_daily_cap_ok($pdo, (int)($config['daily_llm_cap'] ?? 500))) {
        throw new RateLimitException('Das Tageslimit dieser Demo ist erreicht. Bitte morgen erneut versuchen.');
    }

    // API-Call für diese Phase (mit einem Retry bei kaputtem JSON)
    $result = call_anthropic_phase($config, $text, $phase, $meta, $bits);

    // Ablehnung (nur Phase 1): nicht cachen
    if (($result['ist_studie'] ?? null) === false) {
        echo json_encode(['ok' => true, 'cached' => false] + $result, JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Automatische Endstufe (Punkt 3): jede fertige Kurzmeldung läuft ungefragt
    // durch den Studien-Check auf dem ORIGINAL-Material – nicht auf der eigenen
    // Kurzmeldung, die bewusst jargonfrei ist und daher ein schlechter Check-Input
    // wäre. Schlägt der Zusatz-Call fehl oder ist das Tageskontingent aufgebraucht,
    // bleibt die Kurzmeldung trotzdem gültig; "studien_check" wird dann null.
    if ($phase === 2) {
        $result['studien_check'] = brief_run_studien_check($config, $pdo, $text, $meta);
    }

    // Cachen
    $ins = $pdo->prepare('INSERT INTO brief_cache (input_hash, phase, payload) VALUES (?, ?, ?)
                          ON DUPLICATE KEY UPDATE payload = VALUES(payload)');
    $ins->execute([$hash, $phase, json_encode($result, JSON_UNESCAPED_UNICODE)]);

    echo json_encode(['ok' => true, 'cached' => false] + $result, JSON_UNESCAPED_UNICODE);

} catch (RateLimitException $e) {
    fail(429, $e->getMessage());
} catch (ApiException $e) {
    fail(502, $DEBUG ? $e->getMessage() : 'Die Sprach-API ist gerade nicht erreichbar. Bitte später erneut versuchen.', $e);
} catch (Throwable $e) {
    fail(500, 'Es ist ein Serverfehler aufgetreten.', $e);
}

// ======================================================================

/** Ein API-Call für genau eine Phase; ein Retry bei nicht-parsebarem JSON. */
function call_anthropic_phase(array $config, string $text, int $phase, array $meta, array $bits): array {
    $key = (string)($config['anthropic_api_key'] ?? '');
    if ($key === '' || str_starts_with($key, 'sk-ant-DEIN') || str_starts_with($key, 'sk-ant-HIER')) {
        throw new ApiException('Kein gültiger API-Key in lib/config.php hinterlegt.');
    }

    $payload = $phase === 1
        ? brief_payload_phase1($text, $meta)
        : brief_payload_phase2($text, $bits, $meta);

    $messages  = [['role' => 'user', 'content' => $payload]];
    $maxTokens = (int)($config['brief_max_tokens'] ?? 2500);

    for ($attempt = 0; $attempt < 2; $attempt++) {
        $textOut = sc_anthropic_text($config, $messages, brief_system_prompt(), $maxTokens);
        if ($textOut === null) {
            throw new ApiException('API-Aufruf fehlgeschlagen (kein Text / HTTP-Fehler).');
        }

        // Standard-Extraktion (balancierte Klammern) aus dem geteilten Kern.
        $json = sc_extract_json(trim($textOut));
        $parsed = json_decode($json, true);

        if (is_array($parsed)) {
            return brief_validate($parsed, $phase);
        }

        // Retry: Korrekturhinweis anhängen (Konversation endet mit User-Nachricht).
        $messages = [
            ['role' => 'user', 'content' => $payload],
            ['role' => 'assistant', 'content' => $textOut],
            ['role' => 'user', 'content' => 'Deine Antwort war kein gültiges JSON nach dem '
                . 'vorgegebenen Schema. Antworte erneut, ausschließlich mit dem JSON-Objekt, '
                . 'ohne einleitenden Text und ohne Code-Fences.'],
        ];
    }

    throw new ApiException('Modell lieferte kein gültiges JSON (auch nach Retry).');
}

/**
 * Automatische dritte Stufe: das Original-Material (Abstract + Metadaten) durch
 * dieselbe Ampel-Bewertung schicken, die auch der Studien-Check nutzt
 * (check/lib/analyze_llm.php – geteilte Funktionen sc_llm_analyze/sc_system_prompt).
 * Bewusst NICHT die generierte Kurzmeldung selbst prüfen: die ist per Regel 7
 * absichtlich jargonfrei und liefert der Ampel-Bewertung keine verlässlichen
 * Methodik-Signale (Studientyp, Kontrollgruppe etc.) – das Original-Abstract schon.
 * Gibt bei Fehler/Kontingent-Ende still null zurück, statt die Kurzmeldung zu blockieren.
 */
function brief_run_studien_check(array $config, PDO $pdo, string $text, array $meta): ?array {
    if (!sc_daily_cap_ok($pdo, (int)($config['daily_llm_cap'] ?? 500))) {
        return null;
    }
    $ex = [
        'title'      => $meta['titel']   ?: '',
        'journal'    => $meta['journal'] ?: '',
        'abstract'   => $text,
        'datenbasis' => 'abstract',
    ];
    $llm = sc_llm_analyze($config, $ex);
    if (!$llm['ok'] || !is_array($llm['data'])) return null;

    $d = $llm['data'];
    return [
        'ampel'                    => $d['ampel'] ?? null,
        'core_statement'           => $d['core_statement'] ?? null,
        'methode_aussage_abgleich' => $d['methode_aussage_abgleich'] ?? null,
        'einschraenkungen'         => $d['einschraenkungen'] ?? [],
        'study_type'               => $d['methodik_klartext']['study_type'] ?? null,
    ];
}
