# Testing

## Ziel

Kurze manuelle Testmatrix für Lieferstatus/Dachser/Referenz-Extraktion plus Jobs/AB->Rechnung.

## Voraussetzungen

- Zugriff auf `middleware/lieferstatus.php`
- Zugriff auf `middleware/jobs.php`
- DB enthält `mw_addinol_refs` mit Addinol-Fällen
- PHP 8.3 verfügbar für Lint/CLI

## Testfälle

### 1) Zeile ohne AT

- Erwartung:
  - Badge `AT fehlt` sichtbar.
  - Button `AT-Scan` sichtbar.
  - Button `Dachser` nicht sichtbar.

### 2) Zeile mit AT

- Erwartung:
  - AT-Referenz als `<code>AT...`.
  - `Dachser` sichtbar.
  - `AT-Scan` nicht sichtbar.

### 3) API-Parse + Speicherung

1. Bei Zeile mit AT `API` klicken.
2. Erwartung im Modal:
   - `ok: true`
   - `extracted_status` gefüllt (z. B. `Zugestellt`)
   - `shipment_details.status_timestamp` gefüllt (falls vorhanden)
   - `shipment_details.via` / `shipment_details.info` gefüllt (falls vorhanden)
3. Erwartung in DB (`mw_addinol_refs`):
   - `dachser_status`
   - `dachser_status_ts`
   - `dachser_via`
   - `dachser_info`
   - `dachser_last_checked_at`

### 4) Tabellenanzeige

- In Spalte `Dachser`:
  - Status-Badge
  - Zeitstempel (deutsches Datumsformat)
  - `Via: ...`
  - `Info: ...`
  - `Check: ...`

### 5) Parser-Qualität

- Erwartung:
  - `dachser_status` enthält nur Status (nicht `Via/Info/Sendung...`).
  - `Via` und `Info` getrennt gespeichert.

### 6) Job-Erstellung bei `Zugestellt`

1. Bei einer Lieferung mit Statuswechsel auf `Zugestellt` `API` ausführen.
2. Erwartung:
   - API-JSON enthält `job_created: true` (beim ersten Wechsel).
   - In `mw_jobs` erscheint Job `Ware zugestellt`.
   - Schritt `AB in Rechnung umwandeln` ist vorhanden (`mw_job_steps`).

### 7) Job-Worker / AB->Rechnung

1. `bin/jobs_worker.php` per CLI starten.
2. Erwartung:
   - Für offenen Fall wird Rechnung erstellt.
   - Für bereits fakturierte AB wird idempotent gemeldet: `Rechnung existiert bereits`.
3. Erwartung in DB:
   - `mw_job_runs` enthält Eintrag mit `status = ok`.
   - `invoice.from_so_id = <AB-ID>` vorhanden.
   - `sales_orders.so_stage` auf `Closed - Shipped and Invoiced`.

### 8) System-Job Mail Poller sichtbar

1. `middleware/jobs.php` öffnen.
2. Erwartung:
   - Job `Mail Poller` vorhanden (`system:mail_poll_5m`).
   - Schritt `Mailbox pollen` mit `step_type = run_mail_poller` sichtbar.
   - Intervall: 5 Minuten.

## Technische Checks

```bash
phpenv shell 8.3 && php -l middleware/lieferstatus.php
phpenv shell 8.3 && php -l middleware/dachser_status.php
phpenv shell 8.3 && php -l bin/extract_addinol_refs.php
phpenv shell 8.3 && php -l middleware/jobs.php
phpenv shell 8.3 && php -l src/JobService.php
phpenv shell 8.3 && php -l bin/jobs_worker.php
```

## Optionaler DB-Check

```sql
SELECT sales_order_id, at_order_no, dachser_status, dachser_status_ts, dachser_via, dachser_info, dachser_last_checked_at
FROM mw_addinol_refs
ORDER BY updated_at DESC
LIMIT 20;
```

```sql
SELECT id, title, run_mode, status, next_run_at, last_run_at, last_result
FROM mw_jobs
ORDER BY id DESC
LIMIT 20;
```
