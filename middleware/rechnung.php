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

function inv_fetch_one(mysqli $db, string $sql, string $types = '', array $params = []): ?array {
	$rows = inv_fetch_all($db, $sql, $types, $params);
	return $rows[0] ?? null;
}

function inv_normalize_money($value): ?float {
	$value = trim((string)$value);
	if ($value === '') {
		return null;
	}
	$value = str_replace([' ', "\u{00A0}"], '', $value);
	if (strpos($value, ',') !== false) {
		$value = str_replace('.', '', $value);
		$value = str_replace(',', '.', $value);
	}
	if (!is_numeric($value)) {
		return null;
	}
	return round((float)$value, 2);
}

function inv_normalize_qty($value): ?float {
	$money = inv_normalize_money($value);
	if ($money === null) {
		return null;
	}
	return (float)$money;
}

function inv_sync_account_balance(mysqli $db, string $accountId): void {
	if ($accountId === '') {
		return;
	}
	$sumRow = inv_fetch_one(
		$db,
		"SELECT COALESCE(SUM(amount_due), 0) AS open_sum
		 FROM invoice
		 WHERE deleted = 0 AND billing_account_id = ?",
		's',
		[$accountId]
	);
	$openSum = round((float)($sumRow['open_sum'] ?? 0), 2);
	$stmt = $db->prepare('UPDATE accounts SET balance = ?, date_modified = NOW() WHERE id = ? LIMIT 1');
	if ($stmt) {
		$stmt->bind_param('ds', $openSum, $accountId);
		$stmt->execute();
		$stmt->close();
	}
}

function inv_delete_invoice(mysqli $db, array $invoiceBase): array {
	$invoiceId = trim((string)($invoiceBase['id'] ?? ''));
	$accountId = trim((string)($invoiceBase['billing_account_id'] ?? ''));
	$prefix = trim((string)($invoiceBase['prefix'] ?? ''));
	$invoiceNumber = (int)($invoiceBase['invoice_number'] ?? 0);
	if ($invoiceId === '' || $invoiceNumber <= 0) {
		throw new RuntimeException('Rechnung konnte nicht gelöscht werden: unvollständige Daten.');
	}

	$linkedPaymentCountRow = inv_fetch_one(
		$db,
		"SELECT COUNT(*) AS cnt
		 FROM invoices_payments
		 WHERE invoice_id = ? AND deleted = 0",
		's',
		[$invoiceId]
	);
	$linkedPaymentCount = (int)($linkedPaymentCountRow['cnt'] ?? 0);

	$statements = [
		['sql' => "UPDATE invoice_lines SET deleted = 1, date_modified = NOW() WHERE invoice_id = ? AND deleted = 0", 'types' => 's', 'params' => [$invoiceId]],
		['sql' => "UPDATE invoice_line_groups SET deleted = 1, date_modified = NOW() WHERE parent_id = ? AND deleted = 0", 'types' => 's', 'params' => [$invoiceId]],
		['sql' => "UPDATE invoice_adjustments SET deleted = 1, date_modified = NOW() WHERE invoice_id = ? AND deleted = 0", 'types' => 's', 'params' => [$invoiceId]],
		['sql' => "UPDATE invoice_comments SET deleted = 1, date_modified = NOW() WHERE invoice_id = ? AND deleted = 0", 'types' => 's', 'params' => [$invoiceId]],
		['sql' => "UPDATE invoices_payments SET deleted = 1, date_modified = NOW() WHERE invoice_id = ? AND deleted = 0", 'types' => 's', 'params' => [$invoiceId]],
		['sql' => "UPDATE payments SET related_invoice_id = NULL, date_modified = NOW() WHERE related_invoice_id = ?", 'types' => 's', 'params' => [$invoiceId]],
		['sql' => "UPDATE emails_invoices SET deleted = 1, date_modified = NOW() WHERE invoice_id = ? AND deleted = 0", 'types' => 's', 'params' => [$invoiceId]],
		['sql' => "UPDATE securitygroups_records SET deleted = 1, date_modified = NOW() WHERE module = 'Invoice' AND record_id = ? AND deleted = 0", 'types' => 's', 'params' => [$invoiceId]],
		['sql' => "UPDATE invoice SET deleted = 1, date_modified = NOW() WHERE id = ? AND deleted = 0 LIMIT 1", 'types' => 's', 'params' => [$invoiceId]],
	];

	foreach ($statements as $entry) {
		$stmt = $db->prepare($entry['sql']);
		if (!$stmt) {
			throw new RuntimeException('Delete-Statement konnte nicht vorbereitet werden.');
		}
		$stmt->bind_param($entry['types'], ...$entry['params']);
		if (!$stmt->execute()) {
			$error = $stmt->error;
			$stmt->close();
			throw new RuntimeException('Rechnung konnte nicht gelöscht werden: ' . $error);
		}
		$stmt->close();
	}

	inv_sync_account_balance($db, $accountId);

	$sequenceInfo = [
		'rewound' => false,
		'freed_automatically' => false,
		'old_sequence' => null,
		'new_sequence' => null,
		'payment_links_removed' => $linkedPaymentCount,
	];

	$sequenceRow = inv_fetch_one(
		$db,
		"SELECT value
		 FROM config
		 WHERE category = 'company' AND name = 'invoice_number_sequence'
		 LIMIT 1"
	);
	$currentSequence = isset($sequenceRow['value']) ? (int)$sequenceRow['value'] : 0;
	$maxRemainingRow = inv_fetch_one(
		$db,
		"SELECT MAX(invoice_number) AS max_no
		 FROM invoice
		 WHERE deleted = 0 AND prefix = ?",
		's',
		[$prefix]
	);
	$maxRemaining = (int)($maxRemainingRow['max_no'] ?? 0);

	if ($currentSequence === ($invoiceNumber + 1) && $maxRemaining === ($invoiceNumber - 1)) {
		$newSequence = $invoiceNumber;
		$stmt = $db->prepare(
			"UPDATE config
			 SET value = ?
			 WHERE category = 'company' AND name = 'invoice_number_sequence'
			 LIMIT 1"
		);
		if (!$stmt) {
			throw new RuntimeException('Rechnungssequenz konnte nicht vorbereitet werden.');
		}
		$newSequenceValue = (string)$newSequence;
		$stmt->bind_param('s', $newSequenceValue);
		if (!$stmt->execute()) {
			$error = $stmt->error;
			$stmt->close();
			throw new RuntimeException('Rechnungssequenz konnte nicht zurückgesetzt werden: ' . $error);
		}
		$stmt->close();

		$sequenceInfo['rewound'] = true;
		$sequenceInfo['freed_automatically'] = true;
		$sequenceInfo['old_sequence'] = $currentSequence;
		$sequenceInfo['new_sequence'] = $newSequence;
	}

	return $sequenceInfo;
}

