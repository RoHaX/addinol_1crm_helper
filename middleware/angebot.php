<?php
require_once __DIR__ . '/../db.inc.php';

$mysqli = $mysqli ?? null;
if (!$mysqli instanceof mysqli) {
	die('DB connection missing');
}
$mysqli->set_charset('utf8');

function doc_fetch_all(mysqli $db, string $sql, string $types = '', array $params = []): array {
	$out = [];
	$stmt = $db->prepare($sql);
	if (!$stmt) {
		return $out;
	}
	if ($types !== '' && $params) {
		$stmt->bind_param($types, ...$params);
	}
	if (!$stmt->execute()) {
		$stmt->close();
		return $out;
	}
	$res = $stmt->get_result();
	while ($res && ($row = $res->fetch_assoc())) {
		$out[] = $row;
	}
	$stmt->close();
	return $out;
}

function doc_email_status_de(?string $status, ?string $type): string {
	$statusRaw = trim((string)$status);
	$typeRaw = trim((string)$type);
	$s = strtolower($statusRaw);
	$t = strtolower($typeRaw);
	if ($s === 'sent' || $t === 'outbound') {
		return 'Gesendet';
	}
	if ($s === 'received' || $t === 'inbound') {
		return 'Empfangen';
	}
	if ($s === 'draft') {
		return 'Entwurf';
	}
	if ($s === 'archived') {
		return 'Archiviert';
	}
	if ($s === 'read') {
		return 'Gelesen';
	}
	if ($s === 'unread') {
		return 'Ungelesen';
	}
	if ($s === 'pick') {
		return 'Zuordnen';
	}
	if ($statusRaw === '' && $typeRaw === '') {
		return '-';
	}
	return trim($statusRaw . ($statusRaw !== '' && $typeRaw !== '' ? ' / ' : '') . $typeRaw);
}

function doc_email_recipient_label(array $mail): string {
	$toNames = trim((string)($mail['to_addrs_names'] ?? ''));
	if ($toNames !== '') {
		return $toNames;
	}
	$toAddrs = trim((string)($mail['to_addrs'] ?? ''));
	if ($toAddrs !== '') {
		return $toAddrs;
	}
	$contact = trim((string)($mail['contact_name'] ?? ''));
	if ($contact !== '') {
		return $contact;
	}
	return '-';
}

function doc_format_address(array $row, string $prefix): string {
	$street = trim((string)($row[$prefix . 'street'] ?? ''));
	$postal = trim((string)($row[$prefix . 'postalcode'] ?? ''));
	$city = trim((string)($row[$prefix . 'city'] ?? ''));
	$state = trim((string)($row[$prefix . 'state'] ?? ''));
	$country = trim((string)($row[$prefix . 'country'] ?? ''));
	$line2 = trim($postal . ' ' . $city);
	$parts = array_filter([$street, $line2, $state, $country], static fn($v) => $v !== '');
	if (!$parts) {
		return '-';
	}
	return implode("\n", $parts);
}

function doc_fetch_linked_emails(mysqli $db, array $quoteIds, array $orderIds, array $invoiceIds, array $purchaseOrderIds): array {
	$sanitize = static function (array $ids): array {
		$out = [];
		foreach ($ids as $id) {
			$id = trim((string)$id);
			if ($id !== '' && preg_match('/^[a-f0-9-]{36}$/i', $id)) {
				$out[] = $id;
			}
		}
		return array_values(array_unique($out));
	};
	$toIn = static function (mysqli $db, array $ids): string {
		return "'" . implode("','", array_map([$db, 'real_escape_string'], $ids)) . "'";
	};

	$quoteIds = $sanitize($quoteIds);
	$orderIds = $sanitize($orderIds);
	$invoiceIds = $sanitize($invoiceIds);
	$purchaseOrderIds = $sanitize($purchaseOrderIds);

	$where = [];
	if ($quoteIds) {
		$in = $toIn($db, $quoteIds);
		$where[] = "(eq.quote_id IN ($in) OR (e.parent_type = 'Quotes' AND e.parent_id IN ($in)))";
	}
	if ($orderIds) {
		$in = $toIn($db, $orderIds);
		$where[] = "(es.so_id IN ($in) OR (e.parent_type = 'SalesOrders' AND e.parent_id IN ($in)))";
	}
	if ($invoiceIds) {
		$in = $toIn($db, $invoiceIds);
		$where[] = "(ei.invoice_id IN ($in) OR (e.parent_type = 'Invoice' AND e.parent_id IN ($in)))";
	}
	if ($purchaseOrderIds) {
		$in = $toIn($db, $purchaseOrderIds);
		$where[] = "(ep.po_id IN ($in) OR (e.parent_type = 'PurchaseOrders' AND e.parent_id IN ($in)))";
	}
	if (!$where) {
		return [];
	}

	$sql = "SELECT DISTINCT e.id, e.name, e.date_start, e.from_addr, e.status, e.type, f.name AS folder_name,
	               e.to_addrs_names, e.to_addrs, TRIM(CONCAT(COALESCE(c.first_name,''), ' ', COALESCE(c.last_name,''))) AS contact_name
	        FROM emails e
	        LEFT JOIN emails_folders f ON f.id = e.folder AND f.deleted = 0
	        LEFT JOIN contacts c ON c.id = e.contact_id AND c.deleted = 0
	        LEFT JOIN emails_quotes eq ON eq.email_id = e.id AND eq.deleted = 0
	        LEFT JOIN emails_salesorders es ON es.email_id = e.id AND es.deleted = 0
	        LEFT JOIN emails_invoices ei ON ei.email_id = e.id AND ei.deleted = 0
	        LEFT JOIN emails_purchaseorders ep ON ep.email_id = e.id AND ep.deleted = 0
	        WHERE e.deleted = 0 AND (" . implode(' OR ', $where) . ")
	        ORDER BY COALESCE(e.date_start, e.date_entered) DESC, e.date_entered DESC
	        LIMIT 100";
	return doc_fetch_all($db, $sql);
}

