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

	$strSQL = "SELECT count(invoice.billing_account_id) as AnzKunden, invoice.deleted, invoice_lines.related_id, invoice_lines.deleted, invoice_lines.name, Sum(invoice_lines.quantity) AS Stueck, invoice_lines.list_price
		FROM invoice_lines INNER JOIN invoice ON invoice_lines.invoice_id = invoice.id
		GROUP BY invoice.deleted, invoice_lines.deleted, invoice_lines.name
		HAVING (((invoice.deleted)=0) AND ((invoice_lines.deleted)=0))
		ORDER BY Sum(invoice_lines.quantity) desc";
		
	print "\t<tr><th width=370>Artikel</th><th width=60>Stück</th><th width=90>AnzKunden</th><th width=90>Listenpreis</th></tr>\n";

	if ($result = mysqli_query($link, $strSQL)) 
	{
		while ($row = mysqli_fetch_assoc($result)) {	
			print "\t<tr>
			<td><a href='https://addinol-lubeoil.at/crm/index.php?module=ProductCatalog&action=DetailView&record=".$row['related_id']."' target='_blank'>".$row['name']."</a></td>
			<td>".$row['Stueck']."</td>
			<td align='right'>".number_format($row['AnzKunden'], 0, ',', '.')."</td>
			<td align='right'>".number_format($row['list_price'], 2, ',', '.')."</td>
			<td><a href='artikel_kunde.php?id=".$row['related_id']."&aname=".$row['name']."' target='_blank'>Details...</a></td>";

			print "</tr>\n";
		}
	}

	print "\t</table>\n";
	print "\t<br>\n";
	print "\t<br>\n";
	
?>

</body>
</html>