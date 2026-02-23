<?php

require_once __DIR__ . '/../middleware/config.php';
require_once __DIR__ . '/../src/MwDb.php';
require_once __DIR__ . '/../src/MwLogger.php';
require_once __DIR__ . '/../src/ImapClient.php';

$logger = new MwLogger(__DIR__ . '/../logs');
$forceRecheck = getenv('FORCE_RECHECK') === '1';
$allowedRecheck = ['new', 'queued', 'pending_import'];
$lookbackDays = (int)(getenv('MW_POLL_LOOKBACK_DAYS') ?: 14);
if ($lookbackDays < 1) {
	$lookbackDays = 1;
}
if ($lookbackDays > 365) {
	$lookbackDays = 365;
}
$lookbackCutoffTs = strtotime('-' . $lookbackDays . ' days');
$imapSince = date('d-M-Y', $lookbackCutoffTs);

$db = MwDb::getMysqli();
if (!$db) {
	$logger->error('db connection missing');
	fwrite(STDERR, "DB connection missing\n");
	exit(1);
}
@$db->set_charset('utf8');

$mailboxes = array_filter(array_map('trim', explode(',', MW_IMAP_MAILBOXES)));
if (!$mailboxes) {
	$logger->error('no mailboxes configured');
	fwrite(STDERR, "No mailboxes configured\n");
	exit(1);
}

$selectByMailboxUid = $db->prepare('SELECT id, status FROM mw_tracked_mail WHERE mailbox = ? AND uid = ? LIMIT 1');
$selectByHash = $db->prepare('SELECT id, status FROM mw_tracked_mail WHERE message_hash = ? LIMIT 1');
$selectCrmByMessageId = $db->prepare('SELECT id FROM addinol_crm.emails WHERE deleted = 0 AND message_id = ? LIMIT 1');
$selectCrmByMessageIdNormalized = $db->prepare("SELECT id FROM addinol_crm.emails WHERE deleted = 0 AND LOWER(TRIM(BOTH '>' FROM TRIM(BOTH '<' FROM TRIM(message_id)))) = ? LIMIT 1");
$insert = $db->prepare("INSERT INTO mw_tracked_mail (mailbox, uid, message_id, message_hash, date, from_addr, subject, status, created_at, updated_at) VALUES (?,?,?,?,?,?,?,'new',NOW(),NOW())");
$update = $db->prepare('UPDATE mw_tracked_mail SET message_id = ?, message_hash = ?, date = ?, from_addr = ?, subject = ?, status = "new", updated_at = NOW() WHERE id = ?');
$updateImported = $db->prepare('UPDATE mw_tracked_mail SET status = "imported", crm_email_id = ?, last_error = NULL, updated_at = NOW() WHERE id = ?');
$updatePending = $db->prepare('UPDATE mw_tracked_mail SET status = "pending_import", last_error = NULL, updated_at = NOW() WHERE id = ?');
$extractScript = realpath(__DIR__ . '/extract_addinol_refs.php');
$extractPhpBin = resolve_extract_php_binary();
$extractLogFile = realpath(__DIR__ . '/../logs') ?: (__DIR__ . '/../logs');
$extractLogFile = rtrim($extractLogFile, '/') . '/extract_addinol_refs.log';
$extractEnabled = is_string($extractScript) && $extractScript !== '' && is_file($extractScript);
$autoExtractSeenEmails = [];

$stats = [
	'mailboxes' => count($mailboxes),
	'lookback_days' => $lookbackDays,
	'uids_scanned' => 0,
	'new_tracked' => 0,
	'marked_imported' => 0,
	'marked_pending' => 0,
	'skipped_old' => 0,
	'skipped_duplicate' => 0,
	'mail_errors' => 0,
	'mailbox_errors' => 0,
	'auto_extract_triggered' => 0,
	'auto_extract_skipped' => 0,
	'auto_extract_error' => 0,
];

