<?php
// 1. Verbindung zur SMTP-Inbox (IMAP)
$imapServer = '{mail.addinol-lubeoil.at:993/imap/ssl}INBOX'; // Ersetze durch deinen IMAP-Server
$username = 'h.egger@addinol-lubeoil.at';
$password = 'REDACTED_IMAP_PASSWORD';


// Berechne das Datum der letzten 3 Tage
$threeDaysAgo = date('Y-m-d', strtotime('-3 days'));

// Verbindung zum IMAP-Server herstellen
$mailbox = imap_open($imapServer, $username, $password);
if (!$mailbox) {
    die('Verbindung zum IMAP-Server fehlgeschlagen: ' . imap_last_error());
}

$missingEmails = [];

// 2. Abrufen der E-Mails der letzten 3 Tage
$emails = imap_search($mailbox, 'SINCE "' . $threeDaysAgo . '"');

if ($emails) {
    foreach ($emails as $emailNumber) {
        // E-Mail-Kopfzeilen abrufen
        $header = imap_headerinfo($mailbox, $emailNumber);

        if (isset($header->message_id)) {
            $messageId = $header->message_id;
        } else {
            $messageId = "NULL"; // Setze auf null, wenn keine message_id vorhanden ist
        }

        $from = $header->fromaddress;
        // $subject = $header->subject;
        $subject = isset($header->subject) ? $header->subject : 'Kein Betreff';

        $date = $header->date;

        $decodedSubject = imap_mime_header_decode($subject);
        if ($decodedSubject[0]) {
            if (strpos($subject, '=?iso-8859-1?') !== false) {
                $sub_dec = utf8_encode($decodedSubject[0]->text);
            } else {
                $sub_dec = $decodedSubject[0]->text;

            }
        } else {
            $sub_dec = $subject;
        }
        
        // Zeitzonenproblem beheben (optional, wenn du es brauchst)
        $dateFormatted = date('Y-m-d H:i:s', strtotime($date)); // Konvertiere die Zeit ins Standardformat

        // if ($dateFormatted == '2025-08-06 08:47:03') {
        //     imap_delete($mailbox, $emailNumber);
        //     echo "E-Mail mit Betreff '$header->subject' wurde markiert zum Löschen.<br>";
        //     imap_expunge($mailbox);
        //     echo "E-Mails wurden endgültig gelöscht.<br>";
        // }

        // 3. Überprüfen, ob die E-Mail bereits in der 1CRM-Datenbank ist
        $pdo = new PDO('mysql:host=localhost;dbname=addinol_crm', 'addinol_usr', 'lwT1e99~'); // 1CRM-Datenbankverbindung
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM emails WHERE message_id = :message_id");
        $stmt->execute(['message_id' => $messageId]);
        $count = $stmt->fetchColumn();

        // Falls die E-Mail nicht in der Datenbank vorhanden ist
        if ($count == 0) {
            // Ausgabe in einem strukturierten Format
            echo "<div>";
            echo "<h4>Neue E-Mail gefunden:</h4>";
            echo "<p><strong>Datum:</strong> $dateFormatted</p>";
            echo "<p><strong>Message-ID:</strong> $messageId</p>";
            echo "<p><strong>Von:</strong> $from</p>";
            echo "<p><strong>Betreff:</strong> $sub_dec</p>";

            // Optional: Füge die fehlende E-Mail zur Liste der fehlenden E-Mails hinzu
            $missingEmails[] = $messageId;
            
            echo "</div><br>";
        }
    }
    echo "<h3>Gesamtzahl der fehlenden E-Mails: " . count($missingEmails) . "</h3>";
} else {
    echo "Keine E-Mails in den letzten 3 Tagen gefunden.";
}

// 5. Verbindung zum IMAP-Server schließen
imap_close($mailbox);
?>