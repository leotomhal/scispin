<?php
// prompt.php – lädt den System-Prompt, baut die User-Payload für EINE Stufe und
// validiert die Modellantwort gegen das Einzelstufen-Schema.
//
// Der eigentliche Prompt-Text liegt in system_prompt.de.txt (leichter pflegbar).
// Die JSON-Extraktion kommt aus dem geteilten Kern (lib/llm.php: sc_extract_json).

function scispin_system_prompt(): string {
    $datei = __DIR__ . '/system_prompt.de.txt';
    $txt = @file_get_contents($datei);
    if ($txt === false || trim($txt) === '') {
        throw new RuntimeException('System-Prompt-Datei fehlt oder ist leer.');
    }
    return $txt;
}

// Nutzertext als reines Material kapseln, mit vorangestellter Zielstufe.
// Delimiter exakt wie im Prompt beschrieben (<<<MELDUNG … MELDUNG>>>).
function scispin_user_payload(string $text, int $stufe): string {
    return "STUFE: " . $stufe . "\n\n"
         . "<<<MELDUNG\n" . $text . "\nMELDUNG>>>";
}

/**
 * Validiert die Modellantwort für GENAU EINE Stufe.
 * Wirft RuntimeException bei strukturellem Fehler (löst im Aufrufer den Retry aus).
 *
 * Rückgabe bei Ablehnung (nur sinnvoll bei Stufe 0):
 *   ['ist_studie' => false, 'ablehnungsgrund' => string]
 * Rückgabe im Erfolgsfall:
 *   ['ist_studie' => true, 'stufe' => int, 'text' => string,
 *    'kommentar' => string, 'aenderungen' => array]
 */
function scispin_validate_stufe(array $data, int $stufe): array {
    // --- Ablehnung: nur bei Stufe 0 erlaubt. ---
    if (array_key_exists('ist_studie', $data) && $data['ist_studie'] === false) {
        if ($stufe !== 0) {
            // Eine Ablehnung außerhalb Stufe 0 ist ein Modellfehler -> Retry erzwingen.
            throw new RuntimeException("Ablehnung bei Stufe $stufe ist unzulässig.");
        }
        return [
            'ist_studie'      => false,
            'ablehnungsgrund' => isset($data['ablehnungsgrund'])
                ? (string)$data['ablehnungsgrund']
                : 'Der Text wirkt nicht wie eine Forschungsmeldung.',
        ];
    }

    // --- Erfolgsfall: eine Stufe. ---
    if (!isset($data['text']) || trim((string)$data['text']) === '') {
        throw new RuntimeException("Stufe $stufe ohne Text.");
    }

    $aenderungen = [];
    foreach (($data['aenderungen'] ?? []) as $a) {
        if (!is_array($a) || !isset($a['typ'])) continue;
        $typ = $a['typ'];
        if (!in_array($typ, ['eingefuegt', 'gestrichen', 'veraendert'], true)) continue;
        $aenderungen[] = [
            'typ'        => $typ,
            'original'   => (string)($a['original']   ?? ''),
            'neu'        => (string)($a['neu']        ?? ''),
            'fehlertyp'  => (string)($a['fehlertyp']  ?? ''),
            'erklaerung' => (string)($a['erklaerung'] ?? ''),
        ];
    }

    return [
        'ist_studie'  => true,
        'stufe'       => $stufe,                       // immer die angeforderte Stufe – nie die vom Modell behauptete
        'text'        => (string)$data['text'],
        'kommentar'   => (string)($data['kommentar'] ?? ''),
        'aenderungen' => $aenderungen,
    ];
}
