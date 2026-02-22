# TODO

- [ ] AB->Rechnung-Joblogik fachlich verfeinern:
  Steuer-/Rabatt-Sonderlogik je Produkt/Kunde validieren und bei Bedarf nachschärfen.
- [ ] Dachser-Seitenparser härten und beobachten:
  Änderungen im Dachser-HTML früh erkennen, Regex/Parser bei Layout-Änderungen nachziehen.
- [ ] Historische Datensätze rückfüllen:
  vorhandene `mw_addinol_refs` mit leerem/alten `dachser_status` per Batch neu prüfen.
- [ ] Job-Monitoring verbessern:
  Dashboard-Kennzahlen für `mw_jobs`/`mw_job_runs` (Fehlerquote, Durchlaufzeit, fällige Jobs).
- [ ] `lieferstatus.php`: Filter `Nur AT fehlt` ergänzen (schnelle Abarbeitung offener Fälle).
- [ ] `bin/extract_addinol_refs.php`: OCR-Fallback für Scan-PDFs ergänzen (aktuell nur textbasierte PDFs via Ghostscript-Textextraktion).
- [ ] Optionales Matching verbessern:
  Für Fälle ohne `Ihre Bestellung` (BE) alternative Zuordnung über Note-/E-Mail-Verknüpfung und zusätzliche Heuristiken.
- [ ] Telegram-Inbound ergänzen:
  Antworten auf Bot-Nachrichten verarbeiten (z. B. `/status`, `/jobs`) via `getUpdates`-Poller oder Webhook-Endpoint.

## Security

- [ ] `CRITICAL`: Schreibende Endpoints härten (`middleware/jobs.php`, `middleware/lieferstatus.php`):
  Verbindlichen Auth-Guard und CSRF-Schutz für alle `POST`-Aktionen einbauen.
- [ ] `CRITICAL`: Script-Ausführung in `src/JobService.php` einschränken:
  Für `executePhpScript()` harte Allowlist auf erlaubte Scripts einführen (kein frei steuerbarer Script-Pfad aus Payload).
- [ ] `MEDIUM`: Pfad-Containment in `bin/extract_addinol_refs.php` ergänzen:
  Vor Verarbeitung sicherstellen, dass `realpath($absFile)` innerhalb von `uploadRoot` liegt (Traversal verhindern).
- [ ] `MEDIUM`: Umgang mit `X-ACTION-KEY` im Browser verbessern (`middleware/mailboard.php`):
  Kein Persistieren in `sessionStorage`; stattdessen kurzlebige Server-Session/Token-Flow oder geschützte serverseitige Ausführung.
