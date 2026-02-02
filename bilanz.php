<?php
	$link = mysqli_connect('localhost', 'addinol_usr', 'lwT1e99~', 'addinol_crm');
	mysqli_set_charset($link, "utf8");

	$SumSaldo = 0;
	$SumNetto = 0;

	if (isset($_POST['absenden'])){
		$strJahr = $_POST['cmbJahr'];
		$strMonat = $_POST['cmbMonat'];
	} else {
		$strJahr = date('Y');
		$strMonat = date('n');
	}
	if ($strMonat == "*") {
		$strQuery = "";
	} else {
		$strQuery = " AND (MONTH(invoice.invoice_date)=".$strMonat.") ";
	}

	
	/* Begin CHART */
	$strSQL = "SELECT Sum(payments.amount) AS Zahlung, YEAR(payment_date) AS Jahr, MONTH(payment_date) AS Monat, payments.direction, payments.payment_type
		FROM payments
		GROUP BY YEAR(payment_date), MONTH(payment_date), direction, payment_type
		HAVING direction='incoming' AND payment_type<>'Skonto';";
	$arrUmsatz['2015-12'] = [
		'Jahr' => 2015,
		'Monat' => 12,
		'Umsatz' => 0,
		'Zahlung' => 0];		

	if ($result = mysqli_query($link, $strSQL)) 
	{
		while ($row = mysqli_fetch_assoc($result)) {
			$strKey = $row['Jahr']."-".$row['Monat'];
			$arrUmsatz[$strKey] = [
				'Jahr' => $row['Jahr'],
				'Monat' => $row['Monat'],
				'Zahlung' => $row['Zahlung']			
			];
		}
	}		

	$strSQL = "SELECT Sum(subtotal) as Umsatz, YEAR(invoice_date) AS Jahr, MONTH(invoice_date) AS Monat, deleted
		FROM invoice 
		GROUP BY YEAR(invoice_date), MONTH(invoice_date), deleted
		HAVING ((deleted)=0)";
		
	if ($result = mysqli_query($link, $strSQL)) 
	{
		while ($row = mysqli_fetch_assoc($result)) {
			$strKey = $row['Jahr']."-".$row['Monat'];
			$arrUmsatz[$strKey]['Umsatz'] = $row['Umsatz'];
		}
	}
		
	/*
	$strSQL = "SELECT Sum(subtotal) as Rechnung, YEAR(bill_date) AS Jahr, MONTH(bill_date) AS Monat, deleted
		FROM bills 
		GROUP BY YEAR(bill_date), MONTH(bill_date), deleted
		HAVING ((deleted)=0)";
		*/
		$strSQL = "SELECT Sum(subtotal+bill_adjustments.amount) AS Rechnung, Year(bill_date) AS Jahr, Month(bill_date) AS Monat, bills.deleted
				FROM bills LEFT JOIN bill_adjustments ON bills.id = bill_adjustments.bills_id
				WHERE (((bill_adjustments.related_type)='ShippingProviders'))
				GROUP BY Year(bill_date), Month(bill_date), bills.deleted
				HAVING bills.deleted=0;";
	if ($result = mysqli_query($link, $strSQL)) 
	{
		while ($row = mysqli_fetch_assoc($result)) {

			$strKey = $row['Jahr']."-".$row['Monat'];
			$arrUmsatz[$strKey]['Rechnung'] = $row['Rechnung'];
		}
	}
	/* END CHART */
	$arrPie = array();
	/* Begin PIECHART 	*/
	if ($strMonat <> "*") {

		$strSQL = "SELECT Sum(invoice.subtotal) as Umsatz, invoice.deleted, accounts.name, YEAR(invoice_date) as Jahr, MONTH(invoice_date) AS Monat
			FROM accounts 
			INNER JOIN invoice ON accounts.id = invoice.billing_account_id
			GROUP BY YEAR(invoice_date), MONTH(invoice_date), accounts.name, invoice.deleted
			HAVING (((invoice.deleted)=0) AND (Jahr=".$strJahr.") AND (Monat=".$strMonat.")) 
			ORDER BY Umsatz DESC";
		if ($result = mysqli_query($link, $strSQL)) {
			while ($row = mysqli_fetch_assoc($result)) {
				$arrPie[] = [
					'Name' => $row['name'],
					'Umsatz' => $row['Umsatz']			
				];
			}		
		}	
	}
	/* 	END PIECHART */

