<?php
	$link = mysqli_connect('localhost', 'addinol_usr', 'lwT1e99~', 'addinol_crm');
	mysqli_set_charset($link, "utf8");
?>
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1" />
<link href="styles.css" rel="stylesheet" type="text/css" />
</head>
<body>  
<h1>Lagerstand</h1>


<?php

	$strSQL = "SELECT company_addresses.id, company_addresses.name as lagername FROM company_addresses INNER JOIN products_warehouses ON products_warehouses.warehouse_id = company_addresses.id
	WHERE products_warehouses.deleted=0
	GROUP BY company_addresses.id;";
	
	print "<form action='lagerstand.php' method='post'>\n";
	print "\t<select name='formLager'>\n";
	print "\t<option value=''>alle Lager</option>\n";
	if ($result = mysqli_query($link, $strSQL)) 
	{
		while ($row = mysqli_fetch_assoc($result)) {		
			print "\t<option value='".$row['id']."'>".$row['lagername']."</option>\n";
		}
	}
	print "\t</select>\n";
	print "<input type='submit' name='absenden' value='anzeigen'>\n";
	print "</form>\n";
	$strQuery = "";
/*	$strSQL = "SELECT products.name as productname, products.id as productid, products.cost, sum(products_warehouses.in_stock) as lagerstand, products_warehouses.deleted, products_warehouses.created_by, products_warehouses.product_id,  products_warehouses.date_modified as aenderung, products_cstm.mindestbestand
		FROM (products LEFT JOIN products_cstm ON products.id = products_cstm.id_c) INNER JOIN products_warehouses ON products.id = products_warehouses.product_id
		GROUP BY products.name, products.id, products_warehouses.product_id, products.description, products_warehouses.warehouse_id, products_warehouses.in_stock, products_cstm.Mindestbestand";
*/		
	$strSQL = "SELECT products.name AS productname, products.id AS productid, Sum(products.cost) as cost, Sum(products_warehouses.in_stock) AS lagerstand, products_warehouses.product_id, products_cstm.mindestbestand, products_warehouses.date_modified as aenderung
			FROM (products INNER JOIN products_cstm ON products.id = products_cstm.id_c) INNER JOIN products_warehouses ON products.id = products_warehouses.product_id
			WHERE (((products_warehouses.deleted)=0))
			GROUP BY products.name, products.id, products_warehouses.product_id, products_cstm.mindestbestand;";
			
	if (isset($_POST['absenden'])){
		if ($_POST['formLager'] == '') { 
			//$strQuery = "";
/*			$strSQL = "SELECT products.name as productname, products.id as productid, products.cost, sum(products_warehouses.in_stock) as lagerstand, products_warehouses.deleted, products_warehouses.created_by, products_warehouses.product_id,  products_warehouses.date_modified as aenderung, products_cstm.mindestbestand
				FROM (products LEFT JOIN products_cstm ON products.id = products_cstm.id_c) INNER JOIN products_warehouses ON products.id = products_warehouses.product_id
				GROUP BY products.name, products.id, products_warehouses.product_id, products.description, products_warehouses.warehouse_id, products_warehouses.in_stock, products_cstm.Mindestbestand";
				$strSQL = "SELECT products.name AS productname, products.id AS productid, Sum(products_warehouses.in_stock) AS lagerstand, products_warehouses.product_id, products_cstm.mindestbestand
				FROM (products INNER JOIN products_cstm ON products.id = products_cstm.id_c) INNER JOIN products_warehouses ON products.id = products_warehouses.product_id
				WHERE (((products_warehouses.deleted)=0))
				GROUP BY products.name, products.id, products_warehouses.product_id, products_cstm.mindestbestand;";
				*/
			$formLager = '';
			$strSQL = "SELECT products.name AS productname, products.id AS productid, Sum(products.cost) as cost, Sum(products_warehouses.in_stock) AS lagerstand, products_warehouses.product_id, products_cstm.mindestbestand, products_warehouses.date_modified as aenderung 
				FROM (products 
				INNER JOIN products_cstm ON products.id = products_cstm.id_c) 
				INNER JOIN products_warehouses ON products.id = products_warehouses.product_id 
				WHERE (((products_warehouses.deleted)=0)) 
				GROUP BY products.name, products.id, products_warehouses.product_id, products_cstm.mindestbestand;";
		} else {
			$formLager = $_POST['formLager'];
					
			$strSQL = "SELECT products.name AS productname, products.id as productid, products.cost as cost, 
				products_warehouses.product_id, products_warehouses.warehouse_id, products_warehouses.in_stock as lagerstand, products_cstm.mindestbestand, products_warehouses.date_modified as aenderung
				FROM (products INNER JOIN products_warehouses ON products.id = products_warehouses.product_id) LEFT JOIN products_cstm ON products.id = products_cstm.id_c
				GROUP BY products.name, products.id, products_warehouses.product_id, products.description, products_warehouses.warehouse_id, products_warehouses.in_stock, products_cstm.Mindestbestand
				HAVING (((products_warehouses.warehouse_id)='".$_POST['formLager']."') AND (Not (products_warehouses.in_stock)=0));";
		}
	} else {
		$formLager = '387dac2f-b390-9e3e-d5ee-566827893cfa';
	}
	/*
	$strSQL = "SELECT products.name as productname, products.id as productid, products_warehouses.in_stock as lagerstand, company_addresses.name as lager, products_warehouses.deleted, products_warehouses.created_by, products_warehouses.product_id, products_warehouses.warehouse_id, products_warehouses.date_modified as aenderung, products_cstm.mindestbestand
	FROM products_cstm RIGHT JOIN (company_addresses INNER JOIN (products_warehouses INNER JOIN products ON products_warehouses.product_id = products.id) ON company_addresses.id = products_warehouses.warehouse_id) ON products_cstm.id_c = products.id
	WHERE (((products_warehouses.deleted)=0) ".$strQuery." AND ((products_warehouses.in_stock != 0) OR (products_cstm.mindestbestand != 0)))
	ORDER BY products.name;";
	*/


    //print $strSQL;
	
	print "\t<table>\n";
	print "\t<tr><th>Produkt</th><th>Lagerstand</th><th>EK €/Stk</th><th>EK €</th><th>Mindestbestand</th><th>Änderungsdatum</th></tr>\n";
	$mailtext = "\r\n";
	$icount = 0;
	
	$gesamtwert = 0;
	if ($result = mysqli_query($link, $strSQL)) 
	{
		while ($row = mysqli_fetch_assoc($result)) {		
			print "\t<tr><td><a href='https://addinol-lubeoil.at/crm/index.php?module=ProductCatalog&action=DetailView&record=".$row['productid']."' target='_blank'>".$row['productname']."</a></td><td>".$row['lagerstand']."</td>";
			print "<td align=right>".number_format($row['cost'], 2, ',', '.')."</td>";
			$wert = $row['cost'] * $row['lagerstand'];
			print "<td align=right>".number_format($wert, 2, ',', '.')."</td>";
			$gesamtwert += $wert;
			
			if ($row['mindestbestand'] > $row['lagerstand']) {
				$negcls = " class='neg'";
				$icount = $icount + 1;
				$info = $row['mindestbestand']." *NACHBESTELLEN!";
				$mailtext .= "Produkt: ".$row['productname']."\r\nLagerstand: ".$row['lagerstand']."\r\nMindestbestand: ".$row['mindestbestand']."\r\n\r\n";
			} else {
				$negcls = "";
				$info = $row['mindestbestand'];
			}
			
			print "<td".$negcls.">".$info."</td><td>".$row['aenderung']."</td></tr>\n";
		}
	}
	print "<b>Lagerwert: € ".number_format($gesamtwert, 2, ',', '.')."</b><br>";
	print "<a href='lagerstand_export.php?id=".$formLager."'>Export Lagerstand</a>";

	
