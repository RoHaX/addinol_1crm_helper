<?php
	$link = mysqli_connect('localhost', 'addinol_usr', 'lwT1e99~', 'addinol_crm');
	mysqli_set_charset($link, "utf8");
	
	$strProductID = $_GET['id'];
	$strProduct = $_GET['aname'];
	
?>
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1" />
<script type="text/javascript" src="https://www.gstatic.com/charts/loader.js"></script>
<link href="styles.css" rel="stylesheet" type="text/css" />
</head>
<body>  

	<div>
		<h2> 
<?php 
	print $strProduct; 
?>	
		</h2> 
	</div>
	<div>
<?php

	print "\t<table>\n";

	$strSQL = "SELECT accounts.name, invoice.billing_account_id, invoice.deleted, invoice_lines.deleted, Sum(invoice_lines.quantity) AS Anzahl, invoice_lines.related_id
		FROM accounts INNER JOIN (invoice_lines INNER JOIN invoice ON invoice_lines.invoice_id = invoice.id) ON accounts.id = invoice.billing_account_id
		GROUP BY accounts.name, invoice.billing_account_id, invoice.deleted, invoice_lines.deleted, invoice_lines.related_id
		HAVING (((invoice.deleted)=0) AND ((invoice_lines.deleted)=0) AND ((invoice_lines.related_id)='".$strProductID."'))";

	print "\t<tr><th width=370>Name</th><th width=60>Stück</th></tr>\n";
	
	if ($result = mysqli_query($link, $strSQL)) 
	{
		while ($row = mysqli_fetch_assoc($result)) {	
			print "\t<tr>
			<td><a href='https://addinol-lubeoil.at/crm/index.php?module=Accounts&action=DetailView&record=".$row['billing_account_id']."' target='_blank'>".$row['name']."</a></td>
			<td>".$row['Anzahl']."</td>";

			print "</tr>\n";
		}
	}
	print "\t</table>\n";
	print "\t<br>\n";
	print "\t<br>\n";

?>
	</div>
</body>
</html>