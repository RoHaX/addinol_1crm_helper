<?php

require_once __DIR__ . '/middleware/config.php';

function mt_env(string $key, string $default = ''): string
{
    $val = getenv($key);
    if ($val === false || $val === '') {
        return $default;
    }
    return (string)$val;
}

$imapMailbox = mt_env('MW_IMAP_MAILBOXES', 'INBOX');
$imapServer = '{' . MW_IMAP_HOST . ':' . MW_IMAP_PORT . MW_IMAP_FLAGS . '}' . $imapMailbox;
$username = MW_IMAP_USER;
$password = MW_IMAP_PASS;

if ($username === '' || $password === '') {
    die('IMAP credentials missing (MW_IMAP_USER / MW_IMAP_PASS).');
}

$dbHost = mt_env('CRM_DB_HOST', 'localhost');
$dbName = mt_env('CRM_DB_NAME', 'addinol_crm');
$dbUser = mt_env('CRM_DB_USER', '');
$dbPass = mt_env('CRM_DB_PASS', '');

if ($dbUser === '' || $dbPass === '') {
    die('DB credentials missing (CRM_DB_USER / CRM_DB_PASS).');
}

$threeDaysAgo = date('Y-m-d', strtotime('-3 days'));

$mailbox = imap_open($imapServer, $username, $password);
if (!$mailbox) {
    die('Verbindung zum IMAP-Server fehlgeschlagen: ' . imap_last_error());
}

$missingEmails = [];
$emails = imap_search($mailbox, 'SINCE "' . $threeDaysAgo . '"');

if ($emails) {
    $dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', $dbHost, $dbName);
    $pdo = new PDO($dsn, $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM emails WHERE message_id = :message_id');

    foreach ($emails as $emailNumber) {
        $header = imap_headerinfo($mailbox, $emailNumber);

        $messageId = isset($header->message_id) ? $header->message_id : 'NULL';
        $from = $header->fromaddress;
        $subject = isset($header->subject) ? $header->subject : 'Kein Betreff';
        $date = $header->date;

        $decodedSubject = imap_mime_header_decode($subject);
        if (!empty($decodedSubject) && isset($decodedSubject[0])) {
            if (strpos($subject, '=?iso-8859-1?') !== false) {
                $subDec = utf8_encode($decodedSubject[0]->text);
            } else {
                $subDec = $decodedSubject[0]->text;
            }
        } else {
            $subDec = $subject;
        }

        $dateFormatted = date('Y-m-d H:i:s', strtotime($date));

        $stmt->execute(['message_id' => $messageId]);
        $count = (int)$stmt->fetchColumn();

        if ($count === 0) {
            echo '<div>';
            echo '<h4>Neue E-Mail gefunden:</h4>';
            echo '<p><strong>Datum:</strong> ' . htmlspecialchars($dateFormatted, ENT_QUOTES, 'UTF-8') . '</p>';
            echo '<p><strong>Message-ID:</strong> ' . htmlspecialchars($messageId, ENT_QUOTES, 'UTF-8') . '</p>';
            echo '<p><strong>Von:</strong> ' . htmlspecialchars($from, ENT_QUOTES, 'UTF-8') . '</p>';
            echo '<p><strong>Betreff:</strong> ' . htmlspecialchars($subDec, ENT_QUOTES, 'UTF-8') . '</p>';
            echo '</div><br>';

            $missingEmails[] = $messageId;
        }
    }

    echo '<h3>Gesamtzahl der fehlenden E-Mails: ' . count($missingEmails) . '</h3>';
} else {
    echo 'Keine E-Mails in den letzten 3 Tagen gefunden.';
}

imap_close($mailbox);
