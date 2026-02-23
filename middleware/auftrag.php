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

function so_stage_badge(?string $stage): string {
	$raw = trim((string)$stage);
	$key = strtolower($raw);
	$map = [
		'ordered' => ['Beauftragt', 'fas fa-clipboard-check', 'primary'],
		'delivered' => ['Geliefert', 'fas fa-truck', 'success'],
		'pending' => ['Offen', 'fas fa-hourglass-half', 'warning'],
		'shipped' => ['Versendet', 'fas fa-shipping-fast', 'info'],
		'partially shipped' => ['Teilversendet', 'fas fa-shipping-fast', 'info'],
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
	return '<span class="badge text-bg-' . $class . '"><i class="' . $icon . ' me-1"></i>' . htmlspecialchars($label) . '</span>';
}

$salesOrderId = trim((string)($_GET['sales_order_id'] ?? ''));
$salesOrder = null;
$salesOrderLines = [];
$linkedQuotes = [];
$linkedOrders = [];
$linkedInvoices = [];
$linkedPurchaseOrders = [];
$linkedEmails = [];

if ($salesOrderId !== '') {
	$rows = doc_fetch_all(
		$mysqli,
		"SELECT s.id, s.prefix, s.so_number, s.name, s.so_stage, s.due_date, s.delivery_date,
		        s.amount, s.subtotal, s.pretax, s.billing_account_id, s.purchase_order_num,
		        s.description, s.related_quote_id,
		        s.billing_address_street, s.billing_address_postalcode, s.billing_address_city, s.billing_address_state, s.billing_address_country,
		        s.shipping_address_street, s.shipping_address_postalcode, s.shipping_address_city, s.shipping_address_state, s.shipping_address_country,
		        a.name AS account_name
		 FROM sales_orders s
		 LEFT JOIN accounts a ON a.id = s.billing_account_id AND a.deleted = 0
		 WHERE s.deleted = 0 AND s.id = ?
		 LIMIT 1",
		's',
		[$salesOrderId]
	);
	$salesOrder = $rows[0] ?? null;

	if ($salesOrder) {
		$linkedOrders[(string)$salesOrder['id']] = $salesOrder;
		$salesOrderLines = doc_fetch_all(
			$mysqli,
			"SELECT id, line_group_id, position, name, quantity, unit_price, ext_price, net_price
			 FROM sales_order_lines
			 WHERE deleted = 0 AND sales_orders_id = ?
			 ORDER BY line_group_id ASC, position ASC, date_entered ASC",
			's',
			[$salesOrderId]
		);

		$quoteId = trim((string)($salesOrder['related_quote_id'] ?? ''));
		if ($quoteId !== '') {
			$qRows = doc_fetch_all(
				$mysqli,
				"SELECT id, prefix, quote_number, name, quote_stage
				 FROM quotes
				 WHERE deleted = 0 AND id = ?
				 LIMIT 1",
				's',
				[$quoteId]
			);
			foreach ($qRows as $row) {
				$linkedQuotes[(string)$row['id']] = $row;
			}
		}

		$invRows = doc_fetch_all(
			$mysqli,
			"SELECT id, prefix, invoice_number, name, shipping_stage
			 FROM invoice
			 WHERE deleted = 0 AND from_so_id = ?",
			's',
			[$salesOrderId]
		);
		$invoiceIds = [];
		foreach ($invRows as $row) {
			$invoiceIds[] = (string)$row['id'];
			$linkedInvoices[(string)$row['id']] = $row;
		}

		$poRows = doc_fetch_all(
			$mysqli,
			"SELECT id, prefix, po_number, name, shipping_stage
			 FROM purchase_orders
			 WHERE deleted = 0 AND (from_so_id = ?" . (!empty($invoiceIds) ? " OR related_invoice_id IN ('" . implode("','", array_map([$mysqli, 'real_escape_string'], $invoiceIds)) . "')" : "") . ")",
			's',
			[$salesOrderId]
		);
		foreach ($poRows as $row) {
			$linkedPurchaseOrders[(string)$row['id']] = $row;
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

$salesOrderCode = trim((string)($salesOrder['prefix'] ?? '') . (string)($salesOrder['so_number'] ?? ''));
?>
<!doctype html>
<html lang="de">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title><?php echo $salesOrderCode !== '' ? htmlspecialchars($salesOrderCode) . ' - Auftrag Detail' : 'Auftrag Detail'; ?></title>
	<link href="../styles.css" rel="stylesheet" type="text/css">
	<link href="../assets/bootstrap/bootstrap.min.css" rel="stylesheet">
	<link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.5.0/css/all.css" crossorigin="anonymous">
</head>
<body class="bg-light">
	<?php require_once __DIR__ . '/../navbar.php'; ?>

	<main class="container-fluid py-3">
		<div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
			<h1 class="h4 mb-0"><i class="fas fa-clipboard-check me-2"></i>Auftrag Detail</h1>
			<form class="d-flex gap-2" method="get" action="auftrag.php">
				<input type="text" class="form-control form-control-sm" name="sales_order_id" placeholder="SalesOrder-ID" value="<?php echo htmlspecialchars($salesOrderId); ?>">
				<button type="submit" class="btn btn-sm btn-outline-primary">Öffnen</button>
			</form>
		</div>

		<?php if ($salesOrderId === ''): ?>
			<div class="alert alert-info">Bitte einen Auftrag öffnen (z. B. aus Firmen oder Schnellsuche).</div>
		<?php elseif (!$salesOrder): ?>
			<div class="alert alert-warning">Kein Auftrag mit dieser ID gefunden.</div>
		<?php else: ?>
			<div class="card shadow-sm mb-3">
				<div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
					<div>
						<strong><?php echo htmlspecialchars($salesOrderCode); ?></strong>
						<span class="ms-2"><?php echo so_stage_badge($salesOrder['so_stage'] ?? ''); ?></span>
					</div>
					<div class="d-flex gap-2">
						<a class="btn btn-sm btn-outline-secondary" href="firmen.php?account_id=<?php echo urlencode((string)$salesOrder['billing_account_id']); ?>">
							<i class="fas fa-building me-1"></i> Firma
						</a>
						<a class="btn btn-sm btn-success" target="_blank" rel="noopener" href="<?php echo 'https://addinol-lubeoil.at/crm/?module=SalesOrders&action=DetailView&record=' . urlencode((string)$salesOrder['id']); ?>">
							<i class="fas fa-external-link-alt me-1"></i> Im CRM öffnen
						</a>
					</div>
				</div>
				<div class="card-body">
					<div class="row g-3">
						<div class="col-12 col-lg-6">
							<table class="table table-sm table-striped align-middle mb-0">
								<tbody>
									<tr><th style="width: 180px;">Fällig</th><td><?php echo htmlspecialchars((string)$salesOrder['due_date']); ?></td></tr>
									<tr><th>Lieferdatum</th><td><?php echo htmlspecialchars((string)($salesOrder['delivery_date'] ?? '')); ?></td></tr>
									<tr><th>Name</th><td><?php echo htmlspecialchars((string)$salesOrder['name']); ?></td></tr>
									<tr><th>Firma</th><td><?php echo htmlspecialchars((string)($salesOrder['account_name'] ?? '')); ?></td></tr>
									<tr><th>Rechnungsadresse</th><td><?php echo nl2br(htmlspecialchars(doc_format_address($salesOrder, 'billing_address_'))); ?></td></tr>
									<tr><th>Lieferadresse</th><td><?php echo nl2br(htmlspecialchars(doc_format_address($salesOrder, 'shipping_address_'))); ?></td></tr>
								</tbody>
							</table>
						</div>
						<div class="col-12 col-lg-6">
							<table class="table table-sm table-striped align-middle mb-0">
								<tbody>
									<tr><th style="width: 180px;">Betrag</th><td><?php echo number_format((float)$salesOrder['amount'], 2, ',', '.'); ?></td></tr>
									<tr><th>Netto</th><td><?php echo number_format((float)$salesOrder['subtotal'], 2, ',', '.'); ?></td></tr>
									<tr><th>Steuerbar</th><td><?php echo number_format((float)$salesOrder['pretax'], 2, ',', '.'); ?></td></tr>
									<tr><th>Aus Angebot</th><td><?php echo htmlspecialchars((string)($salesOrder['related_quote_id'] ?? '')); ?></td></tr>
								</tbody>
							</table>
						</div>
					</div>
					<?php if (trim((string)($salesOrder['description'] ?? '')) !== ''): ?>
						<hr>
						<div class="small text-muted mb-1">Beschreibung</div>
						<div><?php echo nl2br(htmlspecialchars((string)$salesOrder['description'])); ?></div>
					<?php endif; ?>
				</div>
			</div>

			<div class="card shadow-sm">
				<div class="card-header"><strong>Positionen</strong> <span class="text-muted">(<?php echo count($salesOrderLines); ?>)</span></div>
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
								<?php foreach ($salesOrderLines as $idx => $line): ?>
									<tr>
										<td><?php echo (int)$idx + 1; ?></td>
										<td><?php echo htmlspecialchars((string)($line['name'] ?? '')); ?></td>
										<td class="text-end"><?php echo isset($line['quantity']) ? number_format((float)$line['quantity'], 2, ',', '.') : ''; ?></td>
										<td class="text-end"><?php echo isset($line['unit_price']) ? number_format((float)$line['unit_price'], 2, ',', '.') : ''; ?></td>
										<td class="text-end"><?php echo isset($line['ext_price']) ? number_format((float)$line['ext_price'], 2, ',', '.') : ''; ?></td>
										<td class="text-end"><?php echo isset($line['net_price']) ? number_format((float)$line['net_price'], 2, ',', '.') : ''; ?></td>
									</tr>
								<?php endforeach; ?>
								<?php if (!$salesOrderLines): ?>
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
