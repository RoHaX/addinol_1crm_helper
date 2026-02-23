<?php
require_once __DIR__ . '/../db.inc.php';

$mysqli = $mysqli ?? null;
if (!$mysqli instanceof mysqli) {
	die('DB connection missing');
}
$mysqli->set_charset('utf8');

function inv_fetch_all(mysqli $db, string $sql, string $types = '', array $params = []): array {
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

function inv_email_status_de(?string $status, ?string $type): string {
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

function inv_email_recipient_label(array $mail): string {
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

function inv_format_address(array $row, string $prefix): string {
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

function inv_fetch_linked_emails(mysqli $db, array $quoteIds, array $orderIds, array $invoiceIds, array $purchaseOrderIds): array {
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
	return inv_fetch_all($db, $sql);
}

function inv_stage_badge(?string $stage): string {
	$raw = trim((string)$stage);
	$key = strtolower($raw);
	$map = [
		'pending' => ['Offen', 'fas fa-hourglass-half', 'warning'],
		'shipped' => ['Versendet', 'fas fa-truck', 'info'],
		'partially shipped' => ['Teilversendet', 'fas fa-shipping-fast', 'info'],
		'delivered' => ['Zugestellt', 'fas fa-box-open', 'success'],
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

$invoiceId = trim((string)($_GET['invoice_id'] ?? ''));
$invoice = null;
$invoiceLines = [];
$payments = [];
$linkedQuotes = [];
$linkedOrders = [];
$linkedInvoices = [];
$linkedPurchaseOrders = [];
$linkedEmails = [];

if ($invoiceId !== '') {
	$rows = inv_fetch_all(
		$mysqli,
		"SELECT i.id, i.prefix, i.invoice_number, i.name, i.invoice_date, i.due_date, i.shipping_stage,
		        i.amount, i.amount_due, i.subtotal, i.pretax, i.currency_id,
		        i.billing_account_id, i.purchase_order_num, i.description, i.date_entered, i.date_modified,
		        i.from_quote_id, i.from_so_id,
		        i.billing_address_street, i.billing_address_postalcode, i.billing_address_city, i.billing_address_state, i.billing_address_country,
		        i.shipping_address_street, i.shipping_address_postalcode, i.shipping_address_city, i.shipping_address_state, i.shipping_address_country,
		        a.id AS account_id, a.name AS account_name, a.ticker_symbol AS account_no, a.balance AS account_balance
		 FROM invoice i
		 LEFT JOIN accounts a ON a.id = i.billing_account_id AND a.deleted = 0
		 WHERE i.deleted = 0 AND i.id = ?
		 LIMIT 1",
		's',
		[$invoiceId]
	);
	$invoice = $rows[0] ?? null;

	if ($invoice) {
		$invoiceLines = inv_fetch_all(
			$mysqli,
			"SELECT id, line_group_id, position, name, quantity, unit_price, ext_price, net_price
			 FROM invoice_lines
			 WHERE deleted = 0 AND invoice_id = ?
			 ORDER BY line_group_id ASC, position ASC, date_entered ASC",
			's',
			[$invoiceId]
		);

		$payments = inv_fetch_all(
			$mysqli,
			"SELECT p.id, p.payment_date, p.payment_type, p.prefix, p.payment_id, p.customer_reference, p.notes,
			        ip.amount
			 FROM invoices_payments ip
			 INNER JOIN payments p ON p.id = ip.payment_id
			 WHERE ip.invoice_id = ? AND ip.deleted = 0 AND p.deleted = 0
			 ORDER BY p.payment_date DESC, p.date_entered DESC",
			's',
			[$invoiceId]
		);

		$quoteIds = [];
		$orderIds = [];
		$purchaseOrderIds = [];

		$fromQuoteId = trim((string)($invoice['from_quote_id'] ?? ''));
		$fromSoId = trim((string)($invoice['from_so_id'] ?? ''));
		$purchaseOrderCode = trim((string)($invoice['purchase_order_num'] ?? ''));

		if ($fromQuoteId !== '') {
			$quoteIds[] = $fromQuoteId;
		}
		if ($fromSoId !== '') {
			$orderIds[] = $fromSoId;
		}

		if ($fromSoId !== '') {
			$orderRows = inv_fetch_all(
				$mysqli,
				"SELECT id, prefix, so_number, name, so_stage, related_quote_id
				 FROM sales_orders
				 WHERE deleted = 0 AND id = ?
				 LIMIT 1",
				's',
				[$fromSoId]
			);
			if (!empty($orderRows[0]['related_quote_id'])) {
				$quoteIds[] = (string)$orderRows[0]['related_quote_id'];
			}
			foreach ($orderRows as $row) {
				$linkedOrders[(string)$row['id']] = $row;
			}
		}

		if ($fromQuoteId !== '') {
			$quoteRows = inv_fetch_all(
				$mysqli,
				"SELECT id, prefix, quote_number, name, quote_stage
				 FROM quotes
				 WHERE deleted = 0 AND id = ?
				 LIMIT 1",
				's',
				[$fromQuoteId]
			);
			foreach ($quoteRows as $row) {
				$linkedQuotes[(string)$row['id']] = $row;
			}
		}

		if ($purchaseOrderCode !== '') {
			$poRowsByCode = inv_fetch_all(
				$mysqli,
				"SELECT id, prefix, po_number, name, shipping_stage
				 FROM purchase_orders
				 WHERE deleted = 0
				   AND UPPER(CONCAT(COALESCE(prefix,''), CAST(po_number AS CHAR))) = UPPER(?)
				 LIMIT 20",
				's',
				[$purchaseOrderCode]
			);
			foreach ($poRowsByCode as $row) {
				$purchaseOrderIds[] = (string)$row['id'];
				$linkedPurchaseOrders[(string)$row['id']] = $row;
			}
		}

		$poRows = inv_fetch_all(
			$mysqli,
			"SELECT id, prefix, po_number, name, shipping_stage
			 FROM purchase_orders
			 WHERE deleted = 0
			   AND (related_invoice_id = ? " . ($fromSoId !== '' ? " OR from_so_id = ?" : "") . ")",
			$fromSoId !== '' ? 'ss' : 's',
			$fromSoId !== '' ? [$invoiceId, $fromSoId] : [$invoiceId]
		);
		foreach ($poRows as $row) {
			$purchaseOrderIds[] = (string)$row['id'];
			$linkedPurchaseOrders[(string)$row['id']] = $row;
		}

		$invRows = inv_fetch_all(
			$mysqli,
			"SELECT id, prefix, invoice_number, name, shipping_stage
			 FROM invoice
			 WHERE deleted = 0 AND id = ?
			 LIMIT 1",
			's',
			[$invoiceId]
		);
		foreach ($invRows as $row) {
			$linkedInvoices[(string)$row['id']] = $row;
		}

		$quoteIds = array_values(array_unique(array_filter($quoteIds)));
		foreach ($quoteIds as $qid) {
			$qRows = inv_fetch_all(
				$mysqli,
				"SELECT id, prefix, quote_number, name, quote_stage
				 FROM quotes
				 WHERE deleted = 0 AND id = ?
				 LIMIT 1",
				's',
				[$qid]
			);
			foreach ($qRows as $row) {
				$linkedQuotes[(string)$row['id']] = $row;
			}
		}

		$orderIds = array_values(array_unique(array_filter($orderIds)));
		foreach ($orderIds as $oid) {
			$oRows = inv_fetch_all(
				$mysqli,
				"SELECT id, prefix, so_number, name, so_stage
				 FROM sales_orders
				 WHERE deleted = 0 AND id = ?
				 LIMIT 1",
				's',
				[$oid]
			);
			foreach ($oRows as $row) {
				$linkedOrders[(string)$row['id']] = $row;
			}
		}

		$linkedEmails = inv_fetch_linked_emails(
			$mysqli,
			array_keys($linkedQuotes),
			array_keys($linkedOrders),
			array_keys($linkedInvoices),
			array_keys($linkedPurchaseOrders)
		);
	}
}

$invoiceCode = trim((string)($invoice['prefix'] ?? '') . (string)($invoice['invoice_number'] ?? ''));
?>
<!doctype html>
<html lang="de">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title><?php echo $invoiceCode !== '' ? htmlspecialchars($invoiceCode) . ' - Rechnung Detail' : 'Rechnung Detail'; ?></title>
	<link href="../styles.css" rel="stylesheet" type="text/css">
	<link href="../assets/bootstrap/bootstrap.min.css" rel="stylesheet">
	<link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.5.0/css/all.css" integrity="sha384-B4dIYHKNBt8Bc12p+WXckhzcICo0wtJAoU8YZTY5qE0Id1GSseTk6S+L3BlXeVIU" crossorigin="anonymous">
</head>
<body class="bg-light">
	<?php require_once __DIR__ . '/../navbar.php'; ?>

	<main class="container-fluid py-3">
		<div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
			<h1 class="h4 mb-0"><i class="fas fa-file-invoice-dollar me-2"></i>Rechnung Detail</h1>
			<form class="d-flex gap-2" method="get" action="rechnung.php">
				<input type="text" class="form-control form-control-sm" name="invoice_id" placeholder="Invoice-ID" value="<?php echo htmlspecialchars($invoiceId); ?>">
				<button type="submit" class="btn btn-sm btn-outline-primary">Öffnen</button>
			</form>
		</div>

		<?php if ($invoiceId === ''): ?>
			<div class="alert alert-info">Bitte eine Rechnung öffnen (z. B. aus Firmen oder Schnellsuche).</div>
		<?php elseif (!$invoice): ?>
			<div class="alert alert-warning">Keine Rechnung mit dieser ID gefunden.</div>
		<?php else: ?>
			<div class="card shadow-sm mb-3">
				<div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
					<div>
						<strong><?php echo htmlspecialchars($invoiceCode); ?></strong>
						<span class="ms-2"><?php echo inv_stage_badge($invoice['shipping_stage'] ?? ''); ?></span>
					</div>
					<div class="d-flex gap-2">
						<a class="btn btn-sm btn-outline-secondary" href="firmen.php?account_id=<?php echo urlencode((string)$invoice['billing_account_id']); ?>">
							<i class="fas fa-building me-1"></i> Firma
						</a>
						<a class="btn btn-sm btn-success" target="_blank" rel="noopener" href="<?php echo 'https://addinol-lubeoil.at/crm/?module=Invoice&action=DetailView&record=' . urlencode((string)$invoice['id']); ?>">
							<i class="fas fa-external-link-alt me-1"></i> Im CRM öffnen
						</a>
					</div>
				</div>
				<div class="card-body">
					<div class="row g-3">
						<div class="col-12 col-lg-6">
							<table class="table table-sm table-striped align-middle mb-0">
								<tbody>
									<tr><th style="width: 180px;">Datum</th><td><?php echo htmlspecialchars((string)$invoice['invoice_date']); ?></td></tr>
									<tr><th>Fällig</th><td><?php echo htmlspecialchars((string)$invoice['due_date']); ?></td></tr>
									<tr><th>Name</th><td><?php echo htmlspecialchars((string)$invoice['name']); ?></td></tr>
									<tr><th>Bestellnummer</th><td><?php echo htmlspecialchars((string)($invoice['purchase_order_num'] ?? '')); ?></td></tr>
									<tr><th>Rechnungsadresse</th><td><?php echo nl2br(htmlspecialchars(inv_format_address($invoice, 'billing_address_'))); ?></td></tr>
									<tr><th>Lieferadresse</th><td><?php echo nl2br(htmlspecialchars(inv_format_address($invoice, 'shipping_address_'))); ?></td></tr>
								</tbody>
							</table>
						</div>
						<div class="col-12 col-lg-6">
							<table class="table table-sm table-striped align-middle mb-0">
								<tbody>
									<tr><th style="width: 180px;">Betrag</th><td><?php echo number_format((float)$invoice['amount'], 2, ',', '.'); ?></td></tr>
									<tr><th>Offen</th><td><?php echo number_format((float)$invoice['amount_due'], 2, ',', '.'); ?></td></tr>
									<tr><th>Netto</th><td><?php echo number_format((float)$invoice['subtotal'], 2, ',', '.'); ?></td></tr>
									<tr><th>Firma</th><td><?php echo htmlspecialchars((string)($invoice['account_name'] ?? '')); ?></td></tr>
								</tbody>
							</table>
						</div>
					</div>
					<?php if (trim((string)($invoice['description'] ?? '')) !== ''): ?>
						<hr>
						<div class="small text-muted mb-1">Beschreibung</div>
						<div><?php echo nl2br(htmlspecialchars((string)$invoice['description'])); ?></div>
					<?php endif; ?>
				</div>
			</div>

			<div class="card shadow-sm mb-3">
				<div class="card-header"><strong>Verknüpfte Belege</strong></div>
				<div class="card-body">
					<div class="table-responsive">
						<table class="table table-sm table-striped align-middle mb-0">
							<thead>
								<tr>
									<th>Typ</th>
									<th>Nummer</th>
									<th>Name</th>
									<th>Detail</th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ($linkedQuotes as $row): ?>
									<tr>
										<td>AN</td>
										<td><?php echo htmlspecialchars(trim((string)($row['prefix'] ?? '') . (string)($row['quote_number'] ?? ''))); ?></td>
										<td><?php echo htmlspecialchars((string)($row['name'] ?? '')); ?></td>
										<td><a class="btn btn-sm btn-outline-primary" href="<?php echo 'angebot.php?quote_id=' . urlencode((string)$row['id']); ?>">Öffnen</a></td>
									</tr>
								<?php endforeach; ?>
								<?php foreach ($linkedOrders as $row): ?>
									<tr>
										<td>AB</td>
										<td><?php echo htmlspecialchars(trim((string)($row['prefix'] ?? '') . (string)($row['so_number'] ?? ''))); ?></td>
										<td><?php echo htmlspecialchars((string)($row['name'] ?? '')); ?></td>
										<td><a class="btn btn-sm btn-outline-primary" href="<?php echo 'auftrag.php?sales_order_id=' . urlencode((string)$row['id']); ?>">Öffnen</a></td>
									</tr>
								<?php endforeach; ?>
								<?php foreach ($linkedInvoices as $row): ?>
									<tr>
										<td>RE</td>
										<td><?php echo htmlspecialchars(trim((string)($row['prefix'] ?? '') . (string)($row['invoice_number'] ?? ''))); ?></td>
										<td><?php echo htmlspecialchars((string)($row['name'] ?? '')); ?></td>
										<td><a class="btn btn-sm btn-outline-primary" href="<?php echo 'rechnung.php?invoice_id=' . urlencode((string)$row['id']); ?>">Öffnen</a></td>
									</tr>
								<?php endforeach; ?>
								<?php foreach ($linkedPurchaseOrders as $row): ?>
									<tr>
										<td>BE</td>
										<td><?php echo htmlspecialchars(trim((string)($row['prefix'] ?? '') . (string)($row['po_number'] ?? ''))); ?></td>
										<td><?php echo htmlspecialchars((string)($row['name'] ?? '')); ?></td>
										<td><a class="btn btn-sm btn-outline-primary" href="<?php echo 'bestellung.php?purchase_order_id=' . urlencode((string)$row['id']); ?>">Öffnen</a></td>
									</tr>
								<?php endforeach; ?>
								<?php if (!$linkedQuotes && !$linkedOrders && !$linkedInvoices && !$linkedPurchaseOrders): ?>
									<tr><td colspan="4" class="text-muted">Keine verknüpften Belege gefunden.</td></tr>
								<?php endif; ?>
							</tbody>
						</table>
					</div>
				</div>
			</div>

			<div class="card shadow-sm mb-3">
				<div class="card-header"><strong>Positionen</strong> <span class="text-muted">(<?php echo count($invoiceLines); ?>)</span></div>
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
								<?php foreach ($invoiceLines as $idx => $line): ?>
									<tr>
										<td><?php echo (int)$idx + 1; ?></td>
										<td><?php echo htmlspecialchars((string)($line['name'] ?? '')); ?></td>
										<td class="text-end"><?php echo isset($line['quantity']) ? number_format((float)$line['quantity'], 2, ',', '.') : ''; ?></td>
										<td class="text-end"><?php echo isset($line['unit_price']) ? number_format((float)$line['unit_price'], 2, ',', '.') : ''; ?></td>
										<td class="text-end"><?php echo isset($line['ext_price']) ? number_format((float)$line['ext_price'], 2, ',', '.') : ''; ?></td>
										<td class="text-end"><?php echo isset($line['net_price']) ? number_format((float)$line['net_price'], 2, ',', '.') : ''; ?></td>
									</tr>
								<?php endforeach; ?>
								<?php if (!$invoiceLines): ?>
									<tr><td colspan="6" class="text-muted">Keine Positionen gefunden.</td></tr>
								<?php endif; ?>
							</tbody>
						</table>
					</div>
				</div>
			</div>

			<div class="card shadow-sm">
				<div class="card-header"><strong>Zahlungen</strong> <span class="text-muted">(<?php echo count($payments); ?>)</span></div>
				<div class="card-body">
					<div class="table-responsive">
						<table class="table table-sm table-striped align-middle">
							<thead>
								<tr>
									<th>Datum</th>
									<th>Art</th>
									<th>Nr.</th>
									<th>Referenz</th>
									<th class="text-end">Betrag</th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ($payments as $pay): ?>
									<tr>
										<td><?php echo htmlspecialchars((string)($pay['payment_date'] ?? '')); ?></td>
										<td><?php echo htmlspecialchars((string)($pay['payment_type'] ?? '')); ?></td>
										<td><?php echo htmlspecialchars(trim((string)($pay['prefix'] ?? '') . (string)($pay['payment_id'] ?? ''))); ?></td>
										<td><?php echo htmlspecialchars((string)($pay['customer_reference'] ?? '')); ?></td>
										<td class="text-end"><?php echo isset($pay['amount']) ? number_format((float)$pay['amount'], 2, ',', '.') : ''; ?></td>
									</tr>
								<?php endforeach; ?>
								<?php if (!$payments): ?>
									<tr><td colspan="5" class="text-muted">Keine Zahlungen zugeordnet.</td></tr>
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
										<td><?php echo htmlspecialchars(inv_email_recipient_label($mail)); ?></td>
										<td><?php echo htmlspecialchars(inv_email_status_de($mail['status'] ?? '', $mail['type'] ?? '')); ?></td>
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
