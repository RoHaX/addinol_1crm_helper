# TODO

- [ ] 1CRM Konvertierung `Angebot -> Rechnung` analysieren und erweitern:
  Zusatzlogik für automatische, korrekte Steuerermittlung nach definierten Eigenschaften (aktuell setzt 1CRM die Steuer in bestimmten Fällen falsch).
- [ ] Dachser-Seitenparser härten und beobachten:
  Änderungen im Dachser-HTML früh erkennen, Regex/Parser bei Layout-Änderungen nachziehen.
- [ ] Historische Datensätze rückfüllen:
  vorhandene `mw_addinol_refs` mit leerem/alten `dachser_status` per Batch neu prüfen.
- [ ] `lieferstatus.php`: Filter `Nur AT fehlt` ergänzen (schnelle Abarbeitung offener Fälle).
- [ ] `bin/extract_addinol_refs.php`: OCR-Fallback für Scan-PDFs ergänzen (aktuell nur textbasierte PDFs via Ghostscript-Textextraktion).
- [ ] Optionales Matching verbessern:
  Für Fälle ohne `Ihre Bestellung` (BE) alternative Zuordnung über Note-/E-Mail-Verknüpfung und zusätzliche Heuristiken.
