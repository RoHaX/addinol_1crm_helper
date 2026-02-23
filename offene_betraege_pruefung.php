<?php
require_once __DIR__ . '/db.inc.php';

$link = $mysqli ?? null;
if (!$link instanceof mysqli) {
	die('DB connection missing');
}
mysqli_set_charset($link, 'utf8');

function esc(mysqli $db, string $value): string {
	return mysqli_real_escape_string($db, $value);
}

function fetch_one_assoc(mysqli $db, string $sql): ?array {
	$res = mysqli_query($db, $sql);
	if (!$res) {
		return null;
	}
	$row = mysqli_fetch_assoc($res);
	return $row ?: null;
}

function fetch_all_assoc_local(mysqli $db, string $sql): array {
	$out = [];
	$res = mysqli_query($db, $sql);
	if (!$res) {
		return $out;
	}
	while ($row = mysqli_fetch_assoc($res)) {
		$out[] = $row;
	}
	return $out;
}

function ids_to_in_clause(mysqli $db, array $ids): string {
	$clean = [];
	foreach ($ids as $id) {
		$id = trim((string)$id);
		if ($id !== '' && preg_match('/^[a-f0-9-]{36}$/i', $id)) {
			$clean[] = "'" . mysqli_real_escape_string($db, $id) . "'";
		}
	}
	$clean = array_values(array_unique($clean));
	if (!$clean) {
		return '';
	}
	return implode(',', $clean);
}

function local_balance_log_path(): string {
	return __DIR__ . '/logs/balance_korrekturen.log';
}

function append_local_balance_log(array $row): void {
	$path = local_balance_log_path();
	$dir = dirname($path);
	if (!is_dir($dir)) {
		@mkdir($dir, 0775, true);
	}
	$json = json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	if ($json === false) {
		return;
	}
	@file_put_contents($path, $json . PHP_EOL, FILE_APPEND | LOCK_EX);
}

function read_local_balance_logs(string $accountId, int $limit = 200): array {
	$path = local_balance_log_path();
	if (!is_file($path)) {
		return [];
	}
	$lines = @file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
	if (!is_array($lines)) {
		return [];
	}
	$out = [];
	for ($i = count($lines) - 1; $i >= 0; $i--) {
		$decoded = json_decode((string)$lines[$i], true);
		if (!is_array($decoded)) {
			continue;
		}
		if ((string)($decoded['account_id'] ?? '') !== $accountId) {
			continue;
		}
		$out[] = $decoded;
		if (count($out) >= $limit) {
			break;
		}
	}
	return $out;
}

$accountId = trim((string)($_GET['account_id'] ?? $_POST['account_id'] ?? ''));
$flash = '';
$errors = [];

$requestMethod = (string)($_SERVER['REQUEST_METHOD'] ?? 'GET');
if ($accountId !== '' && $requestMethod === 'POST') {
	$safeId = esc($link, $accountId);
	$action = trim((string)($_POST['action'] ?? ''));

	$sumRow = fetch_one_assoc(
		$link,
		"SELECT COALESCE(SUM(amount_due), 0) AS open_sum
		 FROM invoice
		 WHERE deleted = 0 AND billing_account_id = '" . $safeId . "'"
	);
	$openSum = (float)($sumRow['open_sum'] ?? 0.0);
	$currentBalRow = fetch_one_assoc(
		$link,
		"SELECT balance, name
		 FROM accounts
		 WHERE id = '" . $safeId . "'
		 LIMIT 1"
	);
	$beforeBalance = (float)($currentBalRow['balance'] ?? 0.0);
	$accountNameForLog = (string)($currentBalRow['name'] ?? '');

	if ($action === 'sync_balance_to_invoice_sum') {
		$targetBalance = $openSum;
		$sql = "UPDATE accounts
		        SET balance = " . number_format($targetBalance, 2, '.', '') . "
		        WHERE id = '" . $safeId . "'";
		if (mysqli_query($link, $sql)) {
			$flash = 'Saldo wurde auf Summe offener Rechnungen gesetzt: ' . number_format($openSum, 2, ',', '.');
			$afterRow = fetch_one_assoc($link, "SELECT balance FROM accounts WHERE id = '" . $safeId . "' LIMIT 1");
			$afterBalance = (float)($afterRow['balance'] ?? $targetBalance);
			append_local_balance_log([
				'ts' => date('c'),
				'account_id' => $accountId,
				'account_name' => $accountNameForLog,
				'action' => 'sync_balance_to_invoice_sum',
				'before_balance' => $beforeBalance,
				'target_balance' => $targetBalance,
				'after_balance' => $afterBalance,
				'invoice_due_sum' => $openSum,
				'ip' => (string)($_SERVER['REMOTE_ADDR'] ?? ''),
				'user_agent' => (string)($_SERVER['HTTP_USER_AGENT'] ?? ''),
			]);
		} else {
			$errors[] = 'Korrektur fehlgeschlagen: ' . mysqli_error($link);
		}
	} elseif ($action === 'set_balance_zero') {
		$targetBalance = 0.0;
		$sql = "UPDATE accounts
		        SET balance = 0
		        WHERE id = '" . $safeId . "'";
		if (mysqli_query($link, $sql)) {
			$flash = 'Saldo wurde auf 0 gesetzt.';
			$afterRow = fetch_one_assoc($link, "SELECT balance FROM accounts WHERE id = '" . $safeId . "' LIMIT 1");
			$afterBalance = (float)($afterRow['balance'] ?? $targetBalance);
			append_local_balance_log([
				'ts' => date('c'),
				'account_id' => $accountId,
				'account_name' => $accountNameForLog,
				'action' => 'set_balance_zero',
				'before_balance' => $beforeBalance,
				'target_balance' => $targetBalance,
				'after_balance' => $afterBalance,
				'invoice_due_sum' => $openSum,
				'ip' => (string)($_SERVER['REMOTE_ADDR'] ?? ''),
				'user_agent' => (string)($_SERVER['HTTP_USER_AGENT'] ?? ''),
			]);
		} else {
			$errors[] = 'Korrektur fehlgeschlagen: ' . mysqli_error($link);
		}
	}
}

