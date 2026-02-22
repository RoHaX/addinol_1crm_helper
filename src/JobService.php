<?php

class JobService
{
	public static function ensureTables(?mysqli $db): bool
	{
		if (!$db) {
			return false;
		}

		$sql = [];
		$sql[] = "CREATE TABLE IF NOT EXISTS mw_jobs (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			job_key VARCHAR(255) NULL,
			title VARCHAR(255) NOT NULL,
			job_type VARCHAR(64) NOT NULL DEFAULT 'generic',
			description TEXT NULL,
			relation_type ENUM('none','sales_order','purchase_order','account','customer','other') NOT NULL DEFAULT 'none',
			relation_id CHAR(36) NULL,
			account_id CHAR(36) NULL,
			payload_json JSON NULL,
			schedule_type ENUM('once','interval_minutes','daily_time') NOT NULL DEFAULT 'once',
			run_mode ENUM('manual','auto') NOT NULL DEFAULT 'manual',
			run_at DATETIME NULL,
			next_run_at DATETIME NULL,
			interval_minutes INT UNSIGNED NULL,
			daily_time TIME NULL,
			timezone VARCHAR(64) NOT NULL DEFAULT 'Europe/Vienna',
			status ENUM('active','paused','done','error','archived') NOT NULL DEFAULT 'active',
			last_run_at DATETIME NULL,
			last_result ENUM('ok','error','skipped') NULL,
			last_result_message TEXT NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY uniq_job_key (job_key),
			KEY idx_job_status_mode_next (status, run_mode, next_run_at),
			KEY idx_job_relation (relation_type, relation_id),
			KEY idx_job_type (job_type)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8";

		$sql[] = "CREATE TABLE IF NOT EXISTS mw_job_steps (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			job_id BIGINT UNSIGNED NOT NULL,
			step_order INT UNSIGNED NOT NULL DEFAULT 1,
			step_title VARCHAR(255) NOT NULL,
			step_type VARCHAR(64) NOT NULL DEFAULT 'note',
			step_payload_json JSON NULL,
			due_at DATETIME NULL,
			is_required TINYINT(1) NOT NULL DEFAULT 1,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY uniq_job_step_order (job_id, step_order),
			KEY idx_job_steps_due (job_id, due_at),
			CONSTRAINT fk_job_steps_job FOREIGN KEY (job_id) REFERENCES mw_jobs(id) ON DELETE CASCADE
		) ENGINE=InnoDB DEFAULT CHARSET=utf8";

