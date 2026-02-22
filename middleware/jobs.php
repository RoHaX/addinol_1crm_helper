<?php
require_once __DIR__ . '/../db.inc.php';
require_once __DIR__ . '/../src/JobService.php';

$mysqli = $mysqli ?? null;
if (!$mysqli) {
	die('DB connection missing');
}
$mysqli->set_charset('utf8');

if (!JobService::ensureTables($mysqli)) {
	die('Job tables missing and could not be created.');
}

function fetch_all_assoc_jobs(mysqli $db, string $sql, string $types = '', array $params = []): array {
	$out = [];
	$stmt = $db->prepare($sql);
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

function jobs_decode_json_object(string $json): ?array {
	$json = trim($json);
	if ($json === '') {
		return [];
	}
	$decoded = json_decode($json, true);
	if (!is_array($decoded)) {
		return null;
	}
	return $decoded;
}

$statusFilter = trim((string)($_GET['status'] ?? ''));
$modeFilter = trim((string)($_GET['mode'] ?? ''));
$q = trim((string)($_GET['q'] ?? ''));
$flash = trim((string)($_GET['msg'] ?? ''));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$action = trim((string)($_POST['action'] ?? ''));
	$msg = 'Unbekannte Aktion.';

	if ($action === 'save_job') {
		$jobId = (int)($_POST['job_id'] ?? 0);
		$stepsRaw = trim((string)($_POST['steps_text'] ?? ''));
		$stepsJsonRaw = trim((string)($_POST['steps_json'] ?? ''));
		$defaultDue = trim((string)($_POST['step_due_at'] ?? ''));
		$notifyTelegram = !empty($_POST['notify_telegram']);
		$steps = [];
		if ($stepsJsonRaw !== '') {
			$parsed = json_decode($stepsJsonRaw, true);
			if (!is_array($parsed)) {
				$params = $_GET;
				$params['msg'] = 'Schritte JSON ist ungültig. Bitte prüfen.';
				header('Location: ' . $_SERVER['PHP_SELF'] . '?' . http_build_query($params));
				exit;
			}
			if (is_array($parsed)) {
				foreach ($parsed as $item) {
					if (!is_array($item)) {
						continue;
					}
					$title = trim((string)($item['step_title'] ?? ''));
					$type = trim((string)($item['step_type'] ?? 'note'));
					$payload = $item['step_payload_json'] ?? null;
					$dueAt = trim((string)($item['due_at'] ?? $defaultDue));
					$isRequired = !empty($item['is_required']) ? 1 : 0;
					if ($title === '') {
						continue;
					}
					if ($payload !== null && !is_string($payload)) {
						$payload = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
					}
					$steps[] = [
						'step_title' => $title,
						'step_type' => $type !== '' ? $type : 'note',
						'step_payload_json' => is_string($payload) ? trim($payload) : null,
						'due_at' => $dueAt,
						'is_required' => $isRequired,
					];
				}
			}
		}
		if (!$steps && $stepsRaw !== '') {
			$lines = preg_split('/\r\n|\r|\n/', $stepsRaw);
			foreach ($lines as $line) {
				$title = trim((string)$line);
				if ($title === '') {
					continue;
				}
				$stepType = stripos($title, 'rechnung') !== false ? 'convert_ab_to_invoice' : 'note';
				$steps[] = [
					'step_title' => $title,
					'step_type' => $stepType,
					'due_at' => $defaultDue,
					'is_required' => 1,
				];
			}
		}

		$payloadInput = trim((string)($_POST['payload_json'] ?? ''));
		$payloadData = jobs_decode_json_object($payloadInput);
		if ($payloadData === null) {
			$params = $_GET;
			$params['msg'] = 'Payload JSON ist ungültig. Bitte prüfen.';
			header('Location: ' . $_SERVER['PHP_SELF'] . '?' . http_build_query($params));
			exit;
		}
		if ($notifyTelegram) {
			$payloadData['notify_telegram'] = true;
		} else {
			unset($payloadData['notify_telegram']);
		}
		$payloadJsonForSave = $payloadData ? json_encode($payloadData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '';
		$allowedWeekdaysRaw = $_POST['allowed_weekdays'] ?? [];
		if (!is_array($allowedWeekdaysRaw)) {
			$allowedWeekdaysRaw = $allowedWeekdaysRaw !== '' ? explode(',', (string)$allowedWeekdaysRaw) : [];
		}

		$job = [
			'title' => trim((string)($_POST['title'] ?? '')),
			'job_type' => trim((string)($_POST['job_type'] ?? 'generic')),
			'description' => trim((string)($_POST['description'] ?? '')),
			'relation_type' => trim((string)($_POST['relation_type'] ?? 'none')),
			'relation_id' => trim((string)($_POST['relation_id'] ?? '')),
			'account_id' => trim((string)($_POST['account_id'] ?? '')),
			'payload_json' => $payloadJsonForSave,
			'schedule_type' => trim((string)($_POST['schedule_type'] ?? 'once')),
			'run_mode' => trim((string)($_POST['run_mode'] ?? 'manual')),
			'run_at' => trim((string)($_POST['run_at'] ?? '')),
			'interval_minutes' => (int)($_POST['interval_minutes'] ?? 0),
			'daily_time' => trim((string)($_POST['daily_time'] ?? '')),
			'timezone' => trim((string)($_POST['timezone'] ?? 'Europe/Vienna')),
			'allowed_weekdays' => $allowedWeekdaysRaw,
			'allowed_start_time' => trim((string)($_POST['allowed_start_time'] ?? '')),
			'allowed_end_time' => trim((string)($_POST['allowed_end_time'] ?? '')),
		];

		$result = $jobId > 0
			? JobService::updateJob($mysqli, $jobId, $job, $steps)
			: JobService::insertJob($mysqli, $job, $steps);
		if (!empty($result['ok'])) {
			$msg = $jobId > 0
				? 'Job aktualisiert (ID ' . (int)$result['job_id'] . ').'
				: 'Job erstellt (ID ' . (int)$result['job_id'] . ').';
		} else {
			$msg = $jobId > 0
				? 'Job konnte nicht aktualisiert werden (' . htmlspecialchars((string)($result['reason'] ?? 'unknown')) . ').'
				: 'Job konnte nicht erstellt werden (' . htmlspecialchars((string)($result['reason'] ?? 'unknown')) . ').';
		}
	} elseif ($action === 'run_job') {
		$jobId = (int)($_POST['job_id'] ?? 0);
		$result = JobService::runJobNow($mysqli, $jobId, 'manual', 'jobs_ui');
		if (!empty($result['ok'])) {
			$msg = 'Job #' . $jobId . ' ausgeführt: ' . ($result['status'] ?? 'ok');
		} else {
			$msg = 'Job #' . $jobId . ' konnte nicht ausgeführt werden (' . (string)($result['reason'] ?? 'unknown') . ').';
		}
	} elseif ($action === 'set_status') {
		$jobId = (int)($_POST['job_id'] ?? 0);
		$newStatus = trim((string)($_POST['new_status'] ?? 'active'));
		if (JobService::setJobStatus($mysqli, $jobId, $newStatus)) {
			$msg = 'Job #' . $jobId . ' Status: ' . $newStatus;
		} else {
			$msg = 'Statuswechsel fehlgeschlagen.';
		}
	} elseif ($action === 'clear_runs') {
		$jobId = (int)($_POST['job_id'] ?? 0);
		if ($jobId > 0) {
			$del = $mysqli->prepare('DELETE FROM mw_job_runs WHERE job_id = ?');
			if ($del) {
				$del->bind_param('i', $jobId);
				if ($del->execute()) {
					$msg = 'Run-Verlauf für Job #' . $jobId . ' geleert.';
				} else {
					$msg = 'Run-Verlauf konnte nicht geleert werden.';
				}
			} else {
				$msg = 'Run-Verlauf konnte nicht geleert werden.';
			}
		} else {
			$msg = 'Ungültige Job-ID für Verlauf.';
		}
	}

	$params = $_GET;
	$params['msg'] = $msg;
	header('Location: ' . $_SERVER['PHP_SELF'] . '?' . http_build_query($params));
	exit;
}

$where = [];
$params = [];
$types = '';
if ($statusFilter !== '') {
	$where[] = 'j.status = ?';
	$types .= 's';
	$params[] = $statusFilter;
}
if ($modeFilter !== '') {
	$where[] = 'j.run_mode = ?';
	$types .= 's';
	$params[] = $modeFilter;
}
if ($q !== '') {
	$qLike = '%' . $q . '%';
	$where[] = '(j.title LIKE ? OR j.description LIKE ? OR j.relation_id LIKE ? OR j.job_type LIKE ?)';
	$types .= 'ssss';
	$params[] = $qLike;
	$params[] = $qLike;
	$params[] = $qLike;
	$params[] = $qLike;
}

$sql = "SELECT j.*,
	(SELECT COUNT(*) FROM mw_job_steps s WHERE s.job_id = j.id) AS step_count,
	(SELECT COUNT(*) FROM mw_job_runs r WHERE r.job_id = j.id) AS run_count
	FROM mw_jobs j";
if ($where) {
	$sql .= ' WHERE ' . implode(' AND ', $where);
}
$sql .= " ORDER BY
	CASE j.status
		WHEN 'active' THEN 0
		WHEN 'paused' THEN 1
		WHEN 'error' THEN 2
		WHEN 'done' THEN 3
		ELSE 4
	END ASC,
	COALESCE(j.next_run_at, j.run_at, j.created_at) ASC,
	j.id DESC
	LIMIT 500";

$jobs = fetch_all_assoc_jobs($mysqli, $sql, $types, $params);

$stepsByJob = [];
$runsByJob = [];
if ($jobs) {
	$jobIds = array_map(static function ($row) {
		return (int)($row['id'] ?? 0);
	}, $jobs);
	$jobIds = array_values(array_filter($jobIds, static function ($id) {
		return $id > 0;
	}));
	if ($jobIds) {
		$idList = implode(',', $jobIds);
		$stepsRows = fetch_all_assoc_jobs($mysqli, "SELECT job_id, step_order, step_title, step_type, step_payload_json, due_at FROM mw_job_steps WHERE job_id IN ($idList) ORDER BY job_id ASC, step_order ASC");
		foreach ($stepsRows as $row) {
			$jobId = (int)($row['job_id'] ?? 0);
			$stepsByJob[$jobId][] = $row;
		}

		$runsRows = fetch_all_assoc_jobs($mysqli, "SELECT id, job_id, trigger_type, started_at, finished_at, status, result_message, executed_by FROM mw_job_runs WHERE job_id IN ($idList) ORDER BY id DESC LIMIT 1000");
		foreach ($runsRows as $row) {
			$jobId = (int)($row['job_id'] ?? 0);
			if (!isset($runsByJob[$jobId])) {
				$runsByJob[$jobId] = [];
			}
			if (count($runsByJob[$jobId]) < 5) {
				$runsByJob[$jobId][] = $row;
			}
		}
	}
}

function schedule_label(array $job): string {
	$type = (string)($job['schedule_type'] ?? 'once');
	$parts = [];
	if ($type === 'interval_minutes') {
		$parts[] = 'Alle ' . (int)($job['interval_minutes'] ?? 0) . ' Min.';
	} elseif ($type === 'daily_time') {
		$parts[] = 'Täglich ' . htmlspecialchars((string)($job['daily_time'] ?? ''));
	} else {
		$runAt = trim((string)($job['run_at'] ?? ''));
		$parts[] = $runAt !== '' ? 'Einmalig: ' . htmlspecialchars($runAt) : 'Einmalig (sofort)';
	}

	$wdRaw = trim((string)($job['allowed_weekdays'] ?? '1,2,3,4,5,6,7'));
	$wdMap = ['1' => 'Mo', '2' => 'Di', '3' => 'Mi', '4' => 'Do', '5' => 'Fr', '6' => 'Sa', '7' => 'So'];
	$wdParts = array_values(array_filter(array_map('trim', explode(',', $wdRaw)), static function ($v) {
		return $v !== '';
	}));
	$weekLabel = '';
	if ($wdParts && implode(',', $wdParts) !== '1,2,3,4,5,6,7') {
		if (implode(',', $wdParts) === '1,2,3,4,5') {
			$weekLabel = 'Werktage';
		} else {
			$labels = [];
			foreach ($wdParts as $wd) {
				if (isset($wdMap[$wd])) {
					$labels[] = $wdMap[$wd];
				}
			}
			if ($labels) {
				$weekLabel = implode(',', $labels);
			}
		}
	}

	$start = trim((string)($job['allowed_start_time'] ?? ''));
	$end = trim((string)($job['allowed_end_time'] ?? ''));
	$timeLabel = '';
	if ($start !== '' || $end !== '') {
		$timeLabel = ($start !== '' ? substr($start, 0, 5) : '00:00') . '-' . ($end !== '' ? substr($end, 0, 5) : '23:59');
	}

	if ($weekLabel !== '' || $timeLabel !== '') {
		$parts[] = trim($weekLabel . ($weekLabel !== '' && $timeLabel !== '' ? ' ' : '') . $timeLabel);
	}
	return implode(' | ', $parts);
}

function shorten_label(string $text, int $max = 20): string {
	$text = trim($text);
	if ($text === '') {
		return '-';
	}
	if (function_exists('mb_strlen') && function_exists('mb_substr')) {
		if (mb_strlen($text, 'UTF-8') <= $max) {
			return $text;
		}
		return rtrim(mb_substr($text, 0, $max - 1, 'UTF-8')) . '…';
	}
	if (strlen($text) <= $max) {
		return $text;
	}
	return rtrim(substr($text, 0, $max - 1)) . '...';
}

function build_relation_display_map(mysqli $db, array $jobs): array {
	$byType = [
		'sales_order' => [],
		'purchase_order' => [],
		'account' => [],
		'customer' => [],
	];
	foreach ($jobs as $job) {
		$type = trim((string)($job['relation_type'] ?? 'none'));
		$id = trim((string)($job['relation_id'] ?? ''));
		if ($id === '' || !isset($byType[$type])) {
			continue;
		}
		$byType[$type][$id] = true;
	}

	$lookup = [
		'sales_order' => [],
		'purchase_order' => [],
		'account' => [],
		'customer' => [],
	];

	$fetchMap = static function (string $sql, array $ids) use ($db): array {
		if (!$ids) {
			return [];
		}
		$safe = [];
		foreach (array_keys($ids) as $id) {
			$safe[] = "'" . $db->real_escape_string($id) . "'";
		}
		$q = str_replace('{IDS}', implode(',', $safe), $sql);
		$res = $db->query($q);
		$out = [];
		if ($res) {
			while ($row = $res->fetch_assoc()) {
				$out[(string)$row['id']] = $row;
			}
		}
		return $out;
	};

	$lookup['sales_order'] = $fetchMap(
		"SELECT id, CONCAT(COALESCE(prefix,''), so_number) AS code, name FROM sales_orders WHERE deleted = 0 AND id IN ({IDS})",
		$byType['sales_order']
	);
	$lookup['purchase_order'] = $fetchMap(
		"SELECT id, CONCAT(COALESCE(prefix,''), po_number) AS code, name FROM purchase_orders WHERE deleted = 0 AND id IN ({IDS})",
		$byType['purchase_order']
	);
	$lookup['account'] = $fetchMap(
		"SELECT id, name, ticker_symbol FROM accounts WHERE deleted = 0 AND id IN ({IDS})",
		$byType['account']
	);
	$lookup['customer'] = $fetchMap(
		"SELECT id, CONCAT(TRIM(COALESCE(first_name,'')), ' ', TRIM(COALESCE(last_name,''))) AS full_name, email1 FROM contacts WHERE deleted = 0 AND id IN ({IDS})",
		$byType['customer']
	);

	$map = [];
	foreach ($jobs as $job) {
		$jobId = (int)($job['id'] ?? 0);
		$type = trim((string)($job['relation_type'] ?? 'none'));
		$id = trim((string)($job['relation_id'] ?? ''));
		$label = '';
		$full = '';
		$url = '';
		$icon = 'fas fa-link';

		if ($type === 'none' || $id === '') {
			$map[$jobId] = ['label' => '-', 'full' => '', 'url' => '', 'icon' => 'fas fa-minus'];
			continue;
		}

		if ($type === 'sales_order') {
			$row = $lookup['sales_order'][$id] ?? null;
			$full = trim((string)($row['code'] ?? ''));
			if ($full === '') {
				$full = trim((string)($row['name'] ?? $id));
			}
			$url = 'https://addinol-lubeoil.at/crm/?module=SalesOrders&action=DetailView&record=' . urlencode($id);
			$icon = 'fas fa-file-signature';
		} elseif ($type === 'purchase_order') {
			$row = $lookup['purchase_order'][$id] ?? null;
			$full = trim((string)($row['code'] ?? ''));
			if ($full === '') {
				$full = trim((string)($row['name'] ?? $id));
			}
			$url = 'https://addinol-lubeoil.at/crm/?module=PurchaseOrders&action=DetailView&record=' . urlencode($id);
			$icon = 'fas fa-shopping-cart';
		} elseif ($type === 'account') {
			$row = $lookup['account'][$id] ?? null;
			$full = trim((string)($row['name'] ?? ''));
			if ($full === '') {
				$full = $id;
			}
			$url = 'https://addinol-lubeoil.at/crm/?module=Accounts&action=DetailView&record=' . urlencode($id);
			$icon = 'fas fa-building';
		} elseif ($type === 'customer') {
			$row = $lookup['customer'][$id] ?? null;
			$full = trim((string)($row['full_name'] ?? ''));
			if ($full === '') {
				$full = trim((string)($row['email1'] ?? $id));
			}
			$url = 'https://addinol-lubeoil.at/crm/?module=Contacts&action=DetailView&record=' . urlencode($id);
			$icon = 'fas fa-user';
		} else {
			$full = $type . ':' . $id;
		}

		$label = shorten_label($full, 20);
		$map[$jobId] = ['label' => $label, 'full' => $full, 'url' => $url, 'icon' => $icon];
	}

	return $map;
}

$relationDisplayMap = build_relation_display_map($mysqli, $jobs);
?>
<!doctype html>
<html lang="de">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Jobs</title>
<link href="../styles.css" rel="stylesheet" type="text/css" />
<link href="../assets/bootstrap/bootstrap.min.css" rel="stylesheet" />
<link href="../assets/datatables/dataTables.bootstrap5.min.css" rel="stylesheet" />
<link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.5.0/css/all.css" crossorigin="anonymous">
</head>
<body>
<?php if (file_exists(__DIR__ . '/../navbar.php')) { include __DIR__ . '/../navbar.php'; } ?>

<div class="container-fluid py-3">
	<div class="d-flex align-items-center justify-content-between mb-3">
		<h1 class="h3 mb-0">Jobs / ToDos</h1>
		<div class="d-flex gap-2">
			<button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#createJobModal">
				<i class="fas fa-plus"></i> Job hinzufügen
			</button>
			<a class="btn btn-sm btn-outline-secondary" href="lieferstatus.php">
				<i class="fas fa-truck"></i> Lieferstatus
			</a>
		</div>
	</div>

	<?php if ($flash !== ''): ?>
		<div class="alert alert-info py-2"><?php echo htmlspecialchars($flash); ?></div>
	<?php endif; ?>

	<form method="get" class="row g-2 align-items-end mb-3">
		<div class="col-md-2">
			<label class="form-label">Status</label>
			<select name="status" class="form-select form-select-sm">
				<option value="" <?php echo $statusFilter === '' ? 'selected' : ''; ?>>Alle</option>
				<?php foreach (['active','paused','done','error','archived'] as $opt): ?>
					<option value="<?php echo $opt; ?>" <?php echo $statusFilter === $opt ? 'selected' : ''; ?>><?php echo $opt; ?></option>
				<?php endforeach; ?>
			</select>
		</div>
		<div class="col-md-2">
			<label class="form-label">Mode</label>
			<select name="mode" class="form-select form-select-sm">
				<option value="" <?php echo $modeFilter === '' ? 'selected' : ''; ?>>Alle</option>
				<option value="manual" <?php echo $modeFilter === 'manual' ? 'selected' : ''; ?>>manual</option>
				<option value="auto" <?php echo $modeFilter === 'auto' ? 'selected' : ''; ?>>auto</option>
			</select>
		</div>
		<div class="col-md-7">
			<label class="form-label">Suche</label>
			<input type="text" name="q" value="<?php echo htmlspecialchars($q); ?>" class="form-control form-control-sm" placeholder="Titel, Typ, Bezug-ID">
		</div>
		<div class="col-md-1">
			<button type="submit" class="btn btn-sm btn-outline-primary w-100">Filter</button>
		</div>
	</form>

	<div class="table-responsive">
		<table id="jobsTable" class="table table-striped table-sm align-middle">
			<thead>
				<tr>
					<th>ID</th>
					<th>Titel</th>
					<th>Typ</th>
					<th>Bezug</th>
					<th>Plan</th>
					<th>Mode</th>
					<th>Status</th>
					<th>Nächster Lauf</th>
					<th>Zuletzt</th>
					<th>Schritte</th>
					<th>Aktionen</th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ($jobs as $job): ?>
					<?php
						$jobId = (int)$job['id'];
						$last = trim((string)($job['last_run_at'] ?? ''));
						$lastResult = trim((string)($job['last_result'] ?? ''));
						$isSystemJob = strpos((string)($job['job_key'] ?? ''), 'system:') === 0;
						$relationMeta = $relationDisplayMap[$jobId] ?? ['label' => '-', 'full' => '', 'url' => '', 'icon' => 'fas fa-link'];
						$editSteps = $stepsByJob[$jobId] ?? [];
						$stepsTextArr = [];
						$stepsJsonArr = [];
						$firstStepDue = '';
						foreach ($editSteps as $stepItem) {
							$stepsTextArr[] = (string)($stepItem['step_title'] ?? '');
							$stepsJsonArr[] = [
								'step_title' => (string)($stepItem['step_title'] ?? ''),
								'step_type' => (string)($stepItem['step_type'] ?? 'note'),
								'step_payload_json' => (string)($stepItem['step_payload_json'] ?? ''),
								'due_at' => (string)($stepItem['due_at'] ?? ''),
								'is_required' => 1,
							];
							if ($firstStepDue === '' && !empty($stepItem['due_at'])) {
								$firstStepDue = (string)$stepItem['due_at'];
							}
						}
						$stepsText = trim(implode("\n", array_filter($stepsTextArr, static function ($v) {
							return trim((string)$v) !== '';
						})));
						$runAt = trim((string)($job['run_at'] ?? ''));
						$runAtInput = $runAt !== '' ? date('Y-m-d\TH:i', strtotime($runAt)) : '';
						$allowedStartTime = trim((string)($job['allowed_start_time'] ?? ''));
						$allowedEndTime = trim((string)($job['allowed_end_time'] ?? ''));
						$allowedStartTimeInput = $allowedStartTime !== '' ? substr($allowedStartTime, 0, 5) : '';
						$allowedEndTimeInput = $allowedEndTime !== '' ? substr($allowedEndTime, 0, 5) : '';
						$stepDueInput = $firstStepDue !== '' ? date('Y-m-d\TH:i', strtotime($firstStepDue)) : '';
						$stepsJsonText = json_encode($stepsJsonArr, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
						$payloadRaw = trim((string)($job['payload_json'] ?? ''));
						$payloadDecoded = jobs_decode_json_object($payloadRaw);
						$notifyTelegram = false;
						$payloadForm = $payloadRaw;
						if (is_array($payloadDecoded)) {
							$notifyTelegram = !empty($payloadDecoded['notify_telegram']);
							unset($payloadDecoded['notify_telegram']);
							$payloadForm = $payloadDecoded ? (string)json_encode($payloadDecoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) : '';
						}
						$editData = [
							'id' => $jobId,
							'title' => (string)($job['title'] ?? ''),
							'job_type' => (string)($job['job_type'] ?? 'generic'),
							'description' => (string)($job['description'] ?? ''),
							'relation_type' => (string)($job['relation_type'] ?? 'none'),
							'relation_id' => (string)($job['relation_id'] ?? ''),
							'account_id' => (string)($job['account_id'] ?? ''),
							'payload_json' => $payloadForm,
							'notify_telegram' => $notifyTelegram ? 1 : 0,
							'schedule_type' => (string)($job['schedule_type'] ?? 'once'),
							'run_mode' => (string)($job['run_mode'] ?? 'manual'),
							'run_at' => $runAtInput,
							'interval_minutes' => (string)($job['interval_minutes'] ?? ''),
							'daily_time' => (string)($job['daily_time'] ?? ''),
							'timezone' => (string)($job['timezone'] ?? 'Europe/Vienna'),
							'allowed_weekdays' => (string)($job['allowed_weekdays'] ?? '1,2,3,4,5,6,7'),
							'allowed_start_time' => $allowedStartTimeInput,
							'allowed_end_time' => $allowedEndTimeInput,
							'step_due_at' => $stepDueInput,
							'steps_text' => $stepsText,
							'steps_json' => $stepsJsonText ?: '[]',
						];
					?>
					<tr>
						<td><?php echo $jobId; ?></td>
						<td>
							<div class="fw-semibold">
								<?php echo htmlspecialchars((string)$job['title']); ?>
								<?php if ($isSystemJob): ?>
									<span class="badge text-bg-info ms-1">System-Job</span>
								<?php endif; ?>
								<?php if (!empty($notifyTelegram)): ?>
									<span class="badge text-bg-success ms-1"><i class="fab fa-telegram-plane me-1"></i>Telegram</span>
								<?php endif; ?>
							</div>
							<?php if (!empty($job['description'])): ?>
								<div class="small text-muted"><?php echo htmlspecialchars((string)$job['description']); ?></div>
							<?php endif; ?>
						</td>
						<td><?php echo htmlspecialchars((string)$job['job_type']); ?></td>
						<td>
							<?php if (!empty($relationMeta['url'])): ?>
								<a target="_blank" rel="noopener" href="<?php echo htmlspecialchars((string)$relationMeta['url'], ENT_QUOTES); ?>" title="<?php echo htmlspecialchars((string)$relationMeta['full'], ENT_QUOTES); ?>">
									<i class="<?php echo htmlspecialchars((string)$relationMeta['icon'], ENT_QUOTES); ?> me-1"></i><?php echo htmlspecialchars((string)$relationMeta['label']); ?>
								</a>
							<?php else: ?>
								<span title="<?php echo htmlspecialchars((string)$relationMeta['full'], ENT_QUOTES); ?>">
									<i class="<?php echo htmlspecialchars((string)$relationMeta['icon'], ENT_QUOTES); ?> me-1"></i><?php echo htmlspecialchars((string)$relationMeta['label']); ?>
								</span>
							<?php endif; ?>
						</td>
						<td><?php echo schedule_label($job); ?></td>
						<td><?php echo htmlspecialchars((string)$job['run_mode']); ?></td>
						<td><?php echo htmlspecialchars((string)$job['status']); ?></td>
						<td><?php echo htmlspecialchars((string)($job['next_run_at'] ?? '-')); ?></td>
						<td>
							<?php echo $last !== '' ? htmlspecialchars($last) : '-'; ?>
							<?php if ($lastResult !== ''): ?>
								<div class="small text-muted"><?php echo htmlspecialchars($lastResult); ?></div>
							<?php endif; ?>
						</td>
						<td>
							<button
								type="button"
								class="btn btn-sm btn-outline-secondary steps-detail-btn"
								data-job-id="<?php echo $jobId; ?>"
								data-job-title="<?php echo htmlspecialchars((string)$job['title'], ENT_QUOTES); ?>"
								data-steps="<?php echo htmlspecialchars(json_encode($stepsByJob[$jobId] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES); ?>"
								title="Schritte anzeigen"
							>
								<i class="fas fa-tasks me-1"></i><?php echo (int)($job['step_count'] ?? 0); ?>
							</button>
						</td>
						<td>
							<div class="btn-group btn-group-sm" role="group">
								<button
									type="button"
									class="btn btn-outline-secondary edit-job-btn"
									data-job="<?php echo htmlspecialchars(json_encode($editData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES); ?>"
									data-is-system="<?php echo $isSystemJob ? '1' : '0'; ?>"
									title="Job bearbeiten"
								>
									<i class="fas fa-edit"></i>
								</button>
								<button
									type="button"
									class="btn btn-outline-secondary run-history-btn"
									data-job-id="<?php echo $jobId; ?>"
									data-job-title="<?php echo htmlspecialchars((string)$job['title'], ENT_QUOTES); ?>"
									data-runs="<?php echo htmlspecialchars(json_encode($runsByJob[$jobId] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES); ?>"
									title="Letzte Runs anzeigen"
								>
									<i class="fas fa-history"></i>
								</button>
								<?php if (!$isSystemJob): ?>
									<form method="post" class="d-inline">
										<input type="hidden" name="action" value="run_job">
										<input type="hidden" name="job_id" value="<?php echo $jobId; ?>">
										<button type="submit" class="btn btn-outline-primary">Run</button>
									</form>
								<?php endif; ?>
								<?php if ((string)$job['status'] === 'active'): ?>
									<form method="post" class="d-inline">
										<input type="hidden" name="action" value="set_status">
										<input type="hidden" name="job_id" value="<?php echo $jobId; ?>">
										<input type="hidden" name="new_status" value="paused">
										<button type="submit" class="btn btn-outline-warning">Pause</button>
									</form>
								<?php else: ?>
									<form method="post" class="d-inline">
										<input type="hidden" name="action" value="set_status">
										<input type="hidden" name="job_id" value="<?php echo $jobId; ?>">
										<input type="hidden" name="new_status" value="active">
										<button type="submit" class="btn btn-outline-success">Aktiv</button>
									</form>
								<?php endif; ?>
							</div>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>
</div>

<div class="modal fade" id="runHistoryModal" tabindex="-1" aria-hidden="true">
	<div class="modal-dialog modal-lg modal-dialog-scrollable">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title" id="runHistoryTitle">Letzte Runs</h5>
				<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Schließen"></button>
			</div>
			<div class="modal-body" id="runHistoryBody">
				<div class="small text-muted">Keine Daten vorhanden.</div>
			</div>
			<div class="modal-footer">
				<form method="post" id="clearRunsForm" class="d-inline">
					<input type="hidden" name="action" value="clear_runs">
					<input type="hidden" name="job_id" id="clearRunsJobId" value="">
					<button type="submit" class="btn btn-sm btn-outline-danger" id="clearRunsBtn">
						<i class="fas fa-trash-alt"></i> Verlauf leeren
					</button>
				</form>
				<button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Schließen</button>
			</div>
		</div>
	</div>
</div>

<div class="modal fade" id="stepsDetailModal" tabindex="-1" aria-hidden="true">
	<div class="modal-dialog modal-lg modal-dialog-scrollable">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title" id="stepsDetailTitle">Schritte</h5>
				<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Schließen"></button>
			</div>
			<div class="modal-body" id="stepsDetailBody">
				<div class="small text-muted">Keine Schritte vorhanden.</div>
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Schließen</button>
			</div>
		</div>
	</div>
</div>

<div class="modal fade" id="createJobModal" tabindex="-1" aria-hidden="true">
	<div class="modal-dialog modal-xl modal-dialog-scrollable">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title" id="createJobModalTitle">Neuen Job erstellen</h5>
				<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Schließen"></button>
			</div>
			<div class="modal-body">
				<form method="post" class="row g-2" id="jobForm">
					<input type="hidden" name="action" value="save_job">
					<input type="hidden" name="job_id" id="jobFormJobId" value="0">
					<div class="col-md-4">
						<label class="form-label">Titel</label>
						<input type="text" name="title" id="jobFormTitle" class="form-control form-control-sm" placeholder="z.B. Ware zugestellt" required>
					</div>
					<div class="col-md-2">
						<label class="form-label">Job-Typ</label>
						<input type="text" name="job_type" id="jobFormType" class="form-control form-control-sm" value="generic">
					</div>
					<div class="col-md-2">
						<label class="form-label">Bezug</label>
						<select name="relation_type" id="jobFormRelationType" class="form-select form-select-sm">
							<option value="none">Kein Bezug</option>
							<option value="sales_order">AB</option>
							<option value="purchase_order">Bestellung</option>
							<option value="account">Lieferant/Kunde</option>
							<option value="customer">Customer</option>
							<option value="other">Sonstiges</option>
						</select>
					</div>
					<div class="col-md-2">
						<label class="form-label">Bezug-ID</label>
						<input type="text" name="relation_id" id="jobFormRelationId" class="form-control form-control-sm" placeholder="CRM-ID">
					</div>
					<div class="col-md-2">
						<label class="form-label">Account-ID</label>
						<input type="text" name="account_id" id="jobFormAccountId" class="form-control form-control-sm" placeholder="optional">
					</div>

					<div class="col-md-4">
						<label class="form-label">Beschreibung</label>
						<input type="text" name="description" id="jobFormDescription" class="form-control form-control-sm" placeholder="z.B. AB in Rechnung umwandeln">
					</div>
					<div class="col-md-2">
						<label class="form-label">Ausführung</label>
						<select name="run_mode" id="jobFormRunMode" class="form-select form-select-sm">
							<option value="manual">Manuell</option>
							<option value="auto">Automatisch</option>
						</select>
					</div>
					<div class="col-md-2 d-flex align-items-end">
						<div class="form-check mb-2">
							<input class="form-check-input" type="checkbox" name="notify_telegram" id="jobFormNotifyTelegram" value="1">
							<label class="form-check-label" for="jobFormNotifyTelegram">
								Telegram benachrichtigen
							</label>
						</div>
					</div>
					<div class="col-md-2">
						<label class="form-label">Plan</label>
						<select name="schedule_type" id="jobFormScheduleType" class="form-select form-select-sm">
							<option value="once">Einmalig</option>
							<option value="interval_minutes">Mehrmals täglich</option>
							<option value="daily_time">Täglich fixe Uhrzeit</option>
						</select>
					</div>
					<div class="col-md-2">
						<label class="form-label">Run at</label>
						<input type="datetime-local" name="run_at" id="jobFormRunAt" class="form-control form-control-sm">
					</div>
					<div class="col-md-1">
						<label class="form-label">Intervall</label>
						<input type="number" min="1" name="interval_minutes" id="jobFormInterval" class="form-control form-control-sm" placeholder="Min">
					</div>
					<div class="col-md-1">
						<label class="form-label">Daily</label>
						<input type="time" name="daily_time" id="jobFormDailyTime" class="form-control form-control-sm">
					</div>
					<div class="col-md-2">
						<label class="form-label">TZ</label>
						<input type="text" name="timezone" id="jobFormTimezone" class="form-control form-control-sm" value="Europe/Vienna">
					</div>
					<div class="col-md-3">
						<label class="form-label d-block">Erlaubte Tage</label>
						<div class="d-flex flex-wrap gap-2 small">
							<?php foreach (['1' => 'Mo', '2' => 'Di', '3' => 'Mi', '4' => 'Do', '5' => 'Fr', '6' => 'Sa', '7' => 'So'] as $wdValue => $wdLabel): ?>
								<div class="form-check form-check-inline me-0">
									<input class="form-check-input allowed-weekday" type="checkbox" name="allowed_weekdays[]" id="jobFormWeekday<?php echo $wdValue; ?>" value="<?php echo $wdValue; ?>" checked>
									<label class="form-check-label" for="jobFormWeekday<?php echo $wdValue; ?>"><?php echo $wdLabel; ?></label>
								</div>
							<?php endforeach; ?>
						</div>
					</div>
					<div class="col-md-2">
						<label class="form-label">Startzeit</label>
						<input type="time" name="allowed_start_time" id="jobFormAllowedStart" class="form-control form-control-sm">
					</div>
					<div class="col-md-2">
						<label class="form-label">Endzeit</label>
						<input type="time" name="allowed_end_time" id="jobFormAllowedEnd" class="form-control form-control-sm">
					</div>
					<div class="col-md-2">
						<label class="form-label">Schritte fällig</label>
						<input type="datetime-local" name="step_due_at" id="jobFormStepDue" class="form-control form-control-sm">
					</div>
					<div class="col-md-10">
						<label class="form-label">Arbeitsschritte (je Zeile ein Schritt)</label>
						<textarea name="steps_text" id="jobFormSteps" rows="3" class="form-control form-control-sm" placeholder="Angebot erstellen&#10;AB in Rechnung umwandeln"></textarea>
					</div>
					<div class="col-md-12">
						<label class="form-label">Schritte (JSON, technisch)</label>
						<textarea name="steps_json" id="jobFormStepsJson" rows="8" class="form-control form-control-sm font-monospace" placeholder='[{"step_title":"Mailbox pollen","step_type":"run_mail_poller","step_payload_json":"{\"script\":\"bin/poll.php\"}","due_at":"","is_required":1}]'></textarea>
						<div class="form-text">
							Hier sieht man, was wirklich ausgeführt wird: <code>step_type</code> + <code>step_payload_json</code>.
						</div>
					</div>
					<div class="col-md-12">
						<label class="form-label">Payload JSON (optional)</label>
						<textarea name="payload_json" id="jobFormPayload" rows="2" class="form-control form-control-sm" placeholder='{"key":"value"}'></textarea>
					</div>
					<div class="col-12 d-flex justify-content-end">
						<button type="submit" class="btn btn-sm btn-primary" id="jobFormSubmitBtn">
							<i class="fas fa-plus"></i> Job erstellen
						</button>
					</div>
				</form>
			</div>
		</div>
	</div>
</div>

<script src="../assets/datatables/jquery.min.js"></script>
<script src="../assets/datatables/jquery.dataTables.min.js"></script>
<script src="../assets/datatables/dataTables.bootstrap5.min.js"></script>
<script src="../assets/bootstrap/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
	$('#jobsTable').DataTable({
		order: [[0, 'desc']],
		pageLength: 50
	});

	const runHistoryModalEl = document.getElementById('runHistoryModal');
	const runHistoryModal = runHistoryModalEl ? new bootstrap.Modal(runHistoryModalEl) : null;
	const runHistoryTitle = document.getElementById('runHistoryTitle');
	const runHistoryBody = document.getElementById('runHistoryBody');
	const stepsDetailModalEl = document.getElementById('stepsDetailModal');
	const stepsDetailModal = stepsDetailModalEl ? new bootstrap.Modal(stepsDetailModalEl) : null;
	const stepsDetailTitle = document.getElementById('stepsDetailTitle');
	const stepsDetailBody = document.getElementById('stepsDetailBody');
	const clearRunsJobId = document.getElementById('clearRunsJobId');
	const clearRunsForm = document.getElementById('clearRunsForm');
	const clearRunsBtn = document.getElementById('clearRunsBtn');
	const createJobModalEl = document.getElementById('createJobModal');
	const createJobModal = createJobModalEl ? new bootstrap.Modal(createJobModalEl) : null;
	const createBtn = document.querySelector('[data-bs-target="#createJobModal"]');
	const jobForm = document.getElementById('jobForm');
	const jobFormJobId = document.getElementById('jobFormJobId');
	const jobFormTitle = document.getElementById('jobFormTitle');
	const jobFormType = document.getElementById('jobFormType');
	const jobFormDescription = document.getElementById('jobFormDescription');
	const jobFormRelationType = document.getElementById('jobFormRelationType');
	const jobFormRelationId = document.getElementById('jobFormRelationId');
	const jobFormAccountId = document.getElementById('jobFormAccountId');
	const jobFormScheduleType = document.getElementById('jobFormScheduleType');
	const jobFormRunMode = document.getElementById('jobFormRunMode');
	const jobFormNotifyTelegram = document.getElementById('jobFormNotifyTelegram');
	const jobFormRunAt = document.getElementById('jobFormRunAt');
	const jobFormInterval = document.getElementById('jobFormInterval');
	const jobFormDailyTime = document.getElementById('jobFormDailyTime');
	const jobFormTimezone = document.getElementById('jobFormTimezone');
	const jobFormAllowedStart = document.getElementById('jobFormAllowedStart');
	const jobFormAllowedEnd = document.getElementById('jobFormAllowedEnd');
	const jobFormStepDue = document.getElementById('jobFormStepDue');
	const jobFormSteps = document.getElementById('jobFormSteps');
	const jobFormStepsJson = document.getElementById('jobFormStepsJson');
	const jobFormPayload = document.getElementById('jobFormPayload');
	const jobFormSubmitBtn = document.getElementById('jobFormSubmitBtn');
	const createJobModalTitle = document.getElementById('createJobModalTitle');
	const weekdayCheckboxes = Array.from(document.querySelectorAll('.allowed-weekday'));
	let editingSystemJob = false;

	document.querySelectorAll('.run-history-btn').forEach((btn) => {
		btn.addEventListener('click', () => {
			const jobId = btn.getAttribute('data-job-id') || '';
			const jobTitle = btn.getAttribute('data-job-title') || '';
			const runsRaw = btn.getAttribute('data-runs') || '[]';
			let runs = [];
			try {
				runs = JSON.parse(runsRaw);
			} catch (e) {
				runs = [];
			}

			if (runHistoryTitle) {
				runHistoryTitle.textContent = 'Letzte Runs - Job #' + jobId + ' ' + jobTitle;
			}
			if (clearRunsJobId) {
				clearRunsJobId.value = jobId;
			}

			if (runHistoryBody) {
				if (!Array.isArray(runs) || runs.length === 0) {
					runHistoryBody.innerHTML = '<div class="small text-muted">Keine Runs vorhanden.</div>';
				} else {
					const rows = runs.map((run) => {
						const started = run.started_at || '-';
						const status = run.status || '-';
						const trigger = run.trigger_type || '-';
						const executedBy = run.executed_by || '-';
						const msg = run.result_message ? String(run.result_message).replaceAll('<', '&lt;').replaceAll('>', '&gt;') : '';
						return `
							<div class="border rounded p-2 mb-2">
								<div><strong>${started}</strong> | ${status} | ${trigger} | ${executedBy}</div>
								${msg ? `<div class="small text-muted mt-1">${msg}</div>` : ''}
							</div>
						`;
					});
					runHistoryBody.innerHTML = rows.join('');
				}
			}

			if (runHistoryModal) {
				runHistoryModal.show();
			}
		});
	});

	document.querySelectorAll('.steps-detail-btn').forEach((btn) => {
		btn.addEventListener('click', () => {
			const jobId = btn.getAttribute('data-job-id') || '';
			const jobTitle = btn.getAttribute('data-job-title') || '';
			const stepsRaw = btn.getAttribute('data-steps') || '[]';
			let steps = [];
			try {
				steps = JSON.parse(stepsRaw);
			} catch (e) {
				steps = [];
			}

			if (stepsDetailTitle) {
				stepsDetailTitle.textContent = 'Schritte - Job #' + jobId + ' ' + jobTitle;
			}
			if (stepsDetailBody) {
				if (!Array.isArray(steps) || steps.length === 0) {
					stepsDetailBody.innerHTML = '<div class="small text-muted">Keine Schritte vorhanden.</div>';
				} else {
					const rows = steps.map((step) => {
						const order = step.step_order || '-';
						const title = step.step_title ? String(step.step_title).replaceAll('<', '&lt;').replaceAll('>', '&gt;') : '-';
						const type = step.step_type ? String(step.step_type).replaceAll('<', '&lt;').replaceAll('>', '&gt;') : 'note';
						const dueAt = step.due_at ? String(step.due_at).replaceAll('<', '&lt;').replaceAll('>', '&gt;') : '';
						const payload = step.step_payload_json ? String(step.step_payload_json).replaceAll('<', '&lt;').replaceAll('>', '&gt;') : '';
						return `
							<div class="border rounded p-2 mb-2">
								<div><strong>#${order} - ${title}</strong></div>
								<div class="small text-muted mt-1">Handler: <code>${type}</code>${dueAt ? ` | fällig: ${dueAt}` : ''}</div>
								${payload ? `<div class="small mt-1"><code>${payload}</code></div>` : ''}
							</div>
						`;
					});
					stepsDetailBody.innerHTML = rows.join('');
				}
			}

			if (stepsDetailModal) {
				stepsDetailModal.show();
			}
		});
	});

	if (clearRunsForm && clearRunsBtn) {
		clearRunsForm.addEventListener('submit', (e) => {
			const jobId = clearRunsJobId ? clearRunsJobId.value : '';
			if (!jobId) {
				e.preventDefault();
				return;
			}
			const ok = window.confirm('Run-Verlauf für Job #' + jobId + ' wirklich löschen?');
			if (!ok) {
				e.preventDefault();
			}
		});
	}

	const resetJobForm = () => {
		if (!jobForm) {
			return;
		}
		jobForm.reset();
		if (jobFormJobId) jobFormJobId.value = '0';
		if (jobFormType) jobFormType.value = 'generic';
		if (jobFormRelationType) jobFormRelationType.value = 'none';
		if (jobFormScheduleType) jobFormScheduleType.value = 'once';
		if (jobFormRunMode) jobFormRunMode.value = 'manual';
		if (jobFormNotifyTelegram) jobFormNotifyTelegram.checked = false;
		if (jobFormTimezone) jobFormTimezone.value = 'Europe/Vienna';
		if (jobFormAllowedStart) jobFormAllowedStart.value = '';
		if (jobFormAllowedEnd) jobFormAllowedEnd.value = '';
		weekdayCheckboxes.forEach((cb) => {
			cb.checked = true;
		});
		if (jobFormStepsJson) jobFormStepsJson.value = '';
		if (createJobModalTitle) createJobModalTitle.textContent = 'Neuen Job erstellen';
		if (jobFormSubmitBtn) jobFormSubmitBtn.innerHTML = '<i class="fas fa-plus"></i> Job erstellen';
		editingSystemJob = false;
	};

	if (createBtn) {
		createBtn.addEventListener('click', () => {
			resetJobForm();
		});
	}

	document.querySelectorAll('.edit-job-btn').forEach((btn) => {
		btn.addEventListener('click', () => {
			resetJobForm();
			let data = {};
			try {
				data = JSON.parse(btn.getAttribute('data-job') || '{}');
			} catch (e) {
				data = {};
			}
			if (jobFormJobId) jobFormJobId.value = String(data.id || 0);
			if (jobFormTitle) jobFormTitle.value = data.title || '';
			if (jobFormType) jobFormType.value = data.job_type || 'generic';
			if (jobFormDescription) jobFormDescription.value = data.description || '';
			if (jobFormRelationType) jobFormRelationType.value = data.relation_type || 'none';
			if (jobFormRelationId) jobFormRelationId.value = data.relation_id || '';
			if (jobFormAccountId) jobFormAccountId.value = data.account_id || '';
			if (jobFormScheduleType) jobFormScheduleType.value = data.schedule_type || 'once';
			if (jobFormRunMode) jobFormRunMode.value = data.run_mode || 'manual';
			if (jobFormNotifyTelegram) jobFormNotifyTelegram.checked = Number(data.notify_telegram || 0) === 1;
			if (jobFormRunAt) jobFormRunAt.value = data.run_at || '';
			if (jobFormInterval) jobFormInterval.value = data.interval_minutes || '';
			if (jobFormDailyTime) jobFormDailyTime.value = data.daily_time || '';
			if (jobFormTimezone) jobFormTimezone.value = data.timezone || 'Europe/Vienna';
			if (jobFormAllowedStart) jobFormAllowedStart.value = data.allowed_start_time || '';
			if (jobFormAllowedEnd) jobFormAllowedEnd.value = data.allowed_end_time || '';
			const allowedWeekdays = String(data.allowed_weekdays || '1,2,3,4,5,6,7')
				.split(',')
				.map((v) => v.trim())
				.filter((v) => v !== '');
			const allowedSet = new Set(allowedWeekdays);
			weekdayCheckboxes.forEach((cb) => {
				cb.checked = allowedSet.size === 0 ? true : allowedSet.has(cb.value);
			});
			if (jobFormStepDue) jobFormStepDue.value = data.step_due_at || '';
			if (jobFormSteps) jobFormSteps.value = data.steps_text || '';
			if (jobFormStepsJson) jobFormStepsJson.value = data.steps_json || '';
			if (jobFormPayload) jobFormPayload.value = data.payload_json || '';
			if (createJobModalTitle) createJobModalTitle.textContent = 'Job bearbeiten #' + String(data.id || '');
			if (jobFormSubmitBtn) jobFormSubmitBtn.innerHTML = '<i class="fas fa-save"></i> Job speichern';
			editingSystemJob = btn.getAttribute('data-is-system') === '1';
			if (createJobModal) {
				createJobModal.show();
			}
		});
	});

	if (jobForm) {
		jobForm.addEventListener('submit', (e) => {
			if (!editingSystemJob) {
				return;
			}
			const ok = window.confirm('Achtung: Du bearbeitest einen System-Job. Diese Änderung beeinflusst automatische Abläufe. Wirklich speichern?');
			if (!ok) {
				e.preventDefault();
			}
		});
	}
});
</script>
</body>
</html>
