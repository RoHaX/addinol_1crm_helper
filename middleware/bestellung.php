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

function po_stage_badge(?string $stage): string {
	$raw = trim((string)$stage);
	$key = strtolower($raw);
	$map = [
		'ordered' => ['Bestellt', 'fas fa-clipboard-check', 'primary'],
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
	return '<span class="badge text-bg-' . $class . '"><i class="' . $icon . ' me-1"></i>' . htmlspecialchars($label) . '</span>';
}

$purchaseOrderId = trim((string)($_GET['purchase_order_id'] ?? ''));
$purchaseOrder = null;
$purchaseOrderLines = [];
$linkedQuotes = [];
$linkedOrders = [];
$linkedInvoices = [];
$linkedPurchaseOrders = [];
$linkedEmails = [];

if ($purchaseOrderId !== '') {
	$rows = doc_fetch_all(
		$mysqli,
		"SELECT p.id, p.prefix, p.po_number, p.name, p.shipping_stage, p.amount, p.subtotal, p.pretax,
		        p.supplier_id, p.related_invoice_id, p.from_so_id, p.description, p.date_entered,
		        p.shipping_address_street, p.shipping_address_postalcode, p.shipping_address_city, p.shipping_address_state, p.shipping_address_country,
		        a.name AS supplier_name,
		        a.billing_address_street AS supplier_billing_address_street,
		        a.billing_address_postalcode AS supplier_billing_address_postalcode,
		        a.billing_address_city AS supplier_billing_address_city,
		        a.billing_address_state AS supplier_billing_address_state,
		        a.billing_address_country AS supplier_billing_address_country
		 FROM purchase_orders p
		 LEFT JOIN accounts a ON a.id = p.supplier_id AND a.deleted = 0
		 WHERE p.deleted = 0 AND p.id = ?
		 LIMIT 1",
		's',
		[$purchaseOrderId]
	);
	$purchaseOrder = $rows[0] ?? null;

	if ($purchaseOrder) {
		$linkedPurchaseOrders[(string)$purchaseOrder['id']] = $purchaseOrder;
		$purchaseOrderLines = doc_fetch_all(
			$mysqli,
			"SELECT id, line_group_id, position, name, quantity, unit_price, ext_price
			 FROM purchase_order_lines
			 WHERE deleted = 0 AND purchase_orders_id = ?
			 ORDER BY line_group_id ASC, position ASC, date_entered ASC",
			's',
			[$purchaseOrderId]
		);

		$fromSoId = trim((string)($purchaseOrder['from_so_id'] ?? ''));
		$relatedInvId = trim((string)($purchaseOrder['related_invoice_id'] ?? ''));
		$quoteId = '';

		if ($fromSoId !== '') {
			$oRows = doc_fetch_all(
				$mysqli,
				"SELECT id, prefix, so_number, name, so_stage, related_quote_id
				 FROM sales_orders
				 WHERE deleted = 0 AND id = ?
				 LIMIT 1",
				's',
				[$fromSoId]
			);
			foreach ($oRows as $row) {
				$linkedOrders[(string)$row['id']] = $row;
				$quoteId = trim((string)($row['related_quote_id'] ?? ''));
			}
		}

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
			 WHERE deleted = 0 AND (id = ?" . ($fromSoId !== '' ? " OR from_so_id = ?" : "") . ")",
			$fromSoId !== '' ? 'ss' : 's',
			$fromSoId !== '' ? [$relatedInvId, $fromSoId] : [$relatedInvId]
		);
		foreach ($invRows as $row) {
			$linkedInvoices[(string)$row['id']] = $row;
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

$purchaseOrderCode = trim((string)($purchaseOrder['prefix'] ?? '') . (string)($purchaseOrder['po_number'] ?? ''));
?>
<!doctype html>
<html lang="de">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title><?php echo $purchaseOrderCode !== '' ? htmlspecialchars($purchaseOrderCode) . ' - Bestellung Detail' : 'Bestellung Detail'; ?></title>
	<link href="../styles.css" rel="stylesheet" type="text/css">
	<link href="../assets/bootstrap/bootstrap.min.css" rel="stylesheet">
	<link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.5.0/css/all.css" crossorigin="anonymous">
</head>
<body class="bg-light">
	<?php require_once __DIR__ . '/../navbar.php'; ?>

	<main class="container-fluid py-3">
		<div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
			<h1 class="h4 mb-0"><i class="fas fa-shopping-cart me-2"></i>Bestellung Detail</h1>
			<form class="d-flex gap-2" method="get" action="bestellung.php">
				<input type="text" class="form-control form-control-sm" name="purchase_order_id" placeholder="PurchaseOrder-ID" value="<?php echo htmlspecialchars($purchaseOrderId); ?>">
				<button type="submit" class="btn btn-sm btn-outline-primary">Öffnen</button>
			</form>
		</div>

		<?php if ($purchaseOrderId === ''): ?>
			<div class="alert alert-info">Bitte eine Bestellung öffnen (z. B. aus Firmen oder Schnellsuche).</div>
		<?php elseif (!$purchaseOrder): ?>
			<div class="alert alert-warning">Keine Bestellung mit dieser ID gefunden.</div>
		<?php else: ?>
			<div class="card shadow-sm mb-3">
				<div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
					<div>
						<strong><?php echo htmlspecialchars($purchaseOrderCode); ?></strong>
						<span class="ms-2"><?php echo po_stage_badge($purchaseOrder['shipping_stage'] ?? ''); ?></span>
					</div>
					<div class="d-flex gap-2">
						<a class="btn btn-sm btn-outline-secondary" href="firmen.php?account_id=<?php echo urlencode((string)$purchaseOrder['supplier_id']); ?>">
							<i class="fas fa-building me-1"></i> Firma
						</a>
						<a class="btn btn-sm btn-success" target="_blank" rel="noopener" href="<?php echo 'https://addinol-lubeoil.at/crm/?module=PurchaseOrders&action=DetailView&record=' . urlencode((string)$purchaseOrder['id']); ?>">
							<i class="fas fa-external-link-alt me-1"></i> Im CRM öffnen
						</a>
					</div>
				</div>
				<div class="card-body">
					<div class="row g-3">
						<div class="col-12 col-lg-6">
							<table class="table table-sm table-striped align-middle mb-0">
								<tbody>
									<tr><th style="width: 180px;">Name</th><td><?php echo htmlspecialchars((string)$purchaseOrder['name']); ?></td></tr>
									<tr><th>Lieferant</th><td><?php echo htmlspecialchars((string)($purchaseOrder['supplier_name'] ?? '')); ?></td></tr>
									<tr><th>Eingang/Rechnung</th><td><?php echo htmlspecialchars((string)($purchaseOrder['related_invoice_id'] ?? '')); ?></td></tr>
									<tr><th>Aus Auftrag</th><td><?php echo htmlspecialchars((string)($purchaseOrder['from_so_id'] ?? '')); ?></td></tr>
									<tr><th>Rechnungsadresse</th><td><?php echo nl2br(htmlspecialchars(doc_format_address($purchaseOrder, 'supplier_billing_address_'))); ?></td></tr>
									<tr><th>Lieferadresse</th><td><?php echo nl2br(htmlspecialchars(doc_format_address($purchaseOrder, 'shipping_address_'))); ?></td></tr>
								</tbody>
							</table>
						</div>
						<div class="col-12 col-lg-6">
							<table class="table table-sm table-striped align-middle mb-0">
								<tbody>
									<tr><th style="width: 180px;">Betrag</th><td><?php echo number_format((float)$purchaseOrder['amount'], 2, ',', '.'); ?></td></tr>
									<tr><th>Netto</th><td><?php echo number_format((float)$purchaseOrder['subtotal'], 2, ',', '.'); ?></td></tr>
									<tr><th>Steuerbar</th><td><?php echo number_format((float)$purchaseOrder['pretax'], 2, ',', '.'); ?></td></tr>
									<tr><th>Erstellt</th><td><?php echo htmlspecialchars((string)($purchaseOrder['date_entered'] ?? '')); ?></td></tr>
								</tbody>
							</table>
						</div>
					</div>
					<?php if (trim((string)($purchaseOrder['description'] ?? '')) !== ''): ?>
						<hr>
						<div class="small text-muted mb-1">Beschreibung</div>
						<div><?php echo nl2br(htmlspecialchars((string)$purchaseOrder['description'])); ?></div>
					<?php endif; ?>
				</div>
			</div>

			<div class="card shadow-sm">
				<div class="card-header"><strong>Positionen</strong> <span class="text-muted">(<?php echo count($purchaseOrderLines); ?>)</span></div>
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
								</tr>
							</thead>
							<tbody>
								<?php foreach ($purchaseOrderLines as $idx => $line): ?>
									<tr>
										<td><?php echo (int)$idx + 1; ?></td>
										<td><?php echo htmlspecialchars((string)($line['name'] ?? '')); ?></td>
										<td class="text-end"><?php echo isset($line['quantity']) ? number_format((float)$line['quantity'], 2, ',', '.') : ''; ?></td>
										<td class="text-end"><?php echo isset($line['unit_price']) ? number_format((float)$line['unit_price'], 2, ',', '.') : ''; ?></td>
										<td class="text-end"><?php echo isset($line['ext_price']) ? number_format((float)$line['ext_price'], 2, ',', '.') : ''; ?></td>
									</tr>
								<?php endforeach; ?>
								<?php if (!$purchaseOrderLines): ?>
									<tr><td colspan="5" class="text-muted">Keine Positionen gefunden.</td></tr>
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