?>
<!doctype html>
<html lang="de">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Bilanz</title>
<link href="styles.css" rel="stylesheet" type="text/css" />
<link href="assets/bootstrap/bootstrap.min.css" rel="stylesheet" />
<link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.5.0/css/all.css" integrity="sha384-B4dIYHKNBt8Bc12p+WXckhzcICo0wtJAoU8YZTY5qE0Id1GSseTk6S+L3BlXeVIU" crossorigin="anonymous">

<script type="text/javascript" src="https://www.gstatic.com/charts/loader.js"></script>
<script type="text/javascript">

  google.charts.load('current', {'packages':['corechart']});
  google.charts.setOnLoadCallback(drawChart);
  function drawChart() {
	var data = google.visualization.arrayToDataTable([
<?php
	print "['Monat', 'Zahlungseingang', 'Eingangsrechnungen', 'Umsatz', 'Gewinn']";
	$inhalt = array();
	foreach ($arrUmsatz as $nr => $inhalt)
	{
		if (isset($inhalt['Jahr'])) {

			print ",";
			$Key  = $inhalt['Jahr'] . "-"  . $inhalt['Monat'];
			$Zahlung = round($inhalt['Zahlung'] ?? 0, 2);
			$Rechnung = round($inhalt['Rechnung'] ?? 0, 2);
			$Umsatz = round($inhalt['Umsatz'] ?? 0, 2);
			$Gewinn = $Umsatz - $Rechnung;
			echo "['$Key', $Zahlung, $Rechnung, $Umsatz, $Gewinn]";
		}
		
	}
?>	
	]);


	var options = {
	  title: 'Übersicht',
	  isStacked: false,
	  legend: {position: 'top', maxLines: 3},
	  hAxis: {title: 'Monat',  textStyle: { fontSize: 8, color: '#444'}, titleTextStyle: { color: '#333'}},
	  vAxis: {minValue: 0}
	};

	var chart = new google.visualization.AreaChart(document.getElementById('chart_div'));
	chart.draw(data, options);
	
	var data = google.visualization.arrayToDataTable([
<?php

	if (isset($arrPie)) {

		print "['Firma', 'Umsatz']";
		foreach ($arrPie as $nr => $inhalt)
		{
			print ",";
			$Key  = $inhalt['Name'];
			$Umsatz  = $inhalt['Umsatz'];
			echo "['$Key', $Umsatz]";
		}
	}
?>	
	]);

	var options = {
	  title: 'Monatsumsatz'
	};

	var chart = new google.visualization.PieChart(document.getElementById('piechart'));

	chart.draw(data, options);
	
  }
</script>

	</head>
<body class="bg-light">
	<?php require_once __DIR__ . '/navbar.php'; ?>

	<main class="container-fluid py-3">
		<div class="row g-3">
			<div class="col-12 col-xl-8">
				<div class="card shadow-sm">
					<div class="card-body">
						<div id="chart_div" style="min-height: 350px;"></div>
					</div>
				</div>
			</div>
			<div class="col-12 col-xl-4">
				<div class="card shadow-sm">
					<div class="card-body">
						<div id="piechart" style="min-height: 350px;"></div>
					</div>
				</div>
			</div>
		</div>
<?php
		// if (!isset($strJahr)) {
		// 	$strJahr = "2024";
		// }
		$strSQLJahresUmsatz = "SELECT Sum(invoice.subtotal) as Umsatz, invoice.deleted,  YEAR(invoice_date) as Jahr
		FROM invoice  
		GROUP BY Jahr, invoice.deleted
		HAVING invoice.deleted=0 AND Jahr = " . $strJahr;

	if ($result = mysqli_query($link, $strSQLJahresUmsatz)) {
		while ($row = mysqli_fetch_assoc($result)) {
			if ($row['Umsatz'] >= 1000000) {
				echo "<div class='alert alert-success fw-semibold my-3'>Jahresumsatz: € " . number_format($row['Umsatz'], 2, ',', '.') . " — Million geknackt!</div>";
			} else {
				echo "<div class='alert alert-info my-3'>Jahresumsatz: € " . number_format($row['Umsatz'], 2, ',', '.') . "</div>";
			}
		}		
	}	


