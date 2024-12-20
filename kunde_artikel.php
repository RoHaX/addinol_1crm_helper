<?php
	$link = mysqli_connect('localhost', 'addinol_usr', 'lwT1e99~', 'addinol_crm');
	mysqli_set_charset($link, "utf8");
	
	$strAccount = $_GET['id'];
	
?>
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1" />
<script type="text/javascript" src="https://www.gstatic.com/charts/loader.js"></script>
<link href="styles.css" rel="stylesheet" type="text/css" />
</head>
<body>  

	<div>
		<h1>Kunde Artikel</h1> 
	</div>
<?php
	

	/* Ausgangsrechnungen */
	print "\t<table>\n";

	$strSQL = "SELECT invoice.billing_account_id, invoice_lines.related_id, invoice.deleted, invoice_lines.deleted, invoice_lines.name, Sum(invoice_lines.quantity) AS Stueck, Sum(invoice_lines.unit_price*invoice_lines.quantity) AS Betrag, invoice_lines.unit_price as Stueckpreis
		FROM invoice_lines INNER JOIN invoice ON invoice_lines.invoice_id = invoice.id
		GROUP BY invoice.deleted, invoice_lines.deleted, invoice_lines.name, invoice_lines.unit_price
		HAVING (((invoice.billing_account_id)='".$strAccount."') AND ((invoice.deleted)=0) AND ((invoice_lines.deleted)=0))";

	print "\t<tr><th width=370>Artikel</th><th width=60>Stück</th><th width=90>Betrag</th><th width=90>Stückpreis</th></tr>\n";
	
	if ($result = mysqli_query($link, $strSQL)) 
	{
		while ($row = mysqli_fetch_assoc($result)) {	
			print "\t<tr>
			<td><a href='https://addinol-lubeoil.at/crm/index.php?module=ProductCatalog&action=DetailView&record=".$row['related_id']."' target='_blank'>".$row['name']."</a></td>
			<td>".$row['Stueck']."</td>
			<td align='right'>".number_format($row['Betrag'], 2, ',', '.')."</td>
			<td align='right'>".number_format($row['Stueckpreis'], 2, ',', '.')."</td>";

			print "</tr>\n";
		}
	}
	

	print "\t</table>\n";
	print "\t<br>\n";
	print "\t<br>\n";
	
	


?>

</body>
</html>