<?php
/**
 * Zentrale Konfiguration – VORLAGE.
 *
 * So einrichten:
 *   1. Diese Datei nach  lib/config.php  kopieren.
 *   2. Echte Werte eintragen (oder per Umgebungsvariable im Hosting-Panel setzen).
 *   3. lib/config.php wird durch .gitignore NIEMALS eingecheckt.
 *
 * Beide Modi (check/ und spin/) teilen sich diese eine Konfiguration:
 * ein Anthropic-Key, eine Datenbank, ein gemeinsames Tages-/Rate-Limit.
 */

return [
    // ==================== Anthropic ====================
    // Key niemals hart hier eintragen, wenn das Repo geteilt wird – lieber die
    // Umgebungsvariable ANTHROPIC_API_KEY im Hosting-Panel setzen.
    'anthropic_api_key'  => getenv('ANTHROPIC_API_KEY') ?: 'sk-ant-DEIN-KEY-HIER',
    // Gültige Modell-ID gegen die aktuelle Anthropic-Doku prüfen.
    'anthropic_model'    => getenv('ANTHROPIC_MODEL') ?: 'claude-sonnet-5',
    'anthropic_version'  => '2023-06-01',
    'anthropic_endpoint' => 'https://api.anthropic.com/v1/messages',
    'llm_timeout'        => 90,     // Sekunden pro API-Call

    // ==================== Datenbank (PDO/MySQL) ====================
    'db' => [
        'host'    => getenv('DB_HOST') ?: 'localhost',
        'name'    => getenv('DB_NAME') ?: 'DEINE_DATENBANK',
        'user'    => getenv('DB_USER') ?: 'DEIN_DB_USER',
        'pass'    => getenv('DB_PASS') ?: 'DEIN_DB_PASSWORT',
        'charset' => 'utf8mb4',
    ],

    // ==================== Privatsphäre ====================
    // Basis für die IP-Anonymisierung (Rate-Limit ohne personenbezogene IP).
    // EINMALIG durch eine lange Zufallszeichenkette ersetzen. Mit dem Tagesdatum
    // kombiniert rotiert der tatsächliche Salt täglich.
    'ip_salt_base'       => getenv('IP_SALT_BASE') ?: 'BITTE-DURCH-LANGEN-ZUFALLSWERT-ERSETZEN',

    // ==================== Gemeinsame Kostenbremse ====================
    // Zählt JEDEN Anthropic-Call über alle Modi. Achtung: eine vollständige
    // Spin-Analyse sind 7 Calls (eine pro Stufe), ein Studien-Check ist 1 Call,
    // eine Kurzmeldung sind 3 Calls (Gerüst + Aufmacher + automatischer
    // Studien-Check zum Original-Abstract, läuft innerhalb der Aufmacher-Anfrage).
    'daily_llm_cap'      => 500,    // globale Obergrenze aller LLM-Calls pro Tag

    // ==================== Modus "check" (Studien-Check) ====================
    'contact_mailto'       => getenv('CONTACT_MAILTO') ?: 'you@example.org', // Crossref Polite Pool
    'max_link_len'         => 2000,   // maximale Linklänge
    'max_abstract_chars'   => 12000,  // Abstract vor LLM-Call kappen
    'rate_per_ip_per_hour' => 20,     // Studien-Checks pro Besucher/Stunde
    'rate_hits_per_hour'   => 120,    // Cache-Treffer pro Besucher/Stunde
    'ttl_peerreview'       => 0,          // Cache-TTL peer-reviewed (0 = unbefristet)
    'ttl_preprint'         => 7 * 86400,  // Cache-TTL Preprints (7 Tage)
    'http_timeout'         => 20,     // Timeout für Crossref/EuropePMC/Scrape
    'recent_count'         => 5,      // "Zuletzt geprüft" auf der Startseite
    // Optionales Passwort fürs Archiv ('' = frei zugänglich).
    'archive_password'     => getenv('ARCHIVE_PASSWORD') ?: '',

    // ==================== Modus "spin" (SciSpin-O-Mat) ====================
    'max_input_chars'    => 4000,   // längere Eingaben werden abgelehnt (gilt auch für "brief")
    'rate_per_hour'      => 84,     // Spin-Calls pro Besucher/Stunde (7 = 1 Analyse)
    'spin_max_tokens'    => 6000,   // max_tokens pro Stufen-Call
    // true = statische Beispieldaten, keine API-/DB-Calls (zum Anschauen).
    // Gilt für spin/ UND brief/ (beide liefern dann statische Demos).
    'demo_mode'          => false,

    // ==================== Modus "brief" (Kurzmeldung, 5 Bits Outline) ====================
    'brief_rate_per_hour' => 40,    // Kurzmeldungs-ANFRAGEN pro Besucher/Stunde (2 Anfragen = 1 Meldung; der dritte, automatische Call zählt nur gegen daily_llm_cap)
    'brief_max_tokens'    => 2500,  // max_tokens pro Phasen-Call

    // ==================== Self-Updater (tools/update.php) ====================
    // Spielt neue Releases aus dem GitHub-Repo direkt auf den Server ein.
    // Aufruf:  https://…/tools/update.php?token=<update_token>&action=check
    //          https://…/tools/update.php?token=<update_token>&action=apply
    //
    // Leeres update_token => Updater ist DEAKTIVIERT (empfohlen, solange nicht gebraucht).
    'update_token'   => getenv('UPDATE_TOKEN') ?: '',            // langes Geheimnis zum Aufruf
    'github_repo'    => getenv('GITHUB_REPO') ?: 'leotomhal/scispin',
    // GitHub-Token mit Lese-Zugriff (Contents) – ZWINGEND für PRIVATE Repos.
    'github_token'   => getenv('GITHUB_TOKEN') ?: '',
    // 'release' = neuestes veröffentlichtes Release-Tag, 'branch' = Kopf eines Branches.
    'update_channel' => getenv('UPDATE_CHANNEL') ?: 'release',
    'update_branch'  => getenv('UPDATE_BRANCH') ?: 'main',
    // Diese Pfade/Verzeichnisse werden beim Update NIE überschrieben (relativ
    // zum Projektwurzel). content/ steht hier, weil die Texte jetzt über
    // tools/edit.php live auf dem Server gepflegt werden – ein Update soll
    // diese Änderungen nicht mit dem älteren Git-Stand überschreiben.
    'update_protect' => ['lib/config.php', 'content'],

    // ==================== Inhalts-Editor (tools/edit.php) ====================
    // Bearbeitet content/*.md direkt auf dem Server, ohne GitHub. Eigenes,
    // vom update_token UNABHÄNGIGES Geheimnis. Leer => Editor deaktiviert.
    'content_edit_token' => getenv('CONTENT_EDIT_TOKEN') ?: '',

    // ==================== Debug ====================
    // true  = echte Fehlermeldungen an den Browser (NUR zum Einrichten).
    // false = neutrale Meldung nach außen (richtig fürs Livesystem).
    'debug'              => false,
];
