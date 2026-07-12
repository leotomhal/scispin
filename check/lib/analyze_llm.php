<?php
/**
 * LLM-Analyse-Schicht für den Studien-Check. Das Modell fetcht nichts selbst.
 * Nutzt den geteilten Low-Level-Call aus lib/llm.php.
 */

require_once __DIR__ . '/../../lib/llm.php';

/** System-Prompt inkl. Kalibrierungsregeln. */
function sc_system_prompt(): string {
    return <<<'PROMPT'
Du bewertest wissenschaftliche Studien für Laien. Du misst NICHT Wahrheit und NICHT
Journal-Qualität, sondern die REICHWEITE DER AUSSAGE: Passt die Methode zu dem, was
behauptet wird?

Antworte AUSSCHLIESSLICH mit einem JSON-Objekt nach diesem Schema, ohne Fließtext,
ohne Markdown, ohne Code-Fences:

{
  "core_statement": "Kernaussage in EINEM laienverständlichen Satz",
  "einfache_erklaerung": "Erklärung in sehr einfacher Sprache, 3 bis 5 kurze Sätze",
  "ampel": { "color": "gruen|gelb|rot|grau", "begruendung": "warum diese Farbe, bezogen auf DIESE Studie" },
  "shows": ["konkrete Punkte, die die Studie zeigt"],
  "shows_not": ["konkrete Punkte, die sie NICHT zeigt"],
  "summary": "3 bis 5 Sätze, kein Fachjargon",
  "methodik_klartext": {
    "study_type": "z. B. Korrelationsstudie, RCT, Tiermodell, Modellierung",
    "study_type_key": "GENAU EINER: meta_analyse|systematic_review|rct|kohorte|fallkontroll|querschnitt|fallserie|narrative_review|tiermodell|invitro|modellierung|andere",
    "erklaerung": "Studientyp in Alltagssprache",
    "fehlende_angaben": ["z. B. Stichprobengröße nicht angegeben"]
  },
  "methode_aussage_abgleich": "Herzstück: Design-Decke vs. Behauptung, begründet die Ampel",
  "einschraenkungen": ["konkrete Einschränkungen"],
  "einordnung": "1–2 Sätze, was man im Alltag daraus ableiten darf"
}

KALIBRIERUNGSREGELN (zwingend):
1. Die Ampel misst die Reichweite der Aussage, nicht Wahrheit, nicht Journal-Qualität.
2. Design-Decke beachten: Korrelation -> keine Ursache-Wirkung; Tier/in-vitro -> nicht
   auf den Menschen übertragbar; Modellierung -> keine empirische Bestätigung;
   kleines n / keine Kontrollgruppe -> keine Verallgemeinerung; RCT/Meta-Analyse ->
   höchste Tragfähigkeit.
3. Grau ist Pflicht: Fehlen nötige Methodendetails (n, Kontrollgruppe, Design) im
   gelieferten Text, setze color="grau". Erfinde keine Farbe.
4. Datenbasis respektieren: Bei datenbasis="abstract" triff keine Aussagen über
   Methodendetails, die im Abstract nicht stehen; liste sie in fehlende_angaben.
5. methode_aussage_abgleich muss sich auf DIESE Studie beziehen. Floskeln wie
   "Korrelation ist nicht Kausalität" ohne konkreten Bezug sind verboten.
6. shows_not konkret, nicht abstrakt (z. B. "zeigt nicht, dass Kaffee Krebs verhindert",
   nicht "weitere Forschung nötig").
7. einfache_erklaerung: für Menschen ohne Vorwissen. Sehr kurze Sätze, nur Alltagswörter,
   keine Fachbegriffe (Begriffe wie "Korrelation", "Kausalität", "Stichprobe", "Kontrollgruppe"
   vermeiden oder sofort in Alltagssprache auflösen). Sage in einfachen Worten: was die
   Studie herausgefunden hat, wie sicher man sich sein darf, und was man daraus NICHT
   schließen sollte. Keine Wiederholung der Fachsprache aus den anderen Feldern.
8. study_type_key: ordne die Studie GENAU EINEM Schlüssel zu:
   meta_analyse (rechnerische Zusammenfassung mehrerer Studien) | systematic_review
   (systematische Übersicht ohne Meta-Rechnung) | rct (randomisiert, kontrolliert) |
   kohorte (Gruppen über Zeit verfolgt) | fallkontroll (Erkrankte vs. Gesunde, rückblickend) |
   querschnitt (einmalige Erhebung/Umfrage, auch Korrelationsstudien) | fallserie
   (Einzelfälle ohne Kontrolle) | narrative_review (nicht-systematischer Überblick,
   Expertenmeinung) | tiermodell | invitro (Zell-/Laborstudie) | modellierung
   (Simulation/Rechenmodell) | andere (nicht eindeutig). Nutze NUR diese Schlüssel.
PROMPT;
}