$account = null;
$stats = [];
$openInvoices = [];
$negativeDueInvoices = [];
$orphanPayments = [];
$linkedPayments = [];
$historyRows = [];
$creditNotes = [];
$skontoPayments = [];
$unappliedPayments = [];
$causeStats = [];

if ($accountId !== '') {
	$safeId = esc($link, $accountId);
	$account = fetch_one_assoc(
		$link,
		"SELECT id, ticker_symbol, name, account_type, balance, billing_address_city, email1, phone_office
		 FROM accounts
		 WHERE id = '" . $safeId . "'
		 LIMIT 1"
	);

	if ($account) {
		$stats = fetch_one_assoc(
			$link,
			"SELECT
				(SELECT COALESCE(SUM(i.amount_due), 0) FROM invoice i WHERE i.deleted = 0 AND i.billing_account_id = '" . $safeId . "') AS invoice_due_sum,
				(SELECT COUNT(*) FROM invoice i WHERE i.deleted = 0 AND i.billing_account_id = '" . $safeId . "') AS invoice_count,
				(SELECT COUNT(*) FROM invoice i WHERE i.deleted = 0 AND i.billing_account_id = '" . $safeId . "' AND i.amount_due <> 0) AS invoice_open_count,
				(SELECT COUNT(*) FROM invoice i WHERE i.deleted = 0 AND i.billing_account_id = '" . $safeId . "' AND i.amount_due < 0) AS invoice_negative_due_count,
				(SELECT COUNT(*) FROM payments p WHERE p.deleted = 0 AND p.account_id = '" . $safeId . "') AS payment_count,
				(SELECT COALESCE(SUM(p.amount), 0) FROM payments p WHERE p.deleted = 0 AND p.account_id = '" . $safeId . "') AS payment_sum"
		) ?: [];

		$openInvoices = fetch_all_assoc_local(
			$link,
			"SELECT id, CONCAT(COALESCE(prefix,''), invoice_number) AS invoice_no, invoice_date, due_date, amount, amount_due
			 FROM invoice
			 WHERE deleted = 0
			   AND billing_account_id = '" . $safeId . "'
			   AND amount_due <> 0
			 ORDER BY due_date ASC, invoice_date DESC"
		);

		$negativeDueInvoices = fetch_all_assoc_local(
			$link,
			"SELECT id, CONCAT(COALESCE(prefix,''), invoice_number) AS invoice_no, invoice_date, due_date, amount_due
			 FROM invoice
			 WHERE deleted = 0
			   AND billing_account_id = '" . $safeId . "'
			   AND amount_due < 0
			 ORDER BY amount_due ASC"
		);

		$orphanPayments = fetch_all_assoc_local(
			$link,
			"SELECT p.id, p.payment_date, p.direction, p.payment_type, p.amount, p.customer_reference, p.related_invoice_id
			 FROM payments p
			 LEFT JOIN invoices_payments ip ON ip.payment_id = p.id AND ip.deleted = 0
			 WHERE p.deleted = 0
			   AND p.account_id = '" . $safeId . "'
			   AND ip.payment_id IS NULL
			 ORDER BY p.payment_date DESC"
		);

			$linkedPayments = fetch_all_assoc_local(
				$link,
			"SELECT p.id, p.payment_date, p.direction, p.payment_type, p.amount, p.customer_reference,
			        ip.invoice_id, CONCAT(COALESCE(i.prefix,''), i.invoice_number) AS invoice_no
			 FROM payments p
			 INNER JOIN invoices_payments ip ON ip.payment_id = p.id AND ip.deleted = 0
			 LEFT JOIN invoice i ON i.id = ip.invoice_id
			 WHERE p.deleted = 0
			   AND p.account_id = '" . $safeId . "'
			 ORDER BY p.payment_date DESC
				 LIMIT 200"
			);

			$causeStats = fetch_one_assoc(
				$link,
				"SELECT
					(SELECT COUNT(*) FROM credit_notes c WHERE c.deleted = 0 AND c.billing_account_id = '" . $safeId . "') AS credit_count,
					(SELECT COALESCE(SUM(c.amount), 0) FROM credit_notes c WHERE c.deleted = 0 AND c.billing_account_id = '" . $safeId . "') AS credit_amount_sum,
					(SELECT COALESCE(SUM(c.amount_due), 0) FROM credit_notes c WHERE c.deleted = 0 AND c.billing_account_id = '" . $safeId . "') AS credit_due_sum,
					(SELECT COUNT(*) FROM payments p WHERE p.deleted = 0 AND p.account_id = '" . $safeId . "' AND p.direction = 'incoming' AND p.payment_type = 'Skonto') AS skonto_count,
					(SELECT COALESCE(SUM(p.amount), 0) FROM payments p WHERE p.deleted = 0 AND p.account_id = '" . $safeId . "' AND p.direction = 'incoming' AND p.payment_type = 'Skonto') AS skonto_sum,
					(SELECT COUNT(*) FROM (
						SELECT p.id
						FROM payments p
						LEFT JOIN (
							SELECT payment_id, SUM(amount) AS applied_sum
							FROM invoices_payments
							WHERE deleted = 0
							GROUP BY payment_id
						) x ON x.payment_id = p.id
						WHERE p.deleted = 0
						  AND p.account_id = '" . $safeId . "'
						  AND (p.amount - COALESCE(x.applied_sum, 0)) > 0.009
					) t) AS unapplied_count,
					(SELECT COALESCE(SUM(t.diff), 0) FROM (
						SELECT (p.amount - COALESCE(x.applied_sum, 0)) AS diff
						FROM payments p
						LEFT JOIN (
							SELECT payment_id, SUM(amount) AS applied_sum
							FROM invoices_payments
							WHERE deleted = 0
							GROUP BY payment_id
						) x ON x.payment_id = p.id
						WHERE p.deleted = 0
						  AND p.account_id = '" . $safeId . "'
						  AND (p.amount - COALESCE(x.applied_sum, 0)) > 0.009
					) t) AS unapplied_sum"
			) ?: [];

			$creditNotes = fetch_all_assoc_local(
				$link,
				"SELECT c.id, CONCAT(COALESCE(c.prefix,''), c.credit_number) AS credit_no, c.due_date, c.amount, c.amount_due,
				        CONCAT(COALESCE(i.prefix,''), i.invoice_number) AS invoice_no
				 FROM credit_notes c
				 LEFT JOIN invoice i ON i.id = c.invoice_id
				 WHERE c.deleted = 0
				   AND c.billing_account_id = '" . $safeId . "'
				 ORDER BY c.due_date DESC, c.date_modified DESC
				 LIMIT 25"
			);

			$skontoPayments = fetch_all_assoc_local(
				$link,
				"SELECT p.id, p.payment_date, p.amount, p.customer_reference,
				        COALESCE(SUM(ip.amount), 0) AS applied_sum
				 FROM payments p
				 LEFT JOIN invoices_payments ip ON ip.payment_id = p.id AND ip.deleted = 0
				 WHERE p.deleted = 0
				   AND p.account_id = '" . $safeId . "'
				   AND p.direction = 'incoming'
				   AND p.payment_type = 'Skonto'
				 GROUP BY p.id, p.payment_date, p.amount, p.customer_reference
				 ORDER BY p.payment_date DESC, p.date_modified DESC
				 LIMIT 25"
			);

			$unappliedPayments = fetch_all_assoc_local(
				$link,
				"SELECT p.id, p.payment_date, p.payment_type, p.amount, p.customer_reference,
				        COALESCE(x.applied_sum, 0) AS applied_sum,
				        (p.amount - COALESCE(x.applied_sum, 0)) AS unapplied_diff
				 FROM payments p
				 LEFT JOIN (
					SELECT payment_id, SUM(amount) AS applied_sum
					FROM invoices_payments
					WHERE deleted = 0
					GROUP BY payment_id
				 ) x ON x.payment_id = p.id
				 WHERE p.deleted = 0
				   AND p.account_id = '" . $safeId . "'
				   AND (p.amount - COALESCE(x.applied_sum, 0)) > 0.009
				 ORDER BY unapplied_diff DESC, p.payment_date DESC
				 LIMIT 25"
			);

		$accountAuditRows = fetch_all_assoc_local(
			$link,
			"SELECT a.date_created, a.field_name,
			        COALESCE(NULLIF(a.before_value_string, ''), LEFT(COALESCE(a.before_value_text, ''), 255)) AS before_value,
			        COALESCE(NULLIF(a.after_value_string, ''), LEFT(COALESCE(a.after_value_text, ''), 255)) AS after_value,
			        COALESCE(NULLIF(TRIM(CONCAT(COALESCE(u.first_name,''), ' ', COALESCE(u.last_name,''))), ''), u.user_name, a.created_by) AS user_label
			 FROM accounts_audit a
			 LEFT JOIN users u ON u.id = a.created_by
			 WHERE a.parent_id = '" . $safeId . "'
			 ORDER BY a.date_created DESC
			 LIMIT 200"
		);
		foreach ($accountAuditRows as $row) {
			$historyRows[] = [
				'ts' => (string)($row['date_created'] ?? ''),
				'source' => 'Konto',
				'object' => (string)($account['name'] ?? ''),
				'field' => (string)($row['field_name'] ?? ''),
				'before' => (string)($row['before_value'] ?? ''),
				'after' => (string)($row['after_value'] ?? ''),
				'user' => (string)($row['user_label'] ?? ''),
			];
		}

		$invoiceMap = [];
		$invoiceIds = [];
		$allInvoiceRows = fetch_all_assoc_local(
			$link,
			"SELECT id, CONCAT(COALESCE(prefix,''), invoice_number) AS invoice_no
			 FROM invoice
			 WHERE billing_account_id = '" . $safeId . "'"
		);
		foreach ($allInvoiceRows as $inv) {
			$iid = (string)($inv['id'] ?? '');
			if ($iid !== '') {
				$invoiceMap[$iid] = (string)($inv['invoice_no'] ?? $iid);
				$invoiceIds[] = $iid;
			}
		}
		$invoiceIn = ids_to_in_clause($link, $invoiceIds);
		if ($invoiceIn !== '') {
			$invoiceAuditRows = fetch_all_assoc_local(
				$link,
				"SELECT a.parent_id, a.date_created, a.field_name,
				        COALESCE(NULLIF(a.before_value_string, ''), LEFT(COALESCE(a.before_value_text, ''), 255)) AS before_value,
				        COALESCE(NULLIF(a.after_value_string, ''), LEFT(COALESCE(a.after_value_text, ''), 255)) AS after_value,
				        COALESCE(NULLIF(TRIM(CONCAT(COALESCE(u.first_name,''), ' ', COALESCE(u.last_name,''))), ''), u.user_name, a.created_by) AS user_label
				 FROM invoice_audit a
				 LEFT JOIN users u ON u.id = a.created_by
				 WHERE a.parent_id IN (" . $invoiceIn . ")
				 ORDER BY a.date_created DESC
				 LIMIT 400"
			);
			foreach ($invoiceAuditRows as $row) {
				$pid = (string)($row['parent_id'] ?? '');
				$historyRows[] = [
					'ts' => (string)($row['date_created'] ?? ''),
					'source' => 'Rechnung',
					'object' => (string)($invoiceMap[$pid] ?? $pid),
					'field' => (string)($row['field_name'] ?? ''),
					'before' => (string)($row['before_value'] ?? ''),
					'after' => (string)($row['after_value'] ?? ''),
					'user' => (string)($row['user_label'] ?? ''),
				];
			}
		}

		$paymentMap = [];
		$paymentIds = [];
		$allPaymentRows = fetch_all_assoc_local(
			$link,
			"SELECT id, CONCAT(COALESCE(prefix,''), payment_id) AS payment_no
			 FROM payments
			 WHERE account_id = '" . $safeId . "'"
		);
		foreach ($allPaymentRows as $pay) {
			$pid = (string)($pay['id'] ?? '');
			if ($pid !== '') {
				$paymentMap[$pid] = (string)($pay['payment_no'] ?? $pid);
				$paymentIds[] = $pid;
			}
		}
		$paymentIn = ids_to_in_clause($link, $paymentIds);
		if ($paymentIn !== '') {
			$paymentAuditRows = fetch_all_assoc_local(
				$link,
				"SELECT a.parent_id, a.date_created, a.field_name,
				        COALESCE(NULLIF(a.before_value_string, ''), LEFT(COALESCE(a.before_value_text, ''), 255)) AS before_value,
				        COALESCE(NULLIF(a.after_value_string, ''), LEFT(COALESCE(a.after_value_text, ''), 255)) AS after_value,
				        COALESCE(NULLIF(TRIM(CONCAT(COALESCE(u.first_name,''), ' ', COALESCE(u.last_name,''))), ''), u.user_name, a.created_by) AS user_label
				 FROM payments_audit a
				 LEFT JOIN users u ON u.id = a.created_by
				 WHERE a.parent_id IN (" . $paymentIn . ")
				 ORDER BY a.date_created DESC
				 LIMIT 400"
			);
			foreach ($paymentAuditRows as $row) {
				$pid = (string)($row['parent_id'] ?? '');
				$historyRows[] = [
					'ts' => (string)($row['date_created'] ?? ''),
					'source' => 'Zahlung',
					'object' => (string)($paymentMap[$pid] ?? $pid),
					'field' => (string)($row['field_name'] ?? ''),
					'before' => (string)($row['before_value'] ?? ''),
					'after' => (string)($row['after_value'] ?? ''),
					'user' => (string)($row['user_label'] ?? ''),
				];
			}
		}

		usort($historyRows, static function (array $a, array $b): int {
			return strcmp((string)($b['ts'] ?? ''), (string)($a['ts'] ?? ''));
		});
		if (count($historyRows) > 500) {
			$historyRows = array_slice($historyRows, 0, 500);
		}

		$localLogs = read_local_balance_logs($accountId, 200);
		foreach ($localLogs as $row) {
			$actionLabel = ((string)($row['action'] ?? '') === 'set_balance_zero') ? 'Saldo auf 0 gesetzt' : 'Saldo mit Rechnungssumme synchronisiert';
			$historyRows[] = [
				'ts' => (string)($row['ts'] ?? ''),
				'source' => 'Korrekturtool',
				'object' => (string)($row['account_name'] ?? $accountId),
				'field' => 'balance',
				'before' => (string)number_format((float)($row['before_balance'] ?? 0), 2, '.', ''),
				'after' => (string)number_format((float)($row['after_balance'] ?? 0), 2, '.', ''),
				'user' => $actionLabel . ((string)($row['ip'] ?? '') !== '' ? ' @ ' . (string)$row['ip'] : ''),
			];
		}
		usort($historyRows, static function (array $a, array $b): int {
			return strcmp((string)($b['ts'] ?? ''), (string)($a['ts'] ?? ''));
		});
		if (count($historyRows) > 500) {
			$historyRows = array_slice($historyRows, 0, 500);
		}
	}
}

