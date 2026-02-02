<?php
	$link = mysqli_connect('localhost', 'addinol_usr', 'lwT1e99~', 'addinol_crm');
	mysqli_set_charset($link, "utf8");
	
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
	
	if (isset($_POST['invoice_id']) or isset($_GET['invoice_id'])) {
		if (isset($_POST['invoice_id'])) { $invoice = $_POST['invoice_id']; }
		if (isset($_GET['invoice_id'])) { $invoice = $_GET['invoice_id']; }
		print "<br><br>RECHNUNG:";
		print $invoice;
		//$invoice = $_POST['invoice_id'];
		print "\t<br>\n";
		print "\t<br>\n";
		
		print "\t<br>\n";
		print "\t<br>\n";
		
		if (isset($_POST['amount'])) {
			foreach ($_POST['amount'] as $nr => $inhalt)
			{
				$betrag = mysqli_real_escape_string($link, $inhalt);
				
				$strUpdate = "UPDATE invoices_payments 
					SET amount = $betrag AND 
					amount_usdollar = $betrag
					WHERE payment_id = '$nr'";
				echo $strUpdate."<br>\n";
				echo "<font color='red'><b><i>ACHTUNG : Aktualisierung noch nicht freigeschalten, muss erst getestet werden!</i></b></font><br>\n";
				
				$strUpdate = "UPDATE payments 
					SET amount = $betrag 
					AND amount_usdollar = $betrag
					AND total_amount_= $betrag
					AND total_amount_usdollar = $betrag
					WHERE payment_id = '$nr'";
				echo $strUpdate."<br>\n";
				echo "<font color='red'><b><i>ACHTUNG : Aktualisierung noch nicht freigeschalten, muss erst getestet werden!</i></b></font><br>\n";
			}		
		}
		if (isset($_POST['account'])) {
			// echo "Account: " . $_POST['account'];
			$account = $_POST['account'];
			foreach ($_POST['account'] as $nr => $inhalt)
			{
				$betrag = mysqli_real_escape_string($link, $inhalt);
				
				$strUpdate = "UPDATE accounts 
					SET balance = $betrag 
					WHERE id = '$nr'";
				echo $strUpdate."<br>\n";
				echo "<font color='red'><b><i>ACHTUNG : Aktualisierung noch nicht freigeschalten, muss erst getestet werden!</i></b></font><br>\n";
			}		
		}
		
		if (isset($_POST['invoice_balance'])) {
			foreach ($_POST['invoice_balance'] as $nr => $inhalt)
			{
				$betrag = mysqli_real_escape_string($link, $inhalt);
				
				$strUpdate = "UPDATE invoice 
					SET amount_due = '$betrag'
					AND amount_due_usdollar = '$betrag'
					WHERE id = '$nr'";
				echo $strUpdate."<br>\n";
				$result = mysqli_query($link, $strUpdate);
				if ($result == 1) {
					echo "<font color='green'><b><i>".$result." : Daten erfolgreich aktualisiert</i></b></font><br>\n";
				} else {
					echo "<font color='red'><b><i>ACHTUNG-FEHLER: ".$result."</i></b></font><br>\n";
				}
			}		
		}
		print "<form action='update_invoice.php' method='post'>
				<input type='hidden' id='invoice_id' size='38' name='invoice_id' value=''>";

		//Feld balance in tabelle invoice gibt es nicht mehr...

		$strSQL = "SELECT * FROM invoice WHERE id = '$invoice'";
		echo $strSQL;
		print "\t<table class='table table-sm table-striped table-hover align-middle'>\n";
		print "\t<tr><th width=300>Tabelle invoice Invoice_ID</th>
		<th width=130>RE-Nummer</th>
		<th width=60>Datum</th>
		<th width=60>Amount</th>
		<th width=60>AmUSD</th>
		<th width=60>Amount Due</th>
		<th width=60>AmDueUSD</th></tr>\n";

		if ($result = mysqli_query($link, $strSQL)) 
		{
			while ($row = mysqli_fetch_assoc($result)) {	

						
				print "\t<tr>
				<td><a href='https://addinol-lubeoil.at/crm/index.php?module=Invoice&action=DetailView&record=".$row['id']."' target='_blank'>".$row['id']."</a></td>
				<td>".$row['prefix'].$row['invoice_number']."</td><td>".$row['invoice_date']."</td>
				<td align='right'>".number_format($row['amount'], 2, ',', '.')."</td>
				<td align='right'>".number_format($row['amount_usdollar'], 2, ',', '.')."</td>
				<td align='right'>".number_format($row['amount_due'], 2, ',', '.')."</td>
				<td align='right'>".$row['amount_due']."</td>";

				// <td><input type='text' name='invoice_balance[".$row['id']."]' value='".number_format($row['balance'], 2, ',', '.')."'> Sollte auf 0 stehen, wenn die Rechnungen beglichen ist.</td>";
				print "</tr>\n";
				$account = $row['billing_account_id'];
			}
		}
		print "\t</table>\n";
		

		print "\t<br>\n";
		print "\t<br>\n";
		print $account;
		$strSQL = "SELECT * FROM accounts WHERE id = '$account'";
		
		print "\t<table class='table table-sm table-striped table-hover align-middle'>\n";
		print "\t<tr><th width=300>Tabelle accounts</th>
		<th width=200>Name</th>
		<th width=60>Balance</th>
		<th width=60>Balance</th>
		<th width=500></th></tr>\n";

		if ($result = mysqli_query($link, $strSQL)) 
		{
			while ($row = mysqli_fetch_assoc($result)) {	
				print "\t<tr>
				<td><a href='https://addinol-lubeoil.at/crm/index.php?module=Account&action=DetailView&record=".$row['id']."' target='_blank'>".$row['id']."</a></td>
				<td align='right'>".$row['name']."</td>
				<td align='right'>".number_format($row['balance'], 2, ',', '.')."</td>
				<td align='right'>".$row['balance']."</td>
				<td><input type='text' name='account[".$row['id']."]' value='".number_format($row['balance'], 2, ',', '.')."'> Sollte auf 0 stehen, wenn alle Rechnungen beglichen sind.</td>";
				print "</tr>\n";
			}
		}
		print "\t</table>\n";

		print "\t<br>\n";
		print "\t<br>\n";
		
		
		print "\t<table class='table table-sm table-striped table-hover align-middle'>\n";
				
		$strSQL = "SELECT * FROM invoices_payments WHERE invoice_id = '$invoice'";

		print "\t<tr><th width=300>Tabellen invoices payments</th>
		<th width=130>Zahlungsart</th>
		<th width=60>Amount</th>
		<th width=60>Am USD</th>
		<th width=60>TotAm</th>
		<th width=60>TotAmUSD</th></tr>\n";

		if ($result = mysqli_query($link, $strSQL)) 
		{
			while ($row = mysqli_fetch_assoc($result)) {	
				print "\t<tr>
				<td><a href='https://addinol-lubeoil.at/crm/index.php?module=Payments&action=DetailView&record=".$row['payment_id']."' target='_blank'>".$row['payment_id']."</a></td>
				<td></td><td align='right'>".number_format($row['amount'], 2, ',', '.')."</td>
				<td align='right'>".number_format($row['amount_usdollar'], 2, ',', '.')."</td>";
				print "</tr>\n";

				$strSQLpayment = "SELECT * FROM payments WHERE id = '".$row['payment_id']."'";
				$resultPayment = mysqli_query($link, $strSQLpayment);
				while ($rowPayment = mysqli_fetch_assoc($resultPayment)) {
				print "\t<tr>
				<td>".$rowPayment['prefix'].$rowPayment['payment_id']."</td><td>".$rowPayment['payment_type']."</td>
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
	}
	print "<button class='btn btn-success btn-sm' type='submit' name='action' value='eintragen'>eintragen</button>
			</form> ";

	print "\t<br>\n";
	print "\t<br>\n";
	
?>
	</main>
	<script src="assets/bootstrap/bootstrap.bundle.min.js"></script>
</body>
</html>