?>	
		<div class="row g-3 mt-2">
			<div class="col-12 col-lg-4">
				<div class="card shadow-sm">
					<div class="card-body">
						<h1 class="h4 mb-3">Bilanz</h1>
						<form action='bilanz.php' method='post' class="row g-2 align-items-end">
							<div class="col-12">
								<label class="form-label small text-muted" for="cmbJahr">Jahr</label>
								<select id="cmbJahr" name='cmbJahr' class="form-select form-select-sm">
				<?php
				for ($i = 2016; $i <= 2026; $i++) {
					$selected = $strJahr == $i ? 'selected' : '';
					echo "<option value='$i' $selected>$i</option>";
				}
				?>				
								</select>
							</div>
							<div class="col-12">
								<label class="form-label small text-muted" for="cmbMonat">Monat</label>
								<select id="cmbMonat" name='cmbMonat' class="form-select form-select-sm">
									<option value='*'>alle Monate</option>
									<option value='1' <?php if ($strMonat==1) echo 'selected'; ?> >Jänner</option>
									<option value='2' <?php if ($strMonat==2) echo 'selected'; ?> >Februar</option>
									<option value='3' <?php if ($strMonat==3) echo 'selected'; ?> >März</option>
									<option value='4' <?php if ($strMonat==4) echo 'selected'; ?> >April</option>
									<option value='5' <?php if ($strMonat==5) echo 'selected'; ?> >Mai</option>
									<option value='6' <?php if ($strMonat==6) echo 'selected'; ?> >Juni</option>
									<option value='7' <?php if ($strMonat==7) echo 'selected'; ?> >Juli</option>
									<option value='8' <?php if ($strMonat==8) echo 'selected'; ?> >August</option>
									<option value='9' <?php if ($strMonat==9) echo 'selected'; ?> >September</option>
									<option value='10' <?php if ($strMonat==10) echo 'selected'; ?> >Oktober</option>
									<option value='11' <?php if ($strMonat==11) echo 'selected'; ?> >November</option>
									<option value='12' <?php if ($strMonat==12) echo 'selected'; ?> >Dezember</option>
								</select>
							</div>
							<div class="col-12">
								<button class="btn btn-primary btn-sm w-100" type='submit' name='absenden' value='anzeigen'>anzeigen</button>
							</div>
						</form>
					</div>
				</div>
			</div>
</div>

		<div class="row g-3 mt-3">
			<div class="col-12">
				<div class="card shadow-sm">
					<div class="card-header d-flex align-items-center justify-content-between">
						<h2 class="h5 mb-0">Ausgangsrechnungen</h2>
					</div>
					<div class="card-body">

