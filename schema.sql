-- SciSpin – zusammengeführtes Datenbankschema (check/ + spin/)
-- In phpMyAdmin in die eingerichtete Datenbank importieren.
-- Beide Modi teilen sich rate_limit und llm_daily (eine gemeinsame Kostenbremse).

-- ==================== Gemeinsam ====================

-- Stundenbasiertes Rate-Limit pro anonymisiertem Besucher.
-- bucket z. B. "check-llm:<iphash>", "check-hit:<iphash>", "spin:<iphash>".
CREATE TABLE IF NOT EXISTS rate_limit (
  bucket       VARCHAR(160) NOT NULL,
  window_start INT          NOT NULL,   -- Beginn des Stundenfensters (Unix)
  cnt          INT          NOT NULL DEFAULT 0,
  PRIMARY KEY (bucket, window_start)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Globale Tages-Notbremse über ALLE LLM-Calls beider Modi.
CREATE TABLE IF NOT EXISTS llm_daily (
  day DATE NOT NULL,
  cnt INT  NOT NULL DEFAULT 0,
  PRIMARY KEY (day)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ==================== Modus "check" (Studien-Check) ====================

-- Ergebnis-Cache pro Studie (DOI, sonst URL-Hash).
CREATE TABLE IF NOT EXISTS cache (
  doi         VARCHAR(255) NOT NULL,
  result      MEDIUMTEXT   NOT NULL,   -- vollständiges JSON-Ergebnis
  is_preprint TINYINT(1)   NOT NULL DEFAULT 0,
  created_at  INT          NOT NULL,
  expires_at  INT          NULL,       -- NULL = unbefristet
  PRIMARY KEY (doi)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Dauerhaftes, durchsuchbares Archiv aller analysierten Studien.
CREATE TABLE IF NOT EXISTS archive (
  cache_key      VARCHAR(255) NOT NULL,   -- DOI oder "url:<sha1>"
  doi            VARCHAR(255) NULL,
  title          TEXT         NULL,
  journal        VARCHAR(255) NULL,
  ampel          VARCHAR(16)  NULL,       -- gruen|gelb|rot|grau
  badge          VARCHAR(16)  NULL,       -- peer|preprint|unklar
  is_preprint    TINYINT(1)   NOT NULL DEFAULT 0,
  datenbasis     VARCHAR(16)  NULL,       -- abstract|volltext
  core_statement TEXT         NULL,
  source         TEXT         NULL,
  result         MEDIUMTEXT   NULL,       -- vollständiges JSON-Ergebnis
  created_at     INT          NOT NULL,
  updated_at     INT          NOT NULL,
  PRIMARY KEY (cache_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ==================== Modus "spin" (SciSpin-O-Mat) ====================

-- Ergebnis-Cache: eine Zeile pro (Eingabe-Hash, Stufe).
CREATE TABLE IF NOT EXISTS spin_cache (
  input_hash   CHAR(64)   NOT NULL,   -- SHA-256 des normalisierten Eingabetexts
  stufe        TINYINT    NOT NULL,   -- -3 .. 3
  payload      MEDIUMTEXT NOT NULL,   -- JSON: eine Stufe (text, kommentar, aenderungen)
  erstellt_am  TIMESTAMP  NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (input_hash, stufe)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
