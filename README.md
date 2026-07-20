# SciSpin

Drei Werkzeuge für Wissenschaftskommunikation mit gemeinsamem Kern. Sie stellen
dieselbe Frage aus verschiedenen Richtungen: **Deckt sich die Kommunikation mit
dem, was die Wissenschaft hergibt?**

- **Studien-Check** (`check/`) – *Spin erkennen.* Link zu einer Studie einfügen;
  die App liest Metadaten und Abstract aus (Crossref, Europe PMC, arXiv, Meta-Tags)
  und bewertet auf einer Ampel, wie weit die Aussage trägt: was die Studie zeigt
  und was nicht.
- **SciSpin-O-Mat** (`spin/`) – *Spin vorführen.* Eine Forschungsmeldung einfügen
  und den Regler von −3 („liest eh keiner") bis +3 („völlig überdreht") ziehen.
  Jede Stufe schreibt den Text um, markiert die Änderungen und erklärt per Tooltip,
  welcher Kommunikationsfehler passiert. Stufe +1 ist das Ziel.
- **Kurzmeldung** (`brief/`) – *Sachlich melden.* Aus einem Abstract eine kurze
  Meldung im Stil einer AAAS-Kurzmeldung, entlang der fünf Fragen des „5 Bits
  Outline". Erst entsteht das Gerüst (Frage · Methoden · Engpass · Fortschritt),
  dann – bewusst zuletzt – der Aufmacher. Zwei API-Calls pro Meldung.

Plain PHP / MySQL, für klassisches Shared Hosting. Analyse-Aufrufe gehen an die
Anthropic-API; alle Modi teilen sich einen API-Key, eine Datenbank und eine
gemeinsame Kostenbremse.

## Projektstruktur

```
index.php                Startseite mit beiden Modi
so-funktionierts.php     Erklärt beide Tools (Hülle -> content/so-funktionierts.md)
methoden.php             Methoden & Evidenz (Pyramide + Karten aus content/methoden.md)
impressum.php            Impressum (Hülle -> content/impressum.md)
datenschutz.php          Datenschutz (Hülle -> content/datenschutz.md)
content/                 TEXTE zum Bearbeiten (siehe unten) – über tools/edit.php
  so-funktionierts.md · impressum.md · datenschutz.md · ueber.md · methoden.md
schema.sql               Alle DB-Tabellen (einmal importieren)
lib/                     GETEILTER KERN
  config.example.php       Vorlage -> nach lib/config.php kopieren und ausfüllen
  http.php                 cURL-Wrapper
  llm.php                  Anthropic-Call + robuste JSON-Extraktion
  db.php                   Verbindung, Rate-Limit, Tages-Kostenbremse
  markdown.php · page.php   Rendert content/*.md ins geteilte Layout
  methoden_data.php        Parst content/methoden.md (Pyramide + Karten, kein Fließtext)
assets/                  GETEILTES AUSSEHEN (beide Modi)
  scispin.css              Design-Tokens, Marke, Kopfleiste, Footer
  chrome.js                fügt Navigationsleiste + Footer auf jeder Seite ein
check/                   Modus "Studien-Check"
  index.php  archive.php   Startformular + durchsuchbares Archiv
  api/analyze.php          Orchestrierung (Extraktion -> Cache -> LLM -> Archiv)
  lib/                     extract.php · badge.php · analyze_llm.php · store.php
spin/                    Modus "SciSpin-O-Mat"
  index.html  preview.html  app.js  beispiele.js  style.css
  ueber.php                Hülle -> content/ueber.md
  demo_payload.js          Demo-Daten für preview.html (ohne Server)
  api/generate.php         Endpunkt für EINE Stufe (Cache -> LLM -> Cache)
  api/prompt.php           Prompt bauen + Modellantwort validieren
  api/system_prompt.de.txt Systemprompt (ohne Code änderbar)
  api/demo_data.php        Statische 7-Stufen-Demo (demo_mode)
brief/                   Modus "Kurzmeldung" (5 Bits Outline)
  index.html  preview.html  app.js  style.css
  ueber.php                Hülle -> content/brief.md
  demo_payload.js          Demo-Daten für preview.html (ohne Server)
  api/generate.php         Endpunkt für EINE Phase (Cache -> LLM -> Cache)
  api/prompt.php           Payload je Phase bauen + Antwort validieren
  api/system_prompt.de.txt Systemprompt mit der 5-Bits-Methode (ohne Code änderbar)
  api/demo_data.php        Statische 2-Phasen-Demo (demo_mode)
tools/update.php         Self-Updater (siehe unten)
tools/edit.php           Inhalts-Editor: content/*.md live bearbeiten, ohne GitHub
```

