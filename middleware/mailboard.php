<?php
	require_once __DIR__ . '/../db.inc.php';

	$mysqli = $mysqli ?? null;
	if (!$mysqli) {
		die('DB connection missing');
	}
	$mysqli->set_charset('utf8');

	$statusOptions = ['','new','queued','pending_import','imported','done','error','ignored'];
	$status = $_GET['status'] ?? '';
	$dateFrom = $_GET['date_from'] ?? '';
	$dateTo = $_GET['date_to'] ?? '';
	$q = trim($_GET['q'] ?? '');
	$maxRows = 1500;

	if ($dateFrom === '' && $dateTo === '') {
		$dateFrom = date('Y-m-d', strtotime('-14 days'));
	}

	$where = [];
	$params = [];
	$types = '';

	if ($status !== '') {
		$where[] = 'status = ?';
		$params[] = $status;
		$types .= 's';
	}
	if ($dateFrom !== '') {
		$where[] = 'm.date >= ?';
		$params[] = $dateFrom . ' 00:00:00';
		$types .= 's';
	}
	if ($dateTo !== '') {
		$where[] = 'm.date <= ?';
		$params[] = $dateTo . ' 23:59:59';
		$types .= 's';
	}
	if ($q !== '') {
		$where[] = '(from_addr LIKE ? OR subject LIKE ? OR message_id LIKE ?)';
		$qLike = '%' . $q . '%';
		$params[] = $qLike;
		$params[] = $qLike;
		$params[] = $qLike;
		$types .= 'sss';
	}

	$sql = "SELECT m.id, m.date, m.from_addr, m.subject, m.status, m.crm_email_id, f.name AS folder_name
		FROM mw_tracked_mail m
		LEFT JOIN emails e ON e.id = m.crm_email_id AND e.deleted = 0
		LEFT JOIN emails_folders f ON f.id = e.folder AND f.deleted = 0";
	if ($where) {
		$sql .= " WHERE " . implode(" AND ", $where);
	}
	$sql .= " ORDER BY m.date DESC, m.id DESC LIMIT " . (int)$maxRows;

	$stmt = $mysqli->prepare($sql);
	if ($stmt && $params) {
		$stmt->bind_param($types, ...$params);
	}
	$rows = [];
	if ($stmt && $stmt->execute()) {
		$res = $stmt->get_result();
		while ($row = $res->fetch_assoc()) {
			$rows[] = $row;
		}
	}
?>
<!doctype html>
<html lang="de">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Mailboard</title>
<link href="../styles.css" rel="stylesheet" type="text/css" />
<link href="../assets/bootstrap/bootstrap.min.css" rel="stylesheet" />
<link href="../assets/datatables/dataTables.bootstrap5.min.css" rel="stylesheet" />
<link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.5.0/css/all.css" crossorigin="anonymous">
</head>
<body>
<?php if (file_exists(__DIR__ . '/../navbar.php')) { include __DIR__ . '/../navbar.php'; } ?>