/** Eingabe-Nachricht für das Modell. */
function sc_user_message(array $ex): string {
    $abstract = $ex['abstract'] ?? '';
    return "Bewerte folgende Studie.\n\n"
        . 'Titel: ' . ($ex['title'] ?: 'unbekannt') . "\n"
        . 'Journal: ' . ($ex['journal'] ?: 'unbekannt') . "\n"
        . 'Datenbasis: ' . $ex['datenbasis'] . "\n\n"
        . "Abstract:\n" . $abstract;
}

/**
 * Ruft die Anthropic-API, parst JSON, einmal Retry bei Parse-Fehler.
 * Rückgabe: ['ok'=>bool, 'data'=>?array, 'error'=>?string]
 */
function sc_llm_analyze(array $cfg, array $ex): array {
    $abstract = mb_substr($ex['abstract'] ?? '', 0, (int)$cfg['max_abstract_chars']);
    $ex['abstract'] = $abstract;

    $messages = [
        ['role' => 'user', 'content' => sc_user_message($ex)],
    ];

    for ($attempt = 0; $attempt < 2; $attempt++) {
        $raw = sc_anthropic_text($cfg, $messages, sc_system_prompt(), 2000);
        if ($raw === null) return ['ok' => false, 'data' => null, 'error' => 'api_error'];

        $json = sc_extract_json($raw);
        $data = json_decode($json, true);

        if (is_array($data) && sc_validate_schema($data)) {
            $allowed = ['meta_analyse','systematic_review','rct','kohorte','fallkontroll',
                        'querschnitt','fallserie','narrative_review','tiermodell','invitro',
                        'modellierung','andere'];
            $k = $data['methodik_klartext']['study_type_key'] ?? '';
            if (!in_array($k, $allowed, true)) {
                $data['methodik_klartext']['study_type_key'] = 'andere';
            }
            return ['ok' => true, 'data' => $data, 'error' => null];
        }

        // Retry: Korrekturhinweis anhängen (Konversation endet mit User-Nachricht)
        $messages = [
            ['role' => 'user', 'content' => sc_user_message($ex)],
            ['role' => 'assistant', 'content' => $raw],
            ['role' => 'user', 'content' => 'Deine Antwort war kein gültiges JSON nach dem '
                . 'vorgegebenen Schema. Antworte erneut, ausschließlich mit dem JSON-Objekt, '
                . 'ohne einleitenden Text und ohne Code-Fences.'],
        ];
    }
    return ['ok' => false, 'data' => null, 'error' => 'json_unparseable'];
}

/** Minimal-Validierung des Schemas. */
function sc_validate_schema(array $d): bool {
    $req = ['core_statement', 'einfache_erklaerung', 'ampel', 'shows', 'shows_not', 'summary',
            'methodik_klartext', 'methode_aussage_abgleich', 'einschraenkungen', 'einordnung'];
    foreach ($req as $k) if (!array_key_exists($k, $d)) return false;
    if (!isset($d['ampel']['color']) || !in_array($d['ampel']['color'], ['gruen','gelb','rot','grau'], true)) return false;
    if (!is_array($d['shows']) || !is_array($d['shows_not'])) return false;
    if (!isset($d['methodik_klartext']['study_type'])) return false;
    return true;
}
