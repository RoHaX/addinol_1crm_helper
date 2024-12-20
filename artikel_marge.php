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
		<h1>Artikelliste</h1> 
	</div>
<?php
	
	/* Ausgangsrechnungen */
	print "\t<table>\n";
/*
	$strSQL = "SELECT count(invoice.billing_account_id) as AnzKunden, invoice.deleted, invoice_lines.related_id, invoice_lines.deleted, invoice_lines.name, Sum(invoice_lines.quantity) AS Stueck, invoice_lines.list_price
		FROM invoice_lines INNER JOIN invoice ON invoice_lines.invoice_id = invoice.id
		GROUP BY invoice.deleted, invoice_lines.deleted, invoice_lines.name
		HAVING (((invoice.deleted)=0) AND ((invoice_lines.deleted)=0))
		ORDER BY Sum(invoice_lines.quantity) desc";
		*/
	$strSQL = "SELECT invoice_lines.related_id, products.name, AVG(invoice_lines.cost_price) AS EK_mw, Sum(invoice_lines.ext_price) AS Umsatz, Sum(invoice_lines.quantity) AS Anzahl
		FROM invoice_lines INNER JOIN products ON invoice_lines.related_id = products.id
		GROUP BY invoice_lines.related_id, products.name;";

	print "\t<tr><th width=370>Artikel</th><th width=60>EK</th><th width=90>Umsatz</th><th width=90>Stück</th><th width=90>DurchschnVK</th></tr>\n";

	if ($result = mysqli_query($link, $strSQL)) 
	{
		while ($row = mysqli_fetch_assoc($result)) {	
		
			$ekges = $row['EK_mw']*$row['Anzahl'];
			$marge = $row['Umsatz']-$ekges;
			$margeprz = ($marge/$ekges) * 100;
			$margehrm = 1-($ekges/$row['Umsatz']);
			
			print "\t<tr>
			<td><a href='https://addinol-lubeoil.at/crm/index.php?module=ProductCatalog&action=DetailView&record=".$row['related_id']."' target='_blank'>".$row['name']."</a></td>
			<td align='right'>".number_format($row['EK_mw'], 0, ',', '.')."</td>
			<td align='right'>".number_format($row['Umsatz'], 2, ',', '.')."</td>
			<td align='right'>".$row['Anzahl']."</td>
			<td align='right'>".number_format(($row['Umsatz']/$row['Anzahl']), 2, ',', '.')."</td>
			<td align='right'>".number_format($marge, 2, ',', '.')."</td>
			<td align='right'>".number_format($margeprz, 2, ',', '.')."</td>
			<td align='right'>".number_format($margehrm, 2, ',', '.')."</td>";
			
			print "</tr>\n";
		}
	}

	print "\t</table>\n";
	print "\t<br>\n";
	print "\t<br>\n";
	
?>

</body>
</html>