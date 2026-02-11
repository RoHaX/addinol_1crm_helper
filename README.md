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

## Abhängigkeiten (lokal eingebunden)

- Bootstrap 5.3: `assets/bootstrap/`
- DataTables + jQuery: `assets/datatables/`
- Leaflet (OSM): `assets/leaflet/`

## Hinweise

- Datenbankzugang ist direkt in den PHP-Dateien konfiguriert.
- Einige Seiten nutzen Modals (iframe) für Detail-Views (`embed=1`).
- `lagerheini.php` versendet E-Mails, Texte werden zufällig aus Charakteren gewählt.
- Karten-Daten kommen aus `accounts` + `accounts_cstm` (Latitude/Longitude).

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

## Lokal testen

Beispiel (CLI):

```
php -v
php bilanz.php
```

Hinweis: E-Mail-Versand benötigt korrekt konfigurierten Mail-Server.
