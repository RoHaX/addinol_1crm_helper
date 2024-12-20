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
			<input type='text' id='invoice_id' size='38' name='invoice_id' value='106581a5-7aef-7ddc-af5a-5b6d6c1dbfc2'>
			<button type='submit' name='action' value='invoice'>Rechnung suchen</button>";
	print "</form>";
	
	if (isset($_GET['invoice_id'])) {
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
	
?>

</body>
</html>