<div class="container-fluid py-3">
	<div class="d-flex align-items-center justify-content-between mb-3">
		<h1 class="h3 mb-0">Mailboard</h1>
		<div class="small text-muted me-3">Standard: letzte 14 Tage, max. <?php echo (int)$maxRows; ?> Einträge</div>
		<button class="btn btn-sm btn-outline-primary" id="runPollerBtn">
			<i class="fas fa-sync-alt"></i> Poller starten
		</button>
	</div>

	<form method="get" class="row g-2 align-items-end mb-3">
		<div class="col-sm-2">
			<label class="form-label">Status</label>
			<select name="status" class="form-select form-select-sm">
				<?php foreach ($statusOptions as $opt): ?>
					<option value="<?php echo htmlspecialchars($opt); ?>" <?php echo $opt === $status ? 'selected' : ''; ?>>
						<?php echo $opt === '' ? 'Alle' : htmlspecialchars($opt); ?>
					</option>
				<?php endforeach; ?>
			</select>
		</div>
		<div class="col-sm-2">
			<label class="form-label">Von</label>
			<input type="date" name="date_from" value="<?php echo htmlspecialchars($dateFrom); ?>" class="form-control form-control-sm">
		</div>
		<div class="col-sm-2">
			<label class="form-label">Bis</label>
			<input type="date" name="date_to" value="<?php echo htmlspecialchars($dateTo); ?>" class="form-control form-control-sm">
		</div>
		<div class="col-sm-5">
			<label class="form-label">Suche</label>
			<input type="text" name="q" value="<?php echo htmlspecialchars($q); ?>" class="form-control form-control-sm" placeholder="From, Subject, Message-ID">
		</div>
		<div class="col-sm-1">
			<button class="btn btn-sm btn-primary w-100" type="submit">Filter</button>
		</div>
	</form>

	<div class="table-responsive">
		<table id="mailboard" class="table table-striped table-sm align-middle">
			<thead>
				<tr>
					<th>Datum</th>
					<th>Von</th>
					<th>Betreff</th>
					<th>Ordner</th>
					<th>Status</th>
					<th>In 1CRM?</th>
					<th>Aktionen</th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ($rows as $row): ?>
					<tr data-id="<?php echo (int)$row['id']; ?>">
						<td><?php echo htmlspecialchars($row['date'] ?? ''); ?></td>
						<td><?php echo htmlspecialchars($row['from_addr'] ?? ''); ?></td>
						<td><?php echo htmlspecialchars($row['subject'] ?? ''); ?></td>
						<td><?php echo htmlspecialchars($row['folder_name'] ?? ''); ?></td>
						<td><?php echo htmlspecialchars($row['status'] ?? ''); ?></td>
						<td>
							<?php if (!empty($row['crm_email_id'])): ?>
								<a class="btn btn-sm btn-outline-success" target="_blank" rel="noopener" href="<?php echo 'https://addinol-lubeoil.at/crm/?module=Emails&action=DetailView&record=' . urlencode($row['crm_email_id']); ?>">
									<i class="fas fa-external-link-alt"></i> Öffnen
								</a>
							<?php else: ?>
								<span class="text-muted">Nein</span>
							<?php endif; ?>
						</td>
						<td>
							<div class="btn-group btn-group-sm" role="group">
								<button class="btn btn-outline-primary action-btn" data-action="import">Import</button>
								<button class="btn btn-outline-secondary action-btn" data-action="recheck">Recheck</button>
								<button class="btn btn-outline-danger action-btn" data-action="ignore">Ignorieren</button>
							</div>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>
</div>

<div class="position-fixed bottom-0 end-0 p-3" style="z-index: 11">
	<div id="actionToast" class="toast align-items-center text-bg-dark border-0" role="alert" aria-live="assertive" aria-atomic="true">
		<div class="d-flex">
			<div class="toast-body" id="actionToastBody">Aktion ausgeführt</div>
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
	$('#mailboard').DataTable({
		order: [[0, 'desc']],
		pageLength: 25
	});

	const toastEl = document.getElementById('actionToast');
	const toastBody = document.getElementById('actionToastBody');
	const toast = toastEl ? new bootstrap.Toast(toastEl) : null;

	function showToast(msg) {
		if (toast && toastBody) {
			toastBody.textContent = msg;
			toast.show();
		} else {
			alert(msg);
		}
	}

	async function runPoller() {
		let key = sessionStorage.getItem('mw_action_key') || '';
		if (!key) {
			key = prompt('X-ACTION-KEY');
			if (!key) return;
			sessionStorage.setItem('mw_action_key', key);
		}
		try {
			const resp = await fetch('../middleware/poll.php', {
				method: 'POST',
				headers: { 'X-ACTION-KEY': key }
			});
			const json = await resp.json();
			if (json && json.ok) {
				showToast('Poller gestartet');
				location.reload();
			} else {
				showToast('Poller Fehler');
			}
		} catch (err) {
			showToast('Poller Fehler');
		}
	}

	const pollerBtn = document.getElementById('runPollerBtn');
	if (pollerBtn) {
		pollerBtn.addEventListener('click', runPoller);
	}

	document.querySelectorAll('.action-btn').forEach(btn => {
		btn.addEventListener('click', async (e) => {
			const row = e.target.closest('tr');
			const id = row ? row.getAttribute('data-id') : null;
			const action = e.target.getAttribute('data-action');
			if (!id || !action) {
				return;
			}
			try {
				const resp = await fetch('../middleware/action.php', {
					method: 'POST',
					headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
					body: new URLSearchParams({ id, action })
				});
				const text = await resp.text();
				showToast(text || 'OK');
			} catch (err) {
				showToast('Fehler bei Aktion');
			}
		});
	});
});
</script>
</body>
</html>
