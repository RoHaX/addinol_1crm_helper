<?php
	header('Content-Type: text/csv');
	header('Content-Disposition: attachment; filename=lagerstand.csv');
	header('Pragma: no-cache');
	header("Expires: 0");

	$link = mysqli_connect('localhost', 'addinol_usr', 'lwT1e99~', 'addinol_crm');

	$outstream = fopen("php://output", "w");    
	//fputcsv($outstream, array_keys($results[0]));
			$strSQL = "SELECT products.name AS productname, products.id as productid, products.cost, products_warehouses.product_id, products_warehouses.warehouse_id, products_warehouses.in_stock as lagerstand, products_cstm.mindestbestand
				FROM (products INNER JOIN products_warehouses ON products.id = products_warehouses.product_id) LEFT JOIN products_cstm ON products.id = products_cstm.id_c
				GROUP BY products.name, products.id, products_warehouses.product_id, products.description, products_warehouses.warehouse_id, products_warehouses.in_stock, products_cstm.Mindestbestand
				HAVING (((products_warehouses.warehouse_id)='".$_GET['id']."') AND (Not (products_warehouses.in_stock)=0));";

	print "Produktname;Lagerstand;";
	
	if ($result = mysqli_query($link, $strSQL)) {
		while ($row = mysqli_fetch_assoc($result)) {
			print "\r\n";
			print $row['productname'].";".$row['lagerstand'].";";
		}
	}	

	fclose($outstream);
?>