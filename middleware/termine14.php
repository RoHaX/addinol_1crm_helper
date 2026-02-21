<?php
	require_once __DIR__ . '/../db.inc.php';

	$mysqli = $mysqli ?? null;
	if (!$mysqli) {
		die('DB connection missing');
	}
	$mysqli->set_charset('utf8');

	$items = [];
	$sql = "
		SELECT c.id, c.name, c.date_start, c.status, 'Call' AS source_module, a.name AS account_name
		FROM calls c
		LEFT JOIN accounts a ON a.id = c.account_id AND a.deleted = 0
		WHERE c.deleted = 0
		  AND c.date_start IS NOT NULL
		  AND c.date_start >= NOW()
		  AND c.date_start < DATE_ADD(NOW(), INTERVAL 14 DAY)
		UNION ALL
		SELECT m.id, m.name, m.date_start, m.status, 'Meeting' AS source_module, a2.name AS account_name
		FROM meetings m
		LEFT JOIN accounts a2 ON a2.id = m.account_id AND a2.deleted = 0
		WHERE m.deleted = 0
		  AND m.date_start IS NOT NULL
		  AND m.date_start >= NOW()
		  AND m.date_start < DATE_ADD(NOW(), INTERVAL 14 DAY)
		ORDER BY date_start ASC
		LIMIT 300
	";
	$res = $mysqli->query($sql);
	if ($res) {
		while ($row = $res->fetch_assoc()) {
			$items[] = $row;
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
	.term-item + .term-item { border-top: 1px solid #e9ecef; }
	.term-date { font-size: 0.8rem; color: #6c757d; }
	.term-meta { font-size: 0.8rem; color: #495057; }
	.day-header { background: #f8f9fa; border-top: 1px solid #dee2e6; border-bottom: 1px solid #dee2e6; font-weight: 600; }
</style>
</head>
<body>
<?php if (!$items): ?>
	<div class="p-3 text-muted">Keine Termine in den nächsten 14 Tagen.</div>
<?php else: ?>
	<?php
		$weekdayMap = [
			'Monday' => 'Montag',
			'Tuesday' => 'Dienstag',
			'Wednesday' => 'Mittwoch',
			'Thursday' => 'Donnerstag',
			'Friday' => 'Freitag',
			'Saturday' => 'Samstag',
			'Sunday' => 'Sonntag',
		];
		$lastDayKey = '';
		$tzUtc = new DateTimeZone('UTC');
		$tzGmt1 = new DateTimeZone('+01:00');
	?>
	<div class="px-3 py-2 small text-muted border-bottom">Zeitzone: GMT+1</div>
	<div class="list-group list-group-flush">
		<?php foreach ($items as $row): ?>
			<?php
				$module = ($row['source_module'] ?? '') === 'Meeting' ? 'Meetings' : 'Calls';
				$icon = ($row['source_module'] ?? '') === 'Meeting' ? 'fas fa-users' : 'fas fa-phone';
				$dt = null;
				if (!empty($row['date_start'])) {
					try {
						$dt = (new DateTimeImmutable((string)$row['date_start'], $tzUtc))->setTimezone($tzGmt1);
					} catch (Exception $e) {
						$dt = null;
					}
				}
				$dayKey = $dt ? $dt->format('Y-m-d') : '';
				$weekdayEn = $dt ? $dt->format('l') : '';
				$weekdayDe = $weekdayMap[$weekdayEn] ?? $weekdayEn;
				$dayTitle = $dt ? ($weekdayDe . ', ' . $dt->format('d.m.Y')) : 'Ohne Datum';
				$timeLabel = $dt ? $dt->format('H:i') : '';
			?>
			<?php if ($dayKey !== $lastDayKey): ?>
				<div class="list-group-item day-header px-3 py-2"><?php echo htmlspecialchars($dayTitle); ?></div>
				<?php $lastDayKey = $dayKey; ?>
			<?php endif; ?>
			<div class="list-group-item term-item px-3 py-2">
				<div class="d-flex justify-content-between align-items-start gap-2">
					<div>
						<a class="fw-semibold text-decoration-none" target="_blank" rel="noopener" href="<?php echo 'https://addinol-lubeoil.at/crm/?module=' . urlencode($module) . '&action=DetailView&record=' . urlencode($row['id']); ?>">
							<i class="<?php echo htmlspecialchars($icon); ?> me-1"></i>
							<?php echo htmlspecialchars($row['name'] ?? ''); ?>
						</a>
					</div>
					<div class="term-date text-nowrap"><?php echo htmlspecialchars($timeLabel); ?></div>
				</div>
				<div class="term-meta mt-1">
					Status: <?php echo htmlspecialchars($row['status'] ?? ''); ?>
					<?php if (!empty($row['account_name'])): ?>
						| Firma: <?php echo htmlspecialchars($row['account_name']); ?>
					<?php endif; ?>
				</div>
			</div>
		<?php endforeach; ?>
	</div>
<?php endif; ?>
</body>
</html>
