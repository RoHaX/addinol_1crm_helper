<?php
	$link = mysqli_connect('localhost', 'addinol_usr', 'lwT1e99~', 'addinol_crm');
	mysqli_set_charset($link, "utf8");
	
	$strProductID = $_GET['id'];
	$strProduct = $_GET['aname'];
	
?>
<!doctype html>
<html lang="de">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Artikel Kunden</title>
<link href="styles.css" rel="stylesheet" type="text/css" />
<link href="assets/bootstrap/bootstrap.min.css" rel="stylesheet" />
<link rel="stylesheet" href="assets/datatables/dataTables.bootstrap5.min.css" />
<link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.5.0/css/all.css" integrity="sha384-B4dIYHKNBt8Bc12p+WXckhzcICo0wtJAoU8YZTY5qE0Id1GSseTk6S+L3BlXeVIU" crossorigin="anonymous">
</head>
<body class="<?php echo isset($_GET['embed']) ? 'bg-white' : 'bg-light'; ?>">
<?php if (!isset($_GET['embed'])) { ?>
	<?php require_once __DIR__ . '/navbar.php'; ?>
	<main class="container-fluid py-3">
		<h1 class="h4 mb-3">
<?php 
	print $strProduct; 
?>	
		</h1>
<?php } else { ?>
	<main class="container-fluid py-2">
<?php } ?>
<?php

	print "<div class='card shadow-sm'>\n";
	print "<div class='card-body'>\n";
	print "<div class='table-responsive'>\n";
	print "\t<table id='artikel-kunde-table' class='table table-sm table-striped table-hover align-middle'>\n";

	$strSQL = "SELECT accounts.name, invoice.billing_account_id, invoice.deleted, invoice_lines.deleted, Sum(invoice_lines.quantity) AS Anzahl, invoice_lines.related_id
		FROM accounts INNER JOIN (invoice_lines INNER JOIN invoice ON invoice_lines.invoice_id = invoice.id) ON accounts.id = invoice.billing_account_id
		GROUP BY accounts.name, invoice.billing_account_id, invoice.deleted, invoice_lines.deleted, invoice_lines.related_id
		HAVING (((invoice.deleted)=0) AND ((invoice_lines.deleted)=0) AND ((invoice_lines.related_id)='".$strProductID."'))";

	print "\t<thead class='table-light'><tr><th>Name</th><th>Stück</th></tr></thead>\n";
	print "\t<tfoot class='table-light'><tr><th>Name</th><th>Stück</th></tr></tfoot>\n";
	print "\t<tbody>\n";
	
	if ($result = mysqli_query($link, $strSQL)) 
	{
		while ($row = mysqli_fetch_assoc($result)) {	
			print "\t<tr>
			<td><a href='https://addinol-lubeoil.at/crm/index.php?module=Accounts&action=DetailView&record=".$row['billing_account_id']."' target='_blank'>".$row['name']."</a></td>
			<td>".$row['Anzahl']."</td>";

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
	<script src="assets/datatables/jquery.min.js"></script>
	<script src="assets/datatables/jquery.dataTables.min.js"></script>
	<script src="assets/datatables/dataTables.bootstrap5.min.js"></script>
	<script src="assets/bootstrap/bootstrap.bundle.min.js"></script>
	<script>
		(function () {
			$('#artikel-kunde-table tfoot th').each(function () {
				var title = $(this).text();
				$(this).html('<input type="text" class="form-control form-control-sm" placeholder="Filter ' + title + '" />');
			});

			var table = new DataTable('#artikel-kunde-table', {
				pageLength: 25,
				lengthMenu: [10, 25, 50, 100],
				order: [[1, 'desc']],
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
		})();
	</script>
</body>
</html>
