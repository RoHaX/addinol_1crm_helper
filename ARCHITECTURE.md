# Architecture

## Scope

Dieses Dokument beschreibt die Middleware- und Lieferstatus-Architektur im Projekt `roman`.

## Core Components

- UI:
  - `middleware/lieferstatus.php`
  - `middleware/mailboard.php`
- Endpoints:
  - `middleware/dachser_status.php` (serverseitiges Dachser-Parsing)
  - `middleware/action.php` (Action-Queue enqueue)
  - `middleware/poll.php` (Poller trigger per HTTP)
- CLI:
  - `bin/poll.php` (IMAP einlesen/klassifizieren/importieren)
  - `bin/worker.php` (Queue-Worker)
  - `bin/extract_addinol_refs.php` (BE/AT aus PDF extrahieren)
- Shared:
  - `db.inc.php` (DB-Verbindung)
  - `src/MwLogger.php` (JSON-Line Logging)
  - `middleware/config.php` (optionale Middleware-Konfiguration)

## Data Flow: Lieferstatus / Dachser

1. `middleware/lieferstatus.php` lädt Bestellungen und Referenzen (`mw_addinol_refs`).
2. Nutzer klickt `API` pro Zeile.
3. Browser sendet POST an `middleware/dachser_status.php` mit:
   - `reference` (AT/BE/Bestellnummer fallback)
   - `purchase_order_id`
4. `middleware/dachser_status.php`:
   - lädt Dachser-Trackingseite,
   - folgt ggf. JS/meta-refresh-Continue-Link,
   - parst `extracted_status`, `status_timestamp`, `via`, `info`,
   - speichert Ergebnis in `mw_addinol_refs`,
   - liefert JSON an UI zurück.
5. `middleware/lieferstatus.php` zeigt gespeicherte Dachser-Werte in der Tabelle.

## Data Model (Relevant)

`mw_addinol_refs`:
- `sales_order_id` (PO-ID, historischer Name)
- `be_order_no`
- `at_order_no`
- `dachser_status`
- `dachser_status_ts`
- `dachser_via`
- `dachser_info`
- `dachser_last_checked_at`
- `note_id`, `email_id`, `source_filename`
- `extracted_at`, `updated_at`

## Logging

- JSON lines in `logs/mw-YYYY-MM-DD.log`
- Felder: `ts`, `level`, `message`, `context`
- Dachser-Parsing schreibt u.a.:
  - `reference`
  - `purchase_order_id`
  - `http_code`
  - `effective_url`
  - `extracted_status`

## Design Constraints

- DB-Zugriff über bestehendes `db.inc.php`.
- Änderungen klein und konsistent mit bestehendem PHP-Stil.
- Retry-sichere/robuste Verarbeitung bevorzugen (insb. bei externen Seiten).
