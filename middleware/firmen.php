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

	$q = trim($_GET['q'] ?? '');
	$accountType = trim($_GET['account_type'] ?? '');
	$accountId = trim($_GET['account_id'] ?? '');
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
				 WHERE deleted = 0 AND billing_account_id = ?
				 ORDER BY valid_until DESC, quote_number DESC
				 LIMIT 500",
				's',
				[$accountId]
			);

			$salesOrders = fetch_all_assoc(
				$mysqli,
				"SELECT id, prefix, so_number, so_stage, due_date, delivery_date, amount
				 FROM sales_orders
				 WHERE deleted = 0 AND billing_account_id = ?
				 ORDER BY due_date DESC, so_number DESC
				 LIMIT 500",
				's',
				[$accountId]
			);

			$invoices = fetch_all_assoc(
				$mysqli,
				"SELECT id, prefix, invoice_number, shipping_stage, invoice_date, due_date, amount, amount_due
				 FROM invoice
				 WHERE deleted = 0 AND billing_account_id = ?
				 ORDER BY invoice_date DESC, invoice_number DESC
				 LIMIT 500",
				's',
				[$accountId]
			);
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
					<div class="card shadow-sm h-100">
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
						<div class="card-header py-2"><strong>Angebote</strong> <span class="text-muted">(<?php echo count($quotes); ?>)</span></div>
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
						<div class="card-header py-2"><strong>Rechnungen</strong> <span class="text-muted">(<?php echo count($invoices); ?>)</span></div>
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
				<div class="card-header py-2"><strong>Aufträge</strong> <span class="text-muted">(<?php echo count($salesOrders); ?>)</span></div>
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
										<tr>
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
												<a class="btn btn-sm btn-outline-primary" href="<?php echo 'firmen.php?account_id=' . urlencode($row['id']); ?>">
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
