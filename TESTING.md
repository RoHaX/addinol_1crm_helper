# Testing

## Ziel

Kurze manuelle Testmatrix für Lieferstatus/Dachser/Referenz-Extraktion.

## Voraussetzungen

- Zugriff auf `middleware/lieferstatus.php`
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

## Technische Checks

```bash
phpenv shell 8.3 && php -l middleware/lieferstatus.php
phpenv shell 8.3 && php -l middleware/dachser_status.php
phpenv shell 8.3 && php -l bin/extract_addinol_refs.php
```

## Optionaler DB-Check

```sql
SELECT sales_order_id, at_order_no, dachser_status, dachser_status_ts, dachser_via, dachser_info, dachser_last_checked_at
FROM mw_addinol_refs
ORDER BY updated_at DESC
LIMIT 20;
```