foreach ($mailboxes as $mailbox) {
	try {
		clear_imap_runtime_messages();
		$imap = new ImapClient(MW_IMAP_HOST, MW_IMAP_PORT, MW_IMAP_USER, MW_IMAP_PASS, MW_IMAP_FLAGS);
		$imap->connect($mailbox);
		$uids = $imap->search('SINCE "' . $imapSince . '"');
		if (!is_array($uids)) {
			$uids = [];
		}

		foreach ($uids as $uid) {
			$stats['uids_scanned']++;
			try {
				$headers = $imap->fetchHeaders($uid);
				$body = $imap->fetchBody($uid);

				$messageIdRaw = $headers['message_id'] ?? null;
				$messageId = normalize_message_id($messageIdRaw);
				$dateStr = $headers['date'] ?? null;
				$fromAddr = $headers['from'] ?? null;
				$subject = $headers['subject'] ?? null;
				$date = $dateStr ? date('Y-m-d H:i:s', strtotime($dateStr)) : null;
				if ($date && strtotime($date) < $lookbackCutoffTs) {
					$stats['skipped_old']++;
					continue;
				}

				$hashInput = $mailbox . '|' . $uid . '|' . (string)$messageId . '|' . (string)$dateStr . '|' . (string)$fromAddr . '|' . (string)$subject . '|' . $body;
				$messageHash = hash('sha256', $hashInput);

				$existingHashId = null;
				$selectByHash->bind_param('s', $messageHash);
				if ($selectByHash->execute()) {
					$res = $selectByHash->get_result();
					if ($row = $res->fetch_assoc()) {
						$existingHashId = (int)$row['id'];
						$existingHashStatus = $row['status'];
					}
				}

				$existingId = null;
				$existingStatus = null;
				$selectByMailboxUid->bind_param('ss', $mailbox, $uid);
				if ($selectByMailboxUid->execute()) {
					$res = $selectByMailboxUid->get_result();
					if ($row = $res->fetch_assoc()) {
						$existingId = (int)$row['id'];
						$existingStatus = $row['status'];
					}
				}

				if ($existingHashId && (!$existingId || $existingHashId !== $existingId)) {
					$stats['skipped_duplicate']++;
					continue;
				}

				if ($existingId) {
					if ($forceRecheck && in_array($existingStatus, $allowedRecheck, true)) {
						$update->bind_param('sssssi', $messageId, $messageHash, $date, $fromAddr, $subject, $existingId);
						$update->execute();
					}

					if ($existingStatus === 'new' || $existingStatus === 'pending_import') {
						$crmId = find_crm_email_id($selectCrmByMessageId, $selectCrmByMessageIdNormalized, $messageId);
						if ($crmId) {
							$updateImported->bind_param('si', $crmId, $existingId);
							$updateImported->execute();
							if ($updateImported->affected_rows > 0) {
								$stats['marked_imported']++;
							}
							$extractResult = maybe_trigger_invoice_extract_for_email($db, $logger, $crmId, (string)$subject, (string)$fromAddr, $extractEnabled, (string)$extractScript, $extractPhpBin, $extractLogFile, $autoExtractSeenEmails);
							if ($extractResult === 'triggered') {
								$stats['auto_extract_triggered']++;
							} elseif ($extractResult === 'error') {
								$stats['auto_extract_error']++;
							} else {
								$stats['auto_extract_skipped']++;
							}
						} else {
							$updatePending->bind_param('i', $existingId);
							$updatePending->execute();
							if ($updatePending->affected_rows > 0) {
								$stats['marked_pending']++;
							}
						}
					}
					continue;
				}

				$insert->bind_param('sssssss', $mailbox, $uid, $messageId, $messageHash, $date, $fromAddr, $subject);
				$insert->execute();
				$newId = $db->insert_id;
				if ($newId) {
					$stats['new_tracked']++;
					$crmId = find_crm_email_id($selectCrmByMessageId, $selectCrmByMessageIdNormalized, $messageId);
					if ($crmId) {
						$updateImported->bind_param('si', $crmId, $newId);
						$updateImported->execute();
						if ($updateImported->affected_rows > 0) {
							$stats['marked_imported']++;
						}
						$extractResult = maybe_trigger_invoice_extract_for_email($db, $logger, $crmId, (string)$subject, (string)$fromAddr, $extractEnabled, (string)$extractScript, $extractPhpBin, $extractLogFile, $autoExtractSeenEmails);
						if ($extractResult === 'triggered') {
							$stats['auto_extract_triggered']++;
						} elseif ($extractResult === 'error') {
							$stats['auto_extract_error']++;
						} else {
							$stats['auto_extract_skipped']++;
						}
					} else {
						$updatePending->bind_param('i', $newId);
						$updatePending->execute();
						if ($updatePending->affected_rows > 0) {
							$stats['marked_pending']++;
						}
					}
				}
			} catch (Throwable $e) {
				$logger->error('poll mail error', ['mailbox' => $mailbox, 'uid' => $uid, 'error' => $e->getMessage()]);
				$stats['mail_errors']++;
				clear_imap_runtime_messages();
			}
		}

		$imap->disconnect();
		clear_imap_runtime_messages();
	} catch (Throwable $e) {
		$logger->error('poll mailbox error', ['mailbox' => $mailbox, 'error' => $e->getMessage()]);
		$stats['mailbox_errors']++;
		clear_imap_runtime_messages();
	}
}

