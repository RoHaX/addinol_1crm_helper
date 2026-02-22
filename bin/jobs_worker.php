<?php
require_once __DIR__ . '/../db.inc.php';
require_once __DIR__ . '/../src/MwLogger.php';
require_once __DIR__ . '/../src/JobService.php';

$db = $mysqli ?? null;
if (!$db) {
	fwrite(STDERR, "DB connection missing\n");
	exit(1);
}
$db->set_charset('utf8');

$logger = new MwLogger(__DIR__ . '/../logs');
if (!JobService::ensureTables($db)) {
	$logger->error('jobs_worker_tables_missing', []);
	fwrite(STDERR, "job tables missing\n");
	exit(2);
}

$limit = (int)(getenv('JOB_LIMIT') ?: 30);
if ($limit < 1) {
	$limit = 1;
}
if ($limit > 200) {
	$limit = 200;
}

$ids = [];
$sql = 'SELECT id FROM mw_jobs WHERE status = "active" AND run_mode = "auto" AND (next_run_at IS NULL OR next_run_at <= NOW()) ORDER BY COALESCE(next_run_at, run_at, created_at) ASC LIMIT ' . $limit;
$res = $db->query($sql);
if ($res) {
	while ($row = $res->fetch_assoc()) {
		$ids[] = (int)($row['id'] ?? 0);
	}
}

$stats = ['picked' => count($ids), 'ok' => 0, 'error' => 0];
foreach ($ids as $jobId) {
	$result = JobService::runJobNow($db, $jobId, 'schedule', 'jobs_worker');
	if (!empty($result['ok'])) {
		$stats['ok']++;
	} else {
		$stats['error']++;
		$logger->error('jobs_worker_job_failed', ['job_id' => $jobId, 'result' => $result]);
	}
}

$logger->info('jobs_worker_done', $stats);
echo json_encode(['ok' => true, 'stats' => $stats], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
