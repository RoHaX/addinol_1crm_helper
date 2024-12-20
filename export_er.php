<?php
	header('Content-Type: text/csv');
	$strJahr = $_GET['jahr'];
	$strMonat = $_GET['monat'];
	header('Content-Disposition: attachment; filename="'.$strJahr.'_'.$strMonat.'_export_er.csv"');
	header('Pragma: no-cache');
	header("Expires: 0");

	$link = mysqli_connect('localhost', 'addinol_usr', 'lwT1e99~', 'addinol_crm');

	$outstream = fopen("php://output", "w");
	
	/*
	$strSQL = "SELECT bills.id, bills.prefix, bills.bill_number, bills.bill_date, bills.invoice_reference, bills.pretax, bills.name as billname, bills.deleted, bills.amount, bills.supplier_id
	FROM bills
	WHERE (((bills.supplier_id)='50d36380-cdd2-a5d7-5ad9-567127e9a012') AND ((bills.deleted)=0) AND (YEAR(bills.bill_date)=".$strJahr.") AND (MONTH(bills.bill_date)=".$strMonat.")) 
	ORDER BY bills.bill_number;";
	*/
	$strSQL = "SELECT bills.id, bills.prefix, bills.bill_number, bills.bill_date, bills.invoice_reference, bills.pretax, bills.name as billname, bills.deleted, bills.amount, bills.supplier_id, accounts.ticker_symbol, accounts.name as kontoname
	FROM bills
	INNER JOIN accounts ON bills.supplier_id = accounts.id
	WHERE (((bills.deleted)=0) AND (YEAR(bills.bill_date)=".$strJahr.") AND (MONTH(bills.bill_date)=".$strMonat.")) 
	ORDER BY bills.bill_number;";
	
	print "satzart;gkonto;konto;belegnr;belegdatum;buchsymbol;buchcode;prozent;steuercode;filiale;betrag;steuer;text;";
	
	if ($result = mysqli_query($link, $strSQL)) {
		while ($row = mysqli_fetch_assoc($result)) {
 
			$ymd = $row['bill_date'];
			$timestamp = strtotime($ymd);
			$billymd = date("Ymd", $timestamp);

			
			$ReBetrag = $row['amount'];
			//$Netto = $row['pretax'];
			//$MwSt = $ReBetrag - $Netto;
			$MwSt = 0;
			
			
			$strSQL = "SELECT * FROM bill_adjustments WHERE deleted=0 AND related_type='TaxRates' AND bills_id = '".$row['id']."';";
			if ($resultLines = mysqli_query($link, $strSQL)) {
				while ($rowLine = mysqli_fetch_assoc($resultLines)) {
					$MwSt = $rowLine['amount'];
				}
			}

			$Netto = $ReBetrag - $MwSt;
			$MwStPrz = $MwSt / $Netto;
			$MwStPrz = round($MwStPrz, 2)*100;
			
			if ($MwStPrz == 0) { 
				$MwStPrz = 20 ;
				$MwSt = $ReBetrag * 0.2;
			}
			
			print "\r\n";
			print "0;";
			
			// BelegNr ohne "ER"
			$BelegNr = str_replace("ER", "", $row['invoice_reference']);
			
			// FibuKonto ermitteln anhand supplier_id
			$KontoNr = $row['ticker_symbol'];
			$GKonto = "5200";
			$SteuerCode = "9";
			
			//300009 Hutchinson und 300010 A1 Gegenkonto auf  7297
			if ($KontoNr == "300009" OR $KontoNr == "300009") {
				$GKonto = "7292";
				$SteuerCode = "2";
			};
			//beim Transporte Haselsberger Johann Lieferant Nr. 300015 als gegenkonto bie Rechnungen die Nr. 7270 hinterlegen - ist für Tankrechnungen.
			if ($KontoNr == "300015") {
				$GKonto = "7270";
				$SteuerCode = "2";
			};
			
			
			// 300003 Schenker und 300004 Q logistic 7362
			if ($KontoNr == "300003" OR $KontoNr == "300004") {
				$GKonto = "7362";
				$SteuerCode = "2";
			};
			
			
			if ($ReBetrag < 0 ) {
				print $GKonto.";".$KontoNr.";".$BelegNr.";".$billymd.";ER;2;".$MwStPrz.";19;;"; 
				print number_format($ReBetrag, 2, ',', '').";";
				print "-".number_format($MwSt, 2, ',', '').";";			
			} elseif ($MwStPrz==20) {
				print $GKonto.";".$KontoNr.";".$BelegNr.";".$billymd.";ER;2;".$MwStPrz.";".$SteuerCode.";;";
				print number_format($ReBetrag, 2, ',', '').";";
				print "-".number_format($MwSt, 2, ',', '').";";	
			} elseif ($MwStPrz==19) {
				print "5060;".$KontoNr.";".$BelegNr.";".$billymd.";ER;2;".$MwStPrz.";2;1;"; 
				print number_format($ReBetrag, 2, ',', '').";";
				/*mwst + lt. hermann am 4.4.2018 via whatsapp */
				print "+".number_format($MwSt, 2, ',', '').";";
			} else {
				print "ACHTUNG STEUERSATZ ist ".$MwStPrz." ".$Netto."  ";
			}
			
			print $row['kontoname']." (".$row['billname'].");"; // TODO abschließendes semikolon?
		}	
	 }
fclose($outstream);
?>