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

$statusFilter = trim((string)($_GET['status'] ?? 'active'));
$modeFilter = trim((string)($_GET['mode'] ?? ''));
$q = trim((string)($_GET['q'] ?? ''));
$flash = trim((string)($_GET['msg'] ?? ''));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$action = trim((string)($_POST['action'] ?? ''));
	$msg = 'Unbekannte Aktion.';

	if ($action === 'create_job') {
		$stepsRaw = trim((string)($_POST['steps_text'] ?? ''));
		$defaultDue = trim((string)($_POST['step_due_at'] ?? ''));
		$steps = [];
		if ($stepsRaw !== '') {
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

		$job = [
			'title' => trim((string)($_POST['title'] ?? '')),
			'job_type' => trim((string)($_POST['job_type'] ?? 'generic')),
			'description' => trim((string)($_POST['description'] ?? '')),
			'relation_type' => trim((string)($_POST['relation_type'] ?? 'none')),
			'relation_id' => trim((string)($_POST['relation_id'] ?? '')),
			'account_id' => trim((string)($_POST['account_id'] ?? '')),
			'payload_json' => trim((string)($_POST['payload_json'] ?? '')),
			'schedule_type' => trim((string)($_POST['schedule_type'] ?? 'once')),
			'run_mode' => trim((string)($_POST['run_mode'] ?? 'manual')),
			'run_at' => trim((string)($_POST['run_at'] ?? '')),
			'interval_minutes' => (int)($_POST['interval_minutes'] ?? 0),
			'daily_time' => trim((string)($_POST['daily_time'] ?? '')),
			'timezone' => trim((string)($_POST['timezone'] ?? 'Europe/Vienna')),
		];

		$result = JobService::insertJob($mysqli, $job, $steps);
		if (!empty($result['ok'])) {
			$msg = 'Job erstellt (ID ' . (int)$result['job_id'] . ').';
		} else {
			$msg = 'Job konnte nicht erstellt werden (' . htmlspecialchars((string)($result['reason'] ?? 'unknown')) . ').';
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
		$stepsRows = fetch_all_assoc_jobs($mysqli, "SELECT job_id, step_order, step_title, step_type, due_at FROM mw_job_steps WHERE job_id IN ($idList) ORDER BY job_id ASC, step_order ASC");
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
	if ($type === 'interval_minutes') {
		return 'Alle ' . (int)($job['interval_minutes'] ?? 0) . ' Min.';
	}
	if ($type === 'daily_time') {
		return 'Täglich ' . htmlspecialchars((string)($job['daily_time'] ?? ''));
	}
	$runAt = trim((string)($job['run_at'] ?? ''));
	return $runAt !== '' ? 'Einmalig: ' . htmlspecialchars($runAt) : 'Einmalig (sofort)';
}
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
		<a class="btn btn-sm btn-outline-secondary" href="lieferstatus.php">
			<i class="fas fa-truck"></i> Lieferstatus
		</a>
	</div>

	<?php if ($flash !== ''): ?>
		<div class="alert alert-info py-2"><?php echo htmlspecialchars($flash); ?></div>
	<?php endif; ?>

	<div class="card mb-3">
		<div class="card-header py-2"><strong>Neuen Job erstellen</strong></div>
		<div class="card-body">
			<form method="post" class="row g-2">
				<input type="hidden" name="action" value="create_job">
				<div class="col-md-4">
					<label class="form-label">Titel</label>
					<input type="text" name="title" class="form-control form-control-sm" placeholder="z.B. Ware zugestellt" required>
				</div>
				<div class="col-md-2">
					<label class="form-label">Job-Typ</label>
					<input type="text" name="job_type" class="form-control form-control-sm" value="generic">
				</div>
				<div class="col-md-2">
					<label class="form-label">Bezug</label>
					<select name="relation_type" class="form-select form-select-sm">
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
					<input type="text" name="relation_id" class="form-control form-control-sm" placeholder="CRM-ID">
				</div>
				<div class="col-md-2">
					<label class="form-label">Account-ID</label>
					<input type="text" name="account_id" class="form-control form-control-sm" placeholder="optional">
				</div>

				<div class="col-md-4">
					<label class="form-label">Beschreibung</label>
					<input type="text" name="description" class="form-control form-control-sm" placeholder="z.B. AB in Rechnung umwandeln">
				</div>
				<div class="col-md-2">
					<label class="form-label">Ausführung</label>
					<select name="run_mode" class="form-select form-select-sm">
						<option value="manual">Manuell</option>
						<option value="auto">Automatisch</option>
					</select>
				</div>
				<div class="col-md-2">
					<label class="form-label">Plan</label>
					<select name="schedule_type" class="form-select form-select-sm">
						<option value="once">Einmalig</option>
						<option value="interval_minutes">Mehrmals täglich</option>
						<option value="daily_time">Täglich fixe Uhrzeit</option>
					</select>
				</div>
				<div class="col-md-2">
					<label class="form-label">Run at</label>
					<input type="datetime-local" name="run_at" class="form-control form-control-sm">
				</div>
				<div class="col-md-1">
					<label class="form-label">Intervall</label>
					<input type="number" min="1" name="interval_minutes" class="form-control form-control-sm" placeholder="Min">
				</div>
				<div class="col-md-1">
					<label class="form-label">Daily</label>
					<input type="time" name="daily_time" class="form-control form-control-sm">
				</div>
				<div class="col-md-2">
					<label class="form-label">TZ</label>
					<input type="text" name="timezone" class="form-control form-control-sm" value="Europe/Vienna">
				</div>
				<div class="col-md-2">
					<label class="form-label">Schritte fällig</label>
					<input type="datetime-local" name="step_due_at" class="form-control form-control-sm">
				</div>
				<div class="col-md-10">
					<label class="form-label">Arbeitsschritte (je Zeile ein Schritt)</label>
					<textarea name="steps_text" rows="3" class="form-control form-control-sm" placeholder="Angebot erstellen&#10;AB in Rechnung umwandeln"></textarea>
				</div>
				<div class="col-md-12">
					<label class="form-label">Payload JSON (optional)</label>
					<textarea name="payload_json" rows="2" class="form-control form-control-sm" placeholder='{"key":"value"}'></textarea>
				</div>
				<div class="col-12">
					<button type="submit" class="btn btn-sm btn-primary">
						<i class="fas fa-plus"></i> Job erstellen
					</button>
				</div>
			</form>
		</div>
	</div>

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
						$relation = trim((string)$job['relation_type']) . ($job['relation_id'] ? ':' . (string)$job['relation_id'] : '');
						$last = trim((string)($job['last_run_at'] ?? ''));
						$lastResult = trim((string)($job['last_result'] ?? ''));
					?>
					<tr>
						<td><?php echo $jobId; ?></td>
						<td>
							<div class="fw-semibold"><?php echo htmlspecialchars((string)$job['title']); ?></div>
							<?php if (!empty($job['description'])): ?>
								<div class="small text-muted"><?php echo htmlspecialchars((string)$job['description']); ?></div>
							<?php endif; ?>
						</td>
						<td><?php echo htmlspecialchars((string)$job['job_type']); ?></td>
						<td><code><?php echo htmlspecialchars($relation); ?></code></td>
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
						<td><?php echo (int)($job['step_count'] ?? 0); ?></td>
						<td>
							<div class="btn-group btn-group-sm" role="group">
								<form method="post" class="d-inline">
									<input type="hidden" name="action" value="run_job">
									<input type="hidden" name="job_id" value="<?php echo $jobId; ?>">
									<button type="submit" class="btn btn-outline-primary">Run</button>
								</form>
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
					<tr>
						<td></td>
						<td colspan="10">
							<?php if (!empty($stepsByJob[$jobId])): ?>
								<div class="small">
									<strong>Schritte:</strong>
									<?php foreach ($stepsByJob[$jobId] as $step): ?>
										<div>
											#<?php echo (int)$step['step_order']; ?> -
											<?php echo htmlspecialchars((string)$step['step_title']); ?>
											<span class="text-muted">(<?php echo htmlspecialchars((string)$step['step_type']); ?><?php echo !empty($step['due_at']) ? ', fällig: ' . htmlspecialchars((string)$step['due_at']) : ''; ?>)</span>
										</div>
									<?php endforeach; ?>
								</div>
							<?php else: ?>
								<div class="small text-muted">Keine Schritte definiert.</div>
							<?php endif; ?>

							<?php if (!empty($runsByJob[$jobId])): ?>
								<div class="small mt-1">
									<strong>Letzte Runs:</strong>
									<?php foreach ($runsByJob[$jobId] as $run): ?>
										<div>
											<?php echo htmlspecialchars((string)$run['started_at']); ?> |
											<?php echo htmlspecialchars((string)$run['status']); ?> |
											<?php echo htmlspecialchars((string)$run['trigger_type']); ?>
											<?php if (!empty($run['result_message'])): ?>
												<span class="text-muted"> - <?php echo htmlspecialchars(substr((string)$run['result_message'], 0, 180)); ?></span>
											<?php endif; ?>
										</div>
									<?php endforeach; ?>
								</div>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
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
});
</script>
</body>
</html>
