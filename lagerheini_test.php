<?php
	$link = mysqli_connect('localhost', 'addinol_usr', 'lwT1e99~', 'addinol_crm');
	mysqli_set_charset($link, "utf8");
?>
<?php

	/* $strSQL = "SELECT products.name as productname, products.id as productid, sum(products_warehouses.in_stock) as lagerstand, products_warehouses.deleted, products_warehouses.created_by, products_warehouses.product_id,  products_warehouses.date_modified as aenderung, products_cstm.mindestbestand
		FROM (products INNER JOIN products_cstm ON products.id = products_cstm.id_c) INNER JOIN products_warehouses ON products.id = products_warehouses.product_id
		GROUP BY products.name, products.id, products_warehouses.product_id, products.description, products_warehouses.warehouse_id, products_warehouses.in_stock, products_cstm.Mindestbestand
		HAVING (((products_warehouses.deleted)=0));";
	*/
	$strSQL = "SELECT products.name AS productname, products.id AS productid, Sum(products_warehouses.in_stock) AS lagerstand, products_warehouses.product_id, products_cstm.mindestbestand
			FROM (products INNER JOIN products_cstm ON products.id = products_cstm.id_c) INNER JOIN products_warehouses ON products.id = products_warehouses.product_id
			WHERE (((products_warehouses.deleted)=0))
			GROUP BY products.name, products.id, products_warehouses.product_id, products_cstm.mindestbestand;";

	$mailtext = "\r\n";
	$icount = 0;
	if ($result = mysqli_query($link, $strSQL)) {
		while ($row = mysqli_fetch_assoc($result)) {	

			if ($row['mindestbestand'] > $row['lagerstand']) {
				$icount = $icount + 1;
				//$mailtext .= "Produkt: ".$row['productname']."\r\nLagerstand: ".$row['lagerstand']."\r\nMindestbestand: ".$row['mindestbestand']."\r\n\r\n";
				print "Produkt: ".$row['productname']."\r\nLagerstand: ".$row['lagerstand']."\r\nMindestbestand: ".$row['mindestbestand']."<br>\r\n\r\n";
			}
		}
	}

	
	
		
?>