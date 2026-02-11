<?php
	require_once __DIR__ . '/db.inc.php';
	$link = $mysqli;
	mysqli_set_charset($link, "utf8");

	function normalize_money($value) {
		$value = trim((string)$value);
		if ($value === '') {
			return '';
		}
		$value = str_replace([' ', "\u{00A0}"], '', $value);
		if (strpos($value, ',') !== false) {
			// German style: 1.234,56
			$value = str_replace('.', '', $value);
			$value = str_replace(',', '.', $value);
		}
		return $value;
	}

	function split_sql_statements($sql) {
		$parts = preg_split('/;\s*\n|\r\n|\r|\n|;/', $sql);
		$out = [];
		foreach ($parts as $part) {
			$trim = trim($part);
			if ($trim !== '') {
				$out[] = $trim;
			}
		}
		return $out;
	}
?>
<!doctype html>
<html lang="de">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Rechnung / Zahlung bearbeiten</title>
<link href="styles.css" rel="stylesheet" type="text/css" />
<link href="assets/bootstrap/bootstrap.min.css" rel="stylesheet" />
<link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.5.0/css/all.css" integrity="sha384-B4dIYHKNBt8Bc12p+WXckhzcICo0wtJAoU8YZTY5qE0Id1GSseTk6S+L3BlXeVIU" crossorigin="anonymous">
</head>
<body class="<?php echo isset($_GET['embed']) ? 'bg-white' : 'bg-light'; ?>">
<?php if (!isset($_GET['embed'])) { ?>
	<?php require_once __DIR__ . '/navbar.php'; ?>
	<main class="container-fluid py-3">
		<h1 class="h4 mb-3">Rechnung / Zahlung bearbeiten</h1>
<?php } else { ?>
	<main class="container-fluid py-2">
<?php } ?>
<?php
	
	/* Ausgangsrechnungen */
	// 106581a5-7aef-7ddc-af5a-5b6d6c1dbfc2
	
	print "<form action='update_invoice.php' method='post' class='row g-2 align-items-end mb-3'>
			<div class='col-12 col-md-4'>
				<label class='form-label small text-muted' for='invoice_id'>Invoice ID</label>
				<input class='form-control form-control-sm' type='text' id='invoice_id' name='invoice_id' value=''>
			</div>
			<div class='col-12 col-md-2'>
				<button class='btn btn-primary btn-sm w-100' type='submit' name='action' value='invoice'>Rechnung suchen</button>
			</div>";
	print "</form>";
	
	$generatedSql = [];
	$action = $_POST['action'] ?? '';

	if ($action === 'run_sql' && isset($_POST['sql_edit'])) {
		$statements = split_sql_statements($_POST['sql_edit']);
		print "<div class='alert alert-info'><strong>SQL-Ausführung:</strong> ".count($statements)." Statements</div>";
		foreach ($statements as $stmt) {
			print "<div class='text-muted small mb-1'>".htmlspecialchars($stmt, ENT_QUOTES).";</div>";
			$result = mysqli_query($link, $stmt);
			if ($result === true) {
				print "<div class='text-success small mb-2'>OK</div>";
			} else {
				print "<div class='text-danger small mb-2'>FEHLER: ".mysqli_error($link)."</div>";
			}
		}
	}

	if (isset($_POST['invoice_id']) or isset($_GET['invoice_id'])) {
		if (isset($_POST['invoice_id'])) { $invoice = $_POST['invoice_id']; }
		if (isset($_GET['invoice_id'])) { $invoice = $_GET['invoice_id']; }
		print "<br><br>\n";
		
		if (isset($_POST['amount'])) {
			foreach ($_POST['amount'] as $nr => $inhalt)
			{
				$betrag = normalize_money($inhalt);
				if ($betrag === '') {
					continue;
				}
				$nr = mysqli_real_escape_string($link, $nr);

				$generatedSql[] = "UPDATE invoices_payments SET amount = $betrag, amount_usdollar = $betrag WHERE payment_id = '$nr'";
				$generatedSql[] = "UPDATE payments SET amount = $betrag, amount_usdollar = $betrag, total_amount = $betrag, total_amount_usdollar = $betrag WHERE id = '$nr'";
			}		
		}
		if (isset($_POST['account'])) {
			// echo "Account: " . $_POST['account'];
			$account = $_POST['account'];
			foreach ($_POST['account'] as $nr => $inhalt)
			{
				$betrag = normalize_money($inhalt);
				if ($betrag === '') {
					continue;
				}
				$nr = mysqli_real_escape_string($link, $nr);
				$generatedSql[] = "UPDATE accounts SET balance = $betrag WHERE id = '$nr'";
			}		
		}
		
		if (isset($_POST['invoice_balance'])) {
			foreach ($_POST['invoice_balance'] as $nr => $inhalt)
			{
				$betrag = normalize_money($inhalt);
				if ($betrag === '') {
					continue;
				}
				$nr = mysqli_real_escape_string($link, $nr);
				$generatedSql[] = "UPDATE invoice SET amount_due = '$betrag', amount_due_usdollar = '$betrag' WHERE id = '$nr'";
			}		
		}
		print "<form action='update_invoice.php' method='post'>
				<input type='hidden' id='invoice_id' size='38' name='invoice_id' value='".htmlspecialchars($invoice, ENT_QUOTES)."'>";

		//Feld balance in tabelle invoice gibt es nicht mehr...

		$strSQL = "SELECT * FROM invoice WHERE id = '$invoice'";
		print "<h2 class='h6 mt-4'>Rechnung</h2>";
		print "\t<table class='table table-sm table-striped table-hover align-middle'>\n";
		print "\t<tr>
		<th width=130>RE-Nummer</th>
		<th width=80>Datum</th>
		<th width=90>Amount</th>
		<th width=90>AmUSD</th>
		<th width=90>Amount Due</th>
		<th width=90>AmDueUSD</th>
		<th width=170>Amount Due (edit)</th></tr>\n";

		if ($result = mysqli_query($link, $strSQL)) 
		{
			while ($row = mysqli_fetch_assoc($result)) {	

						
				print "\t<tr>
				<td>".$row['prefix'].$row['invoice_number']."</td><td>".$row['invoice_date']."</td>
				<td align='right'>".number_format($row['amount'], 2, ',', '.')."</td>
				<td align='right'>".number_format($row['amount_usdollar'], 2, ',', '.')."</td>
				<td align='right'>".number_format($row['amount_due'], 2, ',', '.')."</td>
				<td align='right'>".$row['amount_due']."</td>
				<td><input type='text' name='invoice_balance[".$row['id']."]' value='".number_format($row['amount_due'], 2, ',', '.')."'></td>";
				print "</tr>\n";
				$account = $row['billing_account_id'];
			}
		}
		print "\t</table>\n";
		

		print "\t<br>\n";
		print "\t<br>\n";
		$strSQL = "SELECT * FROM accounts WHERE id = '$account'";
		
		print "<h2 class='h6 mt-4'>Account</h2>";
		print "\t<table class='table table-sm table-striped table-hover align-middle'>\n";
		print "\t<tr>
		<th width=240>Name</th>
		<th width=90>Balance</th>
		<th width=90>Balance raw</th>
		<th width=220>Balance (edit)</th></tr>\n";

		if ($result = mysqli_query($link, $strSQL)) 
		{
			while ($row = mysqli_fetch_assoc($result)) {	
				print "\t<tr>
				<td>".$row['name']."</td>
				<td align='right'>".number_format($row['balance'], 2, ',', '.')."</td>
				<td align='right'>".$row['balance']."</td>
				<td><input type='text' name='account[".$row['id']."]' value='".number_format($row['balance'], 2, ',', '.')."'> Sollte auf 0 stehen, wenn alle Rechnungen beglichen sind.</td>";
				print "</tr>\n";
			}
		}
		print "\t</table>\n";

		print "\t<br>\n";
		print "\t<br>\n";

		print "<h2 class='h6 mt-4'>Zahlungen</h2>";
		print "\t<table class='table table-sm table-striped table-hover align-middle'>\n";
		
		$strSQL = "SELECT * FROM invoices_payments WHERE invoice_id = '$invoice'";

		print "\t<tr>
		<th width=140>Zahlungsart</th>
		<th width=90>Amount</th>
		<th width=90>Am USD</th>
		<th width=90>TotAm</th>
		<th width=90>TotAmUSD</th>
		<th width=160>Amount (edit)</th></tr>\n";

		if ($result = mysqli_query($link, $strSQL)) 
		{
			while ($row = mysqli_fetch_assoc($result)) {	
				$strSQLpayment = "SELECT * FROM payments WHERE id = '".$row['payment_id']."'";
				$resultPayment = mysqli_query($link, $strSQLpayment);
				while ($rowPayment = mysqli_fetch_assoc($resultPayment)) {
				print "\t<tr>
				<td>".$rowPayment['payment_type']."</td>
				<td align='right'>".number_format($rowPayment['amount'], 2, ',', '.')."</td>
				<td align='right'>".number_format($rowPayment['amount_usdollar'], 2, ',', '.')."</td>
				<td align='right'>".number_format($rowPayment['total_amount'], 2, ',', '.')."</td>
				<td align='right'>".number_format($rowPayment['total_amount_usdollar'], 2, ',', '.')."</td>
				<td align='right'>
					<input type='text' name='amount[".$row['payment_id']."]' value='".number_format($rowPayment['amount'], 2, ',', '.')."'>
				</td>";
				print "</tr>\n";
					
				}
					
			}
		}

		print "\t</table>\n";

		if (!empty($generatedSql)) {
			$previewSql = implode(";\n", $generatedSql) . ";";
			print "<div class='alert alert-secondary mt-3'>
					<strong>SQL-Vorschläge:</strong> Du kannst die Statements anpassen und dann ausführen.
				  </div>";
			print "<div class='mb-3'>
					<label class='form-label small text-muted' for='sql_edit'>SQL (editierbar)</label>
					<textarea class='form-control form-control-sm' id='sql_edit' name='sql_edit' rows='8'>".htmlspecialchars($previewSql, ENT_QUOTES)."</textarea>
				  </div>";
		}
	}
	print "<div class='d-flex gap-2'>
				<button class='btn btn-outline-primary btn-sm' type='submit' name='action' value='preview_sql'>SQL anzeigen</button>
				<button class='btn btn-success btn-sm' type='submit' name='action' value='run_sql'>SQL ausführen</button>
			</div>
			</form> ";

	print "\t<br>\n";
	print "\t<br>\n";
	
?>
	</main>
	<script src="assets/bootstrap/bootstrap.bundle.min.js"></script>
</body>
</html>
