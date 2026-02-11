<?php

require_once __DIR__ . '/../middleware/config.php';
require_once __DIR__ . '/../src/MwDb.php';
require_once __DIR__ . '/../src/MwLogger.php';
require_once __DIR__ . '/../src/ActionHandlers.php';

$logger = new MwLogger(__DIR__ . '/../logs');
$db = MwDb::getMysqli();
if (!$db) {
	$logger->error('db connection missing');
	fwrite(STDERR, "DB connection missing\n");
	exit(1);
}
@$db->set_charset('utf8');

$handlers = new ActionHandlers($db, $logger);

$limit = (int)(getenv('MW_WORKER_LIMIT') ?: 50);
$now = date('Y-m-d H:i:s');

$select = $db->prepare('SELECT id, tracked_mail_id, action_type, payload_json, attempts FROM mw_actions_queue WHERE status = "QUEUED" AND (next_run_at IS NULL OR next_run_at <= ?) ORDER BY id ASC LIMIT ' . $limit);
$select->bind_param('s', $now);
$select->execute();
$res = $select->get_result();
$jobs = [];
while ($row = $res->fetch_assoc()) {
	$jobs[] = $row;
}

$markRunning = $db->prepare('UPDATE mw_actions_queue SET status = "RUNNING", updated_at = NOW() WHERE id = ?');
$markDone = $db->prepare('UPDATE mw_actions_queue SET status = "DONE", last_error = NULL, updated_at = NOW() WHERE id = ?');
$markFailed = $db->prepare('UPDATE mw_actions_queue SET status = "FAILED", last_error = ?, updated_at = NOW() WHERE id = ?');
$markRetry = $db->prepare('UPDATE mw_actions_queue SET status = "QUEUED", attempts = ?, next_run_at = ?, last_error = ?, updated_at = NOW() WHERE id = ?');

foreach ($jobs as $job) {
	$id = (int)$job['id'];
	$trackedId = (int)$job['tracked_mail_id'];
	$actionType = $job['action_type'];
	$attempts = (int)$job['attempts'];
	$payload = json_decode($job['payload_json'] ?? '[]', true);
	if (!is_array($payload)) {
		$payload = [];
	}

	$markRunning->bind_param('i', $id);
	$markRunning->execute();

	try {
		$result = $handlers->handle($trackedId, $actionType, $payload);
		if (!empty($result['ok'])) {
			$markDone->bind_param('i', $id);
			$markDone->execute();
			continue;
		}
		$errMsg = $result['error'] ?? 'action failed';
		throw new RuntimeException($errMsg);
	} catch (Throwable $e) {
		$attempts++;
		if ($attempts >= 5) {
			$errMsg = $e->getMessage();
			$markFailed->bind_param('si', $errMsg, $id);
			$markFailed->execute();
			continue;
		}
		$delay = pow(2, $attempts) * 60;
		$nextRun = date('Y-m-d H:i:s', time() + $delay);
		$errMsg = $e->getMessage();
		$markRetry->bind_param('issi', $attempts, $nextRun, $errMsg, $id);
		$markRetry->execute();
	}
}