<?php

	$SumReBetrag = 0;
	$SumZahlung = 0;
	$SumSkonto = 0;
	
	/* Ausgangsrechnungen */
	print "<div class='table-responsive'>\n";
	print "\t<table class='table table-sm table-striped table-hover align-middle'>\n";
	
	$strSQL = "SELECT invoice.id, invoice.prefix, invoice.invoice_number, invoice.invoice_date, invoice.name, invoice.amount, invoice.pretax, invoice.deleted, accounts.ticker_symbol
	FROM accounts INNER JOIN invoice ON accounts.id = invoice.billing_account_id
	WHERE (((invoice.deleted)=0) AND (YEAR(invoice.invoice_date)=".$strJahr.")".$strQuery.") ORDER BY invoice.prefix, invoice.invoice_number;";

	print "\t<thead class='table-light'><tr><th>Rechnung</th><th>Datum</th><th>Fibu</th><th>Bezeichnung</th><th>Brutto</th><th>Zahlung</th><th>Betrag</th><th>Skonto</th><th>Saldo</th><th>Netto</th><th>MwSt</th><th>Abzug</th></tr></thead>\n";
	print "\t<tbody>\n";
	if ($result = mysqli_query($link, $strSQL)) 
	{
		while ($row = mysqli_fetch_assoc($result)) {	
			print "\t<tr>
			<td><a href='https://addinol-lubeoil.at/crm/index.php?module=Invoice&action=DetailView&record=".$row['id']."' target='_blank'>".$row['prefix'].$row['invoice_number']."</a></td>
			<td>".$row['invoice_date']."</td>
			<td>".$row['ticker_symbol']."</td>
			<td>".substr($row['name'],0,40)."</td>
			<td align='right'>".number_format($row['amount'], 2, ',', '.')."</td>";
			
			$SumReBetrag += $row['amount'];
			$Zahlung = 0;
			$Skonto = 0;
			$Datum = "";
			/* Zahlung - alles was nicht Skonto ist */
			$strSQL = "SELECT * FROM payments WHERE direction='incoming' AND payment_type<>'Skonto' AND related_invoice_id = '".$row['id']."';";
			if ($resultPay = mysqli_query($link, $strSQL)) 
				{
					while ($rowPay = mysqli_fetch_assoc($resultPay)) {	
					$Datum = $rowPay['payment_date'];
					$Zahlung = $rowPay['amount'];
					$SumZahlung += $rowPay['amount'];
				}
			}
			print "<td>".$Datum."</td><td align='right'>".number_format($Zahlung, 2, ',', '.')."</td>";
			
			/* Skonto */
			$strSQL = "SELECT * FROM payments WHERE direction='incoming' AND payment_type='Skonto' AND related_invoice_id = '".$row['id']."';";
			if ($resultSkt = mysqli_query($link, $strSQL)) 	{
				while ($rowSkt = mysqli_fetch_assoc($resultSkt)) {
					$Skonto = $rowSkt['amount'];
					$SumSkonto += $rowSkt['amount'];
				}
			}
			print "<td align='right'>".number_format($Skonto, 2, ',', '.')."</td>";
			$Saldo = ($Zahlung + $Skonto) - $row['amount'];
			$Saldo = round($Saldo, 2);
			if ($Saldo >= 0) {
				$negcls = "";
			} else {
				$negcls = " class='neg'";
			}
			if ($Zahlung == 0) {
				$AbzugPrz = 0;
				$Netto = 0;
				$MwSt = 0;
			} else {
				$AbzugPrz = $Zahlung / $row['amount'];
				$AbzugPrz = round($AbzugPrz, 2);
				$Netto = $row['pretax'];
				$Netto = $Netto * $AbzugPrz;
				$MwSt = $Zahlung - $Netto;
			}
			
			print "<td".$negcls." align='right'>".number_format($Saldo, 2, ',', '.')."</td>";
			print "<td align='right'>".number_format($Netto, 2, ',', '.')."</td>";
			print "<td align='right'>".number_format($MwSt, 2, ',', '.')."</td>";
			print "<td>".(100 - ($AbzugPrz * 100))." %</td>";
			print "</tr>\n";
			$SumSaldo += $Saldo;
			$SumNetto += $Netto;
		}
	}
	
	//Gutschrift

	if ($strMonat == "*") {
		$strQuery = "";
	} else {
		$strQuery = " AND (MONTH(credit_notes.due_date)=".$strMonat.") ";
	}

	$strSQL = "SELECT credit_notes.id, credit_notes.invoice_id, credit_notes.prefix, credit_notes.credit_number, credit_notes.due_date, credit_notes.name, credit_notes.amount, credit_notes.pretax, credit_notes.deleted, accounts.ticker_symbol
	FROM accounts INNER JOIN credit_notes ON accounts.id = credit_notes.billing_account_id
	WHERE (((credit_notes.deleted)=0) AND (YEAR(due_date)=".$strJahr.")".$strQuery.") ORDER BY credit_notes.prefix, credit_notes.credit_number;";
	
	if ($result = mysqli_query($link, $strSQL)) 
	{
		while ($row = mysqli_fetch_assoc($result)) {	
			print "\t<tr>
			<td><a href='https://addinol-lubeoil.at/crm/index.php?module=CreditNotes&action=DetailView&record=".$row['id']."' target='_blank'>".$row['prefix'].$row['credit_number']."</a></td>
			<td>".$row['due_date']."</td>
			<td>".$row['ticker_symbol']."</td>
			<td>".$row['name']."</td>
			<td align='right' class='neg'>-".number_format($row['amount'], 2, ',', '.')."</td>";
			
			$SumReBetrag -= $row['amount'];
			$Zahlung = 0;
			$Skonto = 0;
			$Datum = "";
			/* Zahlung - alles was nicht Skonto ist */
			
			$strSQL = "SELECT * FROM payments WHERE direction='incoming' AND payment_type<>'Skonto' AND related_invoice_id = '".$row['invoice_id']."';";
			if ($resultPay = mysqli_query($link, $strSQL)) 
				{
					while ($rowPay = mysqli_fetch_assoc($resultPay)) {	
					$Datum = $rowPay['payment_date'];
					$Zahlung = $rowPay['amount'];
					$SumZahlung -= $rowPay['amount'];
				}
			}
			print "<td>".$Datum."</td><td align='right' class='neg'>-".number_format($Zahlung, 2, ',', '.')."</td>";
			
			
			/* Skonto */
			
			$strSQL = "SELECT * FROM payments WHERE direction='incoming' AND payment_type='Skonto' AND related_invoice_id = '".$row['invoice_id']."';";
			if ($resultSkt = mysqli_query($link, $strSQL)) 	{
				while ($rowSkt = mysqli_fetch_assoc($resultSkt)) {
					$Skonto = $rowSkt['amount'];
					$SumSkonto -= $rowSkt['amount'];
				}
			}
			print "<td align='right'>".number_format($Skonto, 2, ',', '.')."</td>";
			$Saldo = ($Zahlung + $Skonto) - $row['amount'];
			$Saldo = round($Saldo, 2);
			if ($Saldo >= 0) {
				$negcls = "";
			} else {
				$negcls = " class='neg'";
			}
			if ($Zahlung == 0) {
				$AbzugPrz = 0;
				$Netto = 0;
				$MwSt = 0;
			} else {
				$AbzugPrz = $Zahlung / $row['amount'];
				$AbzugPrz = round($AbzugPrz, 2);
				$Netto = $row['pretax'];
				$Netto = $Netto * $AbzugPrz;
				$MwSt = $Zahlung - $Netto;
			}
			
			
			print "<td".$negcls." align='right'>".number_format($Saldo, 2, ',', '.')."</td>";
			print "<td align='right' class='neg'>-".number_format($Netto, 2, ',', '.')."</td>";
			print "<td align='right'>".number_format($MwSt, 2, ',', '.')."</td>";
			print "<td>".(100 - ($AbzugPrz * 100))." %</td>";
			print "</tr>\n";
			$SumSaldo += $Saldo;
			$SumNetto -= $Netto;
		}
	}
	
	
	
	if ($SumSaldo < 0.00) { 
		$negcls = " class='neg'";
	} else {
		$negcls = "";
	}
	
	print "\t<tr class='table-secondary fw-semibold'><td></td><td></td><td></td><td>Summe</td><td>".number_format($SumReBetrag, 2, ',', '.')."</td><td></td><td>".number_format($SumZahlung, 2, ',', '.')."</td><td>".number_format($SumSkonto, 2, ',', '.')."</td><td".$negcls.">".number_format($SumSaldo, 2, ',', '.')."</td><td>".number_format($SumNetto, 2, ',', '.')."</td><td></td><td></td></tr>\n";

	print "\t</tbody>\n";
	print "\t</table>\n";
	print "</div>\n";

	if ($strMonat != "*") {
		print "<div class='d-flex flex-wrap gap-2 my-3'>\n";
		print "<a class='btn btn-outline-success btn-sm' href='export_kunden.php'>EXPORT Kunden</a>\n";
		print "<a class='btn btn-outline-success btn-sm' href='export_lieferanten.php'>EXPORT Lieferanten</a>\n";
		print "<a class='btn btn-outline-success btn-sm' href='export_ar.php?jahr=".$strJahr."&monat=".$strMonat."'>EXPORT AR</a>\n";
		print "<a class='btn btn-outline-success btn-sm' href='export_ar_zahlung.php?jahr=".$strJahr."&monat=".$strMonat."'>EXPORT AR Zahlungen</a>\n";
		print "<a class='btn btn-outline-success btn-sm' href='export_er.php?jahr=".$strJahr."&monat=".$strMonat."'>EXPORT ER</a>\n";
		print "<button class='btn btn-success btn-sm' type='button' onclick=\"window.location.href='pdfexport/export_pdf.php?jahr=".$strJahr."&monat=".$strMonat."';\"><i class='fas fa-download'></i> Download PDF ZIP-Archiv</button>\n";
		print "</div>\n";
		//print "<br><button style='border: 1px solid #006600; font-size: 12px; padding: 8px; font-weight: bold; border-radius: 5px; background-color: #00cc00; color: #fff;' onclick=\"window.location.href='pdfexport/export_pdf.php?jahr=".$strJahr."&monat=".$strMonat."';\"><i class='fas fa-download fa-2x'></i> Download PDF ZIP-Archiv</button>\n";
	}	
	
	print "</div>\n";
	print "</div>\n";
	print "</div>\n";
	print "</div>\n";
	
	
	
	/* Eingangsrechnungen */
	print "<div class='row g-3 mt-3'>\n";
	print "<div class='col-12'>\n";
	print "<div class='card shadow-sm'>\n";
	print "<div class='card-header d-flex align-items-center justify-content-between'><h2 class='h5 mb-0'>Eingangsrechnungen</h2></div>\n";
	print "<div class='card-body'>\n";
	print "<div class='table-responsive'>\n";
	print "\t<table class='table table-sm table-striped table-hover align-middle'>\n";
	$SumERBetrag = 0;
	$SumZahlung = 0;
	$SumSaldo = 0;
	$SumMwSt = 0;
	$Nett0 = 0;
	if ($strMonat == "*") {
		$strQuery = "";
	} else {
		$strQuery = " AND (MONTH(bills.bill_date)=".$strMonat.") ";
	}
	
	$strSQL = "SELECT bills.id, bills.prefix, bills.bill_number, bills.bill_date, bills.invoice_reference,  bills.name as billname, accounts.name, bills.deleted, bills.amount, accounts.ticker_symbol
	FROM bills INNER JOIN accounts ON bills.supplier_id = accounts.id
	WHERE (((bills.deleted)=0) AND (YEAR(bills.bill_date)=".$strJahr.")".$strQuery.") ORDER BY bills.prefix, bills.bill_number;";

	print "\t<thead class='table-light'><tr><th>ER-Nr.</th><th>Datum</th><th>Fibu</th><th>BelegNr</th><th>Bezeichnung</th><th>Betrag</th><th>Steuer</th><th>StPrz.</th><th>Zahlung am</th><th>Betrag</th><th></th><th>Saldo</th></tr></thead>\n";
	print "\t<tbody>\n";
	
	if ($result = mysqli_query($link, $strSQL)) {
		while ($row = mysqli_fetch_assoc($result)) {
			$ReBetrag = $row['amount'];
			$MwSt = 0;
			
			$strSQL = "SELECT * FROM bill_adjustments WHERE deleted=0 AND related_type='TaxRates' AND bills_id = '".$row['id']."';";
			if ($resultLines = mysqli_query($link, $strSQL)) {
				while ($rowLine = mysqli_fetch_assoc($resultLines)) {
					$MwSt = $rowLine['amount'];
				}
			}
			$Netto = $ReBetrag - $MwSt;
			if ($Netto > 0) {
				$MwStPrz = $MwSt / $Netto;
			} else {
				$MwStPrz = 0; // Oder eine andere sinnvolle Standardeinstellung
			}
			
			$MwStPrz = round($MwStPrz, 2)*100;
			
			print "\t<tr>
			<td>".$row['invoice_reference']."</td>
			<td>".$row['bill_date']."</td>
			<td>".$row['ticker_symbol']."</td>
			<td><a href='https://addinol-lubeoil.at/crm/index.php?module=Bills&action=DetailView&record=".$row['id']."' target='_blank'>".$row['prefix'].$row['bill_number']."</a></td>
			<td>".$row['billname']."</td>
			<td align='right'>".number_format($row['amount'], 2, ',', '.')."</td>";

			print "<td>".number_format($MwSt, 2, ',', '.')."</td>";
			print "<td>".$MwStPrz." %</td>";
			
			$SumERBetrag += $ReBetrag;
			$SumMwSt += $MwSt;
		}
	}
	
	
		
	print "\t<tr class='table-secondary fw-semibold'><td></td><td></td><td></td><td></td><td>Summe</td><td>".number_format($SumERBetrag, 2, ',', '.')."</td><td>".number_format($SumMwSt, 2, ',', '.')."</td>";
	print "<td></td><td>--</td><td".$negcls.">".number_format(($SumERBetrag-$SumMwSt), 2, ',', '.')."</td><td></td><td></td></tr>\n";
	print "\t</tbody>\n";
	print "\t</table>\n";
	print "</div>\n";
	print "</div>\n";
	print "</div>\n";
	print "</div>\n";
	print "</div>\n";
	

?>
	</main>
	<script src="assets/bootstrap/bootstrap.bundle.min.js"></script>
</body>
</html>
