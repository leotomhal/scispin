<?php
/**
 * spin/api/generate.php – Endpunkt für EINE Stufe des SciSpin-O-Mat.
 * POST { text: string, stufe: int(-3..3) }
 *
 * Antwort:
 *   Erfolg:    { ok:true, cached:bool, ist_studie:true, stufe:int, text, kommentar, aenderungen[] }
 *   Ablehnung: { ok:true, cached:false, ist_studie:false, ablehnungsgrund } (nur bei stufe 0)
 *   Fehler:    HTTP 4xx/5xx + { ok:false, error } (+ wo/typ im Debug)
 */

declare(strict_types=1);
ini_set('max_execution_time', 300);
set_time_limit(300);

// Session sofort schließen, damit parallele Requests sich nicht blockieren.
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

// Fatale Fehler trotzdem als JSON ausliefern (nie HTML).
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
} catch (Throwable $e) {
    fail(500, 'Konfiguration konnte nicht geladen werden.', $e);
}
$DEBUG = !empty($config['debug']);

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    fail(405, 'Nur POST erlaubt.');
}

$in = json_decode(file_get_contents('php://input'), true);
if (!is_array($in) || !isset($in['text'], $in['stufe'])) {
    fail(400, 'Erwartet: { text, stufe }');
}
$text  = trim((string)$in['text']);
$stufe = (int)$in['stufe'];

if ($text === '') {
    fail(400, 'Bitte einen Text eingeben.');
}
$maxChars = (int)($config['max_input_chars'] ?? 4000);
if (mb_strlen($text) > $maxChars) {
    fail(413, "Text zu lang (max. $maxChars Zeichen).");
}
if ($stufe < -3 || $stufe > 3) {
    fail(400, 'Stufe muss zwischen -3 und 3 liegen.');
}

// ---------- DEMO-MODUS ----------
if (!empty($config['demo_mode'])) {
    try {
        $demo = require __DIR__ . '/demo_data.php';
    } catch (Throwable $e) {
        fail(500, 'Demo-Daten konnten nicht geladen werden.', $e);
    }
    $key = (string)$stufe;
    if (!isset($demo['stufen'][$key])) {
        fail(404, 'Keine Demo-Daten für diese Stufe.');
    }
    usleep(250000);
    echo json_encode(['ok' => true, 'cached' => false, 'demo' => true, 'ist_studie' => true]
        + $demo['stufen'][$key], JSON_UNESCAPED_UNICODE);
    exit;
}

// ---------- ECHTBETRIEB ----------
$normalized = preg_replace('/\s+/u', ' ', $text);
$hash = hash('sha256', $normalized);

try {
    $pdo = sc_db($config);

    // Cache-Treffer für genau diese Stufe?
    $sel = $pdo->prepare('SELECT payload FROM spin_cache WHERE input_hash = ? AND stufe = ?');
    $sel->execute([$hash, $stufe]);
    if ($row = $sel->fetch()) {
        $payload = json_decode($row['payload'], true);
        if (is_array($payload)) {
            echo json_encode(['ok' => true, 'cached' => true] + $payload, JSON_UNESCAPED_UNICODE);
            exit;
        }
    }

    // Limits nur bei echtem API-Bedarf: pro Besucher/Stunde + globale Tagesbremse.
    $iph = sc_ip_hash($config);
    if (!sc_rate_ok($pdo, 'spin:' . $iph, (int)($config['rate_per_hour'] ?? 84))) {
        throw new RateLimitException('Zu viele Anfragen. Bitte in einer Stunde erneut versuchen.');
    }
    if (!sc_daily_cap_ok($pdo, (int)($config['daily_llm_cap'] ?? 500))) {
        throw new RateLimitException('Das Tageslimit dieser Demo ist erreicht. Bitte morgen erneut versuchen.');
    }

    // API-Call für diese eine Stufe (mit einem Retry bei kaputtem JSON)
    $result = call_anthropic_stufe($config, $text, $stufe);

    // Ablehnung (nur Stufe 0): nicht cachen
    if (($result['ist_studie'] ?? null) === false) {
        echo json_encode(['ok' => true, 'cached' => false] + $result, JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Cachen
    $ins = $pdo->prepare('INSERT INTO spin_cache (input_hash, stufe, payload) VALUES (?, ?, ?)
                          ON DUPLICATE KEY UPDATE payload = VALUES(payload)');
    $ins->execute([$hash, $stufe, json_encode($result, JSON_UNESCAPED_UNICODE)]);

    echo json_encode(['ok' => true, 'cached' => false] + $result, JSON_UNESCAPED_UNICODE);

} catch (RateLimitException $e) {
    fail(429, $e->getMessage());
} catch (ApiException $e) {
    fail(502, $DEBUG ? $e->getMessage() : 'Die Sprach-API ist gerade nicht erreichbar. Bitte später erneut versuchen.', $e);
} catch (Throwable $e) {
    fail(500, 'Es ist ein Serverfehler aufgetreten.', $e);
}

// ======================================================================

/** Ein API-Call für genau eine Stufe; ein Retry bei nicht-parsebarem JSON. */
function call_anthropic_stufe(array $config, string $text, int $stufe): array {
    $key = (string)($config['anthropic_api_key'] ?? '');
    if ($key === '' || str_starts_with($key, 'sk-ant-DEIN') || str_starts_with($key, 'sk-ant-HIER')) {
        throw new ApiException('Kein gültiger API-Key in lib/config.php hinterlegt.');
    }

    $messages = [
        ['role' => 'user', 'content' => scispin_user_payload($text, $stufe)],
    ];
    $maxTokens = (int)($config['spin_max_tokens'] ?? 6000);

    for ($attempt = 0; $attempt < 2; $attempt++) {
        $textOut = sc_anthropic_text($config, $messages, scispin_system_prompt(), $maxTokens);
        if ($textOut === null) {
            throw new ApiException('API-Aufruf fehlgeschlagen (kein Text / HTTP-Fehler).');
        }

        // 1) Standard-Extraktion (balancierte Klammern) aus dem geteilten Kern.
        $json = sc_extract_json(trim($textOut));
        // 2) Fallback-Regex, falls Markdown-Blöcke oder Sätze drumherum stehen.
        if (preg_match('/\{.*}/s', $json, $matches)) {
            $json = $matches[0];
        }
        // 3) Echte Zeilenumbrüche IN Strings killen sonst das JSON-Parsing.
        $json = str_replace(["\n", "\r"], ' ', $json);
        // 4) Erst jetzt decodieren.
        $parsed = json_decode($json, true);

        if (is_array($parsed)) {
            return scispin_validate_stufe($parsed, $stufe);
        }
    }

    throw new ApiException('Modell lieferte kein gültiges JSON (auch nach Retry).');
}
