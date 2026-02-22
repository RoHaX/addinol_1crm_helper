<?php
	require_once __DIR__ . '/../db.inc.php';
	if (file_exists(__DIR__ . '/config.php')) {
		require_once __DIR__ . '/config.php';
	}

	$mysqli = $mysqli ?? null;
	if (!$mysqli) {
		die('DB connection missing');
	}
	$mysqli->set_charset('utf8');

	function fetch_all_assoc_local(mysqli $mysqli, string $sql, string $types = '', array $params = []): array {
		$out = [];
		$stmt = $mysqli->prepare($sql);
		if (!$stmt) {
			return $out;
		}
		if ($types !== '' && $params) {
			$stmt->bind_param($types, ...$params);
		}
		if (!$stmt->execute()) {
			return $out;
		}
		$res = $stmt->get_result();
		while ($row = $res->fetch_assoc()) {
			$out[] = $row;
		}
		return $out;
	}

	function format_stage_badge_local(?string $stage): string {
		$raw = trim((string)$stage);
		$key = strtolower($raw);
		$map = [
			'ordered' => ['Offen', 'fas fa-hourglass-half', 'warning'],
			'draft' => ['Entwurf', 'fas fa-pencil-alt', 'secondary'],
			'partially received' => ['Teilweise erhalten', 'fas fa-dolly', 'info'],
			'received' => ['Erhalten', 'fas fa-check-circle', 'success'],
			'cancelled' => ['Storniert', 'fas fa-ban', 'dark'],
		];
		$label = $raw !== '' ? $raw : '-';
		$icon = 'fas fa-tag';
		$class = 'secondary';
		if (isset($map[$key])) {
			$label = $map[$key][0];
			$icon = $map[$key][1];
			$class = $map[$key][2];
		}
		$title = $raw !== '' ? ' title="' . htmlspecialchars($raw, ENT_QUOTES) . '"' : '';
		return '<span class="badge text-bg-' . $class . '"' . $title . '><i class="' . $icon . ' me-1"></i>' . htmlspecialchars($label) . '</span>';
	}

	function format_sales_order_stage_badge(?string $stage): string {
		$raw = trim((string)$stage);
		$key = strtolower($raw);
		$map = [
			'ordered' => ['Offen', 'fas fa-hourglass-half', 'warning'],
			'pending' => ['Offen', 'fas fa-hourglass-half', 'warning'],
			'shipped' => ['Versendet', 'fas fa-shipping-fast', 'info'],
			'partially shipped' => ['Teilversendet', 'fas fa-shipping-fast', 'info'],
			'delivered' => ['Geliefert', 'fas fa-truck', 'success'],
			'closed - shipped and invoiced' => ['Abgeschlossen', 'fas fa-check-double', 'secondary'],
		];
		$label = $raw !== '' ? $raw : '-';
		$icon = 'fas fa-tag';
		$class = 'secondary';
		if (isset($map[$key])) {
			$label = $map[$key][0];
			$icon = $map[$key][1];
			$class = $map[$key][2];
		}
		$title = $raw !== '' ? ' title="' . htmlspecialchars($raw, ENT_QUOTES) . '"' : '';
		return '<span class="badge text-bg-' . $class . '"' . $title . '><i class="' . $icon . ' me-1"></i>' . htmlspecialchars($label) . '</span>';
	}

	function resolve_php_cli_binary(): string {
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
			$bin = trim((string)$bin);
			if ($bin === '') {
				continue;
			}
			if (is_file($bin) && is_executable($bin)) {
				return $bin;
			}
		}
		return '/opt/plesk/php/8.3/bin/php';
	}

	function launch_extract_job(string $script, string $logFile, bool $force, int $limit, string $targetNoteId = '', string $targetPoId = ''): array {
		$phpBin = resolve_php_cli_binary();
		$envArgs = [
			'FORCE_RECHECK=' . ($force ? '1' : '0'),
			'LIMIT=' . (int)$limit,
		];
		if ($targetNoteId !== '') {
			$envArgs[] = 'TARGET_NOTE_ID=' . $targetNoteId;
		}
		if ($targetPoId !== '') {
			$envArgs[] = 'TARGET_PO_ID=' . $targetPoId;
		}

		$cmd = 'nohup env';
		foreach ($envArgs as $arg) {
			$cmd .= ' ' . escapeshellarg($arg);
		}
		$cmd .= ' ' . escapeshellarg($phpBin) . ' ' . escapeshellarg($script);
		$cmd .= ' > ' . escapeshellarg($logFile) . ' 2>&1 & echo $!';

		$pid = trim((string)shell_exec($cmd));
		if ($pid === '' || !preg_match('/^\d+$/', $pid)) {
			return ['ok' => false, 'message' => 'Hintergrund-Job konnte nicht gestartet werden.'];
		}
		return ['ok' => true, 'message' => 'Extraktion gestartet (PID ' . $pid . ').'];
	}

	function read_log_tail(string $file, int $maxLines = 40): array {
		if (!is_file($file) || $maxLines < 1) {
			return [];
		}
		$fp = @fopen($file, 'r');
		if (!$fp) {
			return [];
		}
		$lines = [];
		while (($line = fgets($fp)) !== false) {
			$lines[] = rtrim($line, "\r\n");
			if (count($lines) > $maxLines) {
				array_shift($lines);
			}
		}
		fclose($fp);
		return $lines;
	}

	$q = trim($_GET['q'] ?? '');
	$openOnly = (($_GET['open_only'] ?? '1') !== '0');
	$stage = trim($_GET['stage'] ?? '');
	$dachserTrackingBase = trim((string)(getenv('DACHSER_TRACKING_URL_BASE')
		?: (defined('MW_DACHSER_TRACKING_URL_BASE') ? MW_DACHSER_TRACKING_URL_BASE : 'https://elogistics.dachser.com/shp2s/?javalocale=de_DE&search=')));
		$logsDir = realpath(__DIR__ . '/../logs') ?: (__DIR__ . '/../logs');
		$statusFile = rtrim($logsDir, '/') . '/extract_addinol_refs.status.json';
		$logFile = rtrim($logsDir, '/') . '/extract_addinol_refs.log';
		$queueMessage = trim((string)($_GET['queue_msg'] ?? ''));

		if (($_GET['ajax_extract_status'] ?? '') === '1') {
			header('Content-Type: application/json; charset=utf-8');
			$status = null;
			if (is_file($statusFile)) {
				$json = (string)file_get_contents($statusFile);
				$decoded = json_decode($json, true);
				if (is_array($decoded)) {
					$status = $decoded;
				}
			}
			$logTail = read_log_tail($logFile, 50);
			echo json_encode([
				'ok' => true,
				'status' => $status,
				'log_tail' => $logTail,
				'ts' => date('c'),
			], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
			exit;
		}

	if ($_SERVER['REQUEST_METHOD'] === 'POST') {
		$action = trim((string)($_POST['action'] ?? ''));
		$script = realpath(__DIR__ . '/../bin/extract_addinol_refs.php');
		$msg = 'Ungültige Aktion.';

		if ($script && is_file($script)) {
			if ($action === 'reextract_refs') {
				$limit = (int)($_POST['limit'] ?? 5000);
				if ($limit < 1) {
					$limit = 1;
				}
				if ($limit > 50000) {
					$limit = 50000;
				}
				$result = launch_extract_job($script, $logFile, true, $limit);
				$msg = $result['message'];
			} elseif ($action === 'reextract_note') {
				$noteId = trim((string)($_POST['note_id'] ?? ''));
				$poId = trim((string)($_POST['po_id'] ?? ''));
				if (preg_match('/^[a-f0-9-]{36}$/i', $noteId)) {
					$result = launch_extract_job($script, $logFile, true, 50, $noteId, '');
					$msg = $result['message'];
				} elseif (preg_match('/^[a-f0-9-]{36}$/i', $poId)) {
					$result = launch_extract_job($script, $logFile, true, 120, '', $poId);
					$msg = $result['message'];
				} else {
					$msg = 'Ungültige Note-/PO-ID.';
				}
			} else {
				$msg = 'Aktion nicht unterstützt.';
			}
		} else {
			$msg = 'Extractor-Script nicht gefunden.';
		}

		$redirectParams = $_GET;
		$redirectParams['queue_msg'] = $msg;
		header('Location: ' . $_SERVER['PHP_SELF'] . '?' . http_build_query($redirectParams));
		exit;
	}

	$extractStatus = null;
	if (is_file($statusFile)) {
		$json = (string)file_get_contents($statusFile);
		$decoded = json_decode($json, true);
		if (is_array($decoded)) {
			$extractStatus = $decoded;
		}
	}
	$extractLogTail = read_log_tail($logFile, 50);

		$stageRows = fetch_all_assoc_local(
		$mysqli,
		"SELECT shipping_stage
		 FROM purchase_orders
		 WHERE deleted = 0
		 GROUP BY shipping_stage
		 ORDER BY shipping_stage ASC"
	);
	$stageOptions = ['' => 'Alle'];
	foreach ($stageRows as $row) {
		$val = trim((string)($row['shipping_stage'] ?? ''));
		if ($val !== '') {
			$stageOptions[$val] = $val;
		}
	}

	$where = ["p.deleted = 0", "p.date_entered >= '2026-01-01 00:00:00'"];
	$types = '';
	$params = [];

	if ($openOnly) {
		$where[] = "LOWER(COALESCE(s.so_stage, '')) NOT IN ('closed - shipped and invoiced', 'delivered')";
	}
	if ($stage !== '') {
		$where[] = 'p.shipping_stage = ?';
		$types .= 's';
		$params[] = $stage;
	}
	if ($q !== '') {
		$qLike = '%' . $q . '%';
		$where[] = "(a.name LIKE ? OR a.ticker_symbol LIKE ? OR CONCAT(COALESCE(p.prefix,''), p.po_number) LIKE ? OR p.name LIKE ? OR r.at_order_no LIKE ? OR r.be_order_no LIKE ? OR CONCAT(COALESCE(s.prefix,''), s.so_number) LIKE ?)";
		$types .= 'sssssss';
		$params[] = $qLike;
		$params[] = $qLike;
		$params[] = $qLike;
		$params[] = $qLike;
		$params[] = $qLike;
		$params[] = $qLike;
		$params[] = $qLike;
	}

	$sql = "SELECT p.id, p.prefix, p.po_number, p.name, p.shipping_stage, p.amount, p.date_modified,
				r.at_order_no, r.be_order_no, r.note_id, r.dachser_status, r.dachser_status_ts, r.dachser_via, r.dachser_info, r.dachser_last_checked_at,
				s.id AS sales_order_id, s.prefix AS sales_prefix, s.so_number, s.so_stage,
				a.id AS account_id, a.name AS account_name, a.ticker_symbol
			FROM purchase_orders p
			LEFT JOIN accounts a ON a.id = p.supplier_id AND a.deleted = 0
			LEFT JOIN sales_orders s ON s.id = p.from_so_id AND s.deleted = 0
			LEFT JOIN mw_addinol_refs r ON r.sales_order_id = p.id";
	if ($where) {
		$sql .= " WHERE " . implode(' AND ', $where);
	}
	$sql .= " ORDER BY
		CASE
			WHEN LOWER(COALESCE(p.shipping_stage, '')) = 'ordered' THEN 0
			WHEN LOWER(COALESCE(p.shipping_stage, '')) = 'partially received' THEN 1
			ELSE 2
		END ASC,
		p.date_modified DESC
		LIMIT 2000";

	$rows = fetch_all_assoc_local($mysqli, $sql, $types, $params);
?>
<!doctype html>
<html lang="de">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Lieferstatus</title>
<link href="../styles.css" rel="stylesheet" type="text/css" />
<link href="../assets/bootstrap/bootstrap.min.css" rel="stylesheet" />
<link href="../assets/datatables/dataTables.bootstrap5.min.css" rel="stylesheet" />
<link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.5.0/css/all.css" crossorigin="anonymous">
</head>
<body>
<?php if (file_exists(__DIR__ . '/../navbar.php')) { include __DIR__ . '/../navbar.php'; } ?>

<div class="container-fluid py-3">
	<div class="d-flex align-items-center justify-content-between mb-3">
		<h1 class="h3 mb-0">Lieferstatus</h1>
		<div class="d-flex gap-2">
			<?php $isExtractorRunning = !empty($extractStatus['running']); ?>
			<form method="post" class="d-flex align-items-center gap-2">
				<input type="hidden" name="action" value="reextract_refs">
				<input type="hidden" name="force" value="1">
				<label class="small text-muted mb-0" for="extractLimit">Limit</label>
				<input id="extractLimit" type="number" name="limit" value="5000" min="1" max="50000" class="form-control form-control-sm" style="width: 110px;">
				<button class="btn btn-sm btn-warning" type="submit">
					<i class="fas fa-sync-alt"></i> Erneut extrahieren
				</button>
			</form>
			<button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#extractStatusModal">
				<i class="fas fa-info-circle"></i> Extractor-Status
				<?php if ($isExtractorRunning): ?>
					<span class="badge text-bg-warning ms-1">läuft</span>
				<?php endif; ?>
			</button>
			<a class="btn btn-sm btn-outline-primary" target="_blank" rel="noopener" href="https://elogistics.dachser.com/login/home?2">
				<i class="fas fa-external-link-alt"></i> Dachser öffnen
			</a>
		</div>
	</div>

	<?php if ($queueMessage !== ''): ?>
		<div class="alert alert-secondary py-2"><?php echo htmlspecialchars($queueMessage); ?></div>
	<?php endif; ?>

		<div class="modal fade" id="extractStatusModal" tabindex="-1" aria-labelledby="extractStatusModalLabel" aria-hidden="true">
			<div class="modal-dialog modal-lg modal-dialog-scrollable">
				<div class="modal-content">
					<div class="modal-header">
						<h5 class="modal-title" id="extractStatusModalLabel"><i class="fas fa-info-circle me-2"></i>Extractor-Status</h5>
						<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Schließen"></button>
					</div>
					<div class="modal-body">
						<div id="extractStatusAlert" class="alert alert-light py-2 mb-3">
							<div id="extractStatusMeta">Lade aktuellen Status...</div>
							<div class="small mt-1 text-muted" id="extractStatusStats"></div>
						</div>
						<pre class="mb-0 small bg-light border rounded p-2" id="extractStatusLog" style="max-height: 360px; overflow:auto;">Lade Log...</pre>
					</div>
				</div>
			</div>
		</div>

	<form method="get" class="row g-2 align-items-end mb-3">
		<div class="col-sm-5 col-md-4 col-lg-3">
			<label class="form-label">Suche</label>
			<input type="text" name="q" value="<?php echo htmlspecialchars($q); ?>" class="form-control form-control-sm" placeholder="Lieferant, Konto, Bestellung, BE, AT">
		</div>
		<div class="col-sm-3 col-md-3 col-lg-2">
			<label class="form-label">Status</label>
			<select name="stage" class="form-select form-select-sm">
				<?php foreach ($stageOptions as $val => $label): ?>
					<option value="<?php echo htmlspecialchars($val); ?>" <?php echo $val === $stage ? 'selected' : ''; ?>>
						<?php echo htmlspecialchars($label); ?>
					</option>
				<?php endforeach; ?>
			</select>
		</div>
		<div class="col-sm-2 col-md-2 col-lg-2">
			<div class="form-check form-switch mt-4">
				<input type="hidden" name="open_only" value="0">
				<input class="form-check-input" type="checkbox" id="openOnly" name="open_only" value="1" <?php echo $openOnly ? 'checked' : ''; ?>>
				<label class="form-check-label" for="openOnly">Nur offen</label>
			</div>
		</div>
		<div class="col-sm-2 col-md-2 col-lg-1">
			<button class="btn btn-sm btn-primary w-100" type="submit">Filter</button>
		</div>
	</form>

	<div class="table-responsive">
		<table id="deliveryTable" class="table table-striped table-sm align-middle">
			<thead>
				<tr>
					<th>Bestellung</th>
					<th>Auftrag</th>
					<th>Referenz</th>
					<th>Dachser</th>
					<th>Status</th>
					<th>Auftrags-Status</th>
					<th>Stand</th>
					<th>Betrag</th>
					<th>Aktionen</th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ($rows as $row): ?>
					<?php
						$orderNo = trim((string)($row['prefix'] ?? '') . (string)($row['po_number'] ?? ''));
						$salesOrderNo = trim((string)($row['sales_prefix'] ?? '') . (string)($row['so_number'] ?? ''));
						$purchaseOrderUrl = 'https://addinol-lubeoil.at/crm/?module=PurchaseOrders&action=DetailView&record=' . urlencode((string)($row['id'] ?? ''));
						$salesOrderUrl = 'https://addinol-lubeoil.at/crm/?module=SalesOrders&action=DetailView&record=' . urlencode((string)($row['sales_order_id'] ?? ''));
						$reference = trim((string)($row['at_order_no'] ?? ''));
						$beOrderNo = trim((string)($row['be_order_no'] ?? ''));
						$copyValue = $reference !== '' ? $reference : ($beOrderNo !== '' ? $beOrderNo : $orderNo);
						$dachserSearchValue = $reference !== '' ? $reference : $copyValue;
						$dachserTrackingUrl = $dachserTrackingBase . rawurlencode($dachserSearchValue);
						$dachserStatus = trim((string)($row['dachser_status'] ?? ''));
						$dachserStatusTs = trim((string)($row['dachser_status_ts'] ?? ''));
						$dachserVia = trim((string)($row['dachser_via'] ?? ''));
						$dachserInfo = trim((string)($row['dachser_info'] ?? ''));
						$dachserCheckedAt = trim((string)($row['dachser_last_checked_at'] ?? ''));
						$dachserStatusTsLabel = $dachserStatusTs;
						if ($dachserStatusTs !== '' && ($ts = strtotime($dachserStatusTs)) !== false) {
							$dachserStatusTsLabel = date('d.m.Y H:i', $ts);
						}
					?>
						<tr>
							<td>
								<a class="btn btn-sm btn-outline-success fw-semibold" target="_blank" rel="noopener" href="<?php echo htmlspecialchars($purchaseOrderUrl, ENT_QUOTES); ?>">
									<?php echo htmlspecialchars($orderNo); ?>
								</a>
								<div class="text-muted small"><?php echo htmlspecialchars($row['name'] ?? ''); ?></div>
							</td>
							<td>
								<?php if ($salesOrderNo !== '' && !empty($row['sales_order_id'])): ?>
									<a class="btn btn-sm btn-outline-primary fw-semibold" target="_blank" rel="noopener" href="<?php echo htmlspecialchars($salesOrderUrl, ENT_QUOTES); ?>">
										<?php echo htmlspecialchars($salesOrderNo); ?>
									</a>
								<?php else: ?>
									<span class="text-muted">-</span>
								<?php endif; ?>
						</td>
						<td>
							<?php if ($reference !== ''): ?>
								<code><?php echo htmlspecialchars($reference); ?></code>
							<?php else: ?>
								<span class="badge text-bg-danger"><i class="fas fa-exclamation-triangle me-1"></i>AT fehlt</span>
							<?php endif; ?>
								<?php if ($beOrderNo !== ''): ?>
									<div class="text-muted small mt-1">
										BE:
										<span class="ms-1"><?php echo htmlspecialchars($beOrderNo); ?></span>
									</div>
								<?php endif; ?>
						</td>
						<td>
							<?php if ($dachserStatus !== ''): ?>
								<div><span class="badge text-bg-primary"><?php echo htmlspecialchars($dachserStatus); ?></span></div>
								<?php if ($dachserStatusTs !== ''): ?>
									<div class="small text-muted mt-1"><?php echo htmlspecialchars($dachserStatusTsLabel); ?></div>
								<?php endif; ?>
								<?php if ($dachserVia !== ''): ?>
									<div class="small text-muted">Via: <?php echo htmlspecialchars($dachserVia); ?></div>
								<?php endif; ?>
								<?php if ($dachserInfo !== ''): ?>
									<div class="small text-muted">Info: <?php echo htmlspecialchars($dachserInfo); ?></div>
								<?php endif; ?>
								<?php if ($dachserCheckedAt !== ''): ?>
									<div class="small text-muted">Check: <?php echo htmlspecialchars($dachserCheckedAt); ?></div>
								<?php endif; ?>
							<?php else: ?>
								<span class="text-muted">-</span>
							<?php endif; ?>
						</td>
						<td><?php echo format_stage_badge_local($row['shipping_stage'] ?? ''); ?></td>
						<td><?php echo format_sales_order_stage_badge($row['so_stage'] ?? ''); ?></td>
						<td><?php echo htmlspecialchars($row['date_modified'] ?? ''); ?></td>
						<td><?php echo isset($row['amount']) ? number_format((float)$row['amount'], 2, ',', '.') : ''; ?></td>
						<td>
							<div class="btn-group btn-group-sm" role="group">
								<button type="button" class="btn btn-outline-secondary copy-btn" data-copy="<?php echo htmlspecialchars($copyValue, ENT_QUOTES); ?>" title="AT (oder BE/Bestellnummer) kopieren">
									<i class="fas fa-copy"></i> Copy
								</button>
								<button type="button" class="btn btn-outline-dark api-status-btn" data-reference="<?php echo htmlspecialchars($copyValue, ENT_QUOTES); ?>" data-purchase-order-id="<?php echo htmlspecialchars((string)($row['id'] ?? ''), ENT_QUOTES); ?>" title="Lieferstatus via API prüfen">
									<i class="fas fa-satellite-dish"></i> API
								</button>
								<?php if ($reference === ''): ?>
									<form method="post" class="d-inline">
										<input type="hidden" name="action" value="reextract_note">
										<input type="hidden" name="note_id" value="<?php echo htmlspecialchars((string)($row['note_id'] ?? ''), ENT_QUOTES); ?>">
										<input type="hidden" name="po_id" value="<?php echo htmlspecialchars((string)($row['id'] ?? ''), ENT_QUOTES); ?>">
										<button type="submit" class="btn btn-sm btn-outline-warning" title="AT für diese Bestellung neu scannen">
											<i class="fas fa-redo"></i> AT-Scan
										</button>
									</form>
								<?php endif; ?>
								<?php if ($reference !== ''): ?>
									<a class="btn btn-outline-primary" target="_blank" rel="noopener" href="<?php echo htmlspecialchars($dachserTrackingUrl, ENT_QUOTES); ?>">
										<i class="fas fa-truck"></i> Dachser
									</a>
								<?php endif; ?>
							</div>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>
</div>

<div class="modal fade" id="apiStatusModal" tabindex="-1" aria-hidden="true">
	<div class="modal-dialog modal-lg modal-dialog-scrollable">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title">Dachser API Status</h5>
				<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Schließen"></button>
			</div>
			<div class="modal-body">
				<div class="small text-muted mb-2" id="apiStatusMeta"></div>
				<pre class="mb-0 p-2 bg-light border rounded small" id="apiStatusOutput" style="min-height: 200px;">Noch keine Abfrage.</pre>
			</div>
		</div>
	</div>
</div>

<div class="position-fixed bottom-0 end-0 p-3" style="z-index: 11">
	<div id="copyToast" class="toast align-items-center text-bg-dark border-0" role="alert" aria-live="assertive" aria-atomic="true">
		<div class="d-flex">
			<div class="toast-body" id="copyToastBody">Kopiert</div>
			<button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
		</div>
	</div>
</div>

<script src="../assets/datatables/jquery.min.js"></script>
<script src="../assets/datatables/jquery.dataTables.min.js"></script>
<script src="../assets/datatables/dataTables.bootstrap5.min.js"></script>
<script src="../assets/bootstrap/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
	$('#deliveryTable').DataTable({
		order: [[4, 'desc']],
		pageLength: 50,
		language: { url: 'https://cdn.datatables.net/plug-ins/1.13.7/i18n/de-DE.json' }
	});

	const toastEl = document.getElementById('copyToast');
	const toastBody = document.getElementById('copyToastBody');
	const toast = toastEl ? new bootstrap.Toast(toastEl) : null;
	const extractStatusModalEl = document.getElementById('extractStatusModal');
	const extractStatusMeta = document.getElementById('extractStatusMeta');
	const extractStatusStats = document.getElementById('extractStatusStats');
	const extractStatusLog = document.getElementById('extractStatusLog');
	const extractStatusAlert = document.getElementById('extractStatusAlert');
	let extractStatusTimer = null;
	const apiStatusModalEl = document.getElementById('apiStatusModal');
	const apiStatusModal = apiStatusModalEl ? new bootstrap.Modal(apiStatusModalEl) : null;
	const apiStatusOutput = document.getElementById('apiStatusOutput');
	const apiStatusMeta = document.getElementById('apiStatusMeta');
	const showToast = (msg) => {
		if (toast && toastBody) {
			toastBody.textContent = msg;
			toast.show();
		}
	};

	const fallbackCopy = (text) => {
		const ta = document.createElement('textarea');
		ta.value = text;
		document.body.appendChild(ta);
		ta.select();
		try {
			document.execCommand('copy');
		} catch (e) {
		}
		document.body.removeChild(ta);
	};

	const renderExtractStatus = (data) => {
		const status = data && data.status ? data.status : null;
		const lines = data && Array.isArray(data.log_tail) ? data.log_tail : [];
		if (!status) {
			if (extractStatusMeta) extractStatusMeta.textContent = 'Noch kein Extractor-Status vorhanden.';
			if (extractStatusStats) extractStatusStats.textContent = '';
			if (extractStatusLog) extractStatusLog.textContent = lines.length ? lines.join('\n') : 'Kein Log vorhanden.';
			if (extractStatusAlert) {
				extractStatusAlert.classList.remove('alert-warning');
				extractStatusAlert.classList.add('alert-light');
			}
			return;
		}

		const running = !!status.running;
		const parts = ['Status: ' + (running ? 'läuft' : 'inaktiv')];
		if (status.started_at) parts.push('Start: ' + status.started_at);
		if (status.finished_at) parts.push('Ende: ' + status.finished_at);
		if (typeof status.exit_code !== 'undefined') parts.push('Exit: ' + status.exit_code);
		if (status.limit) parts.push('Limit: ' + status.limit);
		if (status.target_note_id) parts.push('Note: ' + status.target_note_id);
		if (status.target_po_id) parts.push('PO: ' + status.target_po_id);

		if (extractStatusMeta) extractStatusMeta.textContent = parts.join(' | ');
		if (extractStatusStats) {
			if (status.stats && typeof status.stats === 'object') {
				extractStatusStats.textContent = 'Stats: ' + JSON.stringify(status.stats);
			} else {
				extractStatusStats.textContent = '';
			}
		}
		if (extractStatusLog) extractStatusLog.textContent = lines.length ? lines.join('\n') : 'Kein Log vorhanden.';
		if (extractStatusAlert) {
			extractStatusAlert.classList.toggle('alert-warning', running);
			extractStatusAlert.classList.toggle('alert-light', !running);
		}
	};

	const loadExtractStatus = async () => {
		try {
			const resp = await fetch('lieferstatus.php?ajax_extract_status=1', { cache: 'no-store' });
			const json = await resp.json();
			if (!json || !json.ok) {
				throw new Error('invalid response');
			}
			renderExtractStatus(json);
		} catch (e) {
			if (extractStatusMeta) extractStatusMeta.textContent = 'Status konnte nicht geladen werden.';
			if (extractStatusStats) extractStatusStats.textContent = '';
			if (extractStatusLog) extractStatusLog.textContent = 'Fehler beim Laden: ' + (e && e.message ? e.message : 'unknown');
			if (extractStatusAlert) {
				extractStatusAlert.classList.remove('alert-warning');
				extractStatusAlert.classList.add('alert-light');
			}
		}
	};

	if (extractStatusModalEl) {
		extractStatusModalEl.addEventListener('shown.bs.modal', () => {
			loadExtractStatus();
			if (extractStatusTimer) {
				clearInterval(extractStatusTimer);
			}
			extractStatusTimer = setInterval(loadExtractStatus, 5000);
		});
		extractStatusModalEl.addEventListener('hidden.bs.modal', () => {
			if (extractStatusTimer) {
				clearInterval(extractStatusTimer);
				extractStatusTimer = null;
			}
		});
	}

	document.querySelectorAll('.copy-btn').forEach((btn) => {
		btn.addEventListener('click', async () => {
			const val = btn.getAttribute('data-copy') || '';
			if (!val) {
				showToast('Kein Wert vorhanden');
				return;
			}
			try {
				if (navigator.clipboard && navigator.clipboard.writeText) {
					await navigator.clipboard.writeText(val);
				} else {
					fallbackCopy(val);
				}
				showToast('Kopiert: ' + val);
			} catch (e) {
				fallbackCopy(val);
				showToast('Kopiert: ' + val);
			}
		});
	});

	document.querySelectorAll('.api-status-btn').forEach((btn) => {
		btn.addEventListener('click', async () => {
			const reference = (btn.getAttribute('data-reference') || '').trim();
			const purchaseOrderId = (btn.getAttribute('data-purchase-order-id') || '').trim();
			if (!reference) {
				showToast('Keine Referenz vorhanden');
				return;
			}

				if (apiStatusMeta) {
					apiStatusMeta.textContent = 'Referenz: ' + reference;
				}
			if (apiStatusOutput) {
				apiStatusOutput.textContent = 'Abfrage läuft...';
			}
			if (apiStatusModal) {
				apiStatusModal.show();
			}

				try {
				const resp = await fetch('dachser_status.php', {
					method: 'POST',
					headers: { 'Content-Type': 'application/json' },
					body: JSON.stringify({ reference, purchase_order_id: purchaseOrderId })
				});
					const json = await resp.json();
					if (apiStatusMeta) {
						const parts = ['Referenz: ' + reference];
						if (json && json.extracted_status) {
							parts.push('Status: ' + json.extracted_status);
						}
						apiStatusMeta.textContent = parts.join(' | ');
					}
					if (apiStatusOutput) {
						apiStatusOutput.textContent = JSON.stringify(json, null, 2);
					}
					if (json && json.job_created) {
						showToast('ToDo-Job erstellt #' + json.job_id + ' (Ware zugestellt)');
					}
					if (!json || !json.ok) {
						showToast('API-Antwort mit Fehler');
					}
			} catch (e) {
				if (apiStatusOutput) {
					apiStatusOutput.textContent = 'API-Aufruf fehlgeschlagen: ' + (e && e.message ? e.message : 'unknown');
				}
				showToast('API-Aufruf fehlgeschlagen');
			}
		});
	});
});
</script>
</body>
</html>
