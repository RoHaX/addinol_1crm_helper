# Migrations

## Purpose

Chronologische Übersicht manueller DB-Änderungen für dieses Projekt.

## 2026-02-21 - Dachser Tracking Fields

### SQL

```sql
ALTER TABLE mw_addinol_refs
  ADD COLUMN IF NOT EXISTS dachser_status VARCHAR(191) NULL AFTER at_order_no,
  ADD COLUMN IF NOT EXISTS dachser_status_ts DATETIME NULL AFTER dachser_status,
  ADD COLUMN IF NOT EXISTS dachser_via VARCHAR(255) NULL AFTER dachser_status_ts,
  ADD COLUMN IF NOT EXISTS dachser_info VARCHAR(255) NULL AFTER dachser_via,
  ADD COLUMN IF NOT EXISTS dachser_last_checked_at DATETIME NULL AFTER dachser_info;
```

### Reason

Persistenz der serverseitig geparsten Dachser-Werte (`Status`, `Status-Zeit`, `Via`, `Info`, letzter Check), damit:
- Lieferstatus-Tabelle ohne erneute externe Anfrage anzeigbar ist.
- Historie/Arbeitsstand sichtbar bleibt.

### Rollback (if required)

```sql
ALTER TABLE mw_addinol_refs
  DROP COLUMN IF EXISTS dachser_last_checked_at,
  DROP COLUMN IF EXISTS dachser_info,
  DROP COLUMN IF EXISTS dachser_via,
  DROP COLUMN IF EXISTS dachser_status_ts,
  DROP COLUMN IF EXISTS dachser_status;
```

Hinweis: Rollback löscht gespeicherte Trackingdaten.

## 2026-02-22 - Job/ToDo Engine

### SQL

```sql
CREATE TABLE IF NOT EXISTS mw_jobs (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  job_key VARCHAR(255) NULL,
  title VARCHAR(255) NOT NULL,
  job_type VARCHAR(64) NOT NULL DEFAULT 'generic',
  description TEXT NULL,
  relation_type ENUM('none','sales_order','purchase_order','account','customer','other') NOT NULL DEFAULT 'none',
  relation_id CHAR(36) NULL,
  account_id CHAR(36) NULL,
  payload_json JSON NULL,
  schedule_type ENUM('once','interval_minutes','daily_time') NOT NULL DEFAULT 'once',
  run_mode ENUM('manual','auto') NOT NULL DEFAULT 'manual',
  run_at DATETIME NULL,
  next_run_at DATETIME NULL,
  interval_minutes INT UNSIGNED NULL,
  daily_time TIME NULL,
  timezone VARCHAR(64) NOT NULL DEFAULT 'Europe/Vienna',
  status ENUM('active','paused','done','error','archived') NOT NULL DEFAULT 'active',
  last_run_at DATETIME NULL,
  last_result ENUM('ok','error','skipped') NULL,
  last_result_message TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_job_key (job_key),
  KEY idx_job_status_mode_next (status, run_mode, next_run_at),
  KEY idx_job_relation (relation_type, relation_id),
  KEY idx_job_type (job_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS mw_job_steps (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  job_id BIGINT UNSIGNED NOT NULL,
  step_order INT UNSIGNED NOT NULL DEFAULT 1,
  step_title VARCHAR(255) NOT NULL,
  step_type VARCHAR(64) NOT NULL DEFAULT 'note',
  step_payload_json JSON NULL,
  due_at DATETIME NULL,
  is_required TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_job_step_order (job_id, step_order),
  KEY idx_job_steps_due (job_id, due_at),
  CONSTRAINT fk_job_steps_job FOREIGN KEY (job_id) REFERENCES mw_jobs(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS mw_job_runs (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  job_id BIGINT UNSIGNED NOT NULL,
  trigger_type ENUM('manual','schedule','event') NOT NULL DEFAULT 'manual',
  started_at DATETIME NOT NULL,
  finished_at DATETIME NULL,
  status ENUM('running','ok','error','skipped') NOT NULL DEFAULT 'running',
  result_message TEXT NULL,
  result_json JSON NULL,
  executed_by VARCHAR(64) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_job_runs_job (job_id, started_at),
  KEY idx_job_runs_status (status, started_at),
  CONSTRAINT fk_job_runs_job FOREIGN KEY (job_id) REFERENCES mw_jobs(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
```

### Reason

Einheitliche Job/ToDo-Struktur für:
- Event-getriebene Jobs (z.B. Dachser-Statuswechsel auf `Zugestellt`).
- Manuelle und später automatische Ausführung.
- Mehrstufige Arbeitsschritte mit Fälligkeit.
- Lauf-/Ergebnis-Historie.

### Rollback (if required)

```sql
DROP TABLE IF EXISTS mw_job_runs;
DROP TABLE IF EXISTS mw_job_steps;
DROP TABLE IF EXISTS mw_jobs;
```

Hinweis: Rollback löscht Job-, Schritt- und Ausführungsdaten.
