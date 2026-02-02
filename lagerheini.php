<?php
$startzeit = microtime(true);
$link = mysqli_connect('localhost', 'addinol_usr', 'lwT1e99~', 'addinol_crm');
mysqli_set_charset($link, "utf8");


// 1. BesucheAnzJahr für alle auf 0 setzen
$sqlReset = "UPDATE addinol_crm.accounts_cstm
             SET BesucheAnzJahr = 0";
if (!mysqli_query($link, $sqlReset)) {
    die("Fehler beim Zurücksetzen von BesucheAnzJahr: " . mysqli_error($link));
}

// 2. BesucheAnzJahr aktualisieren mit Meeting-Anzahl der letzten 365 Tage
$sqlUpdate = "UPDATE addinol_crm.accounts_cstm AS a
              JOIN (
                  SELECT
                      account_id,
                      COUNT(*) AS AnzahlMeetingsAktJahr
                  FROM addinol_crm.meetings
                  WHERE status = 'Held'
                    AND date_start >= DATE_SUB(CURDATE(), INTERVAL 365 DAY)
                  GROUP BY account_id
              ) m ON a.id_c = m.account_id
              SET a.BesucheAnzJahr = m.AnzahlMeetingsAktJahr";
if (!mysqli_query($link, $sqlUpdate)) {
    die("Fehler beim Aktualisieren von BesucheAnzJahr: " . mysqli_error($link));
}
// Anzahl aktualisierter Datensätze ausgeben
$affectedRows = mysqli_affected_rows($link);
$addText = "Anzahl Besuche/Jahr aktualisiert: betroffene Datensätze: $affectedRows<br>";

// BesucheAnzGesamt aktualisieren 
$sqlUpdateGesamt = "UPDATE addinol_crm.accounts_cstm AS a
                    JOIN (
                        SELECT
                            account_id,
                            COUNT(*) AS AnzahlMeetings
                        FROM addinol_crm.meetings
                        WHERE status = 'Held'
                        GROUP BY account_id
                    ) m ON a.id_c = m.account_id
                    SET a.BesucheAnzGesamt = m.AnzahlMeetings";
if (!mysqli_query($link, $sqlUpdateGesamt)) {
    die("Fehler beim Aktualisieren von BesucheAnzGesamt: " . mysqli_error($link));
}
// Anzahl aktualisierter Datensätze ausgeben
$affectedRows = mysqli_affected_rows($link);
$addText .= "Anzahl Besuche gesamt aktualisiert: betroffene Datensätze: $affectedRows<br>";

$overdueCount = 0;
$sqlOverdue = "SELECT COUNT(*) AS cnt
    FROM addinol_crm.invoice
    WHERE amount_due <> 0
      AND due_date <> ''
      AND due_date < CURDATE()";
if ($resultOverdue = mysqli_query($link, $sqlOverdue)) {
    if ($rowOverdue = mysqli_fetch_assoc($resultOverdue)) {
        $overdueCount = (int)$rowOverdue['cnt'];
    }
}

