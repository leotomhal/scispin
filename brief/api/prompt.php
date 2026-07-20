<?php
// prompt.php – lädt den System-Prompt, baut die User-Payload je Phase und
// validiert die Modellantwort gegen das jeweilige Phasen-Schema.
//
// Zwei Phasen (5 Bits Outline):
//   Phase 1 = Gerüst: Bits 1–4 (Frage, Methoden, Engpass, Fortschritt) + Themen-Gate
//   Phase 2 = Aufmacher: Bit 5 (Lede) + Headline + zusammengesetzte Kurzmeldung
//
// Der eigentliche Prompt-Text liegt in system_prompt.de.txt (leichter pflegbar).
// Die JSON-Extraktion kommt aus dem geteilten Kern (lib/llm.php: sc_extract_json).

function brief_system_prompt(): string {
    $datei = __DIR__ . '/system_prompt.de.txt';
    $txt = @file_get_contents($datei);
    if ($txt === false || trim($txt) === '') {
        throw new RuntimeException('System-Prompt-Datei fehlt oder ist leer.');
    }
    return $txt;
}

/**
 * User-Payload für Phase 1: das Studienmaterial als reines Material kapseln,
 * optionale Metadaten voranstellen. Delimiter exakt wie im Prompt beschrieben.
 */
function brief_payload_phase1(string $text, array $meta): string {
    $kopf = '';
    if (($meta['titel'] ?? '') !== '')   $kopf .= 'Titel: '   . $meta['titel']   . "\n";
    if (($meta['journal'] ?? '') !== '') $kopf .= 'Journal: ' . $meta['journal'] . "\n";
    if (($meta['doi'] ?? '') !== '')     $kopf .= 'DOI: '     . $meta['doi']     . "\n";

    return "PHASE: 1\n\n"
         . ($kopf !== '' ? $kopf . "\n" : '')
         . "<<<MATERIAL\n" . $text . "\nMATERIAL>>>";
}

/**
 * User-Payload für Phase 2: die Bits 1–4 aus Phase 1 als Gerüst mitgeben,
 * plus das Ausgangsmaterial (für Zitatnähe/Belege).
 */
