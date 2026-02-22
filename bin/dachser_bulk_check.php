<?php
require_once __DIR__ . '/../db.inc.php';

$db = $mysqli ?? null;
if (!$db) {
	fwrite(STDERR, "DB connection missing\n");
	exit(1);
}
$db->set_charset('utf8');

$limit = (int)(getenv('DACHSER_BULK_LIMIT') ?: 250);
if ($limit < 1) {
	$limit = 1;
}
if ($limit > 2000) {
	$limit = 2000;
}

$statusScript = realpath(__DIR__ . '/../middleware/dachser_status.php');
if ($statusScript === false || !is_file($statusScript)) {
	fwrite(STDERR, "middleware/dachser_status.php not found\n");
	exit(2);
}

function resolvePhpCli(): string
{
	$forced = trim((string)(getenv('DACHSER_BULK_PHP_BIN') ?: ''));
	if ($forced !== '' && is_file($forced) && is_executable($forced)) {
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
	return '/usr/bin/php';
}

function runDachserStatus(string $phpBin, string $scriptPath, string $reference, string $purchaseOrderId): array
{
	$payloadJson = json_encode([
		'reference' => $reference,
		'purchase_order_id' => $purchaseOrderId,
	], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

	$cmd = 'REQUEST_METHOD=POST ' . escapeshellarg($phpBin) . ' ' . escapeshellarg($scriptPath);
	$spec = [
		0 => ['pipe', 'r'],
		1 => ['pipe', 'w'],
		2 => ['pipe', 'w'],
	];
	$proc = proc_open($cmd, $spec, $pipes, dirname($scriptPath));
	if (!is_resource($proc)) {
		return ['ok' => false, 'error' => 'proc_open_failed'];
	}

	fwrite($pipes[0], $payloadJson);
	fclose($pipes[0]);

	$stdout = (string)stream_get_contents($pipes[1]);
	fclose($pipes[1]);
	$stderr = (string)stream_get_contents($pipes[2]);
	fclose($pipes[2]);

	$exitCode = (int)proc_close($proc);
	if ($exitCode !== 0) {
		return ['ok' => false, 'error' => $stderr !== '' ? $stderr : ('exit_' . $exitCode)];
	}

	$decoded = json_decode($stdout, true);
	if (!is_array($decoded) && preg_match('/(\{.*\})/s', $stdout, $m)) {
		$decoded = json_decode((string)$m[1], true);
	}
	if (!is_array($decoded)) {
		return ['ok' => false, 'error' => 'invalid_json_output'];
	}
	return $decoded;
}

$sql = 'SELECT
	r.sales_order_id AS purchase_order_id,
	TRIM(r.at_order_no) AS at_order_no
FROM mw_addinol_refs r
INNER JOIN purchase_orders p ON p.id = r.sales_order_id AND p.deleted = 0
LEFT JOIN sales_orders s ON s.id = p.from_so_id AND s.deleted = 0
WHERE TRIM(COALESCE(r.at_order_no, "")) <> ""
	AND LOWER(TRIM(COALESCE(r.dachser_status, ""))) NOT IN ("zugestellt", "delivered")
	AND LOWER(TRIM(COALESCE(s.so_stage, ""))) NOT IN ("closed - shipped and invoiced", "delivered")
ORDER BY COALESCE(r.dachser_last_checked_at, "1970-01-01 00:00:00") ASC, r.id ASC
LIMIT ' . $limit;

$res = $db->query($sql);
if (!$res) {
	fwrite(STDERR, "SQL error: " . $db->error . "\n");
	exit(3);
}

$rows = [];
while ($row = $res->fetch_assoc()) {
	$poId = trim((string)($row['purchase_order_id'] ?? ''));
	$ref = trim((string)($row['at_order_no'] ?? ''));
	if ($poId === '' || $ref === '') {
		continue;
	}
	$rows[] = ['purchase_order_id' => $poId, 'reference' => $ref];
}

$stats = [
	'total' => count($rows),
	'ok' => 0,
	'error' => 0,
	'status_changed' => 0,
	'job_created' => 0,
];

if (!$rows) {
	echo "Keine offenen Datensaetze mit AT-Nummer gefunden.\n";
	exit(0);
}

$phpBin = resolvePhpCli();
foreach ($rows as $item) {
	$result = runDachserStatus($phpBin, $statusScript, $item['reference'], $item['purchase_order_id']);
	if (!empty($result['ok'])) {
		$stats['ok']++;
	} else {
		$stats['error']++;
		continue;
	}
	if (!empty($result['status_changed'])) {
		$stats['status_changed']++;
	}
	if (!empty($result['job_created'])) {
		$stats['job_created']++;
	}
}

$summary = sprintf(
	'Geprueft: %d, ok: %d, error: %d, status_changed: %d, jobs_created: %d',
	(int)$stats['total'],
	(int)$stats['ok'],
	(int)$stats['error'],
	(int)$stats['status_changed'],
	(int)$stats['job_created']
);
echo $summary . PHP_EOL;

if ($stats['ok'] === 0 && $stats['error'] > 0) {
	exit(4);
}
exit(0);

