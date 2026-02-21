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