$charactersJson = '[
  {
    "name": "Josef",
    "greeting": "Geehrteste, hochwohlgeborene und erlauchte Geschäftsführung,",
    "intro": "Euer demütigster und ergebenster Diener ist nun wieder getreulich jeden Tag ab der achten Stunde des Morgens zu Eurer Verfügung, wobei ich mich mit den Zeiten durchaus Eurem gnädigen Willen anzupassen weiß.",
    "proposal": "Was meinen bescheidenen Vorschlag zur Bestellung betrifft, so lautet er wie folgt:",
    "outro": "Die ehrenvolle Inventur habe ich in %s Sekunden vollbracht, und mit dieser edlen Tat nehme ich mir die Freiheit, mich für den heutigen Tag in aller Bescheidenheit zurückzuziehen.",
    "closing": "Mit tiefster Ergebenheit und stets zu Euren Diensten,<br>Euer treuester Lagerheini"
  },
  {
    "name": "Rudi",
    "greeting": "Servus Geschäftsführung,",
    "intro": "Ich bin wieder ab 8:00 am Start und passe mich halt dem an, was ansteht. Ich hab’s im Griff, aber es wär fein, wenn’s klar bleibt.",
    "proposal": "Mein Bestellvorschlag schaut so aus:",
    "outro": "Inventur war in %s Sekunden erledigt. Ich meld mich wieder, wenn was brennt.",
    "closing": "Gruß,<br>Rudi"
  },
  {
    "name": "Susi",
    "greeting": "Hallo zusammen,",
    "intro": "Ich bin ab 8 Uhr wieder fix im Lager und halte euch den Rücken frei. Ihr sagt’s an, ich liefer’ zuverlässig.",
    "proposal": "Hier mein Vorschlag zur Bestellung:",
    "outro": "Inventur in %s Sekunden – schnell wie der Blitz. Ich mach dann weiter.",
    "closing": "Liebe Grüße,<br>Susi"
  },
  {
    "name": "Max",
    "greeting": "Guten Morgen,",
    "intro": "Ich bin ab 08:00 verfügbar und halte das Lager am Laufen. Zeiten passe ich an, wenn’s nötig ist.",
    "proposal": "Bestellvorschlag:",
    "outro": "Inventur: %s Sekunden. Ich bin fertig und mach weiter.",
    "closing": "Viele Grüße,<br>Max"
  },
  {
    "name": "Hannes",
    "greeting": "Grüß euch,",
    "intro": "Ab 8 Uhr bin ich wieder da. Wenn was ist, meldet’s euch, ich regel’s.",
    "proposal": "Mein Vorschlag:",
    "outro": "Inventur in %s Sekunden. Passt.",
    "closing": "lg<br>Hannes"
  },
  {
    "name": "Petra",
    "greeting": "Sehr geehrte Geschäftsführung,",
    "intro": "Ich bin täglich ab 08:00 Uhr verfügbar und richte mich nach Ihrem Bedarf. Der Betrieb läuft stabil.",
    "proposal": "Mein Bestellvorschlag im Überblick:",
    "outro": "Die Inventur dauerte %s Sekunden. Ich stehe weiterhin zur Verfügung.",
    "closing": "Mit freundlichen Grüßen,<br>Petra"
  },
  {
    "name": "Ali",
    "greeting": "Hallo zusammen,",
    "intro": "Ab 8 bin ich wieder am Start und halte das Lager sauber im Griff. Sagt’s, was gebraucht wird.",
    "proposal": "Bestellvorschlag:",
    "outro": "Inventur in %s Sekunden. Läuft.",
    "closing": "Beste Grüße,<br>Ali"
  },
  {
    "name": "Tom",
    "greeting": "Guten Tag,",
    "intro": "Ich bin ab 8 Uhr verfügbar. Ich mach’s wie immer ordentlich, aber bitte klare Ansagen, dann geht’s schneller.",
    "proposal": "Mein Vorschlag zur Bestellung:",
    "outro": "Inventur in %s Sekunden. Ich bin durch.",
    "closing": "Gruß,<br>Tom"
  },
  {
    "name": "Erika",
    "greeting": "Sehr geehrte Damen und Herren,",
    "intro": "Ich stehe täglich ab 08:00 Uhr zur Verfügung und passe mich den betrieblichen Erfordernissen an.",
    "proposal": "Nachfolgend der Bestellvorschlag:",
    "outro": "Die Inventur war in %s Sekunden abgeschlossen.",
    "closing": "Mit freundlichen Grüßen,<br>Erika"
  },
  {
    "name": "Basti",
    "greeting": "Servus miteinander,",
    "intro": "Ab 8 Uhr bin ich wieder am Start. Ich hab’s Lager im Griff und sorg dafür, dass nix ausgeht.",
    "proposal": "Hier der Bestellvorschlag:",
    "outro": "Inventur in %s Sekunden – ich geh dann wieder ans Werk.",
    "closing": "LG,<br>Basti"
  },
  {
    "name": "Gernot",
    "greeting": "Grüß Gott,",
    "intro": "Ich bin ab 8 Uhr im Lager. Es ist wie immer zu wenig Zeit und zu viel Zeug, aber ich mach’s halt.",
    "proposal": "Was bestellt werden muss, steht hier:",
    "outro": "Inventur %s Sekunden. Ich hab’s erledigt, obwohl’s wieder keiner dankt.",
    "closing": "Grummelgrüße,<br>Gernot"
  },
  {
    "name": "Mehmet",
    "greeting": "Hallo Chef,",
    "intro": "Ich ab 8 Uhr da, mache Lager sauber und alles ok. Wenn was fehlt, ich sage, guckst du.",
    "proposal": "Mein Vorschlag fuer Einkaufen, alder:",
    "outro": "Inventur %s Sekunden, ich fertig.",
    "closing": "Gruss,<br>Mehmet"
  },
  {
    "name": "Ivan",
    "greeting": "Guten Tag,",
    "intro": "Ich bin ab 8 Uhr da. Ich mache Lager, alles ordentlich, bisschen schnell.",
    "proposal": "Hier Bestellung - mach so bestens:",
    "outro": "Inventur %s Sekunden, ich weiter arbeiten.",
    "closing": "Gruss,<br>Ivan"
  },
  {
    "name": "Maja",
    "greeting": "Hallo,",
    "intro": "Ich komme ab 8 Uhr. Ich passen auf Lager auf, wenn Problem bitte sagen.",
    "proposal": "Bestellung so:",
    "outro": "Inventur %s Sekunden, passt.",
    "closing": "Danke,<br>Maja"
  },
  {
    "name": "Grigori",
    "greeting": "Guten Tag Chef,",
    "intro": "Ich bin 8 Uhr da, ich alles im Lager mache, ich schaue dass Ware genug.",
    "proposal": "Du einkaufen:",
    "outro": "Inventur war schnelle %s Sekunden schon fertig.",
    "closing": "Do svidaniya,<br>Grigori"
  },
  {
    "name": "Nikolai",
    "greeting": "Guten Tag,",
    "intro": "Ich bin ab 8 Uhr da, ich mache Lager, alles gut. Wenn was fehlt, ich melden, alder.",
    "proposal": "Bestellung Vorschlag:",
    "outro": "Inventur %s Sekunden, ich gehe weiter.",
    "closing": "Gruss,<br>Nikolai"
  }
]';
$characters = json_decode($charactersJson, true);
$character = $characters[array_rand($characters)];

