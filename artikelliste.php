<?php
	$link = mysqli_connect('localhost', 'addinol_usr', 'lwT1e99~', 'addinol_crm');
	mysqli_set_charset($link, "utf8");
	
?>
<!doctype html>
<html lang="de">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Artikelliste</title>
<link href="styles.css" rel="stylesheet" type="text/css" />
<link href="assets/bootstrap/bootstrap.min.css" rel="stylesheet" />
<link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.5.0/css/all.css" integrity="sha384-B4dIYHKNBt8Bc12p+WXckhzcICo0wtJAoU8YZTY5qE0Id1GSseTk6S+L3BlXeVIU" crossorigin="anonymous">
<link rel="stylesheet" href="assets/datatables/dataTables.bootstrap5.min.css" />
</head>
<body class="bg-light">
	<?php require_once __DIR__ . '/navbar.php'; ?>
	<main class="container-fluid py-3">
		<h1 class="h3 mb-3">Artikelliste</h1>
<?php
	
	/* Ausgangsrechnungen */
	print "<div class='card shadow-sm mx-auto' style='max-width: 1200px;'>\n";
	print "<div class='card-body'>\n";
	print "<div class='table-responsive'>\n";
	print "\t<table id='artikelliste-table' class='table table-sm table-striped table-hover align-middle'>\n";

	$strSQL = "SELECT il.related_id, il.name,
			Sum(il.quantity) AS Stueck,
			MAX(il.list_price) as list_price,
			COALESCE(c.AnzKunden, 0) as AnzKunden
		FROM invoice_lines il
		INNER JOIN invoice i ON il.invoice_id = i.id
		LEFT JOIN (
			SELECT il2.related_id, COUNT(DISTINCT i2.billing_account_id) as AnzKunden
			FROM invoice_lines il2
			INNER JOIN invoice i2 ON il2.invoice_id = i2.id
			WHERE i2.deleted=0 AND il2.deleted=0
			GROUP BY il2.related_id
		) c ON c.related_id = il.related_id
		WHERE i.deleted=0 AND il.deleted=0
		GROUP BY il.related_id, il.name
		ORDER BY Sum(il.quantity) desc";
		
	print "\t<thead class='table-light'><tr><th>Artikel</th><th>Stück</th><th>AnzKunden</th><th>Listenpreis</th><th></th></tr></thead>\n";
	print "\t<tfoot class='table-light'><tr><th>Artikel</th><th>Stück</th><th>AnzKunden</th><th>Listenpreis</th><th></th></tr></tfoot>\n";
	print "\t<tbody>\n";

	if ($result = mysqli_query($link, $strSQL)) 
	{
		while ($row = mysqli_fetch_assoc($result)) {	
			print "\t<tr>
			<td><a href='https://addinol-lubeoil.at/crm/index.php?module=ProductCatalog&action=DetailView&record=".$row['related_id']."' target='_blank'>".$row['name']."</a></td>
			<td>".number_format($row['Stueck'], 2, ',', '.')."</td>
			<td align='right'>".number_format($row['AnzKunden'], 0, ',', '.')."</td>
			<td align='right'>".number_format($row['list_price'], 2, ',', '.')."</td>
			<td><button type='button' class='btn btn-sm btn-outline-primary artikel-details' data-bs-toggle='modal' data-bs-target='#artikelKundeModal' data-article-id='".$row['related_id']."' data-article-name=\"".htmlspecialchars($row['name'], ENT_QUOTES)."\">Details</button></td>";

			print "</tr>\n";
		}
	}

	print "\t</tbody>\n";
	print "\t</table>\n";
	print "</div>\n";
	print "</div>\n";
	print "</div>\n";
	
?>
	</main>
	<div class="modal fade" id="artikelKundeModal" tabindex="-1" aria-labelledby="artikelKundeLabel" aria-hidden="true">
		<div class="modal-dialog modal-xl modal-dialog-scrollable">
			<div class="modal-content">
				<div class="modal-header">
					<h5 class="modal-title" id="artikelKundeLabel">Artikel Kunden</h5>
					<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
				</div>
				<div class="modal-body p-0">
					<iframe id="artikelKundeFrame" title="Artikel Kunden" style="width: 100%; height: 70vh; border: 0;"></iframe>
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
			$('#artikelliste-table tfoot th').each(function () {
				var title = $(this).text();
				if (title) {
					$(this).html('<input type="text" class="form-control form-control-sm" placeholder="Filter ' + title + '" />');
				} else {
					$(this).html('');
				}
			});

			var table = new DataTable('#artikelliste-table', {
				pageLength: 25,
				lengthMenu: [10, 25, 50, 100],
				order: [[1, 'desc']],
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

			var modal = document.getElementById('artikelKundeModal');
			modal.addEventListener('show.bs.modal', function (event) {
				var button = event.relatedTarget;
				var id = button.getAttribute('data-article-id');
				var name = button.getAttribute('data-article-name');
				document.getElementById('artikelKundeLabel').textContent = 'Artikel Kunden: ' + name;
				document.getElementById('artikelKundeFrame').src = 'artikel_kunde.php?id=' + encodeURIComponent(id) + '&aname=' + encodeURIComponent(name) + '&embed=1';
			});
			modal.addEventListener('hidden.bs.modal', function () {
				document.getElementById('artikelKundeFrame').src = '';
			});
		})();
	</script>
</body>
</html>
