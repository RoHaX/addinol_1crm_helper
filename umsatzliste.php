<?php
	$link = mysqli_connect('localhost', 'addinol_usr', 'lwT1e99~', 'addinol_crm');
	mysqli_set_charset($link, "utf8");
	
	if (isset($_POST['absenden'])){
		$strJahr = $_POST['cmbJahr'];
	} else {
		$strJahr = date('Y');
	}

	
	/* Begin PIECHART 	*/
	$strSQL = "SELECT Sum(invoice.amount) as Brutto,  Sum(invoice.pretax) as Netto, invoice.deleted, accounts.name, YEAR(invoice_date) as Jahr
		FROM accounts 
		INNER JOIN invoice ON accounts.id = invoice.billing_account_id
		GROUP BY YEAR(invoice_date), accounts.name, invoice.deleted
		HAVING (((invoice.deleted)=0) AND (Jahr=".$strJahr.")) 
		ORDER BY Netto DESC;";
	if ($result = mysqli_query($link, $strSQL)) {
		while ($row = mysqli_fetch_assoc($result)) {	
			$arrPie[] = [
				'Name' => $row['name'],
				'Netto' => $row['Netto']			
			];
		}
	}
	
	/* 	END PIECHART */
	
?>
<!doctype html>
<html lang="de">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Umsatzliste</title>
<link href="styles.css" rel="stylesheet" type="text/css" />
<link href="assets/bootstrap/bootstrap.min.css" rel="stylesheet" />
<link rel="stylesheet" href="assets/datatables/dataTables.bootstrap5.min.css" />
<link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.5.0/css/all.css" integrity="sha384-B4dIYHKNBt8Bc12p+WXckhzcICo0wtJAoU8YZTY5qE0Id1GSseTk6S+L3BlXeVIU" crossorigin="anonymous">
<script type="text/javascript" src="https://www.gstatic.com/charts/loader.js"></script>
<script type="text/javascript">
  google.charts.load('current', {'packages':['corechart']});
  google.charts.setOnLoadCallback(drawChart);
  function drawChart() {
	var data = google.visualization.arrayToDataTable([

<?php
	print "['Firma', 'Netto']";
	foreach ($arrPie as $nr => $inhalt)
	{
		print ",";
		$Key  = $inhalt['Name'];
		$Netto  = $inhalt['Netto'];
		echo "['$Key', $Netto]";
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
		<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-3">
			<h1 class="h3 mb-0">Umsatzliste</h1> 
			<form action='umsatzliste.php' method='post' class="d-flex flex-wrap gap-2 align-items-center">
				<select name='cmbJahr' class="form-select form-select-sm">
					<?php
					for ($i = 2015; $i <= 2025; $i++) {
						$selected = $strJahr == $i ? 'selected' : '';
						echo "<option value='$i' $selected>$i</option>";
					}
					?>		
				</select>
				<button class="btn btn-primary btn-sm" type='submit' name='absenden' value='anzeigen'>anzeigen</button>
			</form>
		</div>
		<div class="row g-3 mb-3">
			<div class="col-12 col-xl-4">
				<div class="card shadow-sm">
					<div class="card-body">
						<div id="piechart" style="min-height: 350px;"></div>
					</div>
				</div>
			</div>
			<div class="col-12 col-xl-8">
				<div class="card shadow-sm">
					<div class="card-body">
<?php
	
	$SumReBetrag = 0;
	$SumZahlung = 0;
	$SumSkonto = 0;
	
	/* Ausgangsrechnungen */
	print "<div class='table-responsive'>\n";
	print "\t<table id='umsatzliste-table' class='table table-sm table-striped table-hover align-middle'>\n";

	$strSQL = "SELECT Count(invoice.id) as AnzRe, Sum(invoice.amount) as Brutto,  Sum(invoice.pretax) as Netto, invoice.deleted, accounts.id, accounts.name, YEAR(invoice_date) as Jahr
		FROM accounts 
		INNER JOIN invoice ON accounts.id = invoice.billing_account_id
		GROUP BY YEAR(invoice_date), accounts.name, invoice.deleted
		HAVING (((invoice.deleted)=0) AND (Jahr=".$strJahr.")) 
		ORDER BY Netto DESC;";

	print "\t<thead class='table-light'><tr><th>Kunde</th><th>Anz.RE</th><th>Brutto</th><th>Netto</th><th></th></tr></thead>\n";
	print "\t<tfoot class='table-light'><tr><th>Kunde</th><th>Anz.RE</th><th>Brutto</th><th>Netto</th><th></th></tr></tfoot>\n";
	print "\t<tbody>\n";
	if ($result = mysqli_query($link, $strSQL)) {
		while ($row = mysqli_fetch_assoc($result)) {	
			print "\t<tr>
			<td><a href='https://addinol-lubeoil.at/crm/index.php?module=Accounts&action=DetailView&record=".$row['id']."' target='_blank'>".$row['name']."</a></td>
			<td>".$row['AnzRe']."</td>
			<td align='right'>".number_format($row['Brutto'], 2, ',', '.')."</td>
			<td align='right'>".number_format($row['Netto'], 2, ',', '.')."</td>
			<td><button type='button' class='btn btn-sm btn-outline-primary kunde-details' data-bs-toggle='modal' data-bs-target='#kundeArtikelModal' data-account-id='".$row['id']."' data-account-name=\"".htmlspecialchars($row['name'], ENT_QUOTES)."\">Details</button></td>";
			print "</tr>\n";
		}
	}
	
	print "\t</tbody>\n";
	print "\t</table>\n";
	print "</div>\n";

?>
					</div>
				</div>
			</div>
		</div>
	</main>
	<div class="modal fade" id="kundeArtikelModal" tabindex="-1" aria-labelledby="kundeArtikelLabel" aria-hidden="true">
		<div class="modal-dialog modal-xl modal-dialog-scrollable">
			<div class="modal-content">
				<div class="modal-header">
					<h5 class="modal-title" id="kundeArtikelLabel">Kunde Artikel</h5>
					<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
				</div>
				<div class="modal-body p-0">
					<iframe id="kundeArtikelFrame" title="Kunde Artikel" style="width: 100%; height: 70vh; border: 0;"></iframe>
				</div>
			</div>
		</div>
	</div>
	<script src="assets/datatables/jquery.min.js"></script>
	<script src="assets/datatables/jquery.dataTables.min.js"></script>
	<script src="assets/datatables/dataTables.bootstrap5.min.js"></script>
	<script src="assets/bootstrap/bootstrap.bundle.min.js"></script>
	<script>
		(function () {
			$('#umsatzliste-table tfoot th').each(function () {
				var title = $(this).text();
				if (title) {
					$(this).html('<input type="text" class="form-control form-control-sm" placeholder="Filter ' + title + '" />');
				} else {
					$(this).html('');
				}
			});

			var table = new DataTable('#umsatzliste-table', {
				pageLength: 25,
				lengthMenu: [10, 25, 50, 100],
				order: [[3, 'desc']],
				columnDefs: [{ targets: 4, orderable: false, searchable: false }],
				language: {
					search: 'Suche:',
					lengthMenu: '_MENU_ Einträge pro Seite',
					info: '_START_ bis _END_ von _TOTAL_ Einträgen',
					infoEmpty: '0 Einträge',
					infoFiltered: '(gefiltert von _MAX_ Einträgen)',
					paginate: { first: 'Erste', last: 'Letzte', next: 'Weiter', previous: 'Zurück' },
					zeroRecords: 'Keine passenden Einträge gefunden'
				}
			});

			table.columns().every(function () {
				var that = this;
				$('input', this.footer()).on('keyup change clear', function () {
					if (that.search() !== this.value) {
						that.search(this.value).draw();
					}
				});
			});

			var modal = document.getElementById('kundeArtikelModal');
			modal.addEventListener('show.bs.modal', function (event) {
				var button = event.relatedTarget;
				var id = button.getAttribute('data-account-id');
				var name = button.getAttribute('data-account-name');
				document.getElementById('kundeArtikelLabel').textContent = 'Kunde Artikel: ' + name;
				document.getElementById('kundeArtikelFrame').src = 'kunde_artikel.php?id=' + encodeURIComponent(id) + '&embed=1';
			});
			modal.addEventListener('hidden.bs.modal', function () {
				document.getElementById('kundeArtikelFrame').src = '';
			});
		})();
	</script>
</body>
</html>