function quote_stage_badge(?string $stage): string {
	$raw = trim((string)$stage);
	$key = strtolower($raw);
	$map = [
		'draft' => ['Entwurf', 'fas fa-pencil-alt', 'secondary'],
		'negotiation' => ['Verhandlung', 'fas fa-comments', 'warning'],
		'on hold' => ['Pausiert', 'fas fa-pause-circle', 'secondary'],
		'closed accepted' => ['Angenommen', 'fas fa-check-circle', 'success'],
		'closed lost' => ['Verloren', 'fas fa-times-circle', 'danger'],
		'closed dead' => ['Abgeschlossen', 'fas fa-ban', 'dark'],
		'delivered' => ['Zugestellt', 'fas fa-truck', 'success'],
	];
	$label = $raw !== '' ? $raw : '-';
	$icon = 'fas fa-tag';
	$class = 'secondary';
	if (isset($map[$key])) {
		$label = $map[$key][0];
		$icon = $map[$key][1];
		$class = $map[$key][2];
	}
	return '<span class="badge text-bg-' . $class . '"><i class="' . $icon . ' me-1"></i>' . htmlspecialchars($label) . '</span>';
}

$quoteId = trim((string)($_GET['quote_id'] ?? ''));
$quote = null;
$quoteLines = [];
$linkedQuotes = [];
$linkedOrders = [];
$linkedInvoices = [];
$linkedPurchaseOrders = [];
$linkedEmails = [];

