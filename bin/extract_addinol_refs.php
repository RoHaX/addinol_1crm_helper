<?php
	require_once __DIR__ . '/../db.inc.php';

	$mysqli = $mysqli ?? null;
	if (!$mysqli) {
		file_put_contents('php://stderr', "DB connection missing\n");
		exit(1);
	}
	$mysqli->set_charset('utf8');

	function out_line(string $msg): void {
		@file_put_contents('php://stdout', $msg . "\n");
	}

	function err_line(string $msg): void {
		@file_put_contents('php://stderr', $msg . "\n");
	}

	$logsDir = realpath(__DIR__ . '/../logs') ?: (__DIR__ . '/../logs');
	if (!is_dir($logsDir)) {
		@mkdir($logsDir, 0775, true);
	}
	$statusFile = rtrim($logsDir, '/') . '/extract_addinol_refs.status.json';
	$lockFile = rtrim($logsDir, '/') . '/extract_addinol_refs.lock';

	function write_extract_status(string $statusFile, array $payload): void {
		@file_put_contents($statusFile, json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
	}

	function process_is_running(int $pid): bool {
		if ($pid <= 0) {
			return false;
		}
		if (function_exists('posix_kill')) {
			return @posix_kill($pid, 0);
		}
		return is_dir('/proc/' . $pid);
	}

	function read_lock_pid(string $lockFile): int {
		if (!is_file($lockFile)) {
			return 0;
		}
		$raw = trim((string)@file_get_contents($lockFile));
		if ($raw === '') {
			return 0;
		}
		if ($raw[0] === '{') {
			$data = json_decode($raw, true);
			if (is_array($data) && !empty($data['pid'])) {
				return (int)$data['pid'];
			}
		}
		return (int)$raw;
	}

	function write_lock_file(string $lockFile, int $pid): void {
		@file_put_contents($lockFile, json_encode([
			'pid' => $pid,
			'started_at' => date('c'),
		], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), LOCK_EX);
	}

	$force = getenv('FORCE_RECHECK') === '1';
	$limit = (int)(getenv('LIMIT') ?: 300);
	$targetNoteId = trim((string)(getenv('TARGET_NOTE_ID') ?: ''));
	$targetPoId = trim((string)(getenv('TARGET_PO_ID') ?: ''));
	$targetEmailId = trim((string)(getenv('TARGET_EMAIL_ID') ?: ''));
	if ($limit < 1) {
		$limit = 300;
	}

	$existingPid = read_lock_pid($lockFile);
	if ($existingPid > 0 && process_is_running($existingPid)) {
		write_extract_status($statusFile, [
			'running' => true,
			'started_at' => date('c'),
			'message' => 'already_running',
			'force' => $force,
			'limit' => $limit,
			'target_note_id' => $targetNoteId,
			'target_po_id' => $targetPoId,
			'target_email_id' => $targetEmailId,
			'pid' => getmypid(),
		]);
		out_line("already running");
		exit(2);
	}
	write_lock_file($lockFile, (int)getmypid());

	$startedAt = date('c');
	$finalized = false;
	register_shutdown_function(function () use (&$finalized, $statusFile, $startedAt, $force, $limit, $targetNoteId, $targetPoId, $targetEmailId, $lockFile) {
		@unlink($lockFile);
		if ($finalized) {
			return;
		}
		$err = error_get_last();
		write_extract_status($statusFile, [
			'running' => false,
			'started_at' => $startedAt,
			'finished_at' => date('c'),
			'exit_code' => 1,
			'message' => $err ? ('fatal: ' . ($err['message'] ?? 'unknown')) : 'aborted',
			'force' => $force,
			'limit' => $limit,
			'target_note_id' => $targetNoteId,
			'target_po_id' => $targetPoId,
			'target_email_id' => $targetEmailId,
			'pid' => getmypid(),
		]);
	});
	write_extract_status($statusFile, [
		'running' => true,
		'started_at' => $startedAt,
		'force' => $force,
		'limit' => $limit,
		'target_note_id' => $targetNoteId,
		'target_po_id' => $targetPoId,
		'target_email_id' => $targetEmailId,
		'pid' => getmypid(),
		'message' => 'started',
	]);

	$uploadRoot = realpath(__DIR__ . '/../../files/upload');
	if (!$uploadRoot || !is_dir($uploadRoot)) {
		err_line("Upload path not found: " . __DIR__ . "/../../files/upload");
		$finalized = true;
		write_extract_status($statusFile, [
			'running' => false,
			'started_at' => $startedAt,
			'finished_at' => date('c'),
			'exit_code' => 1,
			'message' => 'upload_path_missing',
			'force' => $force,
			'limit' => $limit,
			'target_note_id' => $targetNoteId,
			'target_po_id' => $targetPoId,
			'target_email_id' => $targetEmailId,
			'pid' => getmypid(),
		]);
		exit(1);
	}

	$selectSql = "SELECT n.id AS note_id, n.filename, n.parent_type, n.parent_id, n.date_modified,
					 e.from_addr, e.name AS email_subject,
					 r.id AS existing_ref_id
				  FROM notes n
				  LEFT JOIN emails e ON e.id = n.parent_id AND n.parent_type = 'Emails'
				  LEFT JOIN mw_addinol_refs r ON r.note_id = n.id
				  WHERE n.deleted = 0
					AND n.parent_type IN ('Emails', 'SalesOrders', 'PurchaseOrders')
					AND LOWER(n.filename) LIKE '%.pdf'";
	if ($targetNoteId !== '') {
		$safeNoteId = $mysqli->real_escape_string($targetNoteId);
		$selectSql .= " AND n.id = '" . $safeNoteId . "'";
	}
	if ($targetPoId !== '') {
		$safePoId = $mysqli->real_escape_string($targetPoId);
		$selectSql .= " AND (
			(n.parent_type = 'PurchaseOrders' AND n.parent_id = '" . $safePoId . "')
			OR
			(n.parent_type = 'SalesOrders' AND n.parent_id = (SELECT p0.from_so_id FROM purchase_orders p0 WHERE p0.id = '" . $safePoId . "' LIMIT 1))
			OR
			(n.parent_type = 'Emails' AND (
				n.parent_id IN (SELECT ep.email_id FROM emails_purchaseorders ep WHERE ep.deleted = 0 AND ep.po_id = '" . $safePoId . "')
				OR n.parent_id IN (
					SELECT es.email_id
					FROM emails_salesorders es
					INNER JOIN purchase_orders p1 ON p1.from_so_id = es.so_id
					WHERE es.deleted = 0 AND p1.id = '" . $safePoId . "'
				)
			))
		)";
	}
	if ($targetEmailId !== '') {
		$safeEmailId = $mysqli->real_escape_string($targetEmailId);
		$selectSql .= " AND n.parent_type = 'Emails' AND n.parent_id = '" . $safeEmailId . "'";
	}
	if (!$force) {
		$selectSql .= " AND r.id IS NULL";
	}
	$selectSql .= " ORDER BY n.date_modified DESC LIMIT " . $limit;

	$rows = [];
	$res = $mysqli->query($selectSql);
	if ($res) {
		while ($row = $res->fetch_assoc()) {
			$rows[] = $row;
		}
	}

	$findPurchaseOrderByNumber = $mysqli->prepare(
		"SELECT p.id
		 FROM purchase_orders p
		 LEFT JOIN accounts a ON a.id = p.supplier_id AND a.deleted = 0
		 WHERE p.deleted = 0
		   AND CONCAT(COALESCE(p.prefix, ''), p.po_number) = ?
		   AND (a.name LIKE '%Addinol%' OR a.name LIKE '%ADDINOL%')
		 ORDER BY p.date_modified DESC
		 LIMIT 1"
	);
	$findPurchaseOrderByName = $mysqli->prepare(
		"SELECT p.id
		 FROM purchase_orders p
		 LEFT JOIN accounts a ON a.id = p.supplier_id AND a.deleted = 0
		 WHERE p.deleted = 0
		   AND p.name LIKE ?
		   AND (a.name LIKE '%Addinol%' OR a.name LIKE '%ADDINOL%')
		 ORDER BY p.date_modified DESC
		 LIMIT 1"
	);
	$findPurchaseOrderBySalesOrder = $mysqli->prepare(
		"SELECT p.id
		 FROM purchase_orders p
		 LEFT JOIN accounts a ON a.id = p.supplier_id AND a.deleted = 0
		 WHERE p.deleted = 0
		   AND p.from_so_id = ?
		   AND (a.name LIKE '%Addinol%' OR a.name LIKE '%ADDINOL%')
		 ORDER BY p.date_modified DESC
		 LIMIT 1"
	);
	$upsert = $mysqli->prepare(
		"INSERT INTO mw_addinol_refs (sales_order_id, be_order_no, at_order_no, note_id, email_id, source_filename, extracted_at, updated_at)
		 VALUES (?,?,?,?,?,?,NOW(),NOW())
		 ON DUPLICATE KEY UPDATE
			sales_order_id = VALUES(sales_order_id),
			be_order_no = VALUES(be_order_no),
			at_order_no = IF(VALUES(at_order_no) <> '', VALUES(at_order_no), at_order_no),
			email_id = VALUES(email_id),
			source_filename = IF(VALUES(at_order_no) <> '', VALUES(source_filename), source_filename),
			updated_at = NOW()"
	);

	if (!$findPurchaseOrderByNumber || !$findPurchaseOrderByName || !$findPurchaseOrderBySalesOrder || !$upsert) {
		err_line("Failed to prepare statements");
		$finalized = true;
		write_extract_status($statusFile, [
			'running' => false,
			'started_at' => $startedAt,
			'finished_at' => date('c'),
			'exit_code' => 1,
			'message' => 'prepare_failed',
			'force' => $force,
			'limit' => $limit,
			'target_note_id' => $targetNoteId,
			'target_po_id' => $targetPoId,
			'target_email_id' => $targetEmailId,
			'pid' => getmypid(),
		]);
		exit(1);
	}

	function find_purchase_order_from_sales_order(mysqli_stmt $stmtBySalesOrder, string $salesOrderId): string {
		$salesOrderId = trim($salesOrderId);
		if ($salesOrderId === '') {
			return '';
		}
		$stmtBySalesOrder->bind_param('s', $salesOrderId);
		if ($stmtBySalesOrder->execute()) {
			$res = $stmtBySalesOrder->get_result();
			$row = $res ? $res->fetch_assoc() : null;
			if (!empty($row['id'])) {
				return (string)$row['id'];
			}
		}
		return '';
	}

	function normalize_ref(string $value): string {
		return strtoupper(preg_replace('/\s+/', '', trim($value)));
	}

	function extract_pdf_text(string $filepath): string {
		$cmd = 'timeout 25s gs -q -dNOPAUSE -dBATCH -sDEVICE=txtwrite -sOutputFile=- ' . escapeshellarg($filepath) . ' 2>/dev/null';
		$out = shell_exec($cmd);
		return is_string($out) ? $out : '';
	}

	function find_first_match(string $pattern, string $subject): string {
		if (!preg_match($pattern, $subject, $m)) {
			return '';
		}
		return isset($m[1]) ? normalize_ref((string)$m[1]) : '';
	}

	function extract_be_order_no(string $text): string {
		$patterns = [
			'/Ihre\s+Bestellung\s*[:#]?\s*([A-Z]{2}\s*\d{4}\s*-\s*\d+)/iu',
			'/\b(BE\s*\d{4}\s*-\s*\d+)\b/iu',
		];
		foreach ($patterns as $pattern) {
			$value = find_first_match($pattern, $text);
			if ($value !== '') {
				return str_replace(' ', '', $value);
			}
		}
		return '';
	}

	function extract_at_order_no(string $text): string {
		$patterns = [
			'/Auftragsbest[aä]tigung\s*(?:Nr\.?|Nummer)?\s*[:#]?\s*(AT\s*\d{5,})/iu',
			'/Auftrags(?:nummer|nr\.?)\s*[:#]?\s*(AT\s*\d{5,})/iu',
			'/\b(AT\s*\d{5,})\b/iu',
		];
		foreach ($patterns as $pattern) {
			$value = find_first_match($pattern, $text);
			if ($value !== '') {
				return str_replace(' ', '', $value);
			}
		}
		return '';
	}

	function find_purchase_order_id(mysqli_stmt $stmtByNo, mysqli_stmt $stmtByName, string $beOrderNo): string {
		if ($beOrderNo === '') {
			return '';
		}
		$stmtByNo->bind_param('s', $beOrderNo);
		if ($stmtByNo->execute()) {
			$res = $stmtByNo->get_result();
			$row = $res ? $res->fetch_assoc() : null;
			if (!empty($row['id'])) {
				return (string)$row['id'];
			}
		}

		$nameLike = '%' . $beOrderNo . '%';
		$stmtByName->bind_param('s', $nameLike);
		if ($stmtByName->execute()) {
			$res = $stmtByName->get_result();
			$row = $res ? $res->fetch_assoc() : null;
			if (!empty($row['id'])) {
				return (string)$row['id'];
			}
		}

		return '';
	}

	$stats = [
		'checked' => 0,
		'parsed' => 0,
		'matched_purchase_order' => 0,
		'upserted' => 0,
		'skipped_no_fields' => 0,
		'skipped_no_order_match' => 0,
		'errors' => 0,
	];

	foreach ($rows as $row) {
		$stats['checked']++;

		$relFile = ltrim((string)($row['filename'] ?? ''), '/');
		if ($relFile === '') {
			$stats['errors']++;
			continue;
		}

		$absFile = $uploadRoot . '/' . $relFile;
		if (!is_file($absFile)) {
			out_line("skip missing file: {$relFile}");
			continue;
		}

		$text = extract_pdf_text($absFile);
		if ($text === '') {
			out_line("skip empty text: {$relFile}");
			continue;
		}

		$beOrderNo = extract_be_order_no($text);
		$atOrderNo = extract_at_order_no($text);

		if ($beOrderNo === '' && $atOrderNo === '') {
			$stats['skipped_no_fields']++;
			continue;
		}
		$stats['parsed']++;

		$purchaseOrderId = '';
		$parentType = trim((string)($row['parent_type'] ?? ''));
		$parentId = trim((string)($row['parent_id'] ?? ''));
		if (strcasecmp($parentType, 'PurchaseOrders') === 0 && $parentId !== '') {
			$purchaseOrderId = $parentId;
		} elseif (strcasecmp($parentType, 'SalesOrders') === 0 && $parentId !== '') {
			$purchaseOrderId = find_purchase_order_from_sales_order($findPurchaseOrderBySalesOrder, $parentId);
		}
		if ($purchaseOrderId === '') {
			$purchaseOrderId = find_purchase_order_id($findPurchaseOrderByNumber, $findPurchaseOrderByName, $beOrderNo);
		}
		if ($purchaseOrderId === '') {
			$stats['skipped_no_order_match']++;
			out_line("no PO match for BE={$beOrderNo} file={$relFile}");
			continue;
		}
		$stats['matched_purchase_order']++;

		$noteId = (string)$row['note_id'];
		$emailId = (strcasecmp($parentType, 'Emails') === 0) ? (string)$parentId : '';
		$baseName = basename($relFile);

		$upsert->bind_param('ssssss', $purchaseOrderId, $beOrderNo, $atOrderNo, $noteId, $emailId, $baseName);
		if (!$upsert->execute()) {
			$stats['errors']++;
			err_line("upsert failed for note {$noteId}: " . $upsert->error);
			continue;
		}
		$stats['upserted']++;
		out_line("ok PO={$purchaseOrderId} BE={$beOrderNo} AT={$atOrderNo} file={$baseName}");
	}

	out_line("done " . json_encode($stats, JSON_UNESCAPED_SLASHES));
	$finalized = true;
	write_extract_status($statusFile, [
		'running' => false,
		'started_at' => $startedAt,
		'finished_at' => date('c'),
		'exit_code' => 0,
		'message' => 'done',
		'force' => $force,
		'limit' => $limit,
		'target_note_id' => $targetNoteId,
		'target_po_id' => $targetPoId,
		'target_email_id' => $targetEmailId,
		'pid' => getmypid(),
		'stats' => $stats,
	]);
	@unlink($lockFile);
	exit(0);