clear_imap_runtime_messages();

$summary = sprintf(
	'Poll summary: lookback_days=%d, new=%d, imported=%d, pending=%d, scanned=%d, skipped_old=%d, skipped_duplicate=%d, auto_extract_triggered=%d, auto_extract_skipped=%d, auto_extract_error=%d, mail_errors=%d, mailbox_errors=%d',
	(int)$stats['lookback_days'],
	(int)$stats['new_tracked'],
	(int)$stats['marked_imported'],
	(int)$stats['marked_pending'],
	(int)$stats['uids_scanned'],
	(int)$stats['skipped_old'],
	(int)$stats['skipped_duplicate'],
	(int)$stats['auto_extract_triggered'],
	(int)$stats['auto_extract_skipped'],
	(int)$stats['auto_extract_error'],
	(int)$stats['mail_errors'],
	(int)$stats['mailbox_errors']
);
$logger->info('poll summary', $stats);
echo $summary . PHP_EOL;

maybe_send_telegram_alert($logger, $stats, $summary);

if ((int)$stats['mailbox_errors'] > 0 || (int)$stats['mail_errors'] > 0) {
	exit(2);
}

function normalize_message_id($messageId)
{
	if (!is_string($messageId)) {
		return null;
	}
	$messageId = trim($messageId);
	if ($messageId === '') {
		return null;
	}
	if ($messageId[0] !== '<') {
		$messageId = '<' . $messageId;
	}
	if (substr($messageId, -1) !== '>') {
		$messageId .= '>';
	}
	return $messageId;
}

function find_crm_email_id($stmtExact, $stmtNormalized, $messageId)
{
	if (!$messageId) {
		return null;
	}

	$stmtExact->bind_param('s', $messageId);
	if ($stmtExact->execute()) {
		$res = $stmtExact->get_result();
		if ($row = $res->fetch_assoc()) {
			return $row['id'];
		}
	}

	$normalized = strtolower(trim($messageId, "<> \t\n\r\0\x0B"));
	if ($normalized === '') {
		return null;
	}
	$stmtNormalized->bind_param('s', $normalized);
	if ($stmtNormalized->execute()) {
		$res = $stmtNormalized->get_result();
		if ($row = $res->fetch_assoc()) {
			return $row['id'];
		}
	}

	return null;
}

function clear_imap_runtime_messages()
{
	$errs = @imap_errors();
	if (is_array($errs)) {
		// intentionally clear imap internal error stack to avoid noisy PHP shutdown notices
	}
	$alerts = @imap_alerts();
	if (is_array($alerts)) {
		// intentionally clear imap alert stack
	}
}

function resolve_extract_php_binary(): string
{
	$forced = trim((string)(getenv('EXTRACT_PHP_BIN') ?: ''));
	if ($forced !== '') {
		return $forced;
	}
	$candidates = [
		'/opt/plesk/php/8.3/bin/php',
		'/opt/plesk/php/8.2/bin/php',
		'/opt/plesk/php/8.1/bin/php',
		'/usr/bin/php',
	];
	foreach ($candidates as $bin) {
		if (is_file($bin) && is_executable($bin)) {
			return $bin;
		}
	}
	return '/opt/plesk/php/8.3/bin/php';
}

