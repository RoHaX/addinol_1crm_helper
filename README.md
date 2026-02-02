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

## Lokal testen

Beispiel (CLI):

```
php -v
php bilanz.php
```

Hinweis: E-Mail-Versand benötigt korrekt konfigurierten Mail-Server.