function brief_payload_phase2(string $text, array $bits, array $meta): string {
    $geruest = json_encode([
        'frage'          => $bits['frage']          ?? '',
        'methoden'       => $bits['methoden']       ?? [],
        'methoden_recap' => $bits['methoden_recap'] ?? '',
        'engpass'        => $bits['engpass']        ?? '',
        'fortschritt'    => $bits['fortschritt']    ?? '',
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

    $kopf = '';
    if (($meta['titel'] ?? '') !== '')   $kopf .= 'Titel: '   . $meta['titel']   . "\n";
    if (($meta['journal'] ?? '') !== '') $kopf .= 'Journal: ' . $meta['journal'] . "\n";

    return "PHASE: 2\n\n"
         . ($kopf !== '' ? $kopf . "\n" : '')
         . "GERUEST (Bits 1–4):\n" . $geruest . "\n\n"
         . "<<<MATERIAL\n" . $text . "\nMATERIAL>>>";
}

/** Feste Werte für "evidenz"-Unterfelder, wenn das Material dazu nichts hergibt. */
const BRIEF_UNBEKANNT = 'im Abstract nicht angegeben';

/** Validiert/normalisiert das "evidenz"-Objekt aus Phase 1 (Punkt 8: Pflicht-Etikett). */
function brief_validate_evidenz($e): array {
    $e = is_array($e) ? $e : [];
    $get = function (array $e, string $key): string {
        $v = trim((string)($e[$key] ?? ''));
        return $v !== '' ? $v : BRIEF_UNBEKANNT;
    };
    $population = $get($e, 'population');
    $erlaubtePopulationen = ['Mensch', 'Tiermodell', 'Zellkultur/in-vitro', 'Computermodell/Simulation', BRIEF_UNBEKANNT];
    if (!in_array($population, $erlaubtePopulationen, true)) $population = BRIEF_UNBEKANNT;

    $preprint = strtolower($get($e, 'preprint'));
    if (!in_array($preprint, ['ja', 'nein'], true)) $preprint = BRIEF_UNBEKANNT;

    return [
        'studientyp'         => $get($e, 'studientyp'),
        'stichprobengroesse' => $get($e, 'stichprobengroesse'),
        'population'         => $population,
        'preprint'           => $preprint,
    ];
}

/**
 * Validiert/normalisiert "regel_hinweise" aus Phase 2 (Punkt 6: Regel-Eingriffe
 * inline markieren). Nur strukturell vollständige Einträge behalten; ob "text"
 * tatsächlich in "kurzmeldung" vorkommt, prüft und markiert das Frontend
 * (whitespace-tolerant, wie schon bei den Änderungsmarkierungen im SciSpin-O-Mat).
 */
function brief_validate_regel_hinweise($liste): array {
    $out = [];
    foreach ((is_array($liste) ? $liste : []) as $h) {
        if (!is_array($h)) continue;
        $text = trim((string)($h['text'] ?? ''));
        if ($text === '') continue;
        $out[] = [
            'text'    => $text,
            'regel'   => (string)($h['regel'] ?? ''),
            'hinweis' => (string)($h['hinweis'] ?? ''),
        ];
        if (count($out) >= 5) break;
    }
    return $out;
}

/**
 * Validiert die Modellantwort für eine Phase.
 * Wirft RuntimeException bei strukturellem Fehler (löst im Aufrufer den Retry aus).
 *
 * Rückgabe Phase 1 – Ablehnung (nur hier erlaubt):
 *   ['ist_studie' => false, 'ablehnungsgrund' => string]
 * Rückgabe Phase 1 – Erfolg:
 *   ['ist_studie' => true, 'frage', 'methoden'[], 'methoden_recap', 'engpass',
 *    'fortschritt', 'evidenz'{studientyp,stichprobengroesse,population,preprint},
 *    'abstract_hype_warnung' (string|null)]
 * Rückgabe Phase 2:
 *   ['lede', 'headline', 'kurzmeldung', 'regel_hinweise'[]]
 */
function brief_validate(array $data, int $phase): array {
    if ($phase === 1) {
        // Ablehnung: nur in Phase 1 erlaubt.
        if (array_key_exists('ist_studie', $data) && $data['ist_studie'] === false) {
            return [
                'ist_studie'      => false,
                'ablehnungsgrund' => isset($data['ablehnungsgrund'])
                    ? (string)$data['ablehnungsgrund']
                    : 'Der Text wirkt nicht wie eine Forschungsarbeit.',
            ];
        }

        if (!isset($data['frage']) || trim((string)$data['frage']) === '') {
            throw new RuntimeException('Phase 1 ohne Frage (Bit 1).');
        }

        $methoden = [];
        foreach (($data['methoden'] ?? []) as $m) {
            $m = trim((string)$m);
            if ($m !== '') $methoden[] = $m;
        }

        $hypeWarnung = trim((string)($data['abstract_hype_warnung'] ?? ''));

        return [
            'ist_studie'             => true,
            'frage'                  => (string)$data['frage'],
            'methoden'               => $methoden,
            'methoden_recap'         => (string)($data['methoden_recap'] ?? ''),
            'engpass'                => (string)($data['engpass'] ?? ''),
            'fortschritt'            => (string)($data['fortschritt'] ?? ''),
            'evidenz'                => brief_validate_evidenz($data['evidenz'] ?? null),
            'abstract_hype_warnung'  => $hypeWarnung !== '' && strtolower($hypeWarnung) !== 'null' ? $hypeWarnung : null,
        ];
    }

    // Phase 2
    if (!isset($data['kurzmeldung']) || trim((string)$data['kurzmeldung']) === '') {
        throw new RuntimeException('Phase 2 ohne Kurzmeldung (Bit 5).');
    }
    return [
        'lede'           => (string)($data['lede'] ?? ''),
        'headline'       => (string)($data['headline'] ?? ''),
        'kurzmeldung'    => (string)$data['kurzmeldung'],
        'regel_hinweise' => brief_validate_regel_hinweise($data['regel_hinweise'] ?? null),
    ];
}
