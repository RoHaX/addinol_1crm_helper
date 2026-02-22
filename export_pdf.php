<?php
	$strJahr = $_GET['jahr'];
	$strMonat = $_GET['monat'];


// getFile('https://addinol-lubeoil.at/crm/api.php/printer/pdf/Invoice/da7d7dbc-1af8-fee0-0559-612de3b61627', 'pdfname.pdf');

	$link = mysqli_connect('localhost', 'addinol_usr', 'lwT1e99~', 'addinol_crm');

	
	$strSQL = "SELECT invoice.id, invoice.prefix, invoice.invoice_number, invoice.invoice_date, invoice.name, invoice.amount, invoice.pretax, invoice.deleted, invoice.shipping_address_state, accounts.ticker_symbol
	FROM accounts INNER JOIN invoice ON accounts.id = invoice.billing_account_id
	WHERE (((invoice.deleted)=0) AND (YEAR(invoice_date)=".$strJahr.") AND (MONTH(invoice_date)=".$strMonat.")) ORDER BY invoice.prefix, invoice.invoice_number;";

	if ($result = mysqli_query($link, $strSQL)) {
		while ($row = mysqli_fetch_assoc($result)) {
			$ymd = $row['invoice_date'];
			$timestamp = strtotime($ymd);
			//$dmy = date("d.m.Y", $timestamp);
			$dmy = date("Ymd", $timestamp);
			$url = "https://addinol-lubeoil.at/crm/api.php/printer/pdf/Invoice/".$row['id'];
			$file = "pdfexport/Rechnung_".$row['prefix'].$row['invoice_number']."_".iconv( 'windows-1252', 'UTF-8', $row['name'] ).".pdf";
			echo $url."<br>";
			echo $file."<br>";
			getFile($url, $file);
		}
	 }
	 
	
	
	 
function getFile($url, $pdfname) {
	$username = getenv('CRM_API_USER') ?: '';
	$password = getenv('CRM_API_PASS') ?: '';
	if ($username === '' || $password === '') {
		throw new Exception('Missing CRM_API_USER / CRM_API_PASS environment variables.');
	}
	//Initiate cURL.
	$ch = curl_init($url);
	 
	//Specify the username and password using the CURLOPT_USERPWD option.
	curl_setopt($ch, CURLOPT_USERPWD, $username . ":" . $password);  

	//Tell cURL to return the output as a string instead
	//of dumping it to the browser.
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

	//Execute the cURL request.
	$response = curl_exec($ch);
	 
	//Check for errors.
	if(curl_errno($ch)){
		//If an error occured, throw an Exception.
		throw new Exception(curl_error($ch));
	}

	//Print out the response.
	//echo $response;

	$fp = fopen($pdfname, 'w');
	fwrite($fp,$response);
	echo "fertig ".$url;
}


	 
	 // Gutschriften
	 /*
	$strSQL = "SELECT credit_notes.id, credit_notes.prefix, credit_notes.credit_number, credit_notes.due_date, credit_notes.name, credit_notes.amount, credit_notes.pretax, credit_notes.deleted, accounts.ticker_symbol
	FROM accounts INNER JOIN credit_notes ON accounts.id = credit_notes.billing_account_id
	WHERE (((credit_notes.deleted)=0) AND (YEAR(due_date)=".$strJahr.") AND (MONTH(due_date)=".$strMonat.")) ORDER BY credit_notes.prefix, credit_notes.credit_number;";

	if ($result = mysqli_query($link, $strSQL)) {
		while ($row = mysqli_fetch_assoc($result)) {
			$ymd = $row['due_date'];
			$timestamp = strtotime($ymd);
			//$dmy = date("d.m.Y", $timestamp);
			$dmy = date("Ymd", $timestamp);
			
			$versand = 0;
			$strSQL = "SELECT * FROM credit_adjustments WHERE deleted=0 AND related_type='ShippingProviders' AND credit_id = '".$row['id']."';";
			if ($resultLines = mysqli_query($link, $strSQL)) {
				while ($rowLine = mysqli_fetch_assoc($resultLines)) {
					$versand = $rowLine['amount'];
				}
			}
			
			$MwSt = 0;
			$strSQL = "SELECT * FROM credit_adjustments WHERE deleted=0 AND related_type='TaxRates' AND credit_id = '".$row['id']."';";
			if ($resultLines = mysqli_query($link, $strSQL)) {
				while ($rowLine = mysqli_fetch_assoc($resultLines)) {
					$MwSt += $rowLine['amount'];
				}
			}			
			
			$ReBetrag = $row['amount'];
			//$Netto = $row['subtotal'] + $versand ;
			$Netto = $ReBetrag - $MwSt;
			//print "XXX".$MwSt."XXX";
			$chkMwSt = $ReBetrag - $Netto;
			if (intval($MwSt) <> intval($chkMwSt) ) { 
				print "XXXXXXXXXXXXXXXXXXXX ACHTUNG MWST-Problem - bitte Datensatz prüfen und ggf. bereinigen - XXXXXXXXXXXXXXXXXXXXXXXXX"; 
			}
			$MwStPrz = $MwSt / $Netto;
			$MwStPrz = round($MwStPrz, 2)*100;
			
			print "\r\n";
			print "0;".$row['ticker_symbol'].";";
			// BelegNr ohne "GS"
			$BelegNr = str_replace("GS", "", $row['prefix']).$row['credit_number'];
			
			if ($MwStPrz==20) {
				print "4050;".$BelegNr.";".$dmy.";GS;1;".$MwStPrz.";1;;"; // Konto 4050 normal - Steuercode 1 - filiale leer
			} elseif ($MwStPrz==19) {
				print "4060;".$BelegNr.";".$dmy.";GS;1;".$MwStPrz.";1;1;"; // Konto 4060 bei 19% Steuer - Steuercode 1 Ausfuhrlieferung TODO - filiale 1
			} elseif ($MwStPrz==0) {
				print "4010;".$BelegNr.";".$dmy.";GS;1;".$MwStPrz.";7;1;"; // Konto 4010 bei 0% Steuer (IG Lieferung) - Steuercode 7 iglieferung - TODO filiale?
			} else {
				print "4010;".$BelegNr.";".$dmy.";GS;1;".$MwStPrz.";7;1;"; // Konto 4010 bei 0% Steuer (IG Lieferung) - Steuercode 7 iglieferung - TODO filiale?
			}

			print "-".number_format($ReBetrag, 2, ',', '').";";
			print number_format($MwSt, 2, ',', '').";";
			//print $row['shipping_address_state'].";"; //shipping_address_state
			print ";"; //shipping_address_state
			print $row['name'].";"; 
		}
	 }
	 	*/

?>