function maybe_trigger_invoice_extract_for_email(mysqli $db, MwLogger $logger, string $crmEmailId, string $subject, string $fromAddr, bool $extractEnabled, string $extractScript, string $extractPhpBin, string $extractLogFile, array &$seenEmails): string
{
	$crmEmailId = trim($crmEmailId);
	if ($crmEmailId === '') {
		return 'skipped';
	}
	if (isset($seenEmails[$crmEmailId])) {
		return 'skipped';
	}
	$seenEmails[$crmEmailId] = true;

	if (!$extractEnabled) {
		$logger->error('poll auto extract skipped (script missing)', ['crm_email_id' => $crmEmailId]);
		return 'error';
	}

	$pdfNames = fetch_pdf_attachment_names_for_email($db, $crmEmailId);
	if (!$pdfNames) {
		return 'skipped';
	}
	if (!is_invoice_like_mail($subject, $fromAddr, $pdfNames)) {
		return 'skipped';
	}

	$cmd = 'env FORCE_RECHECK=1 LIMIT=80 TARGET_EMAIL_ID=' . escapeshellarg($crmEmailId)
		. ' ' . escapeshellarg($extractPhpBin)
		. ' ' . escapeshellarg($extractScript)
		. ' >> ' . escapeshellarg($extractLogFile) . ' 2>&1';
	$exitCode = 1;
	exec($cmd, $unused, $exitCode);
	if ($exitCode !== 0) {
		$logger->error('poll auto extract failed', ['crm_email_id' => $crmEmailId, 'exit_code' => $exitCode]);
		return 'error';
	}

	$logger->info('poll auto extract done', [
		'crm_email_id' => $crmEmailId,
		'subject' => $subject,
		'from' => $fromAddr,
	]);
	return 'triggered';
}

function fetch_pdf_attachment_names_for_email(mysqli $db, string $crmEmailId): array
{
	$out = [];
	$stmt = $db->prepare('SELECT filename FROM addinol_crm.notes WHERE deleted = 0 AND parent_type = "Emails" AND parent_id = ? AND LOWER(filename) LIKE "%.pdf" ORDER BY date_modified DESC LIMIT 20');
	if (!$stmt) {
		return $out;
	}
	$stmt->bind_param('s', $crmEmailId);
	if (!$stmt->execute()) {
		return $out;
	}
	$res = $stmt->get_result();
	while ($row = $res->fetch_assoc()) {
		$name = trim((string)($row['filename'] ?? ''));
		if ($name !== '') {
			$out[] = $name;
		}
	}
	return $out;
}

function is_invoice_like_mail(string $subject, string $fromAddr, array $pdfNames): bool
{
	$raw = $subject . ' ' . implode(' ', $pdfNames);
	$haystack = function_exists('mb_strtolower') ? mb_strtolower($raw, 'UTF-8') : strtolower($raw);
	if (preg_match('/\brechnung\b|\binvoice\b|\bgutschrift\b/u', $haystack)) {
		return true;
	}
	if (preg_match('/\bbe\s*\d{4}\s*-\s*\d+\b/u', $haystack)) {
		return true;
	}
	$fromLower = strtolower($fromAddr);
	if (strpos($fromLower, 'addinol') !== false && !empty($pdfNames)) {
		return true;
	}
	return false;
}

