<?php
/**
 * tools/connectivity.php – EINMALIGER Vorab-Check nach dem Deployment.
 * Prüft: Konfiguration geladen, DB erreichbar, ausgehende HTTPS-Verbindungen zu
 * Anthropic / Crossref / Europe PMC. NACH GEBRAUCH LÖSCHEN.
 *
 * Aufruf im Browser: https://…/tools/connectivity.php
 */
header('Content-Type: text/plain; charset=utf-8');
ini_set('display_errors', '1');
error_reporting(E_ALL);

echo "=== SciSpin – Connectivity-Check ===\n\n";

$cfg = require __DIR__ . '/../lib/config.php';
require_once __DIR__ . '/../lib/http.php';
require_once __DIR__ . '/../lib/db.php';

// --- Konfiguration ---
$key = (string)($cfg['anthropic_api_key'] ?? '');
$keyOk = $key !== '' && !str_starts_with($key, 'sk-ant-DEIN');
echo "Modell:            " . ($cfg['anthropic_model'] ?? '?') . "\n";
echo "API-Key gesetzt:   " . ($keyOk ? "OK (" . strlen($key) . " Zeichen)" : "FEHLT – in lib/config.php eintragen") . "\n";

// --- Datenbank ---
try {
    $db = sc_db($cfg);
    $db->query('SELECT 1');
    echo "Datenbank:         OK\n";
    $tables = $db->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    foreach (['rate_limit', 'llm_daily', 'cache', 'archive', 'spin_cache'] as $t) {
        echo "  Tabelle $t: " . (in_array($t, $tables, true) ? "OK" : "FEHLT – schema.sql importieren") . "\n";
    }
} catch (Throwable $e) {
    echo "Datenbank:         FEHLER – " . $e->getMessage() . "\n";
}

echo "\n--- Ausgehende HTTPS-Verbindungen ---\n";

// Anthropic: HTTP 401 (kein/anderer Header) ist hier ein GUTES Zeichen – der Host ist erreichbar.
$r = sc_http_post('https://api.anthropic.com/v1/messages',
    ['content-type: application/json', 'x-api-key: ' . $key,
     'anthropic-version: ' . ($cfg['anthropic_version'] ?? '2023-06-01')],
    json_encode(['model' => $cfg['anthropic_model'] ?? 'claude-sonnet-5', 'max_tokens' => 16,
                 'messages' => [['role' => 'user', 'content' => 'ping']]]),
    (int)($cfg['llm_timeout'] ?? 90));
echo "Anthropic:         HTTP {$r['status']}" . ($r['status'] === 0 ? " – BLOCKIERT? ({$r['error']})" : " – erreichbar") . "\n";

$r = sc_http_get('https://api.crossref.org/works/10.1038/nature12373',
    ['Accept: application/json'], (int)($cfg['http_timeout'] ?? 20));
echo "Crossref:          HTTP {$r['status']}" . ($r['status'] === 0 ? " – BLOCKIERT? ({$r['error']})" : "") . "\n";

$r = sc_http_get('https://www.ebi.ac.uk/europepmc/webservices/rest/search?query=test&format=json&pageSize=1',
    ['Accept: application/json'], (int)($cfg['http_timeout'] ?? 20));
echo "Europe PMC:        HTTP {$r['status']}" . ($r['status'] === 0 ? " – BLOCKIERT? ({$r['error']})" : "") . "\n";

echo "\nHTTP 0 bedeutet: ausgehender Verkehr blockiert – beim Hoster klären.\n";
echo "Läuft alles durch: diese Datei LÖSCHEN.\n";
