<?php
	$link = mysqli_connect('localhost', 'addinol_usr', 'lwT1e99~', 'addinol_crm');
	mysqli_set_charset($link, "utf8");
	
?>
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1" />
<script type="text/javascript" src="https://www.gstatic.com/charts/loader.js"></script>
<link href="styles.css" rel="stylesheet" type="text/css" />
</head>
<body>  

	<div>
		<h1>Rechnung / Zahlung bearbeiten</h1> 
	</div>
<?php
	
	/* Ausgangsrechnungen */
	// 106581a5-7aef-7ddc-af5a-5b6d6c1dbfc2
	
	print "<form action='update_invoice.php' method='post'>
			<input type='text' id='invoice_id' size='38' name='invoice_id' value=''>
			<button type='submit' name='action' value='invoice'>Rechnung suchen</button>";
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
		print "\t<table>\n";
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
		
		print "\t<table>\n";
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
		
		
		print "\t<table>\n";
				
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
	print "<button type='submit' name='action' value='eintragen'>eintragen</button>
			</form> ";

	print "\t<br>\n";
	print "\t<br>\n";
	
?>

</body>
</html>