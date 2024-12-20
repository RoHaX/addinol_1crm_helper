<?php header('Content-Type: text/csv');
	$strJahr = $_GET['jahr'];
	$strMonat = $_GET['monat'];
	header('Content-Disposition: attachment; filename="'.$strJahr.'_'.$strMonat.'_export_ar_zahlung.csv"');
	header('Pragma: no-cache');
	header("Expires: 0");

	$link = mysqli_connect('localhost', 'addinol_usr', 'lwT1e99~', 'addinol_crm');

	$outstream = fopen("php://output", "w");
	
	$strSQL = "SELECT accounts.ticker_symbol, payments.prefix, YEAR(payments.payment_date) as payyear, MONTH(payments.payment_date) as paymonth, payments.payment_id, invoice.name, payments.customer_reference, payments.payment_date, payments.direction, invoice.id as invoiceID, invoice.amount, invoice.prefix as invoiceprefix, invoice.invoice_number, invoice.invoice_date, invoice.pretax, invoice.shipping_address_state, Sum(If(payment_type='Skonto',payments.amount,0)) AS Skonto, Sum(If(payment_type<>'Skonto',payments.amount,0)) AS Betrag 
	FROM (payments 
	INNER JOIN invoice ON payments.related_invoice_id = invoice.id) 
	INNER JOIN accounts ON invoice.billing_account_id = accounts.id 
	GROUP BY accounts.ticker_symbol, invoice.name, 	payments.payment_date, payments.direction, invoice.amount 
	HAVING (((payments.direction)='incoming') AND (payyear=".$strJahr.") AND (paymonth=".$strMonat.")) ;";
	
	print "satzart;konto;gkonto;belegnr;belegdatum;buchsymbol;buchcode;prozent;steuercode;filiale;betrag;skonto;shipping_address_state;text;ausz-belegnr;";
	
	if ($result = mysqli_query($link, $strSQL)) {
		while ($row = mysqli_fetch_assoc($result)) {
			$ReBetrag = $row['Betrag'];
			$Netto = $row['pretax'];
			$Skonto = $row['Skonto'];
			//print "\r\n DEBUG:".$row['payment_id'];
			
			if ($Skonto == 0) {
				$AbzugPrz = 0;
			} else {
				//print $row['prefix'].$row['payment_id']."XXX";
				//print $Skonto."XXX";
				//print $ReBetrag."XXX";
				$AbzugPrz = $Skonto / $ReBetrag;
				$AbzugPrz = round($AbzugPrz, 2) * 100;
				//print "ready";
			}
			
			//MWST
			$strSQLTax = 	"SELECT invoice_lines.tax_class_id, taxrates.rate
							FROM (invoice_lines 
							INNER JOIN taxcodes_rates ON invoice_lines.tax_class_id = taxcodes_rates.code_id) 
							INNER JOIN taxrates ON taxcodes_rates.rate_id = taxrates.id
							WHERE invoice_lines.deleted=0 AND taxcodes_rates.deleted=0 AND taxrates.deleted=0 AND invoice_id='".$row['invoiceID']."';";
			$MwStPrz = "0";
			
			if ($resultLines = mysqli_query($link, $strSQLTax)) {
				while ($rowLine = mysqli_fetch_assoc($resultLines)) {			
					$MwStPrz = $rowLine['rate'];
				}
			}
			
			//Payment-Date
			$ymd = $row['payment_date'];
			$timestamp = strtotime($ymd);
			$payymd = date("Ymd", $timestamp);
			
			//Invoice-Date
			$ymd = $row['invoice_date'];
			$timestamp = strtotime($ymd);
			$invymd = date("Ymd", $timestamp);
			
			print "\r\n";
			print "0;".$row['ticker_symbol'].";2201;".$row['prefix'].$row['payment_id'].";".$payymd.";";
			print "BK;"; //buchsymbol
			print "1;";  //buchcode
			
			
			print $MwStPrz.";"; //prozent
			if ($MwStPrz==20) {
				print "1;";  //steuercode
				print ";"; //Filiale
			} elseif ($MwStPrz==19) {
				print "1;";  //steuercode
				print "1;"; //Filiale
			} elseif ($MwStPrz==0) {
				print "7;";  //steuercode
				print "1;"; //Filiale
			} else {
				print "7;";  //steuercode
				print "1;"; //Filiale
			}

			$ReBetragSkt = $ReBetrag + $Skonto;
			print "-".number_format($ReBetragSkt, 2, ',', '').";";
			print number_format($Skonto, 2, ',', '').";"; //Skonto
			print $row['shipping_address_state'].";"; //shipping_address_state
			print $row['customer_reference']." ".$row['name'].";";  //test
			//print (100 - ($AbzugPrz * 100)).";"; //skontopz
			//print $AbzugPrz.";"; //skontopz
			
			// BelegNr ohne "RE"
			$BelegNr = str_replace("RE", "", $row['invoiceprefix']).$row['invoice_number'];
			print $BelegNr.";";  //ausz-belegnr
			//print $invymd.";";  //ausz-belegnr		

		}
	}
	fclose($outstream); ?>