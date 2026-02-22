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
