# Runbook

## Ziel

Betriebsanleitung für Lieferstatus-, Referenz-Extraktion, Dachser-Parsing und Job-Automation.

## Daily Workflow (Lieferstatus)

1. `middleware/lieferstatus.php` öffnen.
2. Fälle mit fehlender AT-Nummer über Badge `AT fehlt` identifizieren.
3. Bei fehlender AT: `AT-Scan` klicken.
4. Bei vorhandener AT: `API` klicken, danach DB-Werte in Spalte `Dachser` prüfen.
5. Optional `Dachser` klicken für externe Plausibilisierung.

## CLI Jobs

Extraktion:
```bash
/opt/plesk/php/8.3/bin/php bin/extract_addinol_refs.php
```

Voll-Scan:
```bash
FORCE_RECHECK=1 LIMIT=5000 /opt/plesk/php/8.3/bin/php bin/extract_addinol_refs.php
```

Gezielt pro Bestellung:
```bash
FORCE_RECHECK=1 TARGET_PO_ID=<purchase-order-id> LIMIT=120 /opt/plesk/php/8.3/bin/php bin/extract_addinol_refs.php
```

Job-Worker (Auto-Jobs):
```bash
/opt/plesk/php/8.3/bin/php bin/jobs_worker.php
```

Job-Worker mit Limit:
```bash
JOB_LIMIT=50 /opt/plesk/php/8.3/bin/php bin/jobs_worker.php
```

Hinweis:
- Mail-Polling läuft als System-Job (`Mail Poller`) im `jobs_worker` (Intervall 5 Minuten).
- Dachser-Bulkcheck läuft als System-Job (`Dachser API Check (offen)`) im `jobs_worker` (Intervall 60 Minuten).
- Kein separater Poll-Cron nötig.

## Cron Vorschlag

```cron
*/1 * * * * /opt/plesk/php/8.3/bin/php /var/www/vhosts/addinol-lubeoil.at/httpdocs/crm/roman/bin/jobs_worker.php >> /var/www/vhosts/addinol-lubeoil.at/httpdocs/crm/roman/logs/jobs_worker.log 2>&1
```

Optional (falls weniger Frequenz gewünscht):

```cron
*/2 * * * * /opt/plesk/php/8.3/bin/php /var/www/vhosts/addinol-lubeoil.at/httpdocs/crm/roman/bin/jobs_worker.php >> /var/www/vhosts/addinol-lubeoil.at/httpdocs/crm/roman/logs/jobs_worker.log 2>&1
```

Plesk-Hinweis:
- Redirects (`>> ... 2>&1`) dürfen nicht als gequotete Argumente (`'>>'`, `'2>&1'`) eingetragen werden.
- Kein `--` vor den Redirects setzen.

## Monitoring / Logs

- Laufstatus Extraktion:
  - `logs/extract_addinol_refs.status.json`
- Extraktions-Log:
  - `logs/extract_addinol_refs.log`
- Middleware-Events:
  - `logs/mw-YYYY-MM-DD.log`
- Job-Worker:
  - `logs/jobs_worker.log`
- Poll-Summary:
  - `bin/poll.php` schreibt `Poll summary: new=..., imported=..., pending=...`
  - Diese Zeile ist im jeweiligen Run-Ergebnis in `middleware/jobs.php` sichtbar.

## Job-Flow (Zugestellt)

1. In `middleware/lieferstatus.php` auf `API` klicken.
2. Wenn Dachser-Status auf `Zugestellt` wechselt, wird automatisch Job `Ware zugestellt` erstellt.
3. Cron führt `bin/jobs_worker.php` aus.
4. Job-Step `AB in Rechnung umwandeln` erstellt Rechnung oder meldet idempotent "Rechnung existiert bereits".
5. Ergebnisse sind sichtbar in `middleware/jobs.php` und in `mw_job_runs`.

## Job-Flow (Stündlicher Dachser-Check)

1. System-Job `Dachser API Check (offen)` läuft stündlich über `bin/jobs_worker.php`.
2. Step `run_dachser_bulk_check` startet `bin/dachser_bulk_check.php`.
3. Das Script ruft pro offenem Datensatz (`AT vorhanden`, `Status != Zugestellt`, `Auftrag offen`) `middleware/dachser_status.php` auf.
4. Bei Statuswechsel auf `Zugestellt` wird automatisch der Folge-Job `Ware zugestellt` erstellt.

Hinweis:
- `middleware/dachser_status.php` unterstützt dafür auch CLI-Input über `php://stdin` (Fallback zu `php://input`).

## Troubleshooting

1. `API` liefert keinen Status:
- Prüfen, ob Dachser-Seite erreichbar ist.
- `raw_text` im API-Modal prüfen (liefert Parser-Hinweise).
- `logs/mw-YYYY-MM-DD.log` auf `dachser_tracking_*` prüfen.

2. Status wird nicht in DB gespeichert:
- Prüfen, ob `purchase_order_id` mitgegeben wird.
- Prüfen, ob Datensatz in `mw_addinol_refs` existiert.
- Fallback-Match über `at_order_no` kontrollieren.

3. Falscher zusammengesetzter Status:
- Parser in `middleware/dachser_status.php` anpassen.
- Danach betroffene Fälle erneut via `API` aktualisieren.

## Konfiguration

IMAP/Jobs (ENV, keine Klartext-Secrets im Repo):
- `MW_IMAP_HOST` (z. B. `server.haselsberger.at`)
- `MW_IMAP_PORT` (z. B. `993`)
- `MW_IMAP_USER`
- `MW_IMAP_PASS`
- `MW_IMAP_FLAGS` (z. B. `/imap/ssl`)
- `MW_IMAP_MAILBOXES` (z. B. `INBOX`)
- `MW_ACTION_KEY`

Mailtest DB (ENV):
- `CRM_DB_HOST` (default `localhost`)
- `CRM_DB_NAME` (default `addinol_crm`)
- `CRM_DB_USER`
- `CRM_DB_PASS`

PDF-Export API (ENV):
- `CRM_API_USER`
- `CRM_API_PASS`

In `middleware/config.php` optional:
- `MW_DACHSER_TRACKING_URL_BASE`
- `MW_DACHSER_TRACKING_TIMEOUT`
- (optional API-Konstanten für spätere API-Umstellung)

## Recovery

Wenn Parsing-Regeln nach Dachser-Änderung brechen:
1. Regex in `middleware/dachser_status.php` anpassen.
2. `php -l` prüfen.
3. Stichprobe mit 2-3 bekannten AT-Referenzen.
4. Optional Batch-Neuprüfung historischer Datensätze durchführen.