$balance = (float)($account['balance'] ?? 0);
$invoiceDueSum = (float)($stats['invoice_due_sum'] ?? 0);
$diff = $balance - $invoiceDueSum;
?>
<!doctype html>
<html lang="de">
<head>
	<meta charset="utf-8" />
	<meta name="viewport" content="width=device-width, initial-scale=1" />
	<title>Prüfung Offene Beträge Kunde</title>
	<link href="styles.css" rel="stylesheet" type="text/css" />
	<link href="assets/bootstrap/bootstrap.min.css" rel="stylesheet" />
	<link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.5.0/css/all.css" crossorigin="anonymous">
</head>
<body class="bg-light">
<?php require_once __DIR__ . '/navbar.php'; ?>
<main class="container-fluid py-3">
	<h1 class="h3 mb-3">Prüfung Offene Beträge Kunde</h1>

	<form method="get" class="row g-2 mb-3">
		<div class="col-12 col-md-6 col-lg-4">
			<label class="form-label small text-muted">Account-ID</label>
			<input type="text" name="account_id" class="form-control form-control-sm" value="<?php echo htmlspecialchars($accountId); ?>" placeholder="z. B. efdc57ac-0338-07c3-2604-676965e9fa49">
		</div>
		<div class="col-12 col-md-3 col-lg-2 d-flex align-items-end">
			<button type="submit" class="btn btn-primary btn-sm w-100">Prüfen</button>
		</div>
		<div class="col-12 col-md-3 col-lg-2 d-flex align-items-end">
			<a class="btn btn-outline-secondary btn-sm w-100" href="offene_betraege_kunde.php">Zur Liste</a>
		</div>
	</form>

	<?php if ($flash !== ''): ?>
		<div class="alert alert-success"><?php echo htmlspecialchars($flash); ?></div>
	<?php endif; ?>
	<?php foreach ($errors as $err): ?>
		<div class="alert alert-danger"><?php echo htmlspecialchars($err); ?></div>
	<?php endforeach; ?>

	<?php if ($accountId === ''): ?>
		<div class="alert alert-info">Bitte Account-ID eingeben und prüfen.</div>
	<?php elseif (!$account): ?>
		<div class="alert alert-warning">Konto nicht gefunden.</div>
	<?php else: ?>
		<div class="card shadow-sm mb-3">
			<div class="card-header"><strong>Konto & Abgleich</strong></div>
			<div class="card-body">
				<div class="row g-3">
					<div class="col-12 col-lg-6">
						<table class="table table-sm table-striped mb-0">
							<tbody>
								<tr><th style="width:220px;">Kunde</th><td><?php echo htmlspecialchars((string)$account['name']); ?></td></tr>
								<tr><th>Konto-Nr.</th><td><?php echo htmlspecialchars((string)($account['ticker_symbol'] ?? '')); ?></td></tr>
								<tr><th>Account-ID</th><td><code><?php echo htmlspecialchars((string)$account['id']); ?></code></td></tr>
								<tr><th>Typ</th><td><?php echo htmlspecialchars((string)($account['account_type'] ?? '')); ?></td></tr>
							</tbody>
						</table>
					</div>
					<div class="col-12 col-lg-6">
						<table class="table table-sm table-striped mb-0">
							<tbody>
								<tr><th style="width:220px;">`accounts.balance`</th><td class="text-end"><?php echo number_format($balance, 2, ',', '.'); ?></td></tr>
								<tr><th>Summe `invoice.amount_due`</th><td class="text-end"><?php echo number_format($invoiceDueSum, 2, ',', '.'); ?></td></tr>
								<tr><th>Differenz</th><td class="text-end <?php echo abs($diff) > 0.009 ? 'text-danger fw-bold' : 'text-success'; ?>"><?php echo number_format($diff, 2, ',', '.'); ?></td></tr>
								<tr><th>Offene Rechnungen</th><td class="text-end"><?php echo (int)($stats['invoice_open_count'] ?? 0); ?></td></tr>
							</tbody>
						</table>
					</div>
				</div>
				<div class="mt-3 d-flex gap-2 flex-wrap">
					<a class="btn btn-sm btn-outline-secondary" target="_blank" rel="noopener" href="<?php echo 'https://addinol-lubeoil.at/crm/index.php?module=Accounts&action=DetailView&record=' . urlencode((string)$account['id']); ?>">Konto im CRM</a>
					<form method="post" class="d-inline">
						<input type="hidden" name="account_id" value="<?php echo htmlspecialchars((string)$account['id']); ?>">
						<input type="hidden" name="action" value="sync_balance_to_invoice_sum">
						<button type="submit" class="btn btn-sm btn-warning">Saldo = Summe offene Rechnungen</button>
					</form>
					<form method="post" class="d-inline">
						<input type="hidden" name="account_id" value="<?php echo htmlspecialchars((string)$account['id']); ?>">
						<input type="hidden" name="action" value="set_balance_zero">
						<button type="submit" class="btn btn-sm btn-outline-danger">Saldo auf 0 setzen</button>
					</form>
				</div>
			</div>
		</div>

			<div class="row g-3">
				<div class="col-12 col-xl-6">
				<div class="card shadow-sm h-100">
					<div class="card-header"><strong>Offene Rechnungen</strong> <span class="text-muted">(<?php echo count($openInvoices); ?>)</span></div>
					<div class="card-body">
						<div class="table-responsive">
							<table class="table table-sm table-striped align-middle mb-0">
								<thead><tr><th>Rechnung</th><th>Datum</th><th>Fällig</th><th class="text-end">Offen</th><th></th></tr></thead>
								<tbody>
								<?php foreach ($openInvoices as $row): ?>
									<tr>
										<td><?php echo htmlspecialchars((string)$row['invoice_no']); ?></td>
										<td><?php echo htmlspecialchars((string)$row['invoice_date']); ?></td>
										<td><?php echo htmlspecialchars((string)$row['due_date']); ?></td>
										<td class="text-end"><?php echo number_format((float)$row['amount_due'], 2, ',', '.'); ?></td>
										<td><a class="btn btn-sm btn-outline-primary" href="<?php echo 'update_invoice.php?invoice_id=' . urlencode((string)$row['id']); ?>" target="_blank">Detail</a></td>
									</tr>
								<?php endforeach; ?>
								<?php if (!$openInvoices): ?>
									<tr><td colspan="5" class="text-muted">Keine offenen Rechnungen.</td></tr>
								<?php endif; ?>
								</tbody>
							</table>
						</div>
					</div>
				</div>
			</div>
				<div class="col-12 col-xl-6">
					<div class="card shadow-sm h-100">
						<div class="card-header"><strong>Auffälligkeiten</strong></div>
					<div class="card-body">
						<div class="mb-3">
							<div class="small text-muted">Rechnungen mit negativem `amount_due`</div>
							<div class="h5 mb-2"><?php echo (int)count($negativeDueInvoices); ?></div>
							<?php if ($negativeDueInvoices): ?>
								<ul class="mb-0">
								<?php foreach ($negativeDueInvoices as $row): ?>
									<li><?php echo htmlspecialchars((string)$row['invoice_no']); ?>: <?php echo number_format((float)$row['amount_due'], 2, ',', '.'); ?></li>
								<?php endforeach; ?>
								</ul>
							<?php endif; ?>
						</div>
						<div>
							<div class="small text-muted">Zahlungen ohne Rechnungszuordnung (`invoices_payments`)</div>
							<div class="h5 mb-2"><?php echo (int)count($orphanPayments); ?></div>
							<?php if ($orphanPayments): ?>
								<ul class="mb-0">
								<?php foreach (array_slice($orphanPayments, 0, 8) as $row): ?>
									<li><?php echo htmlspecialchars((string)$row['payment_date']); ?> | <?php echo htmlspecialchars((string)$row['payment_type']); ?> | <?php echo number_format((float)$row['amount'], 2, ',', '.'); ?></li>
								<?php endforeach; ?>
								</ul>
							<?php endif; ?>
						</div>
					</div>
					</div>
				</div>
			</div>

			<div class="card shadow-sm mt-3">
				<div class="card-header"><strong>Ursachenanalyse Differenz</strong></div>
				<div class="card-body">
					<div class="row g-3 mb-3">
						<div class="col-12 col-md-6 col-xl-3">
							<div class="border rounded p-2 h-100">
								<div class="small text-muted">Gutschriften (Konto)</div>
								<div class="fw-bold"><?php echo (int)($causeStats['credit_count'] ?? 0); ?> Stk.</div>
								<div class="small">Betrag: <?php echo number_format((float)($causeStats['credit_amount_sum'] ?? 0), 2, ',', '.'); ?></div>
								<div class="small">Offen (`amount_due`): <?php echo number_format((float)($causeStats['credit_due_sum'] ?? 0), 2, ',', '.'); ?></div>
							</div>
						</div>
						<div class="col-12 col-md-6 col-xl-3">
							<div class="border rounded p-2 h-100">
								<div class="small text-muted">Skonto-Zahlungen</div>
								<div class="fw-bold"><?php echo (int)($causeStats['skonto_count'] ?? 0); ?> Stk.</div>
								<div class="small">Summe: <?php echo number_format((float)($causeStats['skonto_sum'] ?? 0), 2, ',', '.'); ?></div>
							</div>
						</div>
						<div class="col-12 col-md-6 col-xl-3">
							<div class="border rounded p-2 h-100">
								<div class="small text-muted">Nicht zugeordnete Zahlungsreste</div>
								<div class="fw-bold"><?php echo (int)($causeStats['unapplied_count'] ?? 0); ?> Zahlungen</div>
								<div class="small">Summe Rest: <?php echo number_format((float)($causeStats['unapplied_sum'] ?? 0), 2, ',', '.'); ?></div>
							</div>
						</div>
						<div class="col-12 col-md-6 col-xl-3">
							<div class="border rounded p-2 h-100">
								<div class="small text-muted">Aktuelle Differenz</div>
								<div class="fw-bold <?php echo abs($diff) > 0.009 ? 'text-danger' : 'text-success'; ?>">
									<?php echo number_format($diff, 2, ',', '.'); ?>
								</div>
								<div class="small text-muted">`accounts.balance - SUM(invoice.amount_due)`</div>
							</div>
						</div>
					</div>

					<div class="row g-3">
						<div class="col-12 col-xl-4">
							<div class="small text-muted mb-1">Gutschriften</div>
							<div class="table-responsive">
								<table class="table table-sm table-striped align-middle mb-0">
									<thead><tr><th>Nr.</th><th>Fällig</th><th class="text-end">Betrag</th><th class="text-end">Offen</th></tr></thead>
									<tbody>
									<?php foreach (array_slice($creditNotes, 0, 8) as $row): ?>
										<tr>
											<td><?php echo htmlspecialchars((string)$row['credit_no']); ?></td>
											<td><?php echo htmlspecialchars((string)$row['due_date']); ?></td>
											<td class="text-end"><?php echo number_format((float)$row['amount'], 2, ',', '.'); ?></td>
											<td class="text-end"><?php echo number_format((float)$row['amount_due'], 2, ',', '.'); ?></td>
										</tr>
									<?php endforeach; ?>
									<?php if (!$creditNotes): ?><tr><td colspan="4" class="text-muted">Keine Gutschriften.</td></tr><?php endif; ?>
									</tbody>
								</table>
							</div>
						</div>
						<div class="col-12 col-xl-4">
							<div class="small text-muted mb-1">Skonto-Zahlungen</div>
							<div class="table-responsive">
								<table class="table table-sm table-striped align-middle mb-0">
									<thead><tr><th>Datum</th><th class="text-end">Betrag</th><th class="text-end">zugeordnet</th></tr></thead>
									<tbody>
									<?php foreach (array_slice($skontoPayments, 0, 8) as $row): ?>
										<tr>
											<td><?php echo htmlspecialchars((string)$row['payment_date']); ?></td>
											<td class="text-end"><?php echo number_format((float)$row['amount'], 2, ',', '.'); ?></td>
											<td class="text-end"><?php echo number_format((float)$row['applied_sum'], 2, ',', '.'); ?></td>
										</tr>
									<?php endforeach; ?>
									<?php if (!$skontoPayments): ?><tr><td colspan="3" class="text-muted">Keine Skonto-Zahlungen.</td></tr><?php endif; ?>
									</tbody>
								</table>
							</div>
						</div>
						<div class="col-12 col-xl-4">
							<div class="small text-muted mb-1">Nicht zugeordnete Zahlungsreste</div>
							<div class="table-responsive">
								<table class="table table-sm table-striped align-middle mb-0">
									<thead><tr><th>Datum</th><th>Typ</th><th class="text-end">Rest</th></tr></thead>
									<tbody>
									<?php foreach (array_slice($unappliedPayments, 0, 8) as $row): ?>
										<tr>
											<td><?php echo htmlspecialchars((string)$row['payment_date']); ?></td>
											<td><?php echo htmlspecialchars((string)$row['payment_type']); ?></td>
											<td class="text-end"><?php echo number_format((float)$row['unapplied_diff'], 2, ',', '.'); ?></td>
										</tr>
									<?php endforeach; ?>
									<?php if (!$unappliedPayments): ?><tr><td colspan="3" class="text-muted">Keine Reste gefunden.</td></tr><?php endif; ?>
									</tbody>
								</table>
							</div>
						</div>
					</div>
				</div>
			</div>

			<div class="card shadow-sm mt-3">
			<div class="card-header d-flex justify-content-between align-items-center">
				<strong>Historie (Konto / Rechnung / Zahlung)</strong>
				<span class="text-muted"><?php echo count($historyRows); ?> Einträge</span>
			</div>
			<div class="card-body">
				<div class="table-responsive">
					<table class="table table-sm table-striped align-middle mb-0">
						<thead>
							<tr>
								<th>Zeit</th>
								<th>Quelle</th>
								<th>Objekt</th>
								<th>Feld</th>
								<th>Alt</th>
								<th>Neu</th>
								<th>Benutzer</th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ($historyRows as $row): ?>
								<tr>
									<td><?php echo htmlspecialchars((string)($row['ts'] ?? '')); ?></td>
									<td><?php echo htmlspecialchars((string)($row['source'] ?? '')); ?></td>
									<td><?php echo htmlspecialchars((string)($row['object'] ?? '')); ?></td>
									<td><?php echo htmlspecialchars((string)($row['field'] ?? '')); ?></td>
									<td><?php echo htmlspecialchars((string)($row['before'] ?? '')); ?></td>
									<td><?php echo htmlspecialchars((string)($row['after'] ?? '')); ?></td>
									<td><?php echo htmlspecialchars((string)($row['user'] ?? '')); ?></td>
								</tr>
							<?php endforeach; ?>
							<?php if (!$historyRows): ?>
								<tr><td colspan="7" class="text-muted">Keine Audit-Historie gefunden.</td></tr>
							<?php endif; ?>
						</tbody>
					</table>
				</div>
			</div>
		</div>
	<?php endif; ?>
</main>
<script src="assets/bootstrap/bootstrap.bundle.min.js"></script>
</body>
</html>
