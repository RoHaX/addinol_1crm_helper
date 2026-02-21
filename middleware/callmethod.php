<?php
	require_once __DIR__ . '/../db.inc.php';

	$mysqli = $mysqli ?? null;
	if (!$mysqli) {
		die('DB connection missing');
	}
	$mysqli->set_charset('utf8');

	$accountId = trim($_GET['account_id'] ?? '');
	$calls = [];

	if ($accountId !== '') {
		$sql = "SELECT id, name, date_start, status, direction, description
			FROM calls
			WHERE deleted = 0 AND account_id = ?
			ORDER BY date_start DESC
			LIMIT 200";
		$stmt = $mysqli->prepare($sql);
		if ($stmt) {
			$stmt->bind_param('s', $accountId);
			if ($stmt->execute()) {
				$res = $stmt->get_result();
				while ($row = $res->fetch_assoc()) {
					$calls[] = $row;
				}
			}
		}
	}
?>
<!doctype html>
<html lang="de">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<link href="../assets/bootstrap/bootstrap.min.css" rel="stylesheet" />
<style>
	body { margin: 0; background: #fff; font-size: 0.9rem; }
	.call-item + .call-item { border-top: 1px solid #e9ecef; }
	.call-date { font-size: 0.8rem; color: #6c757d; }
	.call-meta { font-size: 0.8rem; color: #495057; }
	.call-desc { white-space: pre-wrap; color: #343a40; }
</style>
</head>
<body>
<?php if ($accountId === ''): ?>
	<div class="p-3 text-muted">Keine Firma ausgewählt.</div>
<?php elseif (!$calls): ?>
	<div class="p-3 text-muted">Keine CallMethod-Einträge gefunden.</div>
<?php else: ?>
	<div class="list-group list-group-flush">
		<?php foreach ($calls as $row): ?>
			<div class="list-group-item call-item px-3 py-2">
				<div class="d-flex justify-content-between align-items-start gap-2">
					<div class="fw-semibold"><?php echo htmlspecialchars($row['name'] ?? ''); ?></div>
					<div class="call-date text-nowrap"><?php echo htmlspecialchars($row['date_start'] ?? ''); ?></div>
				</div>
				<div class="call-meta mt-1">
					Status: <?php echo htmlspecialchars($row['status'] ?? ''); ?> | Richtung: <?php echo htmlspecialchars($row['direction'] ?? ''); ?>
				</div>
				<?php if (!empty($row['description'])): ?>
					<div class="call-desc mt-2"><?php echo htmlspecialchars($row['description']); ?></div>
				<?php endif; ?>
			</div>
		<?php endforeach; ?>
	</div>
<?php endif; ?>
</body>
</html>
