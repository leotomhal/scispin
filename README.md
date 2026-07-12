# SciSpin

Zwei Werkzeuge für Wissenschaftskommunikation mit gemeinsamem Kern. Sie stellen
dieselbe Frage aus zwei Richtungen: **Deckt sich die Kommunikation mit dem, was
die Wissenschaft hergibt?**

- **Studien-Check** (`check/`) – *Spin erkennen.* Link zu einer Studie einfügen;
  die App liest Metadaten und Abstract aus (Crossref, Europe PMC, arXiv, Meta-Tags)
  und bewertet auf einer Ampel, wie weit die Aussage trägt: was die Studie zeigt
  und was nicht.
- **SciSpin-O-Mat** (`spin/`) – *Spin vorführen.* Eine Forschungsmeldung einfügen
  und den Regler von −3 („liest eh keiner") bis +3 („völlig überdreht") ziehen.
  Jede Stufe schreibt den Text um, markiert die Änderungen und erklärt per Tooltip,
  welcher Kommunikationsfehler passiert. Stufe +1 ist das Ziel.

Plain PHP / MySQL, für klassisches Shared Hosting. Analyse-Aufrufe gehen an die
Anthropic-API; beide Modi teilen sich einen API-Key, eine Datenbank und eine
gemeinsame Kostenbremse.

## Projektstruktur

```
index.php                Startseite mit beiden Modi
schema.sql               Alle DB-Tabellen (einmal importieren)
lib/                     GETEILTER KERN
  config.example.php       Vorlage -> nach lib/config.php kopieren und ausfüllen
  http.php                 cURL-Wrapper
  llm.php                  Anthropic-Call + robuste JSON-Extraktion
  db.php                   Verbindung, Rate-Limit, Tages-Kostenbremse
check/                   Modus "Studien-Check"
  index.php  archive.php  methoden.php  impressum.php  datenschutz.php
  api/analyze.php          Orchestrierung (Extraktion -> Cache -> LLM -> Archiv)
  lib/                     extract.php · badge.php · analyze_llm.php · store.php
spin/                    Modus "SciSpin-O-Mat"
  index.html  preview.html  ueber.html  style.css  app.js  beispiele.js
  demo_payload.js          Demo-Daten für preview.html (ohne Server)
  api/generate.php         Endpunkt für EINE Stufe (Cache -> LLM -> Cache)
  api/prompt.php           Prompt bauen + Modellantwort validieren
  api/system_prompt.de.txt Systemprompt (ohne Code änderbar)
  api/demo_data.php        Statische 7-Stufen-Demo (demo_mode)
tools/connectivity.php   Vorab-Check nach Deployment – danach LÖSCHEN
```

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
   - zum Einrichten `debug => true`, im Livebetrieb wieder `false`.
4. Alle Dateien per FTP hochladen (Struktur beibehalten).
5. **`tools/connectivity.php`** im Browser aufrufen. Prüft DB + ausgehende
   HTTPS-Verbindungen (Anthropic/Crossref/Europe PMC). Läuft alles durch:
   **die Datei löschen.**
6. Startseite aufrufen und beide Modi testen.

`lib/config.php` enthält Geheimnisse und wird durch `.gitignore` **niemals**
eingecheckt.

### Zum Testen ohne DB

In `lib/config.php` `demo_mode => true` setzen: Der SciSpin-O-Mat liefert dann
statische Beispieldaten ohne API- oder DB-Zugriff.

## Der kritische Punkt

Beide Modi stehen und fallen mit **ausgehenden HTTPS-Verbindungen** vom Server zu
den externen APIs. Genau das prüft `tools/connectivity.php`. Zeigt es `HTTP 0`,
blockiert der Hoster den ausgehenden Verkehr – dann beim Support klären, bevor du
Zeit in Debugging steckst.

## Kostenschutz (nicht optional)

Öffentlicher Endpunkt + bezahlte LLM-API = Kostenfalle. In `lib/config.php`:

- `daily_llm_cap` – globale Obergrenze **aller** LLM-Calls pro Tag (Notbremse über
  beide Modi). **Achtung:** ein Studien-Check ist 1 Call, eine vollständige
  Spin-Analyse sind **7** Calls (eine pro Stufe).
- `rate_per_ip_per_hour` / `rate_hits_per_hour` – Studien-Checks bzw. Cache-Treffer
  pro Besucher/Stunde.
- `rate_per_hour` – Spin-Calls pro Besucher/Stunde (7 ≈ 1 Analyse).
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

## Modell-String

Die Vorlage nutzt `claude-sonnet-5`. Vor dem Produktivbetrieb gegen die aktuelle
Anthropic-Modell-Liste prüfen und ggf. anpassen.

## Grenzen (bewusst)

- Studien-Check: Volltext-Methodik meist nicht verfügbar (Abstract ist der Regelfall);
  kein Predatory-Verdikt; nicht-biomedizinische Felder schwächer abgedeckt.
- SciSpin-O-Mat: bewusst zugespitztes Demonstrationsinstrument, kein Autoren-Tool.

## Lizenz

MIT – siehe `LICENSE`.