		$sql[] = "CREATE TABLE IF NOT EXISTS mw_job_runs (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			job_id BIGINT UNSIGNED NOT NULL,
			trigger_type ENUM('manual','schedule','event') NOT NULL DEFAULT 'manual',
			started_at DATETIME NOT NULL,
			finished_at DATETIME NULL,
			status ENUM('running','ok','error','skipped') NOT NULL DEFAULT 'running',
			result_message TEXT NULL,
			result_json JSON NULL,
			executed_by VARCHAR(64) NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY idx_job_runs_job (job_id, started_at),
			KEY idx_job_runs_status (status, started_at),
			CONSTRAINT fk_job_runs_job FOREIGN KEY (job_id) REFERENCES mw_jobs(id) ON DELETE CASCADE
		) ENGINE=InnoDB DEFAULT CHARSET=utf8";

		foreach ($sql as $query) {
			if (!$db->query($query)) {
				return false;
			}
		}
		return true;
	}

	public static function isDeliveredStatus(?string $status): bool
	{
		$val = strtolower(trim((string)$status));
		return $val !== '' && (strpos($val, 'zugestellt') !== false || strpos($val, 'delivered') !== false);
	}

	public static function createDeliveryTodoFromDachser(?mysqli $db, array $context): array
	{
		if (!$db) {
			return ['ok' => false, 'reason' => 'db_missing'];
		}
		if (!self::ensureTables($db)) {
			return ['ok' => false, 'reason' => 'ensure_tables_failed'];
		}

		$newStatus = trim((string)($context['new_status'] ?? ''));
		$oldStatus = trim((string)($context['old_status'] ?? ''));
		$purchaseOrderId = trim((string)($context['purchase_order_id'] ?? ''));
		$reference = trim((string)($context['reference'] ?? ''));
		$statusChanged = !empty($context['status_changed']);

		if (!self::isDeliveredStatus($newStatus)) {
			return ['ok' => true, 'created' => false, 'reason' => 'status_not_delivered'];
		}
		if (!$statusChanged || self::isDeliveredStatus($oldStatus)) {
			return ['ok' => true, 'created' => false, 'reason' => 'status_not_changed'];
		}

		$jobKeySuffix = $purchaseOrderId !== '' ? $purchaseOrderId : $reference;
		if ($jobKeySuffix === '') {
			return ['ok' => false, 'created' => false, 'reason' => 'missing_reference'];
		}
		$jobKey = 'dachser_zugestellt:' . $jobKeySuffix;

		$existing = $db->prepare('SELECT id FROM mw_jobs WHERE job_key = ? LIMIT 1');
		if ($existing) {
			$existing->bind_param('s', $jobKey);
			$existing->execute();
			$res = $existing->get_result();
			if ($row = $res->fetch_assoc()) {
				return ['ok' => true, 'created' => false, 'reason' => 'idempotent', 'job_id' => (int)$row['id']];
			}
		}

		$ctxRow = self::resolveOrderContext($db, $purchaseOrderId, $reference);
		$relationType = !empty($ctxRow['sales_order_id']) ? 'sales_order' : (!empty($ctxRow['purchase_order_id']) ? 'purchase_order' : 'none');
		$relationId = $relationType === 'sales_order' ? (string)$ctxRow['sales_order_id'] : (string)($ctxRow['purchase_order_id'] ?? '');
		if ($relationId === '') {
			$relationType = 'none';
			$relationId = null;
		}

		$payload = [
			'source' => 'dachser_status',
			'event' => 'status_changed_to_zugestellt',
			'purchase_order_id' => $ctxRow['purchase_order_id'] ?? $purchaseOrderId,
			'be_order_no' => $ctxRow['be_order_no'] ?? '',
			'sales_order_id' => $ctxRow['sales_order_id'] ?? '',
			'ab_order_no' => $ctxRow['ab_order_no'] ?? '',
			'account_id' => $ctxRow['account_id'] ?? '',
			'account_name' => $ctxRow['account_name'] ?? '',
			'reference' => $reference,
			'old_status' => $oldStatus,
			'new_status' => $newStatus,
		];
		$payloadJson = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

		$title = 'Ware zugestellt';
		$description = 'AB in Rechnung umwandeln';
		$jobType = 'delivery_followup';

		$insert = $db->prepare('INSERT INTO mw_jobs
			(job_key, title, job_type, description, relation_type, relation_id, account_id, payload_json, schedule_type, run_mode, run_at, next_run_at, status, created_at, updated_at)
			VALUES (?,?,?,?,?,?,?,?, "once", "auto", NOW(), NOW(), "active", NOW(), NOW())');
		if (!$insert) {
			return ['ok' => false, 'created' => false, 'reason' => 'insert_prepare_failed'];
		}
		$accountId = isset($ctxRow['account_id']) && $ctxRow['account_id'] !== '' ? (string)$ctxRow['account_id'] : null;
		$insert->bind_param('ssssssss', $jobKey, $title, $jobType, $description, $relationType, $relationId, $accountId, $payloadJson);
		if (!$insert->execute()) {
			return ['ok' => false, 'created' => false, 'reason' => 'insert_failed'];
		}
		$jobId = (int)$db->insert_id;

		$stepPayload = json_encode([
			'sales_order_id' => (string)($ctxRow['sales_order_id'] ?? ''),
			'ab_order_no' => (string)($ctxRow['ab_order_no'] ?? ''),
			'purchase_order_id' => (string)($ctxRow['purchase_order_id'] ?? $purchaseOrderId),
			'be_order_no' => (string)($ctxRow['be_order_no'] ?? ''),
		], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		$stepTitle = !empty($ctxRow['ab_order_no']) ? 'AB in Rechnung umwandeln (' . (string)$ctxRow['ab_order_no'] . ')' : 'AB in Rechnung umwandeln';
		$stepInsert = $db->prepare('INSERT INTO mw_job_steps
			(job_id, step_order, step_title, step_type, step_payload_json, due_at, is_required, created_at, updated_at)
			VALUES (?, 1, ?, "convert_ab_to_invoice", ?, NOW(), 1, NOW(), NOW())');
		if ($stepInsert) {
			$stepInsert->bind_param('iss', $jobId, $stepTitle, $stepPayload);
			$stepInsert->execute();
		}

		return ['ok' => true, 'created' => true, 'job_id' => $jobId];
	}

	public static function insertJob(?mysqli $db, array $job, array $steps): array
	{
		if (!$db) {
			return ['ok' => false, 'reason' => 'db_missing'];
		}
		if (!self::ensureTables($db)) {
			return ['ok' => false, 'reason' => 'ensure_tables_failed'];
		}

		$title = trim((string)($job['title'] ?? ''));
		if ($title === '') {
			return ['ok' => false, 'reason' => 'missing_title'];
		}

		$jobType = trim((string)($job['job_type'] ?? 'generic'));
		$description = trim((string)($job['description'] ?? ''));
		$relationType = trim((string)($job['relation_type'] ?? 'none'));
		$allowedRelation = ['none', 'sales_order', 'purchase_order', 'account', 'customer', 'other'];
		if (!in_array($relationType, $allowedRelation, true)) {
			$relationType = 'none';
		}
		$relationId = trim((string)($job['relation_id'] ?? ''));
		if ($relationId === '') {
			$relationId = null;
		}
		$accountId = trim((string)($job['account_id'] ?? ''));
		if ($accountId === '') {
			$accountId = null;
		}
		$payloadJson = trim((string)($job['payload_json'] ?? ''));
		if ($payloadJson === '') {
			$payloadJson = null;
		}

		$scheduleType = trim((string)($job['schedule_type'] ?? 'once'));
		$allowedSchedule = ['once', 'interval_minutes', 'daily_time'];
		if (!in_array($scheduleType, $allowedSchedule, true)) {
			$scheduleType = 'once';
		}

		$runMode = trim((string)($job['run_mode'] ?? 'manual'));
		$runMode = $runMode === 'auto' ? 'auto' : 'manual';

		$runAt = trim((string)($job['run_at'] ?? ''));
		$runAtDb = self::normalizeDateTime($runAt);
		$intervalMinutes = (int)($job['interval_minutes'] ?? 0);
		if ($intervalMinutes < 1) {
			$intervalMinutes = null;
		}
		$dailyTime = trim((string)($job['daily_time'] ?? ''));
		if ($dailyTime === '' || !preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $dailyTime)) {
			$dailyTime = null;
		}
		$timezone = trim((string)($job['timezone'] ?? 'Europe/Vienna'));
		if ($timezone === '') {
			$timezone = 'Europe/Vienna';
		}

		$nextRunAt = self::computeNextRunAt([
			'schedule_type' => $scheduleType,
			'run_mode' => $runMode,
			'run_at' => $runAtDb,
			'interval_minutes' => $intervalMinutes,
			'daily_time' => $dailyTime,
		]);

		$insert = $db->prepare('INSERT INTO mw_jobs
			(job_key, title, job_type, description, relation_type, relation_id, account_id, payload_json, schedule_type, run_mode, run_at, next_run_at, interval_minutes, daily_time, timezone, status, created_at, updated_at)
			VALUES (NULL,?,?,?,?,?,?,?,?,?,?,?,?,?,?,"active",NOW(),NOW())');
		if (!$insert) {
			return ['ok' => false, 'reason' => 'insert_prepare_failed'];
		}
		$insert->bind_param(
			'sssssssssssiss',
			$title,
			$jobType,
			$description,
			$relationType,
			$relationId,
			$accountId,
			$payloadJson,
			$scheduleType,
			$runMode,
			$runAtDb,
			$nextRunAt,
			$intervalMinutes,
			$dailyTime,
			$timezone
		);
		if (!$insert->execute()) {
			return ['ok' => false, 'reason' => 'insert_failed'];
		}
		$jobId = (int)$db->insert_id;

		self::replaceSteps($db, $jobId, $steps);
		return ['ok' => true, 'job_id' => $jobId];
	}

	public static function replaceSteps(?mysqli $db, int $jobId, array $steps): void
	{
		if (!$db || $jobId <= 0) {
			return;
		}
		$del = $db->prepare('DELETE FROM mw_job_steps WHERE job_id = ?');
		if ($del) {
			$del->bind_param('i', $jobId);
			$del->execute();
		}

		$insert = $db->prepare('INSERT INTO mw_job_steps
			(job_id, step_order, step_title, step_type, step_payload_json, due_at, is_required, created_at, updated_at)
			VALUES (?,?,?,?,?,?,?,NOW(),NOW())');
		if (!$insert) {
			return;
		}

		$order = 1;
		foreach ($steps as $step) {
			$title = trim((string)($step['step_title'] ?? ''));
			if ($title === '') {
				continue;
			}
			$type = trim((string)($step['step_type'] ?? 'note'));
			if ($type === '') {
				$type = 'note';
			}
			$payload = $step['step_payload_json'] ?? null;
			if ($payload !== null) {
				$payload = trim((string)$payload);
				if ($payload === '') {
					$payload = null;
				}
			}
			$due = self::normalizeDateTime((string)($step['due_at'] ?? ''));
			$isRequired = !empty($step['is_required']) ? 1 : 0;
			$insert->bind_param('iissssi', $jobId, $order, $title, $type, $payload, $due, $isRequired);
			$insert->execute();
			$order++;
		}
	}

	public static function runJobNow(?mysqli $db, int $jobId, string $triggerType = 'manual', string $executedBy = 'user'): array
	{
		if (!$db) {
			return ['ok' => false, 'reason' => 'db_missing'];
		}
		if (!self::ensureTables($db)) {
			return ['ok' => false, 'reason' => 'ensure_tables_failed'];
		}
		if ($jobId <= 0) {
			return ['ok' => false, 'reason' => 'invalid_job_id'];
		}

		$job = self::getJobById($db, $jobId);
		if (!$job) {
			return ['ok' => false, 'reason' => 'job_not_found'];
		}
		if (in_array((string)$job['status'], ['archived', 'paused'], true)) {
			return ['ok' => false, 'reason' => 'job_not_runnable'];
		}

		$runInsert = $db->prepare('INSERT INTO mw_job_runs (job_id, trigger_type, started_at, status, executed_by, created_at) VALUES (?, ?, NOW(), "running", ?, NOW())');
		if (!$runInsert) {
			return ['ok' => false, 'reason' => 'run_insert_prepare_failed'];
		}
		$runInsert->bind_param('iss', $jobId, $triggerType, $executedBy);
		if (!$runInsert->execute()) {
			return ['ok' => false, 'reason' => 'run_insert_failed'];
		}
		$runId = (int)$db->insert_id;

		$steps = self::getSteps($db, $jobId);
		$messages = [];
		$status = 'ok';

		if (!$steps) {
			$status = 'skipped';
			$messages[] = 'Keine Arbeitsschritte definiert.';
		} else {
			foreach ($steps as $step) {
				$stepType = trim((string)($step['step_type'] ?? 'note'));
				$stepTitle = trim((string)($step['step_title'] ?? 'Schritt'));
				$payload = json_decode((string)($step['step_payload_json'] ?? ''), true);
				if (!is_array($payload)) {
					$payload = [];
				}

				if ($stepType === 'convert_ab_to_invoice') {
					$exec = self::executeConvertAbToInvoice($db, $job, $payload);
					$messages[] = $stepTitle . ': ' . (string)($exec['message'] ?? 'ohne Meldung');
					if (empty($exec['ok'])) {
						$status = 'error';
						break;
					}
				} else {
					$messages[] = $stepTitle . ': als erledigt markiert.';
				}
			}
		}

		$resultMessage = implode("\n", $messages);
		$resultJson = json_encode(['steps' => $steps, 'messages' => $messages], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

		$runUpdate = $db->prepare('UPDATE mw_job_runs SET finished_at = NOW(), status = ?, result_message = ?, result_json = ? WHERE id = ?');
		if ($runUpdate) {
			$runUpdate->bind_param('sssi', $status, $resultMessage, $resultJson, $runId);
			$runUpdate->execute();
		}

		$newJobStatus = 'active';
		$nextRunAt = self::computeNextRunAt($job, date('Y-m-d H:i:s'));
		if ((string)$job['schedule_type'] === 'once') {
			$newJobStatus = 'done';
			$nextRunAt = null;
		}
		if ((string)$job['status'] === 'error' && $status !== 'error') {
			$newJobStatus = 'active';
		}

		$jobUpdate = $db->prepare('UPDATE mw_jobs SET last_run_at = NOW(), last_result = ?, last_result_message = ?, next_run_at = ?, status = ?, updated_at = NOW() WHERE id = ?');
		if ($jobUpdate) {
			$jobUpdate->bind_param('ssssi', $status, $resultMessage, $nextRunAt, $newJobStatus, $jobId);
			$jobUpdate->execute();
		}

		return ['ok' => true, 'run_id' => $runId, 'status' => $status, 'message' => $resultMessage];
	}

	public static function setJobStatus(?mysqli $db, int $jobId, string $status): bool
	{
		if (!$db || $jobId <= 0) {
			return false;
		}
		$allowed = ['active', 'paused', 'done', 'error', 'archived'];
		if (!in_array($status, $allowed, true)) {
			return false;
		}
		$stmt = $db->prepare('UPDATE mw_jobs SET status = ?, updated_at = NOW() WHERE id = ?');
		if (!$stmt) {
			return false;
		}
		$stmt->bind_param('si', $status, $jobId);
		return $stmt->execute();
	}

	private static function resolveOrderContext(mysqli $db, string $purchaseOrderId, string $reference): array
	{
		if ($purchaseOrderId !== '') {
			$stmt = $db->prepare("SELECT
				p.id AS purchase_order_id,
				CONCAT(COALESCE(p.prefix,''), p.po_number) AS be_order_no,
				p.from_so_id AS sales_order_id,
				CONCAT(COALESCE(so.prefix,''), so.so_number) AS ab_order_no,
				a.id AS account_id,
				a.name AS account_name
				FROM purchase_orders p
				LEFT JOIN sales_orders so ON so.id = p.from_so_id AND so.deleted = 0
				LEFT JOIN accounts a ON a.id = p.supplier_id AND a.deleted = 0
				WHERE p.id = ?
				LIMIT 1");
			if ($stmt) {
				$stmt->bind_param('s', $purchaseOrderId);
				$stmt->execute();
				$res = $stmt->get_result();
				if ($row = $res->fetch_assoc()) {
					return $row;
				}
			}
		}

		if ($reference !== '') {
			$stmt = $db->prepare("SELECT
				p.id AS purchase_order_id,
				CONCAT(COALESCE(p.prefix,''), p.po_number) AS be_order_no,
				p.from_so_id AS sales_order_id,
				CONCAT(COALESCE(so.prefix,''), so.so_number) AS ab_order_no,
				a.id AS account_id,
				a.name AS account_name
				FROM mw_addinol_refs r
				INNER JOIN purchase_orders p ON p.id = r.sales_order_id AND p.deleted = 0
				LEFT JOIN sales_orders so ON so.id = p.from_so_id AND so.deleted = 0
				LEFT JOIN accounts a ON a.id = p.supplier_id AND a.deleted = 0
				WHERE r.at_order_no = ?
				ORDER BY r.id DESC
				LIMIT 1");
			if ($stmt) {
				$stmt->bind_param('s', $reference);
				$stmt->execute();
				$res = $stmt->get_result();
				if ($row = $res->fetch_assoc()) {
					return $row;
				}
			}
		}

		return [];
	}

	private static function computeNextRunAt(array $job, ?string $baseNow = null): ?string
	{
		$scheduleType = trim((string)($job['schedule_type'] ?? 'once'));
		$runMode = trim((string)($job['run_mode'] ?? 'manual'));
		if ($runMode !== 'auto' && $scheduleType !== 'once') {
			return null;
		}

		$now = $baseNow !== null ? strtotime($baseNow) : time();
		if ($now === false) {
			$now = time();
		}

		if ($scheduleType === 'once') {
			$runAt = trim((string)($job['run_at'] ?? ''));
			if ($runAt !== '') {
				$ts = strtotime($runAt);
				if ($ts !== false) {
					return date('Y-m-d H:i:s', $ts);
				}
			}
			return date('Y-m-d H:i:s', $now);
		}

		if ($scheduleType === 'interval_minutes') {
			$minutes = (int)($job['interval_minutes'] ?? 0);
			if ($minutes < 1) {
				return null;
			}
			return date('Y-m-d H:i:s', $now + ($minutes * 60));
		}

		if ($scheduleType === 'daily_time') {
			$dailyTime = trim((string)($job['daily_time'] ?? ''));
			if ($dailyTime === '') {
				return null;
			}
			$today = date('Y-m-d', $now);
			$ts = strtotime($today . ' ' . $dailyTime);
			if ($ts === false) {
				return null;
			}
			if ($ts <= $now) {
				$ts = strtotime('+1 day', $ts);
			}
			return date('Y-m-d H:i:s', $ts);
		}

		return null;
	}

	private static function getJobById(mysqli $db, int $jobId): ?array
	{
		$stmt = $db->prepare('SELECT * FROM mw_jobs WHERE id = ? LIMIT 1');
		if (!$stmt) {
			return null;
		}
		$stmt->bind_param('i', $jobId);
		$stmt->execute();
		$res = $stmt->get_result();
		$row = $res->fetch_assoc();
		return is_array($row) ? $row : null;
	}

	private static function getSteps(mysqli $db, int $jobId): array
	{
		$stmt = $db->prepare('SELECT id, step_order, step_title, step_type, step_payload_json, due_at, is_required FROM mw_job_steps WHERE job_id = ? ORDER BY step_order ASC, id ASC');
		if (!$stmt) {
			return [];
		}
		$stmt->bind_param('i', $jobId);
		$stmt->execute();
		$res = $stmt->get_result();
		$out = [];
		while ($row = $res->fetch_assoc()) {
			$out[] = $row;
		}
		return $out;
	}

	private static function normalizeDateTime(string $value): ?string
	{
		$value = trim($value);
		if ($value === '') {
			return null;
		}
		$ts = strtotime($value);
		if ($ts === false) {
			return null;
		}
		return date('Y-m-d H:i:s', $ts);
	}

	private static function executeConvertAbToInvoice(mysqli $db, array $job, array $payload): array
	{
		$salesOrderId = trim((string)($payload['sales_order_id'] ?? ''));
		if ($salesOrderId === '' && ((string)($job['relation_type'] ?? '') === 'sales_order')) {
			$salesOrderId = trim((string)($job['relation_id'] ?? ''));
		}
		if ($salesOrderId === '') {
			$purchaseOrderId = trim((string)($payload['purchase_order_id'] ?? ''));
			if ($purchaseOrderId !== '') {
				$stmtSo = $db->prepare('SELECT from_so_id FROM purchase_orders WHERE id = ? AND deleted = 0 LIMIT 1');
				if ($stmtSo) {
					$stmtSo->bind_param('s', $purchaseOrderId);
					$stmtSo->execute();
					$resSo = $stmtSo->get_result();
					if ($rowSo = $resSo->fetch_assoc()) {
						$salesOrderId = trim((string)($rowSo['from_so_id'] ?? ''));
					}
				}
			}
		}
		if ($salesOrderId === '') {
			return ['ok' => false, 'message' => 'SalesOrder-ID fehlt.'];
		}

		$soStmt = $db->prepare('SELECT * FROM sales_orders WHERE id = ? AND deleted = 0 LIMIT 1');
		if (!$soStmt) {
			return ['ok' => false, 'message' => 'SalesOrder konnte nicht vorbereitet werden.'];
		}
		$soStmt->bind_param('s', $salesOrderId);
		$soStmt->execute();
		$soRes = $soStmt->get_result();
		$salesOrder = $soRes->fetch_assoc();
		if (!$salesOrder) {
			return ['ok' => false, 'message' => 'SalesOrder nicht gefunden: ' . $salesOrderId];
		}

		$existingInvStmt = $db->prepare('SELECT id, prefix, invoice_number FROM invoice WHERE from_so_id = ? AND deleted = 0 ORDER BY date_entered DESC LIMIT 1');
		if ($existingInvStmt) {
			$existingInvStmt->bind_param('s', $salesOrderId);
			$existingInvStmt->execute();
			$existingInvRes = $existingInvStmt->get_result();
			if ($existingInv = $existingInvRes->fetch_assoc()) {
				$invNo = trim((string)($existingInv['prefix'] ?? '') . (string)($existingInv['invoice_number'] ?? ''));
				return ['ok' => true, 'message' => 'Rechnung existiert bereits: ' . $invNo, 'invoice_id' => (string)$existingInv['id']];
			}
		}

		$invoicePrefix = 'RE' . date('Y') . '-';
		$invoiceNumber = 0;
		$invoiceId = self::generateGuid();
		$now = date('Y-m-d H:i:s');
		$today = date('Y-m-d');
		$esc = static function ($v) use ($db): string {
			if ($v === null) {
				return 'NULL';
			}
			return "'" . $db->real_escape_string((string)$v) . "'";
		};
		$num = static function ($v): string {
			if ($v === null || $v === '') {
				return 'NULL';
			}
			return (string)(0 + $v);
		};
		$intVal = static function ($v): int {
			return (int)(is_numeric($v) ? $v : 0);
		};

		$db->begin_transaction();
		try {
			$numStmt = $db->prepare('SELECT COALESCE(MAX(invoice_number), 0) AS max_no FROM invoice WHERE prefix = ? FOR UPDATE');
			if (!$numStmt) {
				throw new RuntimeException('invoice number lock failed');
			}
			$numStmt->bind_param('s', $invoicePrefix);
			$numStmt->execute();
			$numRes = $numStmt->get_result();
			$numRow = $numRes->fetch_assoc();
			$invoiceNumber = (int)($numRow['max_no'] ?? 0) + 1;

			$dueDate = trim((string)($salesOrder['due_date'] ?? ''));
			if ($dueDate === '' || $dueDate === '0000-00-00') {
				$dueDate = $today;
			}

			$insertInvoiceSql = "INSERT INTO invoice (
				id, date_entered, date_modified, modified_user_id, assigned_user_id, created_by, deleted,
				currency_id, exchange_rate, prefix, invoice_number, from_so_id, shipping_stage, cancelled, products_created,
				name, opportunity_id, purchase_order_num, invoice_date, due_date, partner_id, billing_account_id, billing_contact_id,
				billing_address_street, billing_address_city, billing_address_state, billing_address_postalcode, billing_address_country,
				shipping_account_id, shipping_contact_id, shipping_address_street, shipping_address_city, shipping_address_state,
				shipping_address_postalcode, shipping_address_country, shipping_provider_id, description,
				amount, amount_usdollar, amount_due, amount_due_usdollar,
				gross_profit, gross_profit_usdollar, subtotal, subtotal_usd, pretax, pretax_usd,
				terms, tax_information, show_components, tax_exempt, discount_before_taxes, pricebook_id,
				billing_address_statecode, billing_address_countrycode, shipping_address_statecode, shipping_address_countrycode,
				net_amount, net_amount_usdollar
			) VALUES (
				" . $esc($invoiceId) . ", " . $esc($now) . ", " . $esc($now) . ", " . $esc($salesOrder['modified_user_id'] ?? null) . ", " . $esc($salesOrder['assigned_user_id'] ?? null) . ", " . $esc($salesOrder['created_by'] ?? null) . ", 0,
				" . $esc($salesOrder['currency_id'] ?? '-99') . ", " . $num($salesOrder['exchange_rate'] ?? 1) . ", " . $esc($invoicePrefix) . ", " . $invoiceNumber . ", " . $esc($salesOrderId) . ", " . $esc($salesOrder['so_stage'] ?? 'Pending') . ", 0, 0,
				" . $esc($salesOrder['name'] ?? '') . ", " . $esc($salesOrder['opportunity_id'] ?? null) . ", " . $esc($salesOrder['purchase_order_num'] ?? null) . ", " . $esc($today) . ", " . $esc($dueDate) . ", " . $esc($salesOrder['partner_id'] ?? null) . ", " . $esc($salesOrder['billing_account_id'] ?? null) . ", " . $esc($salesOrder['billing_contact_id'] ?? null) . ",
				" . $esc($salesOrder['billing_address_street'] ?? null) . ", " . $esc($salesOrder['billing_address_city'] ?? null) . ", " . $esc($salesOrder['billing_address_state'] ?? null) . ", " . $esc($salesOrder['billing_address_postalcode'] ?? null) . ", " . $esc($salesOrder['billing_address_country'] ?? null) . ",
				" . $esc($salesOrder['shipping_account_id'] ?? null) . ", " . $esc($salesOrder['shipping_contact_id'] ?? null) . ", " . $esc($salesOrder['shipping_address_street'] ?? null) . ", " . $esc($salesOrder['shipping_address_city'] ?? null) . ", " . $esc($salesOrder['shipping_address_state'] ?? null) . ",
				" . $esc($salesOrder['shipping_address_postalcode'] ?? null) . ", " . $esc($salesOrder['shipping_address_country'] ?? null) . ", " . $esc($salesOrder['shipping_provider_id'] ?? null) . ", " . $esc($salesOrder['description'] ?? null) . ",
				" . $num($salesOrder['amount'] ?? 0) . ", " . $num($salesOrder['amount_usdollar'] ?? 0) . ", " . $num($salesOrder['amount'] ?? 0) . ", " . $num($salesOrder['amount_usdollar'] ?? 0) . ",
				" . $num($salesOrder['gross_profit_so'] ?? 0) . ", " . $num($salesOrder['gross_profit_so_usd'] ?? 0) . ", " . $num($salesOrder['subtotal'] ?? 0) . ", " . $num($salesOrder['subtotal_usd'] ?? 0) . ", " . $num($salesOrder['pretax'] ?? 0) . ", " . $num($salesOrder['pretax_usd'] ?? 0) . ",
				" . $esc($salesOrder['terms'] ?? '') . ", " . $esc($salesOrder['tax_information'] ?? null) . ", " . $esc($salesOrder['show_components'] ?? '') . ", " . $intVal($salesOrder['tax_exempt'] ?? 0) . ", " . $intVal($salesOrder['discount_before_taxes'] ?? 0) . ", " . $esc($salesOrder['pricebook_id'] ?? null) . ",
				" . $esc($salesOrder['billing_address_statecode'] ?? null) . ", " . $esc($salesOrder['billing_address_countrycode'] ?? null) . ", " . $esc($salesOrder['shipping_address_statecode'] ?? null) . ", " . $esc($salesOrder['shipping_address_countrycode'] ?? null) . ",
				" . $num($salesOrder['pretax'] ?? 0) . ", " . $num($salesOrder['pretax_usd'] ?? 0) . "
			)";
			if (!$db->query($insertInvoiceSql)) {
				throw new RuntimeException('invoice insert failed: ' . $db->error);
			}

			$groupMap = [];
				$groupsStmt = $db->prepare('SELECT * FROM sales_order_line_groups WHERE parent_id = ? AND deleted = 0 ORDER BY position ASC, id ASC');
				if ($groupsStmt) {
					$groupsStmt->bind_param('s', $salesOrderId);
					$groupsStmt->execute();
					$groupsRes = $groupsStmt->get_result();
					while ($g = $groupsRes->fetch_assoc()) {
					$newGroupId = self::generateGuid();
					$insertGroupSql = "INSERT INTO invoice_line_groups
						(id, date_entered, date_modified, deleted, parent_id, name, position, status, pricing_method, pricing_percentage, cost, cost_usd, subtotal, subtotal_usd, total, total_usd, group_type)
						VALUES (
							" . $esc($newGroupId) . ", " . $esc($now) . ", " . $esc($now) . ", 0, " . $esc($invoiceId) . ",
							" . $esc($g['name'] ?? null) . ", " . $num($g['position'] ?? null) . ", " . $esc($g['status'] ?? null) . ", " . $esc($g['pricing_method'] ?? null) . ",
							" . $num($g['pricing_percentage'] ?? null) . ", " . $num($g['cost'] ?? null) . ", " . $num($g['cost_usd'] ?? null) . ", " . $num($g['subtotal'] ?? null) . ", " . $num($g['subtotal_usd'] ?? null) . ", " . $num($g['total'] ?? null) . ", " . $num($g['total_usd'] ?? null) . ", " . $esc($g['group_type'] ?? 'products') . "
						)";
					if (!$db->query($insertGroupSql)) {
						throw new RuntimeException('group insert failed: ' . $db->error);
					}
					$groupMap[(string)$g['id']] = $newGroupId;
				}
			}

			$lineMap = [];
				$linesStmt = $db->prepare('SELECT * FROM sales_order_lines WHERE sales_orders_id = ? AND deleted = 0 ORDER BY line_group_id ASC, position ASC, id ASC');
				if ($linesStmt) {
					$linesStmt->bind_param('s', $salesOrderId);
					$linesStmt->execute();
					$linesRes = $linesStmt->get_result();
					while ($l = $linesRes->fetch_assoc()) {
					$newLineId = self::generateGuid();
					$oldGroupId = (string)($l['line_group_id'] ?? '');
					$newGroupId = $groupMap[$oldGroupId] ?? $oldGroupId;
					$insertLineSql = "INSERT INTO invoice_lines
						(id, date_entered, date_modified, deleted, invoice_id, line_group_id, pricing_adjust_id, name, position, parent_id, quantity, ext_quantity, related_type, related_id, mfr_part_no, serial_no, serial_numbers, tax_class_id, sum_of_components, cost_price, cost_price_usd, list_price, list_price_usd, unit_price, unit_price_usd, std_unit_price, std_unit_price_usd, ext_price, ext_price_usd, net_price, net_price_usd)
						VALUES (
							" . $esc($newLineId) . ", " . $esc($now) . ", " . $esc($now) . ", 0, " . $esc($invoiceId) . ", " . $esc($newGroupId) . ", " . $esc($l['pricing_adjust_id'] ?? null) . ",
							" . $esc($l['name'] ?? null) . ", " . $num($l['position'] ?? null) . ", " . $esc($l['parent_id'] ?? null) . ", " . $num($l['quantity'] ?? null) . ", " . $num($l['ext_quantity'] ?? null) . ",
							" . $esc($l['related_type'] ?? null) . ", " . $esc($l['related_id'] ?? null) . ", " . $esc($l['mfr_part_no'] ?? null) . ", " . $esc($l['serial_no'] ?? null) . ", " . $esc($l['serial_numbers'] ?? null) . ", " . $esc($l['tax_class_id'] ?? null) . ",
							" . $intVal($l['sum_of_components'] ?? 0) . ", " . $num($l['cost_price'] ?? null) . ", " . $num($l['cost_price_usd'] ?? null) . ", " . $num($l['list_price'] ?? null) . ", " . $num($l['list_price_usd'] ?? null) . ", " . $num($l['unit_price'] ?? null) . ", " . $num($l['unit_price_usd'] ?? null) . ",
							" . $num($l['std_unit_price'] ?? null) . ", " . $num($l['std_unit_price_usd'] ?? null) . ", " . $num($l['ext_price'] ?? null) . ", " . $num($l['ext_price_usd'] ?? null) . ", " . $num($l['net_price'] ?? null) . ", " . $num($l['net_price_usd'] ?? null) . "
						)";
					if (!$db->query($insertLineSql)) {
						throw new RuntimeException('line insert failed: ' . $db->error);
					}
					$lineMap[(string)$l['id']] = $newLineId;
				}
			}

				$adjStmt = $db->prepare('SELECT * FROM sales_order_adjustments WHERE sales_orders_id = ? AND deleted = 0 ORDER BY line_group_id ASC, position ASC, id ASC');
				if ($adjStmt) {
					$adjStmt->bind_param('s', $salesOrderId);
					$adjStmt->execute();
					$adjRes = $adjStmt->get_result();
					while ($a = $adjRes->fetch_assoc()) {
					$newAdjId = self::generateGuid();
					$oldGroupId = (string)($a['line_group_id'] ?? '');
					$newGroupId = $groupMap[$oldGroupId] ?? $oldGroupId;
					$oldLineId = trim((string)($a['line_id'] ?? ''));
					$newLineId = $oldLineId !== '' ? ($lineMap[$oldLineId] ?? null) : null;
					$insertAdjSql = "INSERT INTO invoice_adjustments
						(id, date_entered, date_modified, deleted, invoice_id, line_group_id, line_id, name, position, related_type, related_id, rate, type, amount, amount_usd, tax_class_id)
						VALUES (
							" . $esc($newAdjId) . ", " . $esc($now) . ", " . $esc($now) . ", 0, " . $esc($invoiceId) . ", " . $esc($newGroupId) . ", " . $esc($newLineId) . ",
							" . $esc($a['name'] ?? null) . ", " . $num($a['position'] ?? null) . ", " . $esc($a['related_type'] ?? null) . ", " . $esc($a['related_id'] ?? null) . ", " . $num($a['rate'] ?? null) . ", " . $esc($a['type'] ?? null) . ", " . $num($a['amount'] ?? null) . ", " . $num($a['amount_usd'] ?? null) . ", " . $esc($a['tax_class_id'] ?? null) . "
						)";
					if (!$db->query($insertAdjSql)) {
						throw new RuntimeException('adjustment insert failed: ' . $db->error);
					}
				}
			}

			$soStage = 'Closed - Shipped and Invoiced';
			$updSo = $db->prepare('UPDATE sales_orders SET so_stage = ?, date_modified = ?, modified_user_id = ? WHERE id = ? AND deleted = 0 LIMIT 1');
			if ($updSo) {
				$updSo->bind_param('ssss', $soStage, $now, $salesOrder['modified_user_id'], $salesOrderId);
				$updSo->execute();
			}

			$db->commit();
		} catch (Throwable $e) {
			$db->rollback();
			return ['ok' => false, 'message' => 'AB->Rechnung fehlgeschlagen: ' . $e->getMessage()];
		}

		return [
			'ok' => true,
			'message' => 'Rechnung erstellt: ' . $invoicePrefix . $invoiceNumber,
			'invoice_id' => $invoiceId,
			'invoice_number' => $invoicePrefix . $invoiceNumber,
		];
	}

	private static function generateGuid(): string
	{
		$data = random_bytes(16);
		$data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
		$data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
		return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
	}
}