if ($quoteId !== '') {
	$rows = doc_fetch_all(
		$mysqli,
		"SELECT q.id, q.prefix, q.quote_number, q.name, q.valid_until, q.quote_stage, q.amount, q.subtotal, q.pretax,
		        q.billing_account_id, q.purchase_order_num, q.description, q.sales_order_id,
		        q.billing_address_street, q.billing_address_postalcode, q.billing_address_city, q.billing_address_state, q.billing_address_country,
		        q.shipping_address_street, q.shipping_address_postalcode, q.shipping_address_city, q.shipping_address_state, q.shipping_address_country,
		        a.name AS account_name
		 FROM quotes q
		 LEFT JOIN accounts a ON a.id = q.billing_account_id AND a.deleted = 0
		 WHERE q.deleted = 0 AND q.id = ?
		 LIMIT 1",
		's',
		[$quoteId]
	);
	$quote = $rows[0] ?? null;

	if ($quote) {
		$linkedQuotes[(string)$quote['id']] = $quote;
		$quoteLines = doc_fetch_all(
			$mysqli,
			"SELECT id, line_group_id, position, name, quantity, unit_price, ext_price, net_price
			 FROM quote_lines
			 WHERE deleted = 0 AND quote_id = ?
			 ORDER BY line_group_id ASC, position ASC, date_entered ASC",
			's',
			[$quoteId]
		);

		$orderIds = [];
		$invoiceIds = [];

		if (!empty($quote['sales_order_id'])) {
			$orderIds[] = (string)$quote['sales_order_id'];
		}

		$orderRows = doc_fetch_all(
			$mysqli,
			"SELECT id, prefix, so_number, name, so_stage
			 FROM sales_orders
			 WHERE deleted = 0 AND (related_quote_id = ? OR id = ?)",
			'ss',
			[$quoteId, (string)($quote['sales_order_id'] ?? '')]
		);
		foreach ($orderRows as $row) {
			$orderIds[] = (string)$row['id'];
			$linkedOrders[(string)$row['id']] = $row;
		}
		$orderIds = array_values(array_unique(array_filter($orderIds)));

		$invoiceRows = doc_fetch_all(
			$mysqli,
			"SELECT id, prefix, invoice_number, name, shipping_stage, from_so_id
			 FROM invoice
			 WHERE deleted = 0 AND (from_quote_id = ?" . (!empty($orderIds) ? " OR from_so_id IN ('" . implode("','", array_map([$mysqli, 'real_escape_string'], $orderIds)) . "')" : "") . ")",
			's',
			[$quoteId]
		);
		foreach ($invoiceRows as $row) {
			$invoiceIds[] = (string)$row['id'];
			$linkedInvoices[(string)$row['id']] = $row;
			if (!empty($row['from_so_id'])) {
				$orderIds[] = (string)$row['from_so_id'];
			}
		}
		$orderIds = array_values(array_unique(array_filter($orderIds)));
		$invoiceIds = array_values(array_unique(array_filter($invoiceIds)));

		if (!empty($orderIds) || !empty($invoiceIds)) {
			$whereParts = [];
			if (!empty($orderIds)) {
				$whereParts[] = "from_so_id IN ('" . implode("','", array_map([$mysqli, 'real_escape_string'], $orderIds)) . "')";
			}
			if (!empty($invoiceIds)) {
				$whereParts[] = "related_invoice_id IN ('" . implode("','", array_map([$mysqli, 'real_escape_string'], $invoiceIds)) . "')";
			}
			$sql = "SELECT id, prefix, po_number, name, shipping_stage
			        FROM purchase_orders
			        WHERE deleted = 0 AND (" . implode(' OR ', $whereParts) . ")";
			$poRows = doc_fetch_all($mysqli, $sql);
			foreach ($poRows as $row) {
				$linkedPurchaseOrders[(string)$row['id']] = $row;
			}
		}

		$linkedEmails = doc_fetch_linked_emails(
			$mysqli,
			array_keys($linkedQuotes),
			array_keys($linkedOrders),
			array_keys($linkedInvoices),
			array_keys($linkedPurchaseOrders)
		);
	}
}

$quoteCode = trim((string)($quote['prefix'] ?? '') . (string)($quote['quote_number'] ?? ''));
?>
<!doctype html>
<html lang="de">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title><?php echo $quoteCode !== '' ? htmlspecialchars($quoteCode) . ' - Angebot Detail' : 'Angebot Detail'; ?></title>
	<link href="../styles.css" rel="stylesheet" type="text/css">
	<link href="../assets/bootstrap/bootstrap.min.css" rel="stylesheet">
	<link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.5.0/css/all.css" crossorigin="anonymous">
