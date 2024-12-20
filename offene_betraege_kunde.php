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
		<h1>Offene Beträge Kunde</h1> 
	</div>
<?php
	
	if (isset($_GET['account_id']) && isset($_GET['setnull'])) {
		$account_id = $_GET['account_id'];
		print "<br><br>Kunde:";
		print $account_id;
		
		
		print "\t<br>\n";
		print "\t<br>\n";
		
				
		$strUpdate = "UPDATE accounts 
			SET balance = '0'
			WHERE id = '$account_id'";
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

	$strSQL = "SELECT * FROM accounts WHERE balance <> '0.00' ORDER BY balance;";
	
	print "\t<table>\n";
	print "\t<tr><th width=380>Kunde</th>
	<th width=160>Ort</th>
	<th width=80>offener Betrag</th>
	<th width=80>offen</th>
	<th width=160></th></tr>\n";

	if ($result = mysqli_query($link, $strSQL)) 
	{
		while ($row = mysqli_fetch_assoc($result)) {
			print "\t<tr>
			<td><a href='https://addinol-lubeoil.at/crm/index.php?module=Accounts&action=DetailView&record=".$row['id']."' target='_blank'><b>".$row['name']."</b></a></td>
			<td>".$row['billing_address_city']."</td>
			<td align='right'>".number_format($row['balance'], 2, ',', '.')."</td>
			<td align='right'>".$row['balance']."</td>
			<td><a href='?setnull=OK&account_id=".$row['id']."' target='_self'>auf 0 setzen</a></td>";
			print "</tr>\n";

			$strSQLInvoice = "SELECT * FROM invoice 
				WHERE billing_account_id = '" . $row['id'] . "'
				AND amount_due <> 0";

			$current_date = date('Y-m-d');

			if ($resultI = mysqli_query($link, $strSQLInvoice)) 
			{
				while ($rowI = mysqli_fetch_assoc($resultI)) {
					$due_date = $rowI['due_date'];
					if ($due_date < $current_date) {
						$css_style = "color: red; font-weight: bold;";
					} else {
						$css_style = "";
					}
					print "\t<tr>
					<td><a href='https://addinol-lubeoil.at/crm/index.php?module=Invoice&action=DetailView&record=".$rowI['id']."' target='_blank'>" . $rowI['prefix'] . $rowI['invoice_number'] . "</a></td>
					<td style='" . $css_style . "'>fällig: " . $rowI['due_date'] . "</td>
					<td align='right'>".number_format($rowI['amount_due'], 2, ',', '.')."</td>
					<td><a href='update_invoice.php?invoice_id=".$rowI['id']."' target='_blank'>Detail...</a></td>";
					print "</tr>\n";

				}
			}
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