$strSQL = "SELECT products.name AS productname, products.id AS productid, Sum(products.cost) as cost, Sum(products_warehouses.in_stock) AS lagerstand, products_warehouses.product_id, products_cstm.mindestbestand, products_warehouses.date_modified as aenderung
    FROM (products INNER JOIN products_cstm ON products.id = products_cstm.id_c) INNER JOIN products_warehouses ON products.id = products_warehouses.product_id
    WHERE (((products_warehouses.deleted)=0))
    GROUP BY products.name, products.id, products_warehouses.product_id, products_cstm.mindestbestand;";

$mailtext = "";
$icount = 0;

if ($result = mysqli_query($link, $strSQL)) {
    while ($row = mysqli_fetch_assoc($result)) {  
        if ($row['mindestbestand'] > $row['lagerstand']) {
            $icount++;
            $mailtext .= "
                <tr>
                    <td>" . htmlspecialchars($row['productname']) . "</td>
                    <td>" . htmlspecialchars($row['lagerstand']) . "</td>
                    <td>" . htmlspecialchars($row['mindestbestand']) . "</td>
                </tr>";
        }
    }
}


if ($icount > 0) {
    $endzeit = microtime(true);
    $ausfuehrungszeit = $endzeit - $startzeit;

    $htmlContent = "
        <html>
        <head>
            <style>
                body { font-family: Arial, Helvetica, sans-serif; background: #f6f8fa; color: #1f2328; margin: 0; padding: 0; }
                .wrap { max-width: 760px; margin: 24px auto; background: #ffffff; border: 1px solid #e5e7eb; border-radius: 12px; overflow: hidden; }
                .header { background: #0f766e; color: #ffffff; padding: 16px 20px; }
                .header h1 { margin: 0; font-size: 18px; letter-spacing: 0.3px; }
                .content { padding: 18px 20px; }
                .note { background: #f1f5f9; border-left: 4px solid #0f766e; padding: 10px 12px; border-radius: 6px; margin: 12px 0; }
                table { width: 100%; border-collapse: collapse; margin-top: 10px; }
                th, td { border: 1px solid #e5e7eb; padding: 8px 10px; text-align: left; }
                th { background-color: #f8fafc; font-weight: 700; }
                td:first-child { width: 55%; }
                .meta { color: #6b7280; font-size: 12px; margin-top: 12px; }
                .footer { padding: 14px 20px; background: #f8fafc; font-size: 12px; color: #6b7280; }
                a { color: #0f766e; text-decoration: underline; }
            </style>
        </head>
        <body>
            <div class='wrap'>
                <div class='header'>
                    <h1>Lagerheini Bericht - Bestellvorschlag</h1>
                </div>
                <div class='content'>
                    <p>".$character['greeting']."</p>
                    <p>".$character['intro']."</p>
                    " . ($overdueCount > 0 ? "
                    <div style=\"border: 2px solid #b02a37; background: #f8d7da; color: #842029; padding: 12px; border-radius: 8px; margin: 12px 0;\">
                        <strong>Achtung:</strong> $overdueCount Rechnungen haben das Fälligkeitsdatum überschritten.<br>
                        <a href=\"https://addinol-lubeoil.at/crm/roman/offene_rechnungen.php\" target=\"_blank\" style=\"color:#842029; text-decoration: underline;\">Zu den offenen Rechnungen</a>
                    </div>
                    " : "") . "
                    <div class='note'>".$character['proposal']."</div>
                    <table>
                        <thead>
                            <tr>
                                <th>Produkt</th>
                                <th>Lagerstand</th>
                                <th>Mindestbestand</th>
                            </tr>
                        </thead>
                        <tbody>
                            $mailtext
                        </tbody>
                    </table>
                    $addText
                    <p>" . sprintf($character['outro'], number_format($ausfuehrungszeit, 6)) . "</p>
                    <p>".$character['closing']."</p>
                    <div class='meta'>Hinweis: Diese Nachricht wurde automatisch erstellt. Fiktiver Angestellter (Bot) – steuerbefreit nach §12 Abs. 2 BotG (Satire).</div>
                </div>
                <div class='footer'>Addinol CRM - Lagerheini</div>
            </div>
        </body>
        </html>";

    // Email headers
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= "From: lagerheini@addinol-lubeoil.at" . "\r\n";

    // Send email
    $subject = "Es ist gegebenenfalls eine Lagerbestellung nötig (".$character['name'].")";
    mail("h.egger@addinol-lubeoil.at", $subject, $htmlContent, $headers);
    mail("roman@haselsberger.at", $subject, $htmlContent, $headers);
}

?>