</head>
<body class="bg-light">
	<?php require_once __DIR__ . '/../navbar.php'; ?>

	<main class="container-fluid py-3">
		<div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
			<h1 class="h4 mb-0"><i class="fas fa-file-signature me-2"></i>Angebot Detail</h1>
			<form class="d-flex gap-2" method="get" action="angebot.php">
				<input type="text" class="form-control form-control-sm" name="quote_id" placeholder="Quote-ID" value="<?php echo htmlspecialchars($quoteId); ?>">
				<button type="submit" class="btn btn-sm btn-outline-primary">Öffnen</button>
			</form>
		</div>

		<?php if ($quoteId === ''): ?>
			<div class="alert alert-info">Bitte ein Angebot öffnen (z. B. aus Firmen oder Schnellsuche).</div>
		<?php elseif (!$quote): ?>
			<div class="alert alert-warning">Kein Angebot mit dieser ID gefunden.</div>
		<?php else: ?>
			<div class="card shadow-sm mb-3">
				<div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
					<div>
						<strong><?php echo htmlspecialchars($quoteCode); ?></strong>
						<span class="ms-2"><?php echo quote_stage_badge($quote['quote_stage'] ?? ''); ?></span>
					</div>
					<div class="d-flex gap-2">
						<a class="btn btn-sm btn-outline-secondary" href="firmen.php?account_id=<?php echo urlencode((string)$quote['billing_account_id']); ?>">
							<i class="fas fa-building me-1"></i> Firma
						</a>
						<a class="btn btn-sm btn-success" target="_blank" rel="noopener" href="<?php echo 'https://addinol-lubeoil.at/crm/?module=Quotes&action=DetailView&record=' . urlencode((string)$quote['id']); ?>">
							<i class="fas fa-external-link-alt me-1"></i> Im CRM öffnen
						</a>
					</div>
				</div>
				<div class="card-body">
					<div class="row g-3">
						<div class="col-12 col-lg-6">
							<table class="table table-sm table-striped align-middle mb-0">
								<tbody>
									<tr><th style="width: 180px;">Gültig bis</th><td><?php echo htmlspecialchars((string)$quote['valid_until']); ?></td></tr>
									<tr><th>Name</th><td><?php echo htmlspecialchars((string)$quote['name']); ?></td></tr>
									<tr><th>Bestellnummer</th><td><?php echo htmlspecialchars((string)($quote['purchase_order_num'] ?? '')); ?></td></tr>
									<tr><th>Firma</th><td><?php echo htmlspecialchars((string)($quote['account_name'] ?? '')); ?></td></tr>
									<tr><th>Rechnungsadresse</th><td><?php echo nl2br(htmlspecialchars(doc_format_address($quote, 'billing_address_'))); ?></td></tr>
									<tr><th>Lieferadresse</th><td><?php echo nl2br(htmlspecialchars(doc_format_address($quote, 'shipping_address_'))); ?></td></tr>
								</tbody>
							</table>
						</div>
						<div class="col-12 col-lg-6">
							<table class="table table-sm table-striped align-middle mb-0">
								<tbody>
									<tr><th style="width: 180px;">Betrag</th><td><?php echo number_format((float)$quote['amount'], 2, ',', '.'); ?></td></tr>
									<tr><th>Netto</th><td><?php echo number_format((float)$quote['subtotal'], 2, ',', '.'); ?></td></tr>
									<tr><th>Steuerbar</th><td><?php echo number_format((float)$quote['pretax'], 2, ',', '.'); ?></td></tr>
									<tr><th>Auftrag</th><td><?php echo htmlspecialchars((string)($quote['sales_order_id'] ?? '')); ?></td></tr>
								</tbody>
							</table>
						</div>
					</div>
					<?php if (trim((string)($quote['description'] ?? '')) !== ''): ?>
						<hr>
						<div class="small text-muted mb-1">Beschreibung</div>
						<div><?php echo nl2br(htmlspecialchars((string)$quote['description'])); ?></div>
					<?php endif; ?>
				</div>
			</div>

			<div class="card shadow-sm">
				<div class="card-header"><strong>Positionen</strong> <span class="text-muted">(<?php echo count($quoteLines); ?>)</span></div>
				<div class="card-body">
					<div class="table-responsive">
						<table class="table table-sm table-striped align-middle">
							<thead>
								<tr>
									<th>#</th>
									<th>Bezeichnung</th>
									<th class="text-end">Menge</th>
									<th class="text-end">Einzelpreis</th>
									<th class="text-end">Gesamt</th>
									<th class="text-end">Netto</th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ($quoteLines as $idx => $line): ?>
									<tr>
										<td><?php echo (int)$idx + 1; ?></td>
										<td><?php echo htmlspecialchars((string)($line['name'] ?? '')); ?></td>
										<td class="text-end"><?php echo isset($line['quantity']) ? number_format((float)$line['quantity'], 2, ',', '.') : ''; ?></td>
										<td class="text-end"><?php echo isset($line['unit_price']) ? number_format((float)$line['unit_price'], 2, ',', '.') : ''; ?></td>
										<td class="text-end"><?php echo isset($line['ext_price']) ? number_format((float)$line['ext_price'], 2, ',', '.') : ''; ?></td>
										<td class="text-end"><?php echo isset($line['net_price']) ? number_format((float)$line['net_price'], 2, ',', '.') : ''; ?></td>
									</tr>
								<?php endforeach; ?>
								<?php if (!$quoteLines): ?>
									<tr><td colspan="6" class="text-muted">Keine Positionen gefunden.</td></tr>
								<?php endif; ?>
							</tbody>
						</table>
					</div>
				</div>
			</div>

			<div class="card shadow-sm mt-3">
				<div class="card-header"><strong>Verknüpfte Belege</strong></div>
				<div class="card-body">
					<div class="table-responsive">
						<table class="table table-sm table-striped align-middle mb-0">
							<thead>
								<tr><th>Typ</th><th>Nummer</th><th>Name</th><th>Detail</th></tr>
							</thead>
							<tbody>
								<?php foreach ($linkedQuotes as $row): ?>
									<tr><td>AN</td><td><?php echo htmlspecialchars(trim((string)($row['prefix'] ?? '') . (string)($row['quote_number'] ?? ''))); ?></td><td><?php echo htmlspecialchars((string)($row['name'] ?? '')); ?></td><td><a class="btn btn-sm btn-outline-primary" href="<?php echo 'angebot.php?quote_id=' . urlencode((string)$row['id']); ?>">Öffnen</a></td></tr>
								<?php endforeach; ?>
								<?php foreach ($linkedOrders as $row): ?>
									<tr><td>AB</td><td><?php echo htmlspecialchars(trim((string)($row['prefix'] ?? '') . (string)($row['so_number'] ?? ''))); ?></td><td><?php echo htmlspecialchars((string)($row['name'] ?? '')); ?></td><td><a class="btn btn-sm btn-outline-primary" href="<?php echo 'auftrag.php?sales_order_id=' . urlencode((string)$row['id']); ?>">Öffnen</a></td></tr>
								<?php endforeach; ?>
								<?php foreach ($linkedInvoices as $row): ?>
									<tr><td>RE</td><td><?php echo htmlspecialchars(trim((string)($row['prefix'] ?? '') . (string)($row['invoice_number'] ?? ''))); ?></td><td><?php echo htmlspecialchars((string)($row['name'] ?? '')); ?></td><td><a class="btn btn-sm btn-outline-primary" href="<?php echo 'rechnung.php?invoice_id=' . urlencode((string)$row['id']); ?>">Öffnen</a></td></tr>
								<?php endforeach; ?>
								<?php foreach ($linkedPurchaseOrders as $row): ?>
									<tr><td>BE</td><td><?php echo htmlspecialchars(trim((string)($row['prefix'] ?? '') . (string)($row['po_number'] ?? ''))); ?></td><td><?php echo htmlspecialchars((string)($row['name'] ?? '')); ?></td><td><a class="btn btn-sm btn-outline-primary" href="<?php echo 'bestellung.php?purchase_order_id=' . urlencode((string)$row['id']); ?>">Öffnen</a></td></tr>
								<?php endforeach; ?>
								<?php if (!$linkedQuotes && !$linkedOrders && !$linkedInvoices && !$linkedPurchaseOrders): ?>
									<tr><td colspan="4" class="text-muted">Keine verknüpften Belege gefunden.</td></tr>
								<?php endif; ?>
							</tbody>
						</table>
					</div>
				</div>
			</div>

			<div class="card shadow-sm mt-3">
				<div class="card-header"><strong>Verknüpfte E-Mails</strong> <span class="text-muted">(<?php echo count($linkedEmails); ?>)</span></div>
				<div class="card-body">
					<div class="table-responsive">
						<table class="table table-sm table-striped align-middle mb-0">
							<thead>
								<tr><th>Datum</th><th>Betreff</th><th>Von</th><th>Empfänger/Kontakt</th><th>Status</th><th>Ordner</th><th>CRM</th></tr>
							</thead>
							<tbody>
								<?php foreach ($linkedEmails as $mail): ?>
									<tr>
										<td><?php echo htmlspecialchars((string)($mail['date_start'] ?? '')); ?></td>
										<td><?php echo htmlspecialchars((string)($mail['name'] ?? '')); ?></td>
										<td><?php echo htmlspecialchars((string)($mail['from_addr'] ?? '')); ?></td>
										<td><?php echo htmlspecialchars(doc_email_recipient_label($mail)); ?></td>
										<td><?php echo htmlspecialchars(doc_email_status_de($mail['status'] ?? '', $mail['type'] ?? '')); ?></td>
										<td><?php echo htmlspecialchars((string)($mail['folder_name'] ?? '')); ?></td>
										<td><a class="btn btn-sm btn-outline-success" target="_blank" rel="noopener" href="<?php echo 'https://addinol-lubeoil.at/crm/?module=Emails&action=DetailView&record=' . urlencode((string)$mail['id']); ?>">Öffnen</a></td>
									</tr>
								<?php endforeach; ?>
								<?php if (!$linkedEmails): ?>
									<tr><td colspan="7" class="text-muted">Keine verknüpften E-Mails gefunden.</td></tr>
								<?php endif; ?>
							</tbody>
						</table>
					</div>
				</div>
			</div>
		<?php endif; ?>
	</main>

	<script src="../assets/bootstrap/bootstrap.bundle.min.js"></script>
</body>
</html>
