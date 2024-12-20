<?php
	header('Content-Type: text/csv');
	header('Content-Disposition: attachment; filename=export_kunden.csv');
	header('Pragma: no-cache');
	header("Expires: 0");

	$link = mysqli_connect('localhost', 'addinol_usr', 'lwT1e99~', 'addinol_crm');

	$outstream = fopen("php://output", "w");    
	//fputcsv($outstream, array_keys($results[0]));
	$strSQL = "SELECT *	FROM accounts WHERE ticker_symbol LIKE '2%' ORDER BY ticker_symbol;";

	print "konto;name;nation;plz;ort;uid;";
	
	if ($result = mysqli_query($link, $strSQL)) {
		while ($row = mysqli_fetch_assoc($result)) {
			print "\r\n";
			//print $row['ticker_symbol'].";".$row['name'].";".$row['billing_address_street'].";";
			print $row['ticker_symbol'].";".$row['name'].";";
			if ($row['billing_address_country'] == 'Deutschland') {
				print "2;";
			} else {
				print "1;";
			}
			print $row['billing_address_postalcode'].";".$row['billing_address_city'].";".$row['tax_information'].";";
		}
	}	

	fclose($outstream);
?>