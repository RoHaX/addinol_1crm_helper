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
		<h1>Offene Rechnungen</h1> 
	</div>
<?php
	
	if (isset($_GET['invoice_id']) && isset($_GET['setnull'])) {
		$invoice = $_GET['invoice_id'];
		print "<br><br>RECHNUNG:";
		print $invoice;
		//$invoice = $_POST['invoice_id'];
		
		print "\t<br>\n";
		print "\t<br>\n";
		
				
		$strUpdate = "UPDATE invoice 
			SET amount_due = '0'
			AND amount_due_usdollar = '0'
			WHERE id = '$invoice'";
		echo $strUpdate."<br>\n";
		$result = mysqli_query($link, $strUpdate);
		if ($result == 1) {
			echo "<font color='green'><b><i>".$result." : Offener Betrag der Rechnung ".$invoice." wurde erfolgreich auf 0 gesetzt.</i></b></font><br>\n";
		} else {
			echo "<font color='red'><b><i>ACHTUNG-FEHLER: ".$result."</i></b></font><br>\n";
		}
	}

	print "\t<br>\n";
	print "\t<br>\n";
	
	print "\t<br>\n";
	print "\t<br>\n";

	$strSQL = "SELECT * FROM invoice WHERE amount_due <> '0.00' ORDER BY invoice_date;";
	
	print "\t<table>\n";
	print "\t<tr><th width=300>Tabelle invoice Invoice_ID</th>
	<th width=130>RE-Nummer</th>
	<th width=60>Datum</th>
	<th width=60>Amount</th>
	<th width=60>Offen</th>
	<th width=60>Offen USD</th>
	<th width=60></th>
	<th width=80></th></tr>\n";

	if ($result = mysqli_query($link, $strSQL)) 
	{
		while ($row = mysqli_fetch_assoc($result)) {	
			print "\t<tr>
			<td><a href='https://addinol-lubeoil.at/crm/index.php?module=Invoice&action=DetailView&record=".$row['id']."' target='_blank'>".$row['id']."</a></td>
			<td>".$row['prefix'].$row['invoice_number']."</td><td>".$row['invoice_date']."</td>
			<td align='right'>".number_format($row['amount'], 2, ',', '.')."</td>
			<td align='right'>".$row['amount_due']."</td>
			<td align='right'>".$row['amount_due_usdollar']."</td>
			<td><a href='update_invoice.php?invoice_id=".$row['id']."' target='_blank'>Detail...</a></td>
			<td><a href='?setnull=OK&invoice_id=".$row['id']."' target='_self'>auf 0 setzen</a></td>";
			print "</tr>\n";
			$account = $row['billing_account_id'];
		}
	}
	print "\t</table>\n";

	print "\t<br>\n";
	print "\t<br>\n";


	print "\t<br>\n";
	print "\t<br>\n";
	
?>

</body>
</html>