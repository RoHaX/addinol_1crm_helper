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
$addText = "Anzahl Besuche/Jahr aktualisiert: betroffene Datensätze: $affectedRows\n";

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
$addText .= "Anzahl Besuche gesamt aktualisiert: betroffene Datensätze: $affectedRows\n";

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
                table { width: 700px; border-collapse: collapse; }
                th, td { border: 1px solid black; padding: 8px; text-align: left; }
                th { background-color: #f2f2f2; }
                td { width: 100px; }
                td:first-child { width: 300px; } /* Erste Spalte breiter machen */
            </style>
        </head>
        <body>
            <p>Geehrteste, hochwohlgeborene und erlauchte Geschäftsführung,</p>
            <p>Euer demütigster und ergebenster Diener ist nun wieder getreulich jeden Tag ab der achten Stunde des Morgens zu Eurer Verfügung, wobei ich mich mit den Zeiten durchaus Eurem gnädigen Willen anzupassen weiß.</p>
            <p>Was meinen bescheidenen Vorschlag zur Bestellung betrifft, so lautet er wie folgt:</p>
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
            <p>Die ehrenvolle Inventur habe ich in " . number_format($ausfuehrungszeit, 6) . " Sekunden vollbracht, und mit dieser edlen Tat nehme ich mir die Freiheit, mich für den heutigen Tag in aller Bescheidenheit zurückzuziehen.</p>
            <p>Mit tiefster Ergebenheit und stets zu Euren Diensten,<br>Euer treuester Lagerheini</p>
        </body>
        </html>";

    // Email headers
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= "From: lagerheini@addinol-lubeoil.at" . "\r\n";

    // Send email
    mail("h.egger@addinol-lubeoil.at", "Es ist gegebenenfalls eine Lagerbestellung nötig", $htmlContent, $headers);
}

?>
