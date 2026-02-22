# Project Notes

## 2026-02-21

### Lieferstatus / Dachser

- `middleware/lieferstatus.php` erweitert:
  - `Dachser`-Link nutzt direkte Suche `.../shp2s/?javalocale=de_DE&search=<AT...>`.
  - `AT-Scan` wird nur bei fehlender AT-Nummer angezeigt.
  - `Dachser`-Button wird nur bei vorhandener AT-Nummer angezeigt.
  - Neue Tabellen-Spalte `Dachser` mit gespeicherten Werten aus `mw_addinol_refs`.

- `middleware/dachser_status.php` neu aufgebaut:
  - Serverseitiges Fetch + Parsing der Dachser-Trackingseite (kein offizieller API-Endpoint erforderlich).
  - Auto-Follow der JS/meta-refresh-Weiterleitung.
  - Parsing für:
    - `extracted_status` (z. B. `Zugestellt`)
    - `shipment_details.status_timestamp`
    - `shipment_details.via`
    - `shipment_details.info`
  - Persistenz in DB bei API-Klick über `purchase_order_id`.

### Datenbank

- Tabelle `mw_addinol_refs` erweitert um:
  - `dachser_status` (`VARCHAR(191)`)
  - `dachser_status_ts` (`DATETIME`)
  - `dachser_via` (`VARCHAR(255)`)
  - `dachser_info` (`VARCHAR(255)`)
  - `dachser_last_checked_at` (`DATETIME`)

### Wichtige Hinweise

- Status-Parsing wurde geschärft: `dachser_status` enthält nur den eigentlichen Status.
- Bestehende alte/falsch zusammengesetzte Werte werden erst beim nächsten `API`-Check je Datensatz überschrieben.

## 2026-02-22

### Jobs / ToDos

- Neue Job-Engine eingeführt:
  - `mw_jobs`
  - `mw_job_steps`
  - `mw_job_runs`
- Neue UI: `middleware/jobs.php` (Job anlegen, manuell starten, pausieren/aktivieren, letzte Runs sehen).
- Neuer Worker: `bin/jobs_worker.php` (Auto-Jobs per Cron).

### Event-Integration Dachser

- `middleware/dachser_status.php` erweitert:
  - erkennt Statuswechsel auf `Zugestellt`,
  - erstellt idempotent Job `Ware zugestellt` mit Schritt `AB in Rechnung umwandeln`.

### AB -> Rechnung

- `src/JobService.php` erweitert um echten Schritt `convert_ab_to_invoice`:
  - Idempotenzprüfung über `invoice.from_so_id`.
  - Anlage von `invoice` aus `sales_orders`.
  - Kopie von Gruppen/Zeilen/Adjustments:
    - `sales_order_line_groups` -> `invoice_line_groups`
    - `sales_order_lines` -> `invoice_lines`
    - `sales_order_adjustments` -> `invoice_adjustments`
  - Setzt AB-Status auf `Closed - Shipped and Invoiced`.

### Jobs UI / Bedienung

- `middleware/jobs.php` erweitert:
  - Schrittedetails werden über eigenes Modal angezeigt (Button mit `fa-tasks` + Anzahl).
  - Run-Historie wird über Modal angezeigt (`fa-history`).
  - Verlauf je Job kann geleert werden.
  - Neues Job-Formular im Modal; gleiches Modal für Anlegen + Bearbeiten.
  - System-Jobs können bearbeitet werden, Speichern ist mit zusätzlichem Warn-Dialog abgesichert.
  - Schrittdefinitionen können technisch als JSON gepflegt werden (`step_type`, `step_payload_json` sichtbar/editierbar).
  - Bezugsspalte zeigt sprechende, klickbare CRM-Links statt reiner IDs (gekürzt + FA-Icon).

### Neue System-Jobs / Worker

- `src/JobService.php` erweitert:
  - System-Job `system:mail_poll_5m` (alle 5 Minuten).
  - System-Job `system:lagerheini_daily_0800` (täglich 08:00).
  - System-Job `system:dachser_open_hourly` (stündlich).
  - Neuer Step-Typ `run_dachser_bulk_check`.
- `bin/dachser_bulk_check.php` neu:
  - Prüft stündlich alle offenen Datensätze mit AT-Nummer (`Status != Zugestellt`, Auftrag offen).
  - Nutzt denselben Status-Pfad wie API-Button (`middleware/dachser_status.php`) per CLI-Aufruf.

### Polling / Logs

- `bin/poll.php` erweitert:
  - schreibt Poll-Summary mit Zählern (`new`, `imported`, `pending`, `scanned`, Fehler etc.).
  - Summary erscheint im Job-Run-Ergebnis (`mw_job_runs.result_message`).

### Dachser Endpoint (CLI-Fallback)

- `middleware/dachser_status.php` angepasst:
  - liest JSON bei CLI zusätzlich aus `php://stdin` (wenn `php://input` leer ist).
  - ermöglicht dadurch konsistente Nutzung aus UI und Worker/Bulkjob.

### Scheduling-Fix

- `computeNextRunAt()` auf zeitzonenbasierte Berechnung umgestellt:
  - korrekte Anzeige/Berechnung `next_run_at` für `interval_minutes` und `daily_time`.
