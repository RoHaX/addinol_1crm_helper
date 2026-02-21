<?php
	require_once __DIR__ . '/../db.inc.php';

	$mysqli = $mysqli ?? null;
	if (!$mysqli) {
		die('DB connection missing');
	}
	$mysqli->set_charset('utf8');

	function fetch_all_assoc(mysqli $mysqli, string $sql, string $types = '', array $params = []): array {
		$rows = [];
		$stmt = $mysqli->prepare($sql);
		if (!$stmt) {
			return $rows;
		}
		if ($types !== '' && $params) {
			$stmt->bind_param($types, ...$params);
		}
		if (!$stmt->execute()) {
			return $rows;
		}
		$res = $stmt->get_result();
		while ($row = $res->fetch_assoc()) {
			$rows[] = $row;
		}
		return $rows;
	}

	function format_stage_badge(string $module, ?string $stage): string {
		$raw = trim((string)$stage);
		$key = strtolower($raw);

		$maps = [
			'quote' => [
				'draft' => ['Entwurf', 'fas fa-pencil-alt', 'secondary'],
				'negotiation' => ['Verhandlung', 'fas fa-comments', 'warning'],
				'on hold' => ['Pausiert', 'fas fa-pause-circle', 'secondary'],
				'closed accepted' => ['Angenommen', 'fas fa-check-circle', 'success'],
				'closed lost' => ['Verloren', 'fas fa-times-circle', 'danger'],
				'closed dead' => ['Abgeschlossen', 'fas fa-ban', 'dark'],
				'delivered' => ['Zugestellt', 'fas fa-truck', 'success'],
			],
			'order' => [
				'ordered' => ['Beauftragt', 'fas fa-clipboard-check', 'primary'],
				'delivered' => ['Geliefert', 'fas fa-truck', 'success'],
				'pending' => ['Offen', 'fas fa-hourglass-half', 'warning'],
				'shipped' => ['Versendet', 'fas fa-shipping-fast', 'info'],
				'partially shipped' => ['Teilversendet', 'fas fa-shipping-fast', 'info'],
				'closed - shipped and invoiced' => ['Abgeschlossen', 'fas fa-check-double', 'success'],
			],
			'invoice' => [
				'none' => ['Kein Status', 'fas fa-minus-circle', 'secondary'],
				'pending' => ['Offen', 'fas fa-hourglass-half', 'warning'],
				'partially shipped' => ['Teilversendet', 'fas fa-shipping-fast', 'info'],
				'shipped' => ['Versendet', 'fas fa-truck', 'primary'],
				'delivered' => ['Zugestellt', 'fas fa-box-open', 'success'],
				'closed - shipped and invoiced' => ['Abgeschlossen', 'fas fa-check-double', 'success'],
			],
		];

		$label = $raw !== '' ? $raw : '-';
		$icon = 'fas fa-tag';
		$class = 'secondary';

		if (isset($maps[$module][$key])) {
			$label = $maps[$module][$key][0];
			$icon = $maps[$module][$key][1];
			$class = $maps[$module][$key][2];
		}

		$title = $raw !== '' ? ' title="' . htmlspecialchars($raw, ENT_QUOTES) . '"' : '';

		return '<span class="badge text-bg-' . $class . '"' . $title . '><i class="' . $icon . ' me-1"></i>' . htmlspecialchars($label) . '</span>';
	}

	function format_account_type_badge(?string $type): string {
		$raw = trim((string)$type);
		$key = strtolower($raw);

		$map = [
			'customer' => ['Kunde', 'fas fa-building', 'primary'],
			'prospect' => ['Zielkunde', 'fas fa-bullseye', 'warning'],
			'analyst' => ['Stammkunde', 'fas fa-user-check', 'success'],
			'competitor' => ['Mitbewerber', 'fas fa-chess-knight', 'danger'],
			'partner' => ['Partner', 'fas fa-handshake', 'info'],
			'press' => ['Presse', 'fas fa-newspaper', 'secondary'],
			'other' => ['Andere', 'fas fa-ellipsis-h', 'dark'],
			'investor' => ['Investor', 'fas fa-chart-line', 'success'],
			'supplier' => ['Lieferant', 'fas fa-truck-loading', 'secondary'],
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

	function format_activity_status_badge(?string $status): string {
		$raw = trim((string)$status);
		$key = strtolower($raw);
		$map = [
			'planned' => ['Geplant', 'fas fa-calendar-alt', 'secondary'],
			'held' => ['Erledigt', 'fas fa-check-circle', 'success'],
			'not held' => ['Nicht erfolgt', 'fas fa-times-circle', 'danger'],
			'completed' => ['Abgeschlossen', 'fas fa-check-double', 'success'],
			'in progress' => ['In Arbeit', 'fas fa-spinner', 'info'],
			'deferred' => ['Zurückgestellt', 'fas fa-pause-circle', 'warning'],
			'pending input' => ['Wartet auf Input', 'fas fa-hourglass-half', 'warning'],
			'not started' => ['Nicht begonnen', 'fas fa-minus-circle', 'secondary'],
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

	function format_email_status_badge(?string $status, ?string $type): string {
		$rawStatus = trim((string)$status);
		$rawType = trim((string)$type);
		$rawCombined = trim($rawStatus . ($rawStatus !== '' && $rawType !== '' ? ' / ' : '') . $rawType);
		$key = strtolower($rawStatus !== '' ? $rawStatus : $rawType);

		$map = [
			'sent' => ['Gesendet', 'fas fa-paper-plane', 'primary'],
			'received' => ['Empfangen', 'fas fa-inbox', 'success'],
			'archived' => ['Archiviert', 'fas fa-archive', 'secondary'],
			'draft' => ['Entwurf', 'fas fa-pencil-alt', 'warning'],
			'read' => ['Gelesen', 'fas fa-envelope-open-text', 'info'],
			'unread' => ['Ungelesen', 'fas fa-envelope', 'dark'],
			'outbound' => ['Ausgehend', 'fas fa-paper-plane', 'primary'],
			'inbound' => ['Eingehend', 'fas fa-inbox', 'success'],
			'pick' => ['Zuordnen', 'fas fa-tags', 'secondary'],
		];

		$label = $rawCombined !== '' ? $rawCombined : '-';
		$icon = 'fas fa-envelope';
		$class = 'secondary';
		if (isset($map[$key])) {
			$label = $map[$key][0];
			$icon = $map[$key][1];
			$class = $map[$key][2];
		}

		$title = $rawCombined !== '' ? ' title="' . htmlspecialchars($rawCombined, ENT_QUOTES) . '"' : '';
		return '<span class="badge text-bg-' . $class . '"' . $title . '><i class="' . $icon . ' me-1"></i>' . htmlspecialchars($label) . '</span>';
	}

	function build_detail_url(string $accountId, bool $currentQuotes, bool $currentInvoices, bool $currentActivities, bool $currentOrders, bool $currentEmails, array $overrides = []): string {
		$params = [
			'account_id' => $accountId,
			'current_quotes' => $currentQuotes ? '1' : '0',
			'current_invoices' => $currentInvoices ? '1' : '0',
			'current_activities' => $currentActivities ? '1' : '0',
			'current_orders' => $currentOrders ? '1' : '0',
			'current_emails' => $currentEmails ? '1' : '0',
		];
		foreach ($overrides as $key => $val) {
			$params[$key] = $val;
		}
		return 'firmen.php?' . http_build_query($params);
	}

	function shorten_text(?string $value, int $maxLen = 160): string {
		$txt = trim(strip_tags((string)$value));
		$txt = preg_replace('/\s+/', ' ', $txt);
		if ($txt === '') {
			return '';
		}
		if (function_exists('mb_strlen') && function_exists('mb_substr')) {
			if (mb_strlen($txt) <= $maxLen) {
				return $txt;
			}
			return rtrim(mb_substr($txt, 0, $maxLen - 1)) . '...';
		}
		if (strlen($txt) <= $maxLen) {
			return $txt;
		}
		return rtrim(substr($txt, 0, $maxLen - 1)) . '...';
	}

	function build_account_overview(mysqli $mysqli, string $accountId): array {
		$overview = [
			'open_quotes' => 0,
			'open_orders' => 0,
			'open_invoices' => 0,
			'next_appointments' => [],
			'latest_quote_products' => [],
			'latest_notes' => [],
		];

		$openQuoteRows = fetch_all_assoc(
			$mysqli,
			"SELECT COUNT(*) AS cnt
			 FROM quotes
			 WHERE deleted = 0
			   AND billing_account_id = ?
			   AND valid_until >= DATE_SUB(CURDATE(), INTERVAL 60 DAY)
			   AND LOWER(COALESCE(quote_stage, '')) NOT IN ('closed accepted', 'closed lost', 'closed dead')",
			's',
			[$accountId]
		);
		$overview['open_quotes'] = (int)($openQuoteRows[0]['cnt'] ?? 0);

		$openOrderRows = fetch_all_assoc(
			$mysqli,
			"SELECT COUNT(*) AS cnt
			 FROM sales_orders
			 WHERE deleted = 0
			   AND billing_account_id = ?
			   AND COALESCE(due_date, delivery_date, date_entered) >= DATE_SUB(CURDATE(), INTERVAL 60 DAY)
			   AND LOWER(COALESCE(so_stage, '')) NOT IN ('delivered', 'closed - shipped and invoiced')",
			's',
			[$accountId]
		);
		$overview['open_orders'] = (int)($openOrderRows[0]['cnt'] ?? 0);

		$openInvoiceRows = fetch_all_assoc(
			$mysqli,
			"SELECT COUNT(*) AS cnt
			 FROM invoice
			 WHERE deleted = 0
			   AND billing_account_id = ?
			   AND invoice_date >= DATE_SUB(CURDATE(), INTERVAL 60 DAY)
			   AND COALESCE(amount_due, 0) > 0",
			's',
			[$accountId]
		);
		$overview['open_invoices'] = (int)($openInvoiceRows[0]['cnt'] ?? 0);

		$overview['next_appointments'] = fetch_all_assoc(
			$mysqli,
			"SELECT a.module, a.id, a.name, a.date_start, a.status
			 FROM (
				SELECT 'Calls' AS module, c.id, c.name, c.date_start, c.status
				FROM calls c
				WHERE c.deleted = 0 AND c.date_start >= NOW()
				  AND (c.account_id = ? OR (c.parent_type = 'Accounts' AND c.parent_id = ?))
				UNION ALL
				SELECT 'Meetings' AS module, m.id, m.name, m.date_start, m.status
				FROM meetings m
				WHERE m.deleted = 0 AND m.date_start >= NOW()
				  AND (m.account_id = ? OR (m.parent_type = 'Accounts' AND m.parent_id = ?))
			 ) a
			 ORDER BY a.date_start ASC
			 LIMIT 5",
			'ssss',
			[$accountId, $accountId, $accountId, $accountId]
		);

		$overview['latest_quote_products'] = fetch_all_assoc(
			$mysqli,
			"SELECT ql.related_id, ql.name, MAX(COALESCE(q.date_modified, q.date_entered)) AS last_offered_at
			 FROM quote_lines ql
			 INNER JOIN quotes q ON q.id = ql.quote_id
			 WHERE q.deleted = 0
			   AND ql.deleted = 0
			   AND q.billing_account_id = ?
			 GROUP BY ql.related_id, ql.name
			 ORDER BY last_offered_at DESC
			 LIMIT 5",
			's',
			[$accountId]
		);

		$overview['latest_notes'] = fetch_all_assoc(
			$mysqli,
			"SELECT id, name, description, date_modified
			 FROM notes
			 WHERE deleted = 0
			   AND account_id = ?
			 ORDER BY date_modified DESC, date_entered DESC
			 LIMIT 3",
			's',
			[$accountId]
		);

		return $overview;
	}

	$q = trim($_GET['q'] ?? '');
	$accountType = trim($_GET['account_type'] ?? '');
	$accountId = trim($_GET['account_id'] ?? '');
	$onlyCurrentQuotes = (($_GET['current_quotes'] ?? '1') !== '0');
	$onlyCurrentInvoices = (($_GET['current_invoices'] ?? '1') !== '0');
	$onlyCurrentActivities = (($_GET['current_activities'] ?? '1') !== '0');
	$onlyCurrentOrders = (($_GET['current_orders'] ?? '1') !== '0');
	$onlyCurrentEmails = (($_GET['current_emails'] ?? '1') !== '0');

	if (($_GET['overview_json'] ?? '') === '1') {
		header('Content-Type: application/json; charset=utf-8');
		if ($accountId === '') {
			http_response_code(400);
			echo json_encode(['ok' => false, 'error' => 'missing_account_id']);
			exit;
		}
		$firmRows = fetch_all_assoc(
			$mysqli,
			"SELECT id, ticker_symbol, name
			 FROM accounts
			 WHERE deleted = 0 AND id = ?
			 LIMIT 1",
			's',
			[$accountId]
		);
		$firmRow = $firmRows[0] ?? null;
		if (!$firmRow) {
			http_response_code(404);
			echo json_encode(['ok' => false, 'error' => 'not_found']);
			exit;
		}
		$overviewJson = build_account_overview($mysqli, $accountId);
		echo json_encode([
			'ok' => true,
			'firm' => $firmRow,
			'overview' => $overviewJson,
		]);
		exit;
	}

	$localNoteFile = __DIR__ . '/../logs/firmen-notiz.html';
	$localNoteHtml = '';
	$noteSaved = false;
	$noteSaveError = '';

	if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_local_note') {
		$localNoteHtml = (string)($_POST['local_note_html'] ?? '');
		$localNoteHtml = preg_replace('~<script\\b[^>]*>.*?</script>~is', '', $localNoteHtml);
		if (@file_put_contents($localNoteFile, $localNoteHtml) === false) {
			$noteSaveError = 'Notiz konnte nicht gespeichert werden.';
		} else {
			$noteSaved = true;
		}
		$q = trim((string)($_POST['q'] ?? $q));
		$accountType = trim((string)($_POST['account_type'] ?? $accountType));
	} elseif (is_file($localNoteFile)) {
		$localNoteHtml = (string)file_get_contents($localNoteFile);
	}

	$accountTypeOptions = [
		'' => 'Alle',
		'Customer' => 'Kunde',
		'Analyst' => 'Stammkunde',
		'Prospect' => 'Zielkunde',
		'Competitor' => 'Mitbewerber',
		'Partner' => 'Partner',
		'Press' => 'Presse',
		'Investor' => 'Investor',
		'Supplier' => 'Lieferant',
		'Other' => 'Andere',
	];

	$isDetailView = ($accountId !== '');
	$firm = null;
	$contacts = [];
	$quotes = [];
	$salesOrders = [];
	$invoices = [];
	$soldProducts = [];
	$activities = [];
	$emailHistory = [];
	$overview = [
		'open_quotes' => 0,
		'open_orders' => 0,
		'open_invoices' => 0,
		'next_appointments' => [],
		'latest_quote_products' => [],
		'latest_notes' => [],
	];
	$firms = [];

	if ($isDetailView) {
		$firmRows = fetch_all_assoc(
			$mysqli,
			"SELECT id, ticker_symbol, name, account_type, phone_office, email1, website, balance,
					shipping_address_street, shipping_address_postalcode, shipping_address_city, shipping_address_state, shipping_address_country
			 FROM accounts
			 WHERE deleted = 0 AND id = ?
			 LIMIT 1",
			's',
			[$accountId]
		);
		$firm = $firmRows[0] ?? null;

		if ($firm) {
			$contacts = fetch_all_assoc(
				$mysqli,
				"SELECT id, salutation, first_name, last_name, title, email1, phone_mobile, phone_work
				 FROM contacts
				 WHERE deleted = 0 AND primary_account_id = ?
				 ORDER BY last_name ASC, first_name ASC",
				's',
				[$accountId]
			);

			$quotes = fetch_all_assoc(
				$mysqli,
				"SELECT id, prefix, quote_number, quote_stage, valid_until, amount
				 FROM quotes
				 WHERE deleted = 0 AND billing_account_id = ?" . ($onlyCurrentQuotes ? " AND valid_until >= DATE_SUB(CURDATE(), INTERVAL 60 DAY)" : "") . "
				 ORDER BY valid_until DESC, quote_number DESC
				 LIMIT 500",
				's',
				[$accountId]
			);

			$salesOrders = fetch_all_assoc(
				$mysqli,
				"SELECT id, prefix, so_number, so_stage, due_date, delivery_date, amount
				 FROM sales_orders
				 WHERE deleted = 0 AND billing_account_id = ?" . ($onlyCurrentOrders ? " AND COALESCE(due_date, delivery_date, date_entered) >= DATE_SUB(CURDATE(), INTERVAL 60 DAY)" : "") . "
				 ORDER BY due_date DESC, so_number DESC
				 LIMIT 500",
				's',
				[$accountId]
			);

			$invoices = fetch_all_assoc(
				$mysqli,
				"SELECT id, prefix, invoice_number, shipping_stage, invoice_date, due_date, amount, amount_due
				 FROM invoice
				 WHERE deleted = 0 AND billing_account_id = ?" . ($onlyCurrentInvoices ? " AND invoice_date >= DATE_SUB(CURDATE(), INTERVAL 60 DAY)" : "") . "
				 ORDER BY invoice_date DESC, invoice_number DESC
				 LIMIT 500",
				's',
				[$accountId]
			);

			$soldProducts = fetch_all_assoc(
				$mysqli,
				"SELECT il.related_id, il.name,
						SUM(il.quantity) AS qty_total,
						SUM(il.unit_price * il.quantity) AS amount_total,
						MAX(il.unit_price) AS unit_price_latest
				 FROM invoice_lines il
				 INNER JOIN invoice i ON i.id = il.invoice_id
				 WHERE i.deleted = 0
				   AND il.deleted = 0
				   AND i.billing_account_id = ?" . ($onlyCurrentInvoices ? " AND i.invoice_date >= DATE_SUB(CURDATE(), INTERVAL 60 DAY)" : "") . "
				 GROUP BY il.related_id, il.name
				 ORDER BY qty_total DESC, amount_total DESC
				 LIMIT 500",
				's',
				[$accountId]
			);

			$activities = fetch_all_assoc(
				$mysqli,
				"SELECT a.activity_type, a.id, a.name, a.status, a.activity_date, a.activity_hint
				 FROM (
					SELECT 'Call' AS activity_type, c.id, c.name, c.status, c.date_start AS activity_date, c.phone_number AS activity_hint
					FROM calls c
					WHERE c.deleted = 0 AND (c.account_id = ? OR (c.parent_type = 'Accounts' AND c.parent_id = ?))

					UNION ALL

					SELECT 'Meeting' AS activity_type, m.id, m.name, m.status, m.date_start AS activity_date, m.location AS activity_hint
					FROM meetings m
					WHERE m.deleted = 0 AND (m.account_id = ? OR (m.parent_type = 'Accounts' AND m.parent_id = ?))

					UNION ALL

					SELECT 'Task' AS activity_type, t.id, t.name, t.status, COALESCE(t.date_due, t.date_start) AS activity_date, t.priority AS activity_hint
					FROM tasks t
					WHERE t.deleted = 0 AND (t.account_id = ? OR (t.parent_type = 'Accounts' AND t.parent_id = ?))
				 ) a" . ($onlyCurrentActivities ? "
				 WHERE a.activity_date >= DATE_SUB(CURDATE(), INTERVAL 60 DAY)" : "") . "
				 ORDER BY a.activity_date DESC, a.id DESC
				 LIMIT 500",
				'ssssss',
				[$accountId, $accountId, $accountId, $accountId, $accountId, $accountId]
			);

			$emailHistory = fetch_all_assoc(
				$mysqli,
				"SELECT DISTINCT e.id, e.name, e.date_start, e.from_addr, e.status, e.type, f.name AS folder_name
				 FROM emails e
				 LEFT JOIN emails_accounts ea ON ea.email_id = e.id AND ea.deleted = 0
				 LEFT JOIN emails_folders f ON f.id = e.folder AND f.deleted = 0
				 WHERE e.deleted = 0
				   AND (e.account_id = ? OR ea.account_id = ? OR (e.parent_type = 'Accounts' AND e.parent_id = ?))" . ($onlyCurrentEmails ? "
				   AND COALESCE(e.date_start, e.date_entered) >= DATE_SUB(CURDATE(), INTERVAL 60 DAY)" : "") . "
				 ORDER BY e.date_start DESC, e.date_entered DESC
				 LIMIT 500",
				'sss',
				[$accountId, $accountId, $accountId]
			);

			$overview = build_account_overview($mysqli, $accountId);
		}
	} else {
		$where = ['a.deleted = 0'];
		$types = '';
		$params = [];

		if ($accountType !== '') {
			$where[] = 'a.account_type = ?';
			$types .= 's';
			$params[] = $accountType;
		}
		if ($q !== '') {
			$qLike = '%' . $q . '%';
			$where[] = '(a.ticker_symbol LIKE ? OR a.name LIKE ? OR a.email1 LIKE ? OR a.phone_office LIKE ? OR a.shipping_address_city LIKE ?)';
			$types .= 'sssss';
			$params[] = $qLike;
			$params[] = $qLike;
			$params[] = $qLike;
			$params[] = $qLike;
			$params[] = $qLike;
		}

		$sql = "SELECT a.id, a.ticker_symbol, a.name, a.account_type, a.phone_office, a.email1, a.website,
					a.shipping_address_postalcode, a.shipping_address_city, a.shipping_address_state, a.balance
				FROM accounts a";
		if ($where) {
			$sql .= " WHERE " . implode(" AND ", $where);
		}
		$sql .= " ORDER BY a.ticker_symbol ASC, a.name ASC LIMIT 5000";

		$firms = fetch_all_assoc($mysqli, $sql, $types, $params);
	}
?>
<!doctype html>
<html lang="de">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Firmen</title>
<link href="../styles.css" rel="stylesheet" type="text/css" />
<link href="../assets/bootstrap/bootstrap.min.css" rel="stylesheet" />
<link href="../assets/datatables/dataTables.bootstrap5.min.css" rel="stylesheet" />
<link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.5.0/css/all.css" crossorigin="anonymous">
<style>
	@media (min-width: 992px) {
		.note-fixed {
			height: 360px;
		}
		.appointments-fill {
			height: calc(100vh - 140px - 380px);
			min-height: 260px;
		}
	}
	.note-fixed .card-body {
		overflow: hidden;
	}
	.note-fixed form {
		min-height: 0;
	}
	#localNoteSurface {
		min-height: 0;
	}
	#firmsTable tbody tr.firm-row {
		cursor: pointer;
	}
</style>
</head>
<body>
<?php if (file_exists(__DIR__ . '/../navbar.php')) { include __DIR__ . '/../navbar.php'; } ?>

<div class="container-fluid py-3">
	<div class="d-flex align-items-center justify-content-between mb-3">
		<span class="badge text-bg-secondary">Read-Only</span>
	</div>

	<?php if ($isDetailView): ?>
		<a href="firmen.php" class="btn btn-sm btn-outline-secondary mb-3">
			<i class="fas fa-arrow-left"></i> Zur Firmenliste
		</a>

		<?php if (!$firm): ?>
			<div class="alert alert-warning">Firma nicht gefunden.</div>
		<?php else: ?>
			<div class="row g-3 mb-3">
				<div class="col-12 col-xl-4">
					<div class="card shadow-sm h-100">
						<div class="card-header py-2"><strong>Firma</strong></div>
						<div class="card-body">
							<div class="row g-2">
								<div class="col-md-4 col-xl-12"><strong>Konto:</strong> <?php echo htmlspecialchars($firm['ticker_symbol'] ?? ''); ?></div>
								<div class="col-md-4 col-xl-12"><strong>Typ:</strong> <?php echo format_account_type_badge($firm['account_type'] ?? ''); ?></div>
								<div class="col-md-4 col-xl-12"><strong>Saldo:</strong> <?php echo isset($firm['balance']) ? number_format((float)$firm['balance'], 2, ',', '.') : ''; ?></div>
								<div class="col-md-12"><strong>Name:</strong> <?php echo htmlspecialchars($firm['name'] ?? ''); ?></div>
								<div class="col-md-6 col-xl-12"><strong>Telefon:</strong> <?php echo htmlspecialchars($firm['phone_office'] ?? ''); ?></div>
								<div class="col-md-6 col-xl-12"><strong>E-Mail:</strong> <?php echo htmlspecialchars($firm['email1'] ?? ''); ?></div>
								<div class="col-md-12"><strong>Adresse:</strong> <?php echo htmlspecialchars(trim(($firm['shipping_address_street'] ?? '') . ', ' . ($firm['shipping_address_postalcode'] ?? '') . ' ' . ($firm['shipping_address_city'] ?? '') . ', ' . ($firm['shipping_address_state'] ?? '') . ', ' . ($firm['shipping_address_country'] ?? ''))); ?></div>
							</div>
							<div class="mt-3">
								<a class="btn btn-sm btn-outline-success" target="_blank" rel="noopener" href="<?php echo 'https://addinol-lubeoil.at/crm/?module=Accounts&action=DetailView&record=' . urlencode($firm['id']); ?>">
									<i class="fas fa-external-link-alt"></i> In 1CRM öffnen
								</a>
							</div>
						</div>
					</div>
				</div>
				<div class="col-12 col-xl-8">
					<div class="card shadow-sm mb-3">
						<div class="card-header py-2"><strong>Auf Einen Blick</strong></div>
						<div class="card-body">
							<div class="row g-2 mb-3">
								<div class="col-sm-4">
									<div class="p-2 border rounded bg-light">
										<div class="small text-muted">Offene Angebote</div>
										<div class="h5 mb-0"><?php echo (int)$overview['open_quotes']; ?></div>
									</div>
								</div>
								<div class="col-sm-4">
									<div class="p-2 border rounded bg-light">
										<div class="small text-muted">Offene Aufträge</div>
										<div class="h5 mb-0"><?php echo (int)$overview['open_orders']; ?></div>
									</div>
								</div>
								<div class="col-sm-4">
									<div class="p-2 border rounded bg-light">
										<div class="small text-muted">Offene Rechnungen</div>
										<div class="h5 mb-0"><?php echo (int)$overview['open_invoices']; ?></div>
									</div>
								</div>
							</div>

							<div class="row g-3">
								<div class="col-12 col-xxl-4">
									<div class="small fw-semibold mb-1">Nächste Termine</div>
									<?php if (empty($overview['next_appointments'])): ?>
										<div class="text-muted small">Keine zukünftigen Termine.</div>
									<?php else: ?>
										<ul class="list-group list-group-flush">
											<?php foreach ($overview['next_appointments'] as $row): ?>
												<li class="list-group-item px-0 py-1">
													<div class="small">
														<strong><?php echo htmlspecialchars($row['date_start'] ?? ''); ?></strong><br>
														<?php echo htmlspecialchars($row['name'] ?? ''); ?>
														<span class="text-muted">(<?php echo htmlspecialchars($row['status'] ?? ''); ?>)</span>
													</div>
												</li>
											<?php endforeach; ?>
										</ul>
									<?php endif; ?>
								</div>
								<div class="col-12 col-xxl-4">
									<div class="small fw-semibold mb-1">Zuletzt angebotene Produkte</div>
									<?php if (empty($overview['latest_quote_products'])): ?>
										<div class="text-muted small">Keine Daten.</div>
									<?php else: ?>
										<ul class="list-group list-group-flush">
											<?php foreach ($overview['latest_quote_products'] as $row): ?>
												<li class="list-group-item px-0 py-1">
													<div class="small">
														<?php echo htmlspecialchars($row['name'] ?? ''); ?><br>
														<span class="text-muted"><?php echo htmlspecialchars($row['last_offered_at'] ?? ''); ?></span>
													</div>
												</li>
											<?php endforeach; ?>
										</ul>
									<?php endif; ?>
								</div>
								<div class="col-12 col-xxl-4">
									<div class="small fw-semibold mb-1">Letzte Notizen</div>
									<?php if (empty($overview['latest_notes'])): ?>
										<div class="text-muted small">Keine Notizen.</div>
									<?php else: ?>
										<ul class="list-group list-group-flush">
											<?php foreach ($overview['latest_notes'] as $row): ?>
												<li class="list-group-item px-0 py-1">
													<div class="small">
														<?php if (!empty($row['name'])): ?><strong><?php echo htmlspecialchars($row['name']); ?></strong><br><?php endif; ?>
														<?php echo htmlspecialchars(shorten_text($row['description'] ?? '', 130)); ?><br>
														<span class="text-muted"><?php echo htmlspecialchars($row['date_modified'] ?? ''); ?></span>
													</div>
												</li>
											<?php endforeach; ?>
										</ul>
									<?php endif; ?>
								</div>
							</div>
						</div>
					</div>

					<div class="card shadow-sm">
						<div class="card-header py-2"><strong>Kontakte</strong> <span class="text-muted">(<?php echo count($contacts); ?>)</span></div>
						<div class="card-body">
							<div class="table-responsive">
								<table id="contactsTable" class="table table-striped table-sm align-middle js-dt">
									<thead>
										<tr>
											<th>Name</th>
											<th>Titel</th>
											<th>Mobil</th>
											<th>Arbeit</th>
											<th>E-Mail</th>
											<th>1CRM</th>
										</tr>
									</thead>
									<tbody>
										<?php foreach ($contacts as $row): ?>
											<?php $fullName = trim(($row['salutation'] ?? '') . ' ' . ($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? '')); ?>
											<tr>
												<td><?php echo htmlspecialchars($fullName); ?></td>
												<td><?php echo htmlspecialchars($row['title'] ?? ''); ?></td>
												<td><?php echo htmlspecialchars($row['phone_mobile'] ?? ''); ?></td>
												<td><?php echo htmlspecialchars($row['phone_work'] ?? ''); ?></td>
												<td><?php echo htmlspecialchars($row['email1'] ?? ''); ?></td>
												<td>
													<a class="btn btn-sm btn-outline-success" target="_blank" rel="noopener" href="<?php echo 'https://addinol-lubeoil.at/crm/?module=Contacts&action=DetailView&record=' . urlencode($row['id']); ?>">
														<i class="fas fa-external-link-alt"></i> Öffnen
													</a>
												</td>
											</tr>
										<?php endforeach; ?>
									</tbody>
								</table>
							</div>
						</div>
					</div>
				</div>
			</div>

			<div class="row g-3 mb-3">
				<div class="col-12 col-xxl-6">
					<div class="card shadow-sm h-100">
						<div class="card-header py-2 d-flex align-items-center justify-content-between gap-2">
							<div><strong>Angebote</strong> <span class="text-muted">(<?php echo count($quotes); ?>)</span></div>
							<div class="form-check form-switch mb-0">
								<?php $quotesOnUrl = build_detail_url($accountId, true, $onlyCurrentInvoices, $onlyCurrentActivities, $onlyCurrentOrders, $onlyCurrentEmails); ?>
								<?php $quotesOffUrl = build_detail_url($accountId, false, $onlyCurrentInvoices, $onlyCurrentActivities, $onlyCurrentOrders, $onlyCurrentEmails); ?>
								<input class="form-check-input current-toggle" type="checkbox" id="toggleCurrentQuotes" <?php echo $onlyCurrentQuotes ? 'checked' : ''; ?> data-url-on="<?php echo htmlspecialchars($quotesOnUrl, ENT_QUOTES); ?>" data-url-off="<?php echo htmlspecialchars($quotesOffUrl, ENT_QUOTES); ?>">
								<label class="form-check-label small" for="toggleCurrentQuotes">Nur aktuelle</label>
							</div>
						</div>
						<div class="card-body">
							<div class="table-responsive">
								<table id="quotesTable" class="table table-striped table-sm align-middle js-dt">
									<thead>
										<tr>
											<th>Nr.</th>
											<th>Status</th>
											<th>Gültig bis</th>
											<th>Betrag</th>
											<th>1CRM</th>
										</tr>
									</thead>
									<tbody>
										<?php foreach ($quotes as $row): ?>
											<tr>
												<td><?php echo htmlspecialchars(trim(($row['prefix'] ?? '') . ' ' . ($row['quote_number'] ?? ''))); ?></td>
												<td><?php echo format_stage_badge('quote', $row['quote_stage'] ?? ''); ?></td>
												<td><?php echo htmlspecialchars($row['valid_until'] ?? ''); ?></td>
												<td><?php echo isset($row['amount']) ? number_format((float)$row['amount'], 2, ',', '.') : ''; ?></td>
												<td>
													<a class="btn btn-sm btn-outline-success" target="_blank" rel="noopener" href="<?php echo 'https://addinol-lubeoil.at/crm/?module=Quotes&action=DetailView&record=' . urlencode($row['id']); ?>">
														<i class="fas fa-external-link-alt"></i> Öffnen
													</a>
												</td>
											</tr>
										<?php endforeach; ?>
									</tbody>
								</table>
							</div>
						</div>
					</div>
				</div>
				<div class="col-12 col-xxl-6">
					<div class="card shadow-sm h-100">
						<div class="card-header py-2 d-flex align-items-center justify-content-between gap-2">
							<div><strong>Rechnungen</strong> <span class="text-muted">(<?php echo count($invoices); ?>)</span></div>
							<div class="form-check form-switch mb-0">
								<?php $invoicesOnUrl = build_detail_url($accountId, $onlyCurrentQuotes, true, $onlyCurrentActivities, $onlyCurrentOrders, $onlyCurrentEmails); ?>
								<?php $invoicesOffUrl = build_detail_url($accountId, $onlyCurrentQuotes, false, $onlyCurrentActivities, $onlyCurrentOrders, $onlyCurrentEmails); ?>
								<input class="form-check-input current-toggle" type="checkbox" id="toggleCurrentInvoices" <?php echo $onlyCurrentInvoices ? 'checked' : ''; ?> data-url-on="<?php echo htmlspecialchars($invoicesOnUrl, ENT_QUOTES); ?>" data-url-off="<?php echo htmlspecialchars($invoicesOffUrl, ENT_QUOTES); ?>">
								<label class="form-check-label small" for="toggleCurrentInvoices">Nur aktuelle</label>
							</div>
						</div>
						<div class="card-body">
							<div class="table-responsive">
								<table id="invoicesTable" class="table table-striped table-sm align-middle js-dt">
									<thead>
										<tr>
											<th>Nr.</th>
											<th>Status</th>
											<th>Datum</th>
											<th>Fällig</th>
											<th>Betrag</th>
											<th>Offen</th>
											<th>1CRM</th>
										</tr>
									</thead>
									<tbody>
										<?php foreach ($invoices as $row): ?>
											<tr>
												<td><?php echo htmlspecialchars(trim(($row['prefix'] ?? '') . ' ' . ($row['invoice_number'] ?? ''))); ?></td>
												<td><?php echo format_stage_badge('invoice', $row['shipping_stage'] ?? ''); ?></td>
												<td><?php echo htmlspecialchars($row['invoice_date'] ?? ''); ?></td>
												<td><?php echo htmlspecialchars($row['due_date'] ?? ''); ?></td>
												<td><?php echo isset($row['amount']) ? number_format((float)$row['amount'], 2, ',', '.') : ''; ?></td>
												<td><?php echo isset($row['amount_due']) ? number_format((float)$row['amount_due'], 2, ',', '.') : ''; ?></td>
												<td>
													<a class="btn btn-sm btn-outline-success" target="_blank" rel="noopener" href="<?php echo 'https://addinol-lubeoil.at/crm/?module=Invoice&action=DetailView&record=' . urlencode($row['id']); ?>">
														<i class="fas fa-external-link-alt"></i> Öffnen
													</a>
												</td>
											</tr>
										<?php endforeach; ?>
									</tbody>
								</table>
							</div>
						</div>
					</div>
				</div>
			</div>

			<div class="card shadow-sm mb-3">
				<div class="card-header py-2 d-flex align-items-center justify-content-between gap-2">
					<div><strong>Aufträge</strong> <span class="text-muted">(<?php echo count($salesOrders); ?>)</span></div>
					<div class="form-check form-switch mb-0">
						<?php $ordersOnUrl = build_detail_url($accountId, $onlyCurrentQuotes, $onlyCurrentInvoices, $onlyCurrentActivities, true, $onlyCurrentEmails); ?>
						<?php $ordersOffUrl = build_detail_url($accountId, $onlyCurrentQuotes, $onlyCurrentInvoices, $onlyCurrentActivities, false, $onlyCurrentEmails); ?>
						<input class="form-check-input current-toggle" type="checkbox" id="toggleCurrentOrders" <?php echo $onlyCurrentOrders ? 'checked' : ''; ?> data-url-on="<?php echo htmlspecialchars($ordersOnUrl, ENT_QUOTES); ?>" data-url-off="<?php echo htmlspecialchars($ordersOffUrl, ENT_QUOTES); ?>">
						<label class="form-check-label small" for="toggleCurrentOrders">Nur aktuelle</label>
					</div>
				</div>
				<div class="card-body">
					<div class="table-responsive">
						<table id="ordersTable" class="table table-striped table-sm align-middle js-dt">
							<thead>
								<tr>
									<th>Nr.</th>
									<th>Status</th>
									<th>Fällig</th>
									<th>Lieferdatum</th>
									<th>Betrag</th>
									<th>1CRM</th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ($salesOrders as $row): ?>
									<tr>
										<td><?php echo htmlspecialchars(trim(($row['prefix'] ?? '') . ' ' . ($row['so_number'] ?? ''))); ?></td>
										<td><?php echo format_stage_badge('order', $row['so_stage'] ?? ''); ?></td>
										<td><?php echo htmlspecialchars($row['due_date'] ?? ''); ?></td>
										<td><?php echo htmlspecialchars($row['delivery_date'] ?? ''); ?></td>
										<td><?php echo isset($row['amount']) ? number_format((float)$row['amount'], 2, ',', '.') : ''; ?></td>
										<td>
											<a class="btn btn-sm btn-outline-success" target="_blank" rel="noopener" href="<?php echo 'https://addinol-lubeoil.at/crm/?module=SalesOrders&action=DetailView&record=' . urlencode($row['id']); ?>">
												<i class="fas fa-external-link-alt"></i> Öffnen
											</a>
										</td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					</div>
				</div>
			</div>

			<div class="card shadow-sm mb-3">
				<div class="card-header py-2">
					<strong>Verkaufte Produkte</strong> <span class="text-muted">(<?php echo count($soldProducts); ?>)</span>
				</div>
				<div class="card-body">
					<div class="table-responsive">
						<table id="soldProductsTable" class="table table-striped table-sm align-middle js-dt">
							<thead>
								<tr>
									<th>Artikel</th>
									<th>Stück</th>
									<th>Umsatz</th>
									<th>Letzter Preis</th>
									<th>1CRM</th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ($soldProducts as $row): ?>
									<tr>
										<td><?php echo htmlspecialchars($row['name'] ?? ''); ?></td>
										<td><?php echo isset($row['qty_total']) ? number_format((float)$row['qty_total'], 2, ',', '.') : ''; ?></td>
										<td><?php echo isset($row['amount_total']) ? number_format((float)$row['amount_total'], 2, ',', '.') : ''; ?></td>
										<td><?php echo isset($row['unit_price_latest']) ? number_format((float)$row['unit_price_latest'], 2, ',', '.') : ''; ?></td>
										<td>
											<?php if (!empty($row['related_id'])): ?>
												<a class="btn btn-sm btn-outline-success" target="_blank" rel="noopener" href="<?php echo 'https://addinol-lubeoil.at/crm/?module=ProductCatalog&action=DetailView&record=' . urlencode($row['related_id']); ?>">
													<i class="fas fa-external-link-alt"></i> Öffnen
												</a>
											<?php endif; ?>
										</td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					</div>
				</div>
			</div>

			<div class="row g-3 mb-3">
				<div class="col-12 col-xxl-6">
					<div class="card shadow-sm h-100">
						<div class="card-header py-2 d-flex align-items-center justify-content-between gap-2">
							<div><strong>Aktivitäten</strong> <span class="text-muted">(<?php echo count($activities); ?>)</span></div>
							<div class="form-check form-switch mb-0">
								<?php $activitiesOnUrl = build_detail_url($accountId, $onlyCurrentQuotes, $onlyCurrentInvoices, true, $onlyCurrentOrders, $onlyCurrentEmails); ?>
								<?php $activitiesOffUrl = build_detail_url($accountId, $onlyCurrentQuotes, $onlyCurrentInvoices, false, $onlyCurrentOrders, $onlyCurrentEmails); ?>
								<input class="form-check-input current-toggle" type="checkbox" id="toggleCurrentActivities" <?php echo $onlyCurrentActivities ? 'checked' : ''; ?> data-url-on="<?php echo htmlspecialchars($activitiesOnUrl, ENT_QUOTES); ?>" data-url-off="<?php echo htmlspecialchars($activitiesOffUrl, ENT_QUOTES); ?>">
								<label class="form-check-label small" for="toggleCurrentActivities">Nur aktuelle</label>
							</div>
						</div>
						<div class="card-body">
							<div class="table-responsive">
								<table id="activitiesTable" class="table table-striped table-sm align-middle js-dt">
									<thead>
										<tr>
											<th>Typ</th>
											<th>Titel</th>
											<th>Status</th>
											<th>Datum</th>
											<th>Hinweis</th>
											<th>1CRM</th>
										</tr>
									</thead>
									<tbody>
										<?php foreach ($activities as $row): ?>
											<?php
												$type = (string)($row['activity_type'] ?? '');
												$module = $type === 'Meeting' ? 'Meetings' : ($type === 'Task' ? 'Tasks' : 'Calls');
											?>
											<tr>
												<td><?php echo htmlspecialchars($type); ?></td>
												<td><?php echo htmlspecialchars($row['name'] ?? ''); ?></td>
												<td><?php echo format_activity_status_badge($row['status'] ?? ''); ?></td>
												<td><?php echo htmlspecialchars($row['activity_date'] ?? ''); ?></td>
												<td><?php echo htmlspecialchars($row['activity_hint'] ?? ''); ?></td>
												<td>
													<a class="btn btn-sm btn-outline-success" target="_blank" rel="noopener" href="<?php echo 'https://addinol-lubeoil.at/crm/?module=' . urlencode($module) . '&action=DetailView&record=' . urlencode($row['id']); ?>">
														<i class="fas fa-external-link-alt"></i> Öffnen
													</a>
												</td>
											</tr>
										<?php endforeach; ?>
									</tbody>
								</table>
							</div>
						</div>
					</div>
				</div>
				<div class="col-12 col-xxl-6">
					<div class="card shadow-sm h-100">
						<div class="card-header py-2 d-flex align-items-center justify-content-between gap-2">
							<div><strong>E-Mail-Verlauf</strong> <span class="text-muted">(<?php echo count($emailHistory); ?>)</span></div>
							<div class="form-check form-switch mb-0">
								<?php $emailsOnUrl = build_detail_url($accountId, $onlyCurrentQuotes, $onlyCurrentInvoices, $onlyCurrentActivities, $onlyCurrentOrders, true); ?>
								<?php $emailsOffUrl = build_detail_url($accountId, $onlyCurrentQuotes, $onlyCurrentInvoices, $onlyCurrentActivities, $onlyCurrentOrders, false); ?>
								<input class="form-check-input current-toggle" type="checkbox" id="toggleCurrentEmails" <?php echo $onlyCurrentEmails ? 'checked' : ''; ?> data-url-on="<?php echo htmlspecialchars($emailsOnUrl, ENT_QUOTES); ?>" data-url-off="<?php echo htmlspecialchars($emailsOffUrl, ENT_QUOTES); ?>">
								<label class="form-check-label small" for="toggleCurrentEmails">Nur aktuelle</label>
							</div>
						</div>
						<div class="card-body">
							<div class="table-responsive">
								<table id="emailHistoryTable" class="table table-striped table-sm align-middle js-dt">
									<thead>
										<tr>
											<th>Datum</th>
											<th>Betreff</th>
											<th>Von</th>
											<th>Status</th>
											<th>Ordner</th>
											<th>1CRM</th>
										</tr>
									</thead>
									<tbody>
										<?php foreach ($emailHistory as $row): ?>
											<tr>
												<td><?php echo htmlspecialchars($row['date_start'] ?? ''); ?></td>
												<td><?php echo htmlspecialchars($row['name'] ?? ''); ?></td>
												<td><?php echo htmlspecialchars($row['from_addr'] ?? ''); ?></td>
												<td><?php echo format_email_status_badge($row['status'] ?? '', $row['type'] ?? ''); ?></td>
												<td><?php echo htmlspecialchars($row['folder_name'] ?? ''); ?></td>
												<td>
													<a class="btn btn-sm btn-outline-success" target="_blank" rel="noopener" href="<?php echo 'https://addinol-lubeoil.at/crm/?module=Emails&action=DetailView&record=' . urlencode($row['id']); ?>">
														<i class="fas fa-external-link-alt"></i> Öffnen
													</a>
												</td>
											</tr>
										<?php endforeach; ?>
									</tbody>
								</table>
							</div>
						</div>
					</div>
				</div>
			</div>

		<?php endif; ?>

	<?php else: ?>
		<div class="row g-3">
			<div class="col-12 col-lg-4 col-xl-3">
				<div class="card shadow-sm mb-3 note-fixed">
					<div class="card-header py-2"><strong>Eigene Notiz</strong></div>
					<div class="card-body d-flex flex-column">
						<?php if ($noteSaved): ?>
							<div class="alert alert-success py-2 mb-3">Notiz gespeichert.</div>
						<?php endif; ?>
						<?php if ($noteSaveError !== ''): ?>
							<div class="alert alert-danger py-2 mb-3"><?php echo htmlspecialchars($noteSaveError); ?></div>
						<?php endif; ?>
						<form method="post" class="d-flex flex-column flex-grow-1">
							<input type="hidden" name="action" value="save_local_note">
							<input type="hidden" name="q" value="<?php echo htmlspecialchars($q); ?>">
							<input type="hidden" name="account_type" value="<?php echo htmlspecialchars($accountType); ?>">
							<div class="btn-toolbar mb-2 gap-1" role="toolbar" aria-label="Notiz Toolbar">
								<button type="button" class="btn btn-sm btn-outline-secondary note-cmd" data-cmd="bold"><i class="fas fa-bold"></i></button>
								<button type="button" class="btn btn-sm btn-outline-secondary note-cmd" data-cmd="italic"><i class="fas fa-italic"></i></button>
								<button type="button" class="btn btn-sm btn-outline-secondary note-cmd" data-cmd="underline"><i class="fas fa-underline"></i></button>
								<button type="button" class="btn btn-sm btn-outline-secondary note-cmd" data-cmd="insertUnorderedList"><i class="fas fa-list-ul"></i></button>
								<button type="button" class="btn btn-sm btn-outline-secondary note-cmd" data-cmd="insertOrderedList"><i class="fas fa-list-ol"></i></button>
								<button type="button" class="btn btn-sm btn-outline-secondary note-cmd" data-cmd="createLink"><i class="fas fa-link"></i></button>
								<button type="button" class="btn btn-sm btn-outline-secondary note-cmd" data-cmd="removeFormat"><i class="fas fa-eraser"></i></button>
							</div>
							<div id="localNoteSurface" class="form-control flex-grow-1" contenteditable="true" style="overflow:auto;"><?php echo $localNoteHtml; ?></div>
							<textarea id="localNoteHtml" name="local_note_html" class="d-none"><?php echo htmlspecialchars($localNoteHtml); ?></textarea>
							<div class="mt-2">
								<button class="btn btn-sm btn-primary" type="submit">
									<i class="fas fa-save"></i> Speichern
								</button>
								<span class="text-muted small ms-2">Datei: logs/firmen-notiz.html</span>
							</div>
						</form>
					</div>
				</div>
				<div class="card shadow-sm appointments-fill">
					<div class="card-header py-2"><strong>Termine (nächste 14 Tage)</strong></div>
					<div class="card-body p-0 h-100">
						<iframe id="appointmentsFrame" title="Termine nächste 14 Tage" style="display:block;width:100%;height:100%;border:0;" src="termine14.php"></iframe>
					</div>
				</div>
			</div>
			<div class="col-12 col-lg-8 col-xl-9">
				<div class="card shadow-sm">
					<div class="card-header py-2">
						<strong>Firmenliste</strong> <span class="text-muted">(<?php echo count($firms); ?>)</span>
					</div>
					<div class="card-body">
						<form method="get" class="row g-2 align-items-end mb-3">
							<div class="col-sm-6 col-md-5 col-lg-5">
								<label class="form-label">Suche</label>
								<input type="text" name="q" value="<?php echo htmlspecialchars($q); ?>" class="form-control form-control-sm" placeholder="Name, Konto, Mail, Telefon, Ort">
							</div>
							<div class="col-sm-4 col-md-4 col-lg-3">
								<label class="form-label">Firmentyp</label>
								<select name="account_type" class="form-select form-select-sm">
									<?php foreach ($accountTypeOptions as $val => $label): ?>
										<option value="<?php echo htmlspecialchars($val); ?>" <?php echo $val === $accountType ? 'selected' : ''; ?>>
											<?php echo htmlspecialchars($label); ?>
										</option>
									<?php endforeach; ?>
								</select>
							</div>
							<div class="col-sm-2 col-md-2 col-lg-2">
								<button class="btn btn-sm btn-primary w-100" type="submit">Filter</button>
							</div>
						</form>
						<div class="table-responsive">
							<table id="firmsTable" class="table table-striped table-sm align-middle">
								<thead>
									<tr>
										<th>Konto</th>
										<th>Firma</th>
										<th>Ort</th>
										<th>Kontakt</th>
										<th>Saldo</th>
										<th>Details</th>
									</tr>
								</thead>
								<tbody>
									<?php foreach ($firms as $row): ?>
										<tr class="firm-row" data-account-id="<?php echo htmlspecialchars($row['id'] ?? '', ENT_QUOTES); ?>" data-account-name="<?php echo htmlspecialchars($row['name'] ?? '', ENT_QUOTES); ?>" data-account-ticker="<?php echo htmlspecialchars($row['ticker_symbol'] ?? '', ENT_QUOTES); ?>">
											<td><?php echo htmlspecialchars($row['ticker_symbol'] ?? ''); ?></td>
											<td>
												<div><?php echo htmlspecialchars($row['name'] ?? ''); ?></div>
												<div class="mt-1"><?php echo format_account_type_badge($row['account_type'] ?? ''); ?></div>
											</td>
											<td>
												<div><?php echo htmlspecialchars(trim(($row['shipping_address_postalcode'] ?? '') . ' ' . ($row['shipping_address_city'] ?? '') . ' ' . ($row['shipping_address_state'] ?? ''))); ?></div>
												<?php if (!empty($row['website'])): ?>
													<?php
														$website = trim((string)$row['website']);
														$websiteHref = preg_match('~^https?://~i', $website) ? $website : ('https://' . $website);
													?>
													<div class="mt-1"><a href="<?php echo htmlspecialchars($websiteHref); ?>" target="_blank" rel="noopener"><?php echo htmlspecialchars($website); ?></a></div>
												<?php endif; ?>
											</td>
											<td>
												<div><?php echo htmlspecialchars($row['phone_office'] ?? ''); ?></div>
												<?php if (!empty($row['email1'])): ?>
													<div class="mt-1"><a href="<?php echo 'mailto:' . htmlspecialchars($row['email1']); ?>"><?php echo htmlspecialchars($row['email1']); ?></a></div>
												<?php endif; ?>
											</td>
											<td><?php echo isset($row['balance']) ? number_format((float)$row['balance'], 2, ',', '.') : ''; ?></td>
											<td>
												<a class="btn btn-sm btn-outline-primary detail-link" href="<?php echo 'firmen.php?account_id=' . urlencode($row['id']); ?>">
													<i class="fas fa-eye"></i> Öffnen
												</a>
											</td>
										</tr>
									<?php endforeach; ?>
								</tbody>
							</table>
						</div>
					</div>
				</div>
			</div>
		</div>

		<div class="modal fade" id="firmQuickModal" tabindex="-1" aria-labelledby="firmQuickModalLabel" aria-hidden="true">
			<div class="modal-dialog modal-lg modal-dialog-scrollable">
				<div class="modal-content">
					<div class="modal-header">
						<h5 class="modal-title" id="firmQuickModalLabel">Auf Einen Blick</h5>
						<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Schließen"></button>
					</div>
					<div class="modal-body" id="firmQuickModalBody">
						<div class="text-muted">Lade Daten...</div>
					</div>
					<div class="modal-footer">
						<a href="#" class="btn btn-outline-primary" id="firmQuickModalOpenDetail">
							<i class="fas fa-eye"></i> Detailansicht öffnen
						</a>
						<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Schließen</button>
					</div>
				</div>
			</div>
		</div>
	<?php endif; ?>
</div>

<script src="../assets/datatables/jquery.min.js"></script>
<script src="../assets/datatables/jquery.dataTables.min.js"></script>
<script src="../assets/datatables/dataTables.bootstrap5.min.js"></script>
<script src="../assets/bootstrap/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
	const dtLang = { url: 'https://cdn.datatables.net/plug-ins/1.13.7/i18n/de-DE.json' };
	if (document.getElementById('firmsTable')) {
		$('#firmsTable').DataTable({
			order: [[1, 'asc']],
			pageLength: 25,
			language: dtLang
		});
	}
	if (document.getElementById('contactsTable')) {
		$('#contactsTable').DataTable({
			order: [[0, 'asc']],
			pageLength: 25,
			language: dtLang
		});
	}
	if (document.getElementById('quotesTable')) {
		$('#quotesTable').DataTable({
			order: [[2, 'desc']],
			pageLength: 25,
			language: dtLang
		});
	}
	if (document.getElementById('ordersTable')) {
		$('#ordersTable').DataTable({
			order: [[2, 'desc']],
			pageLength: 25,
			language: dtLang
		});
	}
	if (document.getElementById('invoicesTable')) {
		$('#invoicesTable').DataTable({
			order: [[2, 'desc']],
			pageLength: 25,
			language: dtLang
		});
	}
	if (document.getElementById('soldProductsTable')) {
		$('#soldProductsTable').DataTable({
			order: [[1, 'desc']],
			pageLength: 25,
			language: dtLang
		});
	}
	if (document.getElementById('activitiesTable')) {
		$('#activitiesTable').DataTable({
			order: [[3, 'desc']],
			pageLength: 25,
			language: dtLang
		});
	}
	if (document.getElementById('emailHistoryTable')) {
		$('#emailHistoryTable').DataTable({
			order: [[0, 'desc']],
			pageLength: 25,
			language: dtLang
		});
	}
	if (document.getElementById('firmsTable')) {
		const modalEl = document.getElementById('firmQuickModal');
		const modalBodyEl = document.getElementById('firmQuickModalBody');
		const modalTitleEl = document.getElementById('firmQuickModalLabel');
		const modalOpenDetailEl = document.getElementById('firmQuickModalOpenDetail');
		const modal = modalEl ? new bootstrap.Modal(modalEl) : null;

		const escapeHtml = (value) => String(value ?? '')
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;')
			.replace(/'/g, '&#039;');

		const renderList = (items, rowBuilder, emptyText) => {
			if (!items || !items.length) {
				return '<div class="text-muted small">' + escapeHtml(emptyText) + '</div>';
			}
			return '<ul class="list-group list-group-flush">' + items.map((item) => rowBuilder(item)).join('') + '</ul>';
		};

		document.querySelectorAll('#firmsTable tbody tr.firm-row').forEach((rowEl) => {
			rowEl.addEventListener('click', async (ev) => {
				const target = ev.target;
				if (target && target.closest('a, button, input, select, textarea, label')) {
					return;
				}
				const accountId = rowEl.getAttribute('data-account-id') || '';
				const accountName = rowEl.getAttribute('data-account-name') || '';
				const accountTicker = rowEl.getAttribute('data-account-ticker') || '';
				if (!accountId || !modal || !modalBodyEl || !modalTitleEl || !modalOpenDetailEl) {
					return;
				}

				modalTitleEl.textContent = 'Auf Einen Blick: ' + accountTicker + ' ' + accountName;
				modalBodyEl.innerHTML = '<div class="text-muted">Lade Daten...</div>';
				modalOpenDetailEl.setAttribute('href', 'firmen.php?account_id=' + encodeURIComponent(accountId));
				modal.show();

				try {
					const url = 'firmen.php?overview_json=1&account_id=' + encodeURIComponent(accountId);
					const response = await fetch(url, { credentials: 'same-origin' });
					const data = await response.json();
					if (!response.ok || !data || !data.ok || !data.overview) {
						throw new Error('invalid_response');
					}

					const overview = data.overview;
					const openBlocks = [];
					if ((overview.open_quotes || 0) > 0) {
						openBlocks.push('<div class="col-sm-4"><div class="p-2 border rounded bg-light"><div class="small text-muted">Offene Angebote</div><div class="h5 mb-0">' + escapeHtml(overview.open_quotes) + '</div></div></div>');
					}
					if ((overview.open_orders || 0) > 0) {
						openBlocks.push('<div class="col-sm-4"><div class="p-2 border rounded bg-light"><div class="small text-muted">Offene Aufträge</div><div class="h5 mb-0">' + escapeHtml(overview.open_orders) + '</div></div></div>');
					}
					if ((overview.open_invoices || 0) > 0) {
						openBlocks.push('<div class="col-sm-4"><div class="p-2 border rounded bg-light"><div class="small text-muted">Offene Rechnungen</div><div class="h5 mb-0">' + escapeHtml(overview.open_invoices) + '</div></div></div>');
					}

					const appointmentsHtml = renderList(
						overview.next_appointments || [],
						(item) => '<li class="list-group-item px-0 py-1"><div class="small"><strong>' + escapeHtml(item.date_start || '') + '</strong><br>' + escapeHtml(item.name || '') + ' <span class="text-muted">(' + escapeHtml(item.status || '') + ')</span></div></li>',
						'Keine zukünftigen Termine.'
					);
					const productsHtml = renderList(
						overview.latest_quote_products || [],
						(item) => '<li class="list-group-item px-0 py-1"><div class="small">' + escapeHtml(item.name || '') + '<br><span class="text-muted">' + escapeHtml(item.last_offered_at || '') + '</span></div></li>',
						'Keine Daten.'
					);
						const notesHtml = renderList(
							overview.latest_notes || [],
							(item) => {
								const title = item.name ? '<strong>' + escapeHtml(item.name) + '</strong><br>' : '';
								const rawDesc = (item.description || '').replace(/\s+/g, ' ').trim();
								const shortDesc = rawDesc.length > 180 ? (rawDesc.slice(0, 180) + '...') : rawDesc;
								return '<li class="list-group-item px-0 py-1"><div class="small">' + title + escapeHtml(shortDesc) + '<br><span class="text-muted">' + escapeHtml(item.date_modified || '') + '</span></div></li>';
							},
							'Keine Notizen.'
						);

					modalBodyEl.innerHTML =
						(openBlocks.length ? '<div class="row g-2 mb-3">' + openBlocks.join('') + '</div>' : '') +
						'<div class="row g-3">' +
						'<div class="col-12 col-md-4"><div class="small fw-semibold mb-1">Nächste Termine</div>' + appointmentsHtml + '</div>' +
						'<div class="col-12 col-md-4"><div class="small fw-semibold mb-1">Zuletzt angebotene Produkte</div>' + productsHtml + '</div>' +
						'<div class="col-12 col-md-4"><div class="small fw-semibold mb-1">Letzte Notizen</div>' + notesHtml + '</div>' +
						'</div>';
				} catch (e) {
					modalBodyEl.innerHTML = '<div class="alert alert-warning py-2 mb-0">Daten konnten nicht geladen werden.</div>';
				}
			});
		});
	}
	document.querySelectorAll('.current-toggle').forEach((toggle) => {
		toggle.addEventListener('change', () => {
			const targetUrl = toggle.checked ? toggle.getAttribute('data-url-on') : toggle.getAttribute('data-url-off');
			if (targetUrl) {
				window.location.href = targetUrl;
			}
		});
	});
	const noteSurface = document.getElementById('localNoteSurface');
	const noteHtmlField = document.getElementById('localNoteHtml');
	if (noteSurface && noteHtmlField) {
		document.querySelectorAll('.note-cmd').forEach((btn) => {
			btn.addEventListener('click', () => {
				const cmd = btn.getAttribute('data-cmd');
				if (!cmd) {
					return;
				}
				noteSurface.focus();
				if (cmd === 'createLink') {
					const url = window.prompt('URL eingeben (https://...)');
					if (!url) {
						return;
					}
					document.execCommand('createLink', false, url);
					return;
				}
				document.execCommand(cmd, false, null);
			});
		});
		const form = noteSurface.closest('form');
		if (form) {
			form.addEventListener('submit', () => {
				noteHtmlField.value = noteSurface.innerHTML;
			});
		}
	}
});
</script>
</body>
</html>
