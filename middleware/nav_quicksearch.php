<?php
require_once __DIR__ . '/../db.inc.php';

header('Content-Type: application/json; charset=utf-8');

$mysqli = $mysqli ?? null;
if (!$mysqli instanceof mysqli) {
	http_response_code(500);
	echo json_encode(['ok' => false, 'error' => 'db_connection_missing']);
	exit;
}
$mysqli->set_charset('utf8');

$rawQuery = trim((string)($_GET['q'] ?? ''));
if ($rawQuery === '' || strlen($rawQuery) < 2) {
	echo json_encode(['ok' => true, 'items' => []]);
	exit;
}

$scriptName = (string)($_SERVER['SCRIPT_NAME'] ?? '');
$romanBase = preg_replace('~/middleware/nav_quicksearch\.php$~', '', $scriptName);
$romanBase = rtrim((string)$romanBase, '/');

function qs_fetch_all(mysqli $mysqli, string $sql, string $types = '', array $params = []): array
{
	$rows = [];
	$stmt = $mysqli->prepare($sql);
	if (!$stmt) {
		return $rows;
	}
	if ($types !== '' && $params) {
		$stmt->bind_param($types, ...$params);
	}
	if (!$stmt->execute()) {
		$stmt->close();
		return $rows;
	}
	$res = $stmt->get_result();
	while ($res && ($row = $res->fetch_assoc())) {
		$rows[] = $row;
	}
	$stmt->close();
	return $rows;
}

function qs_prefix(string $query): string
{
	if (!preg_match('/^\s*([a-z]{2})/i', $query, $m)) {
		return '';
	}
	return strtoupper((string)$m[1]);
}

$items = [];
$prefix = qs_prefix($rawQuery);
$normalizedQuery = strtoupper((string)preg_replace('/\s+/', '', $rawQuery));

if ($prefix === 'RE') {
	$rows = qs_fetch_all(
		$mysqli,
		"SELECT id, COALESCE(prefix, '') AS prefix, invoice_number, name
		 FROM invoice
		 WHERE deleted = 0
		   AND UPPER(CONCAT(COALESCE(prefix, ''), CAST(invoice_number AS CHAR))) LIKE ?
		 ORDER BY invoice_date DESC, invoice_number DESC
		 LIMIT 12",
		's',
		[$normalizedQuery . '%']
	);
	foreach ($rows as $row) {
		$code = trim((string)$row['prefix'] . (string)$row['invoice_number']);
		$items[] = [
			'type' => 'Rechnung',
			'code' => $code,
			'label' => (string)($row['name'] ?? ''),
			'url' => $romanBase . '/middleware/rechnung.php?invoice_id=' . rawurlencode((string)$row['id']),
		];
	}
} elseif ($prefix === 'AN') {
	$rows = qs_fetch_all(
		$mysqli,
		"SELECT id, COALESCE(prefix, '') AS prefix, quote_number, name
		 FROM quotes
		 WHERE deleted = 0
		   AND UPPER(CONCAT(COALESCE(prefix, ''), CAST(quote_number AS CHAR))) LIKE ?
		 ORDER BY date_modified DESC, quote_number DESC
		 LIMIT 12",
		's',
		[$normalizedQuery . '%']
	);
	foreach ($rows as $row) {
		$code = trim((string)$row['prefix'] . (string)$row['quote_number']);
		$items[] = [
			'type' => 'Angebot',
			'code' => $code,
			'label' => (string)($row['name'] ?? ''),
			'url' => $romanBase . '/middleware/angebot.php?quote_id=' . rawurlencode((string)$row['id']),
		];
	}
} elseif ($prefix === 'BE') {
	$rows = qs_fetch_all(
		$mysqli,
		"SELECT id, COALESCE(prefix, '') AS prefix, po_number, name
		 FROM purchase_orders
		 WHERE deleted = 0
		   AND UPPER(CONCAT(COALESCE(prefix, ''), CAST(po_number AS CHAR))) LIKE ?
		 ORDER BY date_modified DESC, po_number DESC
		 LIMIT 12",
		's',
		[$normalizedQuery . '%']
	);
	foreach ($rows as $row) {
		$code = trim((string)$row['prefix'] . (string)$row['po_number']);
		$items[] = [
			'type' => 'Bestellung',
			'code' => $code,
			'label' => (string)($row['name'] ?? ''),
			'url' => $romanBase . '/middleware/bestellung.php?purchase_order_id=' . rawurlencode((string)$row['id']),
		];
	}
} elseif ($prefix === 'AB') {
	$rows = qs_fetch_all(
		$mysqli,
		"SELECT id, COALESCE(prefix, '') AS prefix, so_number, name
		 FROM sales_orders
		 WHERE deleted = 0
		   AND UPPER(CONCAT(COALESCE(prefix, ''), CAST(so_number AS CHAR))) LIKE ?
		 ORDER BY date_modified DESC, so_number DESC
		 LIMIT 12",
		's',
		[$normalizedQuery . '%']
	);
	foreach ($rows as $row) {
		$code = trim((string)$row['prefix'] . (string)$row['so_number']);
		$items[] = [
			'type' => 'Auftrag',
			'code' => $code,
			'label' => (string)($row['name'] ?? ''),
			'url' => $romanBase . '/middleware/auftrag.php?sales_order_id=' . rawurlencode((string)$row['id']),
		];
	}
} else {
	$like = '%' . $rawQuery . '%';
	$rows = qs_fetch_all(
		$mysqli,
		"SELECT id, ticker_symbol, name
		 FROM accounts
		 WHERE deleted = 0
		   AND (name LIKE ? OR ticker_symbol LIKE ?)
		 ORDER BY name ASC
		 LIMIT 12",
		'ss',
		[$like, $like]
	);
	foreach ($rows as $row) {
		$items[] = [
			'type' => 'Firma',
			'code' => (string)($row['ticker_symbol'] ?? ''),
			'label' => (string)($row['name'] ?? ''),
			'url' => $romanBase . '/middleware/firmen.php?account_id=' . rawurlencode((string)$row['id']),
		];
	}
}

echo json_encode(['ok' => true, 'items' => $items], JSON_UNESCAPED_UNICODE);