function maybe_send_telegram_alert(MwLogger $logger, array $stats, string $summary): void
{
	$newTracked = (int)($stats['new_tracked'] ?? 0);
	$mailErrors = (int)($stats['mail_errors'] ?? 0);
	$mailboxErrors = (int)($stats['mailbox_errors'] ?? 0);
	$hasErrors = ($mailErrors > 0 || $mailboxErrors > 0);
	$botToken = trim((string)(getenv('TG_BOT_TOKEN') ?: getenv('TELEGRAM_BOT_TOKEN') ?: ''));
	$chatId = trim((string)(getenv('TG_CHAT_ID') ?: getenv('TELEGRAM_CHAT_ID') ?: ''));
	$minNew = (int)(getenv('TG_NOTIFY_MIN_NEW') ?: 1);
	$notifyAlways = getenv('TG_NOTIFY_ALWAYS') === '1';
	$cooldownSec = (int)(getenv('TG_NOTIFY_COOLDOWN_SEC') ?: 600);

	if ($minNew < 0) {
		$minNew = 0;
	}
	if ($cooldownSec < 0) {
		$cooldownSec = 0;
	}
	if (!$notifyAlways && !$hasErrors && $newTracked < $minNew) {
		return;
	}
	if ($botToken === '' || $chatId === '') {
		$logger->info('telegram alert skipped (missing config)', [
			'new_tracked' => $newTracked,
			'mail_errors' => $mailErrors,
			'mailbox_errors' => $mailboxErrors,
		]);
		return;
	}
	if (!telegram_cooldown_ok($cooldownSec)) {
		$logger->info('telegram alert skipped (cooldown)', [
			'new_tracked' => $newTracked,
			'mail_errors' => $mailErrors,
			'mailbox_errors' => $mailboxErrors,
			'cooldown_sec' => $cooldownSec
		]);
		return;
	}

	if ($hasErrors) {
		$message = "Mail Poller ALARM\nMailbox-Fehler: " . $mailboxErrors . "\nMail-Fehler: " . $mailErrors . "\nNeue Mails: " . $newTracked . "\n" . $summary;
	} else {
		$message = "Mail Poller\nNeue Mails: " . $newTracked . "\n" . $summary;
	}
	$ok = send_telegram_message($botToken, $chatId, $message);
	if ($ok) {
		telegram_cooldown_touch();
		$logger->info('telegram alert sent', [
			'new_tracked' => $newTracked,
			'mail_errors' => $mailErrors,
			'mailbox_errors' => $mailboxErrors,
			'notify_always' => $notifyAlways,
		]);
	} else {
		$logger->error('telegram alert failed', [
			'new_tracked' => $newTracked,
			'mail_errors' => $mailErrors,
			'mailbox_errors' => $mailboxErrors,
			'notify_always' => $notifyAlways,
		]);
	}
}

function send_telegram_message(string $botToken, string $chatId, string $message): bool
{
	$url = 'https://api.telegram.org/bot' . rawurlencode($botToken) . '/sendMessage';
	$payload = http_build_query([
		'chat_id' => $chatId,
		'text' => $message,
		'disable_web_page_preview' => 'true',
	], '', '&');

	$opts = [
		'http' => [
			'method' => 'POST',
			'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
			'content' => $payload,
			'timeout' => 15,
		],
	];
	$ctx = stream_context_create($opts);
	$res = @file_get_contents($url, false, $ctx);
	if ($res === false) {
		return false;
	}
	$decoded = json_decode((string)$res, true);
	return is_array($decoded) && !empty($decoded['ok']);
}

function telegram_cooldown_state_file(): string
{
	return __DIR__ . '/../logs/telegram_mail_notify.state.json';
}

function telegram_cooldown_ok(int $cooldownSec): bool
{
	if ($cooldownSec <= 0) {
		return true;
	}
	$file = telegram_cooldown_state_file();
	if (!is_file($file)) {
		return true;
	}
	$raw = (string)@file_get_contents($file);
	$data = json_decode($raw, true);
	if (!is_array($data)) {
		return true;
	}
	$lastTs = (int)($data['last_sent_ts'] ?? 0);
	if ($lastTs <= 0) {
		return true;
	}
	return (time() - $lastTs) >= $cooldownSec;
}

function telegram_cooldown_touch(): void
{
	$file = telegram_cooldown_state_file();
	$data = [
		'last_sent_ts' => time(),
		'last_sent_at' => date('Y-m-d H:i:s'),
	];
	@file_put_contents($file, json_encode($data, JSON_UNESCAPED_SLASHES));
}
