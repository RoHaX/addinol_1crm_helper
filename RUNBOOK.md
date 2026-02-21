# Runbook

## Ziel

Betriebsanleitung für Lieferstatus-, Referenz-Extraktion und Dachser-Parsing.

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

## Monitoring / Logs

- Laufstatus Extraktion:
  - `logs/extract_addinol_refs.status.json`
- Extraktions-Log:
  - `logs/extract_addinol_refs.log`
- Middleware-Events:
  - `logs/mw-YYYY-MM-DD.log`

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