?>
</table>
<?php

	if ($icount > 0) {
//h.egger@addinol-lubeoil.at
//rh@bkh-kufstein.at
		if (isset($_GET['mailsend'])){
			mail("h.egger@addinol-lubeoil.at",
				"Es ist gegebenenfalls eine Lagerbestellung nötig",
				"Geschätzte Geschäftsführung,\r\n\r\nvielen Dank, jeatz muass i mi a schon vom Programmierer zsammscheissen lassen...\r\nbin also wieder mal ins Lager nachschaun, habe ja sonst nix zu tun! Mein Bestellvorschlag:\r\n ".$mailtext."\r\n\r\nHochachtungsvoll und untertänigst\r\nEuer Lagerheini\r\n\r\nPS: Betriebsrat für Bodenpersonal in Gründung...",
				"From: lagerheini@addinol-lubeoil.at");			
		}
		if (isset($_GET['romansend'])){
			mail("rh@bkh-kufstein.at",
				"Es ist gegebenenfalls eine Lagerbestellung nötig",
				"Geschätzte Geschäftsführung,\r\n\r\nvielen Dank, jeatz muass i mi a schon vom Programmierer zsammscheissen lassen...\r\nbin also wieder mal ins Lager nachschaun, habe ja sonst nix zu tun! Mein Bestellvorschlag:\r\n ".$mailtext."\r\n\r\nHochachtungsvoll und untertänigst\r\nEuer Lagerheini\r\n\r\nPS: Betriebsrat für Bodenpersonal in Gründung...",
				"From: lagerheini@addinol-lubeoil.at");			
		}
	}
	
		
?>
</body>
</html>