<?php
	$link = mysqli_connect('localhost', 'addinol_usr', 'lwT1e99~', 'addinol_crm');
	mysqli_set_charset($link, "utf8");

	$SumSaldo = 0;
	$SumNetto = 0;

	if (isset($_POST['absenden'])){
		$strJahr = $_POST['cmbJahr'];
		$strMonat = $_POST['cmbMonat'];
		$strRange = $_POST['cmbRange'] ?? '36';
	} else {
		$strJahr = date('Y');
		$strMonat = date('n');
		$strRange = '36';
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
	$arrUmsatzSorted = $arrUmsatz;
	ksort($arrUmsatzSorted);
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

	$chartLabels = array();
	$chartZahlung = array();
	$chartRechnung = array();
	$chartUmsatz = array();
	$chartGewinn = array();

	foreach ($arrUmsatzSorted as $nr => $inhalt) {
		if (isset($inhalt['Jahr'])) {
			$yearShort = substr($inhalt['Jahr'], -2);
			$monthPadded = str_pad($inhalt['Monat'], 2, "0", STR_PAD_LEFT);
			$label = $yearShort . "/" . $monthPadded;
			$zahlung = round($inhalt['Zahlung'] ?? 0, 2);
			$rechnung = round($inhalt['Rechnung'] ?? 0, 2);
			$umsatz = round($inhalt['Umsatz'] ?? 0, 2);
			$gewinn = $umsatz - $rechnung;
			$chartLabels[] = $label;
			$chartZahlung[] = $zahlung;
			$chartRechnung[] = $rechnung;
			$chartUmsatz[] = $umsatz;
			$chartGewinn[] = $gewinn;
		}
	}

	$pieLabels = array();
	$pieValues = array();
	foreach ($arrPie as $inhalt) {
		$pieLabels[] = $inhalt['Name'];
		$pieValues[] = round($inhalt['Umsatz'], 2);
	}

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

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script type="text/javascript">
  document.addEventListener('DOMContentLoaded', () => {
	let chartLabels = <?php echo json_encode($chartLabels); ?>;
	let chartZahlung = <?php echo json_encode($chartZahlung); ?>;
	let chartRechnung = <?php echo json_encode($chartRechnung); ?>;
	let chartUmsatz = <?php echo json_encode($chartUmsatz); ?>;
	let chartGewinn = <?php echo json_encode($chartGewinn); ?>;
	let pieLabels = <?php echo json_encode($pieLabels); ?>;
	let pieValues = <?php echo json_encode($pieValues); ?>;

	const useRange = "<?php echo $strRange; ?>" === "36";
	if (useRange && chartLabels.length > 36) {
		const start = chartLabels.length - 36;
		chartLabels = chartLabels.slice(start);
		chartZahlung = chartZahlung.slice(start);
		chartRechnung = chartRechnung.slice(start);
		chartUmsatz = chartUmsatz.slice(start);
		chartGewinn = chartGewinn.slice(start);
	}

	const overviewCtx = document.getElementById('chart_div').getContext('2d');
	new Chart(overviewCtx, {
		type: 'line',
		data: {
			labels: chartLabels,
			datasets: [
				{
					label: 'Zahlungseingang',
					data: chartZahlung,
					borderColor: '#0d6efd',
					backgroundColor: 'rgba(13,110,253,0.15)',
					tension: 0.3,
					fill: true,
					hidden: true
				},
				{
					label: 'Eingangsrechnungen',
					data: chartRechnung,
					borderColor: '#dc3545',
					backgroundColor: 'rgba(220,53,69,0.12)',
					tension: 0.3,
					fill: true,
					hidden: true
				},
				{
					label: 'Umsatz',
					data: chartUmsatz,
					borderColor: '#198754',
					backgroundColor: 'rgba(25,135,84,0.15)',
					tension: 0.3,
					fill: true
				},
				{
					label: 'Gewinn',
					data: chartGewinn,
					borderColor: '#6f42c1',
					backgroundColor: 'rgba(111,66,193,0.15)',
					tension: 0.3,
					fill: true
				}
			]
		},
		options: {
			responsive: true,
			maintainAspectRatio: false,
			interaction: { mode: 'index', intersect: false },
			plugins: {
				title: {
					display: true,
					text: useRange ? 'Übersicht (letzte 36 Monate)' : 'Übersicht (alle Jahre)'
				},
				legend: { position: 'top' },
				tooltip: { mode: 'index', intersect: false }
			},
			scales: {
				x: {
					title: { display: true, text: 'Monat' },
					ticks: { autoSkip: true, maxTicksLimit: 12 }
				},
				y: {
					beginAtZero: true
				}
			}
		}
	});

	const pieCtx = document.getElementById('piechart').getContext('2d');
	new Chart(pieCtx, {
		type: 'doughnut',
		data: {
			labels: pieLabels,
			datasets: [
				{
					data: pieValues,
					backgroundColor: [
						'#0d6efd', '#198754', '#ffc107', '#dc3545', '#6f42c1',
						'#20c997', '#fd7e14', '#0dcaf0', '#6c757d', '#1982c4'
					],
					borderWidth: 0
				}
			]
		},
		options: {
			responsive: true,
			maintainAspectRatio: false,
			plugins: {
				title: { display: true, text: 'Monatsumsatz' },
				legend: { display: false }
			},
			cutout: '60%'
		}
	});
  });
</script>

	</head>
<body class="bg-light">
	<?php require_once __DIR__ . '/navbar.php'; ?>

	<main class="container-fluid py-3">
		<div class="row g-3">
			<div class="col-12 col-xl-8">
				<div class="card shadow-sm">
					<div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
						<h2 class="h6 mb-0">Übersicht</h2>
						<form action="bilanz.php" method="post" class="d-flex align-items-center gap-2 m-0">
							<input type="hidden" name="cmbJahr" value="<?php echo htmlspecialchars($strJahr, ENT_QUOTES); ?>">
							<input type="hidden" name="cmbMonat" value="<?php echo htmlspecialchars($strMonat, ENT_QUOTES); ?>">
							<select name="cmbRange" class="form-select form-select-sm" onchange="this.form.submit()">
								<option value="36" <?php if ($strRange === "36") echo 'selected'; ?>>letzte 36 Monate</option>
								<option value="all" <?php if ($strRange === "all") echo 'selected'; ?>>alle Jahre</option>
							</select>
							<input type="hidden" name="absenden" value="anzeigen">
						</form>
					</div>
					<div class="card-body p-2">
						<canvas id="chart_div" style="min-height: 320px;"></canvas>
					</div>
				</div>
			</div>
			<div class="col-12 col-xl-4">
				<div class="card shadow-sm">
					<div class="card-body">
						<canvas id="piechart" style="min-height: 175px;"></canvas>
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

	$jahrAlertHtml = "";
	if ($result = mysqli_query($link, $strSQLJahresUmsatz)) {
		while ($row = mysqli_fetch_assoc($result)) {
			if ($row['Umsatz'] >= 1000000) {
				$jahrAlertHtml = "<div class='alert alert-success fw-semibold mb-0'>Jahresumsatz: € " . number_format($row['Umsatz'], 2, ',', '.') . " — Million geknackt!</div>";
			} else {
				$jahrAlertHtml = "<div class='alert alert-info mb-0'>Jahresumsatz: € " . number_format($row['Umsatz'], 2, ',', '.') . "</div>";
			}
		}		
	}	


?>	
		<div class="row g-3 mt-2">
			<div class="col-12 col-lg-8">
				<div class="card shadow-sm">
					<div class="card-body">
						<form action='bilanz.php' method='post' class="row g-2 align-items-end">
							<div class="col-12 col-md-3">
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
							<div class="col-12 col-md-3">
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
							<div class="col-12 col-md-3">
								<button class="btn btn-primary btn-sm w-100" type='submit' name='absenden' value='anzeigen'>anzeigen</button>
							</div>
						</form>
					</div>
				</div>
			</div>
			<div class="col-12 col-lg-4">
				<div class="card shadow-sm h-100">
					<div class="card-body">
						<?php echo $jahrAlertHtml; ?>
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

	print "\t<thead class='table-light'><tr><th>Rechnung</th><th>Datum</th><th>Fibu</th><th>Bezeichnung</th><th class='text-end'>Brutto</th><th>Zahlung</th><th class='text-end'>Betrag</th><th class='text-end'>Skonto</th><th class='text-end'>Saldo</th><th class='text-end'>Netto</th><th class='text-end'>MwSt</th><th class='text-end'>Abzug</th></tr></thead>\n";
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
	
	
	
	$negClassSum = $SumSaldo < 0.00 ? " neg" : "";
	
	print "\t<tr class='table-secondary fw-semibold'><td></td><td></td><td></td><td>Summe</td><td class='text-end'>".number_format($SumReBetrag, 2, ',', '.')."</td><td></td><td class='text-end'>".number_format($SumZahlung, 2, ',', '.')."</td><td class='text-end'>".number_format($SumSkonto, 2, ',', '.')."</td><td class='text-end".$negClassSum."'>".number_format($SumSaldo, 2, ',', '.')."</td><td class='text-end'>".number_format($SumNetto, 2, ',', '.')."</td><td></td><td></td></tr>\n";

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

	print "\t<thead class='table-light'><tr><th>ER-Nr.</th><th>Datum</th><th>Fibu</th><th>BelegNr</th><th>Bezeichnung</th><th class='text-end'>Betrag</th><th class='text-end'>Steuer</th><th>StPrz.</th><th>Zahlung am</th><th class='text-end'>Betrag</th><th></th><th class='text-end'>Saldo</th></tr></thead>\n";
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
	
	
		
	$saldoEr = $SumERBetrag - $SumMwSt;
	$negClassEr = $saldoEr < 0.00 ? " neg" : "";
	print "\t<tr class='table-secondary fw-semibold'><td></td><td></td><td></td><td></td><td>Summe</td><td class='text-end'>".number_format($SumERBetrag, 2, ',', '.')."</td><td class='text-end'>".number_format($SumMwSt, 2, ',', '.')."</td>";
	print "<td></td><td>--</td><td class='text-end".$negClassEr."'>".number_format($saldoEr, 2, ',', '.')."</td><td></td><td></td></tr>\n";
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