function inv_recalculate_totals(mysqli $db, string $invoiceId): void {
	$groups = inv_fetch_all(
		$db,
		"SELECT id
		 FROM invoice_line_groups
		 WHERE deleted = 0 AND parent_id = ?
		 ORDER BY position ASC, id ASC",
		's',
		[$invoiceId]
	);

	$pretax = 0.0;
	$total = 0.0;

	foreach ($groups as $group) {
		$groupId = (string)($group['id'] ?? '');
		if ($groupId === '') {
			continue;
		}

		$lineSumRow = inv_fetch_one(
			$db,
			"SELECT COALESCE(SUM(ext_price), 0) AS subtotal
			 FROM invoice_lines
			 WHERE deleted = 0 AND invoice_id = ? AND line_group_id = ?",
			'ss',
			[$invoiceId, $groupId]
		);
		$groupSubtotal = round((float)($lineSumRow['subtotal'] ?? 0), 2);

		$adjRows = inv_fetch_all(
			$db,
			"SELECT id, type, rate, amount, line_id
			 FROM invoice_adjustments
			 WHERE deleted = 0 AND invoice_id = ? AND line_group_id = ?
			 ORDER BY position ASC, id ASC",
			'ss',
			[$invoiceId, $groupId]
		);

		$groupTotal = $groupSubtotal;
		foreach ($adjRows as $adj) {
			$type = trim((string)($adj['type'] ?? ''));
			$lineId = trim((string)($adj['line_id'] ?? ''));
			$rate = (float)($adj['rate'] ?? 0);
			$amount = (float)($adj['amount'] ?? 0);
			$newAmount = $amount;

			if (($type === 'StandardTax' || $type === 'TaxedShipping') && $lineId === '') {
				if ($type === 'StandardTax') {
					$newAmount = round($groupSubtotal * ($rate / 100), 2);
				} else {
					$newAmount = round($amount, 2);
				}
				$stmtAdj = $db->prepare('UPDATE invoice_adjustments SET amount = ?, amount_usd = ?, date_modified = NOW() WHERE id = ? LIMIT 1');
				if ($stmtAdj) {
					$stmtAdj->bind_param('dds', $newAmount, $newAmount, $adj['id']);
					$stmtAdj->execute();
					$stmtAdj->close();
				}
				$groupTotal += $newAmount;
			}
		}

		$groupTotal = round($groupTotal, 2);
		$pretax += $groupSubtotal;
		$total += $groupTotal;

		$stmtGroup = $db->prepare('UPDATE invoice_line_groups SET subtotal = ?, subtotal_usd = ?, total = ?, total_usd = ?, date_modified = NOW() WHERE id = ? LIMIT 1');
		if ($stmtGroup) {
			$stmtGroup->bind_param('dddds', $groupSubtotal, $groupSubtotal, $groupTotal, $groupTotal, $groupId);
			$stmtGroup->execute();
			$stmtGroup->close();
		}
	}

	$pretax = round($pretax, 2);
	$total = round($total, 2);

	$paymentsRow = inv_fetch_one(
		$db,
		"SELECT COALESCE(SUM(ip.amount), 0) AS paid_sum
		 FROM invoices_payments ip
		 WHERE ip.deleted = 0 AND ip.invoice_id = ?",
		's',
		[$invoiceId]
	);
	$paidSum = round((float)($paymentsRow['paid_sum'] ?? 0), 2);
	$amountDue = round($total - $paidSum, 2);

	$stmtInv = $db->prepare('UPDATE invoice
		SET amount = ?, amount_usdollar = ?, amount_due = ?, amount_due_usdollar = ?,
			subtotal = ?, subtotal_usd = ?, pretax = ?, pretax_usd = ?,
			net_amount = ?, net_amount_usdollar = ?, date_modified = NOW()
		WHERE id = ? AND deleted = 0
		LIMIT 1');
	if ($stmtInv) {
		$stmtInv->bind_param('dddddddddds', $total, $total, $amountDue, $amountDue, $pretax, $pretax, $pretax, $pretax, $pretax, $pretax, $invoiceId);
		$stmtInv->execute();
		$stmtInv->close();
	}
}

function inv_sync_comments_from_sales_order(mysqli $db, string $invoiceId, string $salesOrderId, array $groupMap = []): void {
	$existingComments = inv_fetch_all(
		$db,
		"SELECT id FROM invoice_comments WHERE deleted = 0 AND invoice_id = ?",
		's',
		[$invoiceId]
	);
	foreach ($existingComments as $row) {
		$stmtDel = $db->prepare('UPDATE invoice_comments SET deleted = 1, date_modified = NOW() WHERE id = ? LIMIT 1');
		if ($stmtDel) {
			$stmtDel->bind_param('s', $row['id']);
			$stmtDel->execute();
			$stmtDel->close();
		}
	}

	$srcComments = inv_fetch_all(
		$db,
		"SELECT *
		 FROM sales_order_comments
		 WHERE deleted = 0 AND sales_orders_id = ?
		 ORDER BY position ASC, id ASC",
		's',
		[$salesOrderId]
	);
	$commentMap = [];
	$commentParentMap = [];
	foreach ($srcComments as $src) {
		$newCommentId = md5(uniqid((string)mt_rand(), true));
		$newCommentId = substr($newCommentId, 0, 8) . '-' . substr($newCommentId, 8, 4) . '-' . substr($newCommentId, 12, 4) . '-' . substr($newCommentId, 16, 4) . '-' . substr($newCommentId, 20, 12);
		$oldGroupId = (string)($src['line_group_id'] ?? '');
		$newGroupId = $groupMap[$oldGroupId] ?? $oldGroupId;
		$stmt = $db->prepare('INSERT INTO invoice_comments
			(id, date_entered, date_modified, deleted, invoice_id, line_group_id, name, position, parent_id, body)
			VALUES (?, NOW(), NOW(), 0, ?, ?, ?, ?, NULL, ?)');
		if ($stmt) {
			$stmt->bind_param('ssssis', $newCommentId, $invoiceId, $newGroupId, $src['name'], $src['position'], $src['body']);
			$stmt->execute();
			$stmt->close();
		}
		$oldCommentId = (string)($src['id'] ?? '');
		$commentMap[$oldCommentId] = $newCommentId;
		$commentParentMap[$oldCommentId] = trim((string)($src['parent_id'] ?? ''));
	}

	foreach ($commentParentMap as $oldCommentId => $oldParentId) {
		if ($oldParentId === '' || !isset($commentMap[$oldCommentId]) || !isset($commentMap[$oldParentId])) {
			continue;
		}
		$stmt = $db->prepare('UPDATE invoice_comments SET parent_id = ?, date_modified = NOW() WHERE id = ? LIMIT 1');
		if ($stmt) {
			$newParentId = $commentMap[$oldParentId];
			$newCommentId = $commentMap[$oldCommentId];
			$stmt->bind_param('ss', $newParentId, $newCommentId);
			$stmt->execute();
			$stmt->close();
		}
	}
}

function inv_save_manual_lines(mysqli $db, string $invoiceId, array $lineInput): int {
	$lineRows = inv_fetch_all(
		$db,
		"SELECT id, quantity, list_price, unit_price, pricing_adjust_id
		 FROM invoice_lines
		 WHERE deleted = 0 AND invoice_id = ?",
		's',
		[$invoiceId]
	);
	$lineMap = [];
	foreach ($lineRows as $row) {
		$lineMap[(string)$row['id']] = $row;
	}

	$updated = 0;
	foreach ($lineInput as $lineId => $row) {
		$lineId = trim((string)$lineId);
		if ($lineId === '' || !isset($lineMap[$lineId]) || !is_array($row)) {
			continue;
		}

		$current = $lineMap[$lineId];
		$name = trim((string)($row['name'] ?? $current['name'] ?? ''));
		$quantity = inv_normalize_qty($row['quantity'] ?? $current['quantity'] ?? null);
		$listPrice = inv_normalize_money($row['list_price'] ?? $current['list_price'] ?? null);
		$discountRate = inv_normalize_money($row['discount_rate'] ?? null);

		if ($quantity === null) {
			$quantity = (float)($current['quantity'] ?? 0);
		}
		if ($listPrice === null) {
			$listPrice = (float)($current['list_price'] ?? 0);
		}

		$pricingAdjustId = trim((string)($current['pricing_adjust_id'] ?? ''));
		$unitPrice = round($listPrice, 2);
		if ($pricingAdjustId !== '') {
			$adj = inv_fetch_one(
				$db,
				"SELECT id, type, rate, amount
				 FROM invoice_adjustments
				 WHERE deleted = 0 AND id = ?
				 LIMIT 1",
				's',
				[$pricingAdjustId]
			);
			if ($adj) {
				$type = trim((string)($adj['type'] ?? ''));
				if ($discountRate !== null && ($type === 'PercentDiscount' || $type === 'FixedDiscount' || $type === 'Fixed')) {
					$newRate = $discountRate;
					$stmtAdj = $db->prepare('UPDATE invoice_adjustments SET rate = ?, date_modified = NOW() WHERE id = ? LIMIT 1');
					if ($stmtAdj) {
						$stmtAdj->bind_param('ds', $newRate, $pricingAdjustId);
						$stmtAdj->execute();
						$stmtAdj->close();
					}
					if ($type === 'PercentDiscount') {
						$unitPrice = round($listPrice * (1 - ($newRate / 100)), 2);
					} elseif ($type === 'FixedDiscount' || $type === 'Fixed') {
						$unitPrice = round($listPrice - $newRate, 2);
					}
				} elseif ($type === 'PercentDiscount') {
					$unitPrice = round($listPrice * (1 - (((float)($adj['rate'] ?? 0)) / 100)), 2);
				} elseif ($type === 'FixedDiscount' || $type === 'Fixed') {
					$unitPrice = round($listPrice - (float)($adj['rate'] ?? 0), 2);
				}
			}
		}

		$extPrice = round($quantity * $unitPrice, 2);
		$stmtLine = $db->prepare('UPDATE invoice_lines
			SET name = ?, quantity = ?, ext_quantity = ?, list_price = ?, list_price_usd = ?,
				unit_price = ?, unit_price_usd = ?, std_unit_price = ?, std_unit_price_usd = ?,
				ext_price = ?, ext_price_usd = ?, net_price = ?, net_price_usd = ?, date_modified = NOW()
			WHERE id = ? AND invoice_id = ? AND deleted = 0
			LIMIT 1');
		if ($stmtLine) {
			$stmtLine->bind_param(
				'sddddddddddddss',
				$name,
				$quantity,
				$quantity,
				$listPrice,
				$listPrice,
				$unitPrice,
				$unitPrice,
				$unitPrice,
				$unitPrice,
				$extPrice,
				$extPrice,
				$extPrice,
				$extPrice,
				$lineId,
				$invoiceId
			);
			$stmtLine->execute();
			$stmtLine->close();
			$updated++;
		}
	}

	inv_recalculate_totals($db, $invoiceId);
	return $updated;
}

function inv_sync_from_sales_order(mysqli $db, string $invoiceId, string $salesOrderId): bool {
	if ($invoiceId === '' || $salesOrderId === '') {
		return false;
	}

	$srcGroups = inv_fetch_all(
		$db,
		"SELECT * FROM sales_order_line_groups WHERE parent_id = ? AND deleted = 0 ORDER BY position ASC, id ASC",
		's',
		[$salesOrderId]
	);
	$dstGroups = inv_fetch_all(
		$db,
		"SELECT * FROM invoice_line_groups WHERE parent_id = ? AND deleted = 0 ORDER BY position ASC, id ASC",
		's',
		[$invoiceId]
	);
	$srcGroupByPos = [];
	$dstGroupByPos = [];
	foreach ($srcGroups as $row) {
		$srcGroupByPos[(string)$row['position']] = $row;
	}
	foreach ($dstGroups as $row) {
		$dstGroupByPos[(string)$row['position']] = $row;
	}
	$groupMap = [];
	foreach ($dstGroupByPos as $position => $dst) {
		if (!isset($srcGroupByPos[$position])) {
			continue;
		}
		$src = $srcGroupByPos[$position];
		$groupMap[(string)$src['id']] = (string)$dst['id'];
		$stmt = $db->prepare('UPDATE invoice_line_groups
			SET name = ?, status = ?, pricing_method = ?, pricing_percentage = ?,
				cost = ?, cost_usd = ?, subtotal = ?, subtotal_usd = ?, total = ?, total_usd = ?, date_modified = NOW()
			WHERE id = ? LIMIT 1');
		if ($stmt) {
			$stmt->bind_param('sssddddddds', $src['name'], $src['status'], $src['pricing_method'], $src['pricing_percentage'], $src['cost'], $src['cost_usd'], $src['subtotal'], $src['subtotal_usd'], $src['total'], $src['total_usd'], $dst['id']);
			$stmt->execute();
			$stmt->close();
		}
	}

	$srcLines = inv_fetch_all(
		$db,
		"SELECT * FROM sales_order_lines WHERE sales_orders_id = ? AND deleted = 0 ORDER BY position ASC, id ASC",
		's',
		[$salesOrderId]
	);
	$dstLines = inv_fetch_all(
		$db,
		"SELECT * FROM invoice_lines WHERE invoice_id = ? AND deleted = 0 ORDER BY position ASC, id ASC",
		's',
		[$invoiceId]
	);
	$srcLineByPos = [];
	$dstLineByPos = [];
	$lineIdMap = [];
	foreach ($srcLines as $row) {
		$srcLineByPos[(string)$row['position']] = $row;
	}
	foreach ($dstLines as $row) {
		$dstLineByPos[(string)$row['position']] = $row;
	}
	foreach ($dstLineByPos as $position => $dst) {
		if (!isset($srcLineByPos[$position])) {
			continue;
		}
		$src = $srcLineByPos[$position];
		$lineIdMap[(string)$src['id']] = (string)$dst['id'];
		$stmt = $db->prepare('UPDATE invoice_lines
			SET pricing_adjust_id = ?, name = ?, parent_id = ?, quantity = ?, ext_quantity = ?,
				related_type = ?, related_id = ?, mfr_part_no = ?, serial_no = ?, serial_numbers = ?,
				tax_class_id = ?, sum_of_components = ?, cost_price = ?, cost_price_usd = ?,
				list_price = ?, list_price_usd = ?, unit_price = ?, unit_price_usd = ?,
				std_unit_price = ?, std_unit_price_usd = ?, ext_price = ?, ext_price_usd = ?,
				net_price = ?, net_price_usd = ?, pp_lineitem_id = ?, date_modified = NOW()
			WHERE id = ? LIMIT 1');
		if ($stmt) {
			$stmt->bind_param('sssddssssssidddddddddddsss', $src['pricing_adjust_id'], $src['name'], $src['parent_id'], $src['quantity'], $src['ext_quantity'], $src['related_type'], $src['related_id'], $src['mfr_part_no'], $src['serial_no'], $src['serial_numbers'], $src['tax_class_id'], $src['sum_of_components'], $src['cost_price'], $src['cost_price_usd'], $src['list_price'], $src['list_price_usd'], $src['unit_price'], $src['unit_price_usd'], $src['std_unit_price'], $src['std_unit_price_usd'], $src['ext_price'], $src['ext_price_usd'], $src['net_price'], $src['net_price_usd'], $src['id'], $dst['id']);
			$stmt->execute();
			$stmt->close();
		}
	}

	$srcAdj = inv_fetch_all(
		$db,
		"SELECT * FROM sales_order_adjustments WHERE sales_orders_id = ? AND deleted = 0 ORDER BY position ASC, id ASC",
		's',
		[$salesOrderId]
	);
	$dstAdj = inv_fetch_all(
		$db,
		"SELECT * FROM invoice_adjustments WHERE invoice_id = ? AND deleted = 0 ORDER BY position ASC, id ASC",
		's',
		[$invoiceId]
	);
	$srcAdjByPos = [];
	$dstAdjByPos = [];
	$adjustIdMap = [];
	foreach ($srcAdj as $row) {
		$srcAdjByPos[(string)$row['position']] = $row;
	}
	foreach ($dstAdj as $row) {
		$dstAdjByPos[(string)$row['position']] = $row;
	}
	foreach ($dstAdjByPos as $position => $dst) {
		if (!isset($srcAdjByPos[$position])) {
			continue;
		}
		$src = $srcAdjByPos[$position];
		$newLineId = null;
		$srcLineId = trim((string)($src['line_id'] ?? ''));
		if ($srcLineId !== '' && isset($lineIdMap[$srcLineId])) {
			$newLineId = $lineIdMap[$srcLineId];
		}
		$stmt = $db->prepare('UPDATE invoice_adjustments
			SET line_id = ?, name = ?, related_type = ?, related_id = ?, rate = ?, type = ?,
				amount = ?, amount_usd = ?, tax_class_id = ?, pp_lineadjust_id = ?, date_modified = NOW()
			WHERE id = ? LIMIT 1');
		if ($stmt) {
			$stmt->bind_param('ssssdsddsss', $newLineId, $src['name'], $src['related_type'], $src['related_id'], $src['rate'], $src['type'], $src['amount'], $src['amount_usd'], $src['tax_class_id'], $src['id'], $dst['id']);
			$stmt->execute();
			$stmt->close();
		}
		$adjustIdMap[(string)$src['id']] = (string)$dst['id'];
	}

	foreach ($dstLineByPos as $position => $dst) {
		if (!isset($srcLineByPos[$position])) {
			continue;
		}
		$oldAdjustId = trim((string)($srcLineByPos[$position]['pricing_adjust_id'] ?? ''));
		if ($oldAdjustId === '' || !isset($adjustIdMap[$oldAdjustId])) {
			continue;
		}
		$newAdjustId = $adjustIdMap[$oldAdjustId];
		$stmt = $db->prepare('UPDATE invoice_lines SET pricing_adjust_id = ?, date_modified = NOW() WHERE id = ? LIMIT 1');
		if ($stmt) {
			$stmt->bind_param('ss', $newAdjustId, $dst['id']);
			$stmt->execute();
			$stmt->close();
		}
	}

	$so = inv_fetch_one(
		$db,
		"SELECT amount, amount_usdollar, subtotal, subtotal_usd, pretax, pretax_usd, gross_profit_so, gross_profit_so_usd
		 FROM sales_orders WHERE id = ? LIMIT 1",
		's',
		[$salesOrderId]
	);
	if ($so) {
		$stmt = $db->prepare('UPDATE invoice
			SET amount = ?, amount_usdollar = ?, amount_due = ?, amount_due_usdollar = ?,
				subtotal = ?, subtotal_usd = ?, pretax = ?, pretax_usd = ?,
				gross_profit = ?, gross_profit_usdollar = ?,
				net_amount = ?, net_amount_usdollar = ?, date_modified = NOW()
			WHERE id = ? LIMIT 1');
		if ($stmt) {
			$stmt->bind_param('dddddddddddds', $so['amount'], $so['amount_usdollar'], $so['amount'], $so['amount_usdollar'], $so['subtotal'], $so['subtotal_usd'], $so['pretax'], $so['pretax_usd'], $so['gross_profit_so'], $so['gross_profit_so_usd'], $so['pretax'], $so['pretax_usd'], $invoiceId);
			$stmt->execute();
			$stmt->close();
		}
	}

	inv_sync_comments_from_sales_order($db, $invoiceId, $salesOrderId, $groupMap);
	inv_recalculate_totals($db, $invoiceId);
	return true;
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
$requestMethod = (string)($_SERVER['REQUEST_METHOD'] ?? 'GET');
$flash = '';
$errors = [];

if ($requestMethod === 'POST') {
	$invoiceId = trim((string)($_POST['invoice_id'] ?? $invoiceId));
	$action = trim((string)($_POST['action'] ?? ''));
	if ($invoiceId !== '') {
		$invoiceBase = inv_fetch_one(
			$mysqli,
			"SELECT id, from_so_id, billing_account_id, prefix, invoice_number
			 FROM invoice
			 WHERE deleted = 0 AND id = ?
			 LIMIT 1",
			's',
			[$invoiceId]
		);
		if (!$invoiceBase) {
			$errors[] = 'Rechnung nicht gefunden.';
		} else {
			$mysqli->begin_transaction();
			try {
				if ($action === 'save_positions') {
					$updatedCount = inv_save_manual_lines($mysqli, $invoiceId, $_POST['lines'] ?? []);
					inv_sync_account_balance($mysqli, (string)($invoiceBase['billing_account_id'] ?? ''));
					$flash = $updatedCount > 0 ? 'Positionen gespeichert, Rechnung neu berechnet und Firmensaldo synchronisiert.' : 'Keine Positionsänderung erkannt.';
				} elseif ($action === 'sync_from_order') {
					$salesOrderId = trim((string)($invoiceBase['from_so_id'] ?? ''));
					if ($salesOrderId === '') {
						throw new RuntimeException('Keine verknüpfte AB vorhanden.');
					}
					inv_sync_from_sales_order($mysqli, $invoiceId, $salesOrderId);
					inv_sync_account_balance($mysqli, (string)($invoiceBase['billing_account_id'] ?? ''));
					$flash = 'Rechnung wurde aus der verknüpften AB übernommen und der Firmensaldo wurde synchronisiert.';
				} elseif ($action === 'sync_account_balance') {
					inv_recalculate_totals($mysqli, $invoiceId);
					inv_sync_account_balance($mysqli, (string)($invoiceBase['billing_account_id'] ?? ''));
					$flash = 'Rechnungssummen und Firmensaldo wurden synchronisiert.';
				} elseif ($action === 'delete_invoice') {
					$deleteInfo = inv_delete_invoice($mysqli, $invoiceBase);
					$invoiceCode = trim((string)($invoiceBase['prefix'] ?? '') . (string)($invoiceBase['invoice_number'] ?? ''));
					$flash = 'Rechnung ' . $invoiceCode . ' wurde gelöscht.';
					if (($deleteInfo['payment_links_removed'] ?? 0) > 0) {
						$flash .= ' Verknüpfte Zahlungen wurden von der Rechnung gelöst.';
					}
					if (!empty($deleteInfo['freed_automatically'])) {
						$flash .= ' Die Rechnungsnummer ist für den nächsten neu erzeugten Beleg wieder frei.';
					} else {
						$flash .= ' Die Nummer wurde nicht automatisch in die 1CRM-Sequenz zurückgesetzt, weil sie nicht der letzte freie Folgewert der aktuellen Reihe war.';
					}
					$invoiceId = '';
					$invoice = null;
				}
				$mysqli->commit();
			} catch (Throwable $e) {
				$mysqli->rollback();
				$errors[] = $e->getMessage();
			}
		}
	}
}

$invoice = null;
$invoiceLines = [];
$invoiceComments = [];
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
			$invoiceComments = inv_fetch_all(
				$mysqli,
				"SELECT id, position, name, body
				 FROM invoice_comments
				 WHERE deleted = 0 AND invoice_id = ?
				 ORDER BY position ASC, id ASC",
				's',
				[$invoiceId]
			);

			$invoiceLines = inv_fetch_all(
				$mysqli,
			"SELECT il.id, il.line_group_id, il.position, il.name, il.quantity, il.list_price, il.unit_price, il.ext_price, il.net_price,
			        il.pricing_adjust_id, ia.type AS discount_type, ia.rate AS discount_rate
			 FROM invoice_lines il
			 LEFT JOIN invoice_adjustments ia ON ia.id = il.pricing_adjust_id AND ia.deleted = 0
			 WHERE il.deleted = 0 AND il.invoice_id = ?
			 ORDER BY il.line_group_id ASC, il.position ASC, il.date_entered ASC",
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
			<?php if ($flash !== ''): ?>
				<div class="alert alert-success"><?php echo htmlspecialchars($flash); ?></div>
			<?php endif; ?>
			<?php foreach ($errors as $err): ?>
				<div class="alert alert-danger"><?php echo htmlspecialchars((string)$err); ?></div>
			<?php endforeach; ?>

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
				<div class="card-header"><strong>Aktionen</strong></div>
				<div class="card-body">
					<div class="d-flex flex-wrap gap-2">
						<form method="post" class="m-0">
							<input type="hidden" name="invoice_id" value="<?php echo htmlspecialchars($invoiceId); ?>">
							<input type="hidden" name="action" value="sync_account_balance">
							<button type="submit" class="btn btn-sm btn-outline-secondary">
								<i class="fas fa-sync-alt me-1"></i> Rechnung + Firmensaldo synchronisieren
							</button>
						</form>
						<?php if (trim((string)($invoice['from_so_id'] ?? '')) !== ''): ?>
							<form method="post" class="m-0" onsubmit="return confirm('Rechnung wirklich aus der verknüpften AB neu übernehmen? Manuelle Positionsänderungen auf dieser Rechnung werden überschrieben.');">
								<input type="hidden" name="invoice_id" value="<?php echo htmlspecialchars($invoiceId); ?>">
								<input type="hidden" name="action" value="sync_from_order">
								<button type="submit" class="btn btn-sm btn-warning">
									<i class="fas fa-file-import me-1"></i> Positionen aus AB übernehmen
								</button>
							</form>
						<?php endif; ?>
						<a class="btn btn-sm btn-outline-primary" href="<?php echo '../update_invoice.php?invoice_id=' . urlencode((string)$invoice['id']); ?>">
							<i class="fas fa-tools me-1"></i> Erweiterte Zahlungs-/Saldoansicht
						</a>
						<?php if (trim((string)($invoice['billing_account_id'] ?? '')) !== ''): ?>
							<a class="btn btn-sm btn-outline-primary" href="<?php echo '../offene_betraege_pruefung.php?account_id=' . urlencode((string)$invoice['billing_account_id']); ?>">
								<i class="fas fa-balance-scale me-1"></i> Konto-Prüfung öffnen
							</a>
						<?php endif; ?>
						<form method="post" class="m-0" onsubmit="return confirm('Rechnung wirklich löschen? Positionen und Verknüpfungen werden deaktiviert. Die Rechnungsnummer wird nur dann automatisch wieder freigegeben, wenn es die letzte Nummer der aktuellen Reihe ist.');">
							<input type="hidden" name="invoice_id" value="<?php echo htmlspecialchars($invoiceId); ?>">
							<input type="hidden" name="action" value="delete_invoice">
							<button type="submit" class="btn btn-sm btn-outline-danger">
								<i class="fas fa-trash-alt me-1"></i> Rechnung löschen
							</button>
						</form>
					</div>
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
					<form method="post">
						<input type="hidden" name="invoice_id" value="<?php echo htmlspecialchars($invoiceId); ?>">
						<input type="hidden" name="action" value="save_positions">
						<div class="table-responsive">
							<table class="table table-sm table-striped align-middle">
							<thead>
								<tr>
									<th>#</th>
									<th>Bezeichnung</th>
									<th class="text-end">Menge</th>
									<th class="text-end">Listenpreis</th>
									<th class="text-end">Rabatt %</th>
									<th class="text-end">Einzelpreis</th>
									<th class="text-end">Gesamt</th>
									<th class="text-end">Netto</th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ($invoiceLines as $idx => $line): ?>
									<tr>
										<td><?php echo (int)$idx + 1; ?></td>
										<td>
											<input type="text" class="form-control form-control-sm" name="lines[<?php echo htmlspecialchars((string)$line['id']); ?>][name]" value="<?php echo htmlspecialchars((string)($line['name'] ?? '')); ?>">
										</td>
										<td class="text-end">
											<input type="text" class="form-control form-control-sm text-end" name="lines[<?php echo htmlspecialchars((string)$line['id']); ?>][quantity]" value="<?php echo isset($line['quantity']) ? htmlspecialchars(number_format((float)$line['quantity'], 2, ',', '.')) : ''; ?>">
										</td>
										<td class="text-end">
											<input type="text" class="form-control form-control-sm text-end" name="lines[<?php echo htmlspecialchars((string)$line['id']); ?>][list_price]" value="<?php echo isset($line['list_price']) ? htmlspecialchars(number_format((float)$line['list_price'], 2, ',', '.')) : ''; ?>">
										</td>
										<td class="text-end">
											<input type="text" class="form-control form-control-sm text-end" name="lines[<?php echo htmlspecialchars((string)$line['id']); ?>][discount_rate]" value="<?php echo isset($line['discount_rate']) && ($line['discount_type'] === 'PercentDiscount' || $line['discount_type'] === 'FixedDiscount' || $line['discount_type'] === 'Fixed') ? htmlspecialchars(number_format((float)$line['discount_rate'], 2, ',', '.')) : ''; ?>">
										</td>
										<td class="text-end"><?php echo isset($line['unit_price']) ? number_format((float)$line['unit_price'], 2, ',', '.') : ''; ?></td>
										<td class="text-end"><?php echo isset($line['ext_price']) ? number_format((float)$line['ext_price'], 2, ',', '.') : ''; ?></td>
										<td class="text-end"><?php echo isset($line['net_price']) ? number_format((float)$line['net_price'], 2, ',', '.') : ''; ?></td>
									</tr>
								<?php endforeach; ?>
								<?php if (!$invoiceLines): ?>
									<tr><td colspan="8" class="text-muted">Keine Positionen gefunden.</td></tr>
								<?php endif; ?>
							</tbody>
							</table>
						</div>
						<div class="d-flex justify-content-between flex-wrap gap-2">
							<div class="small text-muted">Änderbar: Bezeichnung, Menge, Listenpreis und Rabatt. Danach werden Rechnungsbetrag, Offenbetrag und Firmensaldo neu berechnet.</div>
							<button type="submit" class="btn btn-sm btn-primary">
								<i class="fas fa-save me-1"></i> Positionen speichern
							</button>
						</div>
					</form>
				</div>
			</div>

			<div class="card shadow-sm mb-3">
				<div class="card-header"><strong>Kommentare</strong> <span class="text-muted">(<?php echo count($invoiceComments); ?>)</span></div>
				<div class="card-body">
					<?php if ($invoiceComments): ?>
						<ul class="list-group list-group-flush">
							<?php foreach ($invoiceComments as $comment): ?>
								<li class="list-group-item px-0">
									<div class="small text-muted">Pos. <?php echo (int)($comment['position'] ?? 0); ?></div>
									<div><?php echo nl2br(htmlspecialchars((string)($comment['body'] ?? ''))); ?></div>
								</li>
							<?php endforeach; ?>
						</ul>
					<?php else: ?>
						<div class="text-muted">Keine Kommentare gefunden.</div>
					<?php endif; ?>
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
