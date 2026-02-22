# Addinol 1CRM Helper

Kleine Sammlung interner Helfer-Skripte rund um 1CRM (Berichte, Exporte, Lager, offene Posten, Karten, etc.).

## Einstieg

- `index.php` leitet direkt auf `bilanz.php` weiter.

## Inhalte (Auszug)

- `bilanz.php` – Übersicht mit Charts, Auswertungen, Exporten
- `lagerstand.php` – Lagerstand mit Filter + DataTables
- `artikelliste.php` – Artikelliste mit DataTables + Detail-Modal
- `artikel_kunde.php` – Kunden je Artikel (Modal/Embed)
- `umsatzliste.php` – Umsatzliste mit Chart + Detail-Modal
- `kunde_artikel.php` – Artikel je Kunde (Modal/Embed)
- `offene_rechnungen.php` – Offene Rechnungen (Filter, Inline-Confirm, Modal)
- `offene_betraege_kunde.php` – Offene Beträge je Kunde (Modal, Inline-Confirm)
- `addinol_map.php` – OpenStreetMap/Leaflet Karte mit Filtern
- `lagerheini.php` – Automatisierte Lagerbestell-Mail
- `middleware/firmen.php` – Firmenliste + Detailansicht (inkl. Aktivitäten, E-Mail-Verlauf, Auf-einen-Blick)
- `middleware/lieferstatus.php` – Addinol-Bestellungen, Dachser-Referenzcheck, Extraktions-Trigger
- `middleware/jobs.php` – Job-/ToDo-Übersicht, manuelle Ausführung, Status, Run-Historie
- `bin/extract_addinol_refs.php` – PDF-Extraktion (`BE...`/`AT...`) aus Addinol-Rechnungsanhängen
- `bin/jobs_worker.php` – Ausführung geplanter Auto-Jobs

## Abhängigkeiten (lokal eingebunden)

- Bootstrap 5.3: `assets/bootstrap/`
- DataTables + jQuery: `assets/datatables/`
- Leaflet (OSM): `assets/leaflet/`

## Hinweise

- Datenbankzugang ist direkt in den PHP-Dateien konfiguriert.
- Einige Seiten nutzen Modals (iframe) für Detail-Views (`embed=1`).
- `lagerheini.php` versendet E-Mails, Texte werden zufällig aus Charakteren gewählt.
- Karten-Daten kommen aus `accounts` + `accounts_cstm` (Latitude/Longitude).
- Addinol-Rechnungs-PDFs liegen typischerweise unter `../files/upload/...` (über `notes.filename`).

## Agent Rules

- Siehe `AGENTS.md`.

## DB Schema

- Doku: `docs/DB_SCHEMA.md`
- Snapshot: `schema.sql`

## Middleware

Poller (CLI):

```
php bin/poll.php
FORCE_RECHECK=1 php bin/poll.php
```

Poller updates status to `imported` when 1CRM email exists, else `pending_import`.
Pending imports are created in 1CRM (optional link via `MW_DEFAULT_PARENT_TYPE` + `MW_DEFAULT_PARENT_ID` env).

Action endpoint (POST JSON, header `X-ACTION-KEY`):

```
curl -s -X POST -H "Content-Type: application/json" -H "X-ACTION-KEY: YOUR_KEY" \
  -d '{"tracked_mail_id":123,"action_type":"CREATE_TASK"}' \
  https://your-host/crm/roman/middleware/action.php
```

Worker (CLI):

```
php bin/worker.php
```

### Lieferstatus / Addinol-Referenzen

- Seite: `middleware/lieferstatus.php`
- Datenbasis: `purchase_orders` (Addinol), optional verknüpfter `sales_orders`-Status.
- Referenzquelle: `mw_addinol_refs.at_order_no` (Fallback: `BE...`/Bestellnummer).
- `AT-Scan`: startet asynchronen Extraktionsjob für die gewählte Bestellung/Note (nur sichtbar wenn AT fehlt).
- `Dachser`: Quick-Link zur Tracking-Suche mit `search=<AT...>` (nur sichtbar wenn AT vorhanden).
- `Erneut extrahieren`: startet asynchronen Voll-/Teilscan im Hintergrund (kein Web-Timeout).
- `API`: ruft `middleware/dachser_status.php` auf, parst Dachser-Seite serverseitig und speichert Ergebnisse.
- Bei Statuswechsel auf `Zugestellt`: erstellt automatisch einen Job `Ware zugestellt` mit Schritt `AB in Rechnung umwandeln`.

Gespeicherte Dachser-Felder in `mw_addinol_refs`:

- `dachser_status` (z. B. `Zugestellt`)
- `dachser_status_ts` (Zeitstempel aus `Status (...)`)
- `dachser_via`
- `dachser_info`
- `dachser_last_checked_at`

Extraktor (CLI, empfohlen mit festem PHP-Binary):

```
/opt/plesk/php/8.3/bin/php bin/extract_addinol_refs.php
```

Beispiele:

```
FORCE_RECHECK=1 LIMIT=5000 /opt/plesk/php/8.3/bin/php bin/extract_addinol_refs.php
FORCE_RECHECK=1 TARGET_NOTE_ID=<note-id> LIMIT=20 /opt/plesk/php/8.3/bin/php bin/extract_addinol_refs.php
FORCE_RECHECK=1 TARGET_PO_ID=<purchase-order-id> LIMIT=120 /opt/plesk/php/8.3/bin/php bin/extract_addinol_refs.php
```

Status-/Log-Dateien:

- `logs/extract_addinol_refs.status.json`
- `logs/extract_addinol_refs.log`

### Jobs / ToDos

- Seite: `middleware/jobs.php`
- Tabellen: `mw_jobs`, `mw_job_steps`, `mw_job_runs`
- Unterstützte Pläne:
  - `once` (einmalig)
  - `interval_minutes` (mehrmals täglich)
  - `daily_time` (täglich fixe Uhrzeit)
- Ausführungsarten:
  - `manual`
  - `auto` (über `bin/jobs_worker.php` + Cron)
- Spezial-Step:
  - `convert_ab_to_invoice`: wandelt AB (`sales_orders`) in Rechnung (`invoice`) um.
  - Idempotent: wenn bereits Rechnung zu `from_so_id` existiert, wird keine zweite erstellt.
  - `run_mail_poller`: führt `bin/poll.php` aus.
- System-Job:
  - `Mail Poller` (`job_key = system:mail_poll_5m`) wird automatisch angelegt und läuft alle 5 Minuten über `jobs_worker`.

Beispiel:

```bash
/opt/plesk/php/8.3/bin/php bin/jobs_worker.php
```

Cron:

```cron
*/1 * * * * /opt/plesk/php/8.3/bin/php /var/www/vhosts/addinol-lubeoil.at/httpdocs/crm/roman/bin/jobs_worker.php >> /var/www/vhosts/addinol-lubeoil.at/httpdocs/crm/roman/logs/jobs_worker.log 2>&1
```

## Lokal testen

Beispiel (CLI):

```
php -v
php bilanz.php
```

Hinweis: E-Mail-Versand benötigt korrekt konfigurierten Mail-Server.
