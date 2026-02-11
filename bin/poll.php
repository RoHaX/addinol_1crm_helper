<?php

require_once __DIR__ . '/../middleware/config.php';
require_once __DIR__ . '/../src/MwDb.php';
require_once __DIR__ . '/../src/MwLogger.php';
require_once __DIR__ . '/../src/ImapClient.php';

$logger = new MwLogger(__DIR__ . '/../logs');
$forceRecheck = getenv('FORCE_RECHECK') === '1';
$allowedRecheck = ['new', 'queued', 'pending_import'];

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
$insert = $db->prepare('INSERT INTO mw_tracked_mail (mailbox, uid, message_id, message_hash, date, from_addr, subject, status, created_at, updated_at) VALUES (?,?,?,?,?,?,?,'"'"'new'"'"',NOW(),NOW())');
$update = $db->prepare('UPDATE mw_tracked_mail SET message_id = ?, message_hash = ?, date = ?, from_addr = ?, subject = ?, status = "new", updated_at = NOW() WHERE id = ?');
$updateImported = $db->prepare('UPDATE mw_tracked_mail SET status = "imported", crm_email_id = ?, last_error = NULL, updated_at = NOW() WHERE id = ?');
$updatePending = $db->prepare('UPDATE mw_tracked_mail SET status = "pending_import", last_error = NULL, updated_at = NOW() WHERE id = ?');

foreach ($mailboxes as $mailbox) {
	try {
		$imap = new ImapClient(MW_IMAP_HOST, MW_IMAP_PORT, MW_IMAP_USER, MW_IMAP_PASS, MW_IMAP_FLAGS);
		$imap->connect($mailbox);
		$uids = $imap->search('ALL');

		foreach ($uids as $uid) {
			try {
				$headers = $imap->fetchHeaders($uid);
				$body = $imap->fetchBody($uid);

				$messageIdRaw = $headers['message_id'] ?? null;
				$messageId = normalize_message_id($messageIdRaw);
				$dateStr = $headers['date'] ?? null;
				$fromAddr = $headers['from'] ?? null;
				$subject = $headers['subject'] ?? null;
				$date = $dateStr ? date('Y-m-d H:i:s', strtotime($dateStr)) : null;
				if ($date && strtotime($date) < strtotime('-30 days')) {
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
					continue;
				}

				if ($existingId) {
					if ($forceRecheck && in_array($existingStatus, $allowedRecheck, true)) {
						$update->bind_param('sssssi', $messageId, $messageHash, $date, $fromAddr, $subject, $existingId);
						$update->execute();
					}

					if ($existingStatus === 'new' || $existingStatus === 'pending_import') {
						$crmId = find_crm_email_id($selectCrmByMessageId, $messageId);
						if ($crmId) {
							$updateImported->bind_param('si', $crmId, $existingId);
							$updateImported->execute();
						} else {
							$updatePending->bind_param('i', $existingId);
							$updatePending->execute();
						}
					}
					continue;
				}

				$insert->bind_param('sssssss', $mailbox, $uid, $messageId, $messageHash, $date, $fromAddr, $subject);
				$insert->execute();
				$newId = $db->insert_id;
				if ($newId) {
					$crmId = find_crm_email_id($selectCrmByMessageId, $messageId);
					if ($crmId) {
						$updateImported->bind_param('si', $crmId, $newId);
						$updateImported->execute();
					} else {
						$updatePending->bind_param('i', $newId);
						$updatePending->execute();
					}
				}
			} catch (Throwable $e) {
				$logger->error('poll mail error', ['mailbox' => $mailbox, 'uid' => $uid, 'error' => $e->getMessage()]);
			}
		}

		$imap->disconnect();
	} catch (Throwable $e) {
		$logger->error('poll mailbox error', ['mailbox' => $mailbox, 'error' => $e->getMessage()]);
	}
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

function find_crm_email_id($stmt, $messageId)
{
	if (!$messageId) {
		return null;
	}
	$stmt->bind_param('s', $messageId);
	if ($stmt->execute()) {
		$res = $stmt->get_result();
		if ($row = $res->fetch_assoc()) {
			return $row['id'];
		}
	}
	return null;
}
