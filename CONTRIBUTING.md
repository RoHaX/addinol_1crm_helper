# Contributing

## Scope

Regeln für saubere, sichere Erweiterungen in diesem Projekt.

## Grundregeln

- Kleine, nachvollziehbare Änderungen.
- Bestehenden PHP-Stil beibehalten.
- Keine neuen DB-Zugangsdaten-Dateien; nur `db.inc.php` verwenden.
- Logging im JSON-Line-Format über `src/MwLogger.php` beibehalten.

## Datenbank-Änderungen

- Jede Schemaänderung dokumentieren in:
  - `MIGRATIONS.md`
  - `docs/DB_SCHEMA.md`
  - `schema.sql`
- Keine stillen DB-Änderungen ohne Dokumentation.

## UI / Lieferstatus

- In `middleware/lieferstatus.php` nur notwendige UI-Änderungen.
- Button-Logik:
  - `AT-Scan` nur wenn AT fehlt.
  - `Dachser` nur wenn AT vorhanden.
- Neue externe Calls möglichst serverseitig kapseln (Endpoint in `middleware/`).

## Externe Integrationen

- Bei instabilen HTML-Seiten Parsing robust halten:
  - defensive Regex
  - klare Fallbacks
  - Log-Einträge mit Kontext
- Neue Action/Job-Logik idempotent und retry-sicher gestalten.

## Vor dem Abschluss

1. PHP Lint für geänderte Dateien.
2. UI manuell prüfen (mind. 1 Fall mit/ohne AT).
3. DB-Speicherung stichprobenartig validieren.
4. Doku aktualisieren.

## Commit-Hinweise (falls genutzt)

- Aussagekräftige, kleine Commits.
- Technische Motivation kurz im Commit-Text.
- Keine geheimen Schlüssel/Tokens committen.