Beide Modi teilen sich eine **Kopfleiste** (Navigation zwischen „Prüfen" und
„Vorführen") und eine Designsprache aus `assets/`. Aus dem Studien-Check führt
eine **Brücke** direkt in den SciSpin-O-Mat: Der Ergebnis-Text lässt sich per
Klick übernehmen und dort weiterverarbeiten.

## Inhalte bearbeiten

Die Texte der Info-Seiten liegen als **Markdown** in `content/` – kein
kompliziertes Backend, keine DB. Bearbeiten geht **direkt auf der Seite**,
ohne GitHub:

```
https://deine-domain/tools/edit.php?token=DEIN_CONTENT_EDIT_TOKEN
```

Seite aus der Liste wählen, Text im Feld ändern, „Speichern" – die Datei wird
sofort auf dem Server geschrieben. Kein Commit, kein Update-Lauf, keine
Versionsnummer nötig. Voraussetzung: `content_edit_token` ist in
`lib/config.php` gesetzt (siehe unten).

Formatierung (bei den Fließtext-Seiten): `##` Überschrift, `**fett**`,
`*kursiv*`, `- ` Liste, `1. ` nummeriert, `[Text](link.php)`, `> ` Zitat.
Zeilen, die mit `<` beginnen, sind kleine HTML-Bausteine (z. B. die
Ampel-Grafik) – die kannst du in Ruhe lassen, wenn du nur Text änderst.
Layout, Kopfleiste und Design kommen automatisch dazu.

`content/methoden.md` ist ein Sonderfall (siehe Kommentar am Dateianfang):
kein Fließtext, sondern ein Block pro Studientyp mit festen Feldern
(Name/Rang/Gruppe/Kurz/Zeigt/Grenzen). Damit bleiben Pyramide und Karten
automatisch gebaut – du änderst nur die Werte, nicht das Layout.

| Seite | Text-Datei |
|---|---|
| So funktioniert's | `content/so-funktionierts.md` |
| Über den SciSpin-O-Mat | `content/ueber.md` |
| Methoden & Evidenz | `content/methoden.md` (strukturiert, siehe oben) |
| Impressum | `content/impressum.md` |
| Datenschutz | `content/datenschutz.md` |

Neue Seiten unter `content/` legt `tools/edit.php` automatisch mit auf (jede
`.md`-Datei im Ordner erscheint in der Liste) – eine ganz neue *Seite* im
Sinne einer neuen URL braucht aber weiterhin eine kleine Hülle wie
`impressum.php` und bleibt ein Code-Schritt.

Wichtig fürs Zusammenspiel mit dem Self-Updater: `content/` steht standardmäßig
in `update_protect` (siehe unten) – ein `tools/update.php?...&action=apply`
überschreibt live bearbeitete Texte also nie mit dem (älteren) Git-Stand.
Im Gegenzug werden Änderungen aus `tools/edit.php` **nicht** zurück nach
GitHub geschrieben; das Repo enthält nur die ursprünglichen Ausgangstexte.

## Ohne Server ansehen

`spin/preview.html` im Browser öffnen: läuft ohne PHP und zeigt fest hinterlegte
Demo-Daten. Regler ziehen.

## Einrichten (mit PHP/MySQL)

1. **Datenbank** anlegen, Zugangsdaten notieren.
2. **`schema.sql`** in phpMyAdmin importieren.
3. **`lib/config.example.php`** nach **`lib/config.php`** kopieren und ausfüllen:
   - `anthropic_api_key` (oder Umgebungsvariable `ANTHROPIC_API_KEY`),
   - `db` (host/name/user/pass),
   - `ip_salt_base` durch einen langen Zufallswert ersetzen,
   - `contact_mailto` (für den Crossref Polite Pool),
   - `anthropic_model` gegen die aktuelle Anthropic-Doku prüfen,
   - `content_edit_token` setzen, um `tools/edit.php` zu aktivieren (Inhalte
     ohne GitHub bearbeiten, siehe „Inhalte bearbeiten" unten),
   - zum Einrichten `debug => true`, im Livebetrieb wieder `false`.
4. Alle Dateien per FTP hochladen (Struktur beibehalten).
5. Startseite aufrufen und beide Modi testen. Klappt ein echter Studien-Link
   bzw. eine Spin-Analyse, sind DB und ausgehende HTTPS-Verbindungen in Ordnung.

`lib/config.php` enthält Geheimnisse und wird durch `.gitignore` **niemals**
eingecheckt.

### Zum Testen ohne DB

In `lib/config.php` `demo_mode => true` setzen: Der SciSpin-O-Mat liefert dann
statische Beispieldaten ohne API- oder DB-Zugriff.

## Der kritische Punkt

Beide Modi stehen und fallen mit **ausgehenden HTTPS-Verbindungen** vom Server zu
den externen APIs (Anthropic, Crossref, Europe PMC). Schlägt eine echte Analyse
mit Server-/Zeitüberschreitungsfehlern fehl, blockiert der Hoster womöglich den
ausgehenden Verkehr – dann beim Support klären, bevor du Zeit in Debugging steckst.
(Bei `debug => true` geben die Endpunkte konkrete Fehlermeldungen aus.)

## Kostenschutz (nicht optional)

Öffentlicher Endpunkt + bezahlte LLM-API = Kostenfalle. In `lib/config.php`:

- `daily_llm_cap` – globale Obergrenze **aller** LLM-Calls pro Tag (Notbremse über
  alle Modi). **Achtung:** ein Studien-Check ist 1 Call, eine Kurzmeldung sind
  **2** Calls (Gerüst + Aufmacher), eine vollständige Spin-Analyse sind **7** Calls
  (eine pro Stufe).
- `rate_per_ip_per_hour` / `rate_hits_per_hour` – Studien-Checks bzw. Cache-Treffer
  pro Besucher/Stunde.
- `rate_per_hour` – Spin-Calls pro Besucher/Stunde (7 ≈ 1 Analyse).
- `brief_rate_per_hour` – Kurzmeldungs-Calls pro Besucher/Stunde (2 ≈ 1 Meldung).
- `max_abstract_chars` / `max_input_chars` – kappen die Eingabelänge.

Das MySQL-Rate-Limit ist stundenbasiert und gröber als ein Token-Bucket – für den
Anfang ausreichend, aber im Blick behalten. Besucher werden nur anonymisiert
gezählt (SHA-256 aus IP + täglich rotierendem Salt), keine Klartext-IP.

## Architektur

**Studien-Check** – `api/analyze.php`: Link → Identifier/Abstract extrahieren →
Cache prüfen → Rate-Limit/Tagesbremse → Anthropic-Analyse (Schema-validiert, 1 Retry)
→ cachen + ins durchsuchbare Archiv. Die Ampel misst die *Reichweite der Aussage*,
nicht Wahrheit oder Journal-Qualität.

**SciSpin-O-Mat** – Einzelstufen-Architektur: das Frontend holt die sieben Stufen
nacheinander, ein API-Call pro Stufe (Stufe 0 zuerst als Themen-Gate). Ein kleiner
Call pro Stufe ist robuster auf Shared Hosting als ein großer Call für alles.

**Kurzmeldung** – Zwei-Phasen-Architektur nach dem 5 Bits Outline: Phase 1 baut das
Gerüst (Bits 1–4 + Themen-Gate wie Stufe 0 im Spin), Phase 2 schreibt daraus – erst
danach – den Aufmacher (Bit 5) und die zusammengesetzte Kurzmeldung. Die Regel
„Lede zuletzt" ist so nicht nur im Prompt formuliert, sondern in zwei getrennten
Calls angelegt. Ein Call pro Phase, gecacht je (Eingabe-Hash, Phase).

## Updates einspielen (Self-Updater)

`tools/update.php` zieht neue Releases (oder einen Branch-Kopf) direkt aus dem
GitHub-Repo auf den Server – kein FTP nötig. In `lib/config.php` konfigurieren:

- `update_token` – langes Geheimnis; **leer = Updater deaktiviert** (Standard).
- `github_token` – GitHub-Token mit Lese-Zugriff (Contents); **zwingend für das
  private Repo**, sonst schlägt der Download fehl.
- `update_channel` – `release` (neuestes veröffentlichtes Tag) oder `branch`.
- `update_protect` – Pfade/Verzeichnisse, die nie überschrieben werden
  (Standard: `lib/config.php` und `content` – siehe „Inhalte bearbeiten" oben).

Aufruf im Browser:

```
tools/update.php?token=SECRET&action=check            # nur Versionen vergleichen
tools/update.php?token=SECRET&action=apply&dry_run=1  # Vorschau: was würde sich ändern
tools/update.php?token=SECRET&action=apply            # Update wirklich einspielen
```

Dateien werden nur **überlagert** (kopiert), nie gelöscht; `lib/config.php` und
alles unter `update_protect` bleibt unangetastet. Zip-Slip-Schutz verhindert
Schreibzugriffe außerhalb des Projektverzeichnisses.

**Die beiden Kanäle unterscheiden sich beim `apply`:**

- **`branch`** (einfachster Weg, ideal für Inhalts-Edits): `apply` spielt
  **immer** den aktuellen Stand des Branches ein und schreibt nur tatsächlich
  geänderte Dateien. **Keine Versionsnummer nötig** – einfach `content/*.md` (oder
  anderes) auf `main` committen und `apply` aufrufen. `check` zeigt den aktuellen
  Commit-Stand.
- **`release`**: `apply` spielt nur ein, wenn eine höhere Version verfügbar ist.
  Zum Veröffentlichen `VERSION` erhöhen und ein Git-Tag `vX.Y.Z` anlegen/pushen
  (bevorzugt ein GitHub-Release, sonst genügt das neueste Tag). Für kontrollierte
  Releases.

## Modell-String

Die Vorlage nutzt `claude-sonnet-5`. Vor dem Produktivbetrieb gegen die aktuelle
Anthropic-Modell-Liste prüfen und ggf. anpassen.

## Grenzen (bewusst)

- Studien-Check: Volltext-Methodik meist nicht verfügbar (Abstract ist der Regelfall);
  kein Predatory-Verdikt; nicht-biomedizinische Felder schwächer abgedeckt.
- SciSpin-O-Mat: bewusst zugespitztes Demonstrationsinstrument, kein Autoren-Tool.

## Lizenz

MIT – siehe `LICENSE`.
