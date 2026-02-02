<?php
	$link = mysqli_connect('localhost', 'addinol_usr', 'lwT1e99~', 'addinol_crm');
	mysqli_set_charset($link, "utf8");
	
?>
<!doctype html>
<html lang="de">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Offene Rechnungen</title>
<link href="styles.css" rel="stylesheet" type="text/css" />
<link href="assets/bootstrap/bootstrap.min.css" rel="stylesheet" />
<link rel="stylesheet" href="assets/datatables/dataTables.bootstrap5.min.css" />
<link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.5.0/css/all.css" integrity="sha384-B4dIYHKNBt8Bc12p+WXckhzcICo0wtJAoU8YZTY5qE0Id1GSseTk6S+L3BlXeVIU" crossorigin="anonymous">
</head>
<body class="bg-light">
	<?php require_once __DIR__ . '/navbar.php'; ?>
	<main class="container-fluid py-3">
		<h1 class="h3 mb-3">Offene Rechnungen</h1> 
<?php
	
	if (isset($_GET['invoice_id']) && isset($_GET['setnull'])) {
		$invoice = $_GET['invoice_id'];
		print "<div class='alert alert-warning'>RECHNUNG: ".$invoice."</div>";
		
		$strUpdate = "UPDATE invoice 
			SET amount_due = '0'
			AND amount_due_usdollar = '0'
			WHERE id = '$invoice'";
		echo "<div class='text-muted small mb-2'>".$strUpdate."</div>";
		$result = mysqli_query($link, $strUpdate);
		if ($result == 1) {
			echo "<div class='alert alert-success'><i>".$result." : Offener Betrag der Rechnung ".$invoice." wurde erfolgreich auf 0 gesetzt.</i></div>";
		} else {
			echo "<div class='alert alert-danger'><i>ACHTUNG-FEHLER: ".$result."</i></div>";
		}
	}

	$strSQL = "SELECT invoice.*, accounts.name AS kundenname
		FROM invoice
		LEFT JOIN accounts ON accounts.id = invoice.billing_account_id
		WHERE invoice.amount_due <> '0.00'
		ORDER BY invoice.invoice_date;";
	
print "<div class='card shadow-sm mx-auto' style='max-width: 1200px;'>\n";
	print "<div class='card-body'>\n";
	print "<div class='table-responsive'>\n";
	print "\t<table id='offene-rechnungen-table' class='table table-sm table-striped table-hover align-middle'>\n";
print "\t<thead class='table-light'><tr><th>CRM</th><th>Kunde</th><th>RE-Nummer</th><th>Datum</th><th>Fällig</th><th>Offen</th><th></th><th></th></tr></thead>\n";
print "\t<tfoot class='table-light'><tr><th>CRM</th><th>Kunde</th><th>RE-Nummer</th><th>Datum</th><th>Fällig</th><th>Offen</th><th></th><th></th></tr></tfoot>\n";
	print "\t<tbody>\n";

	if ($result = mysqli_query($link, $strSQL)) 
	{
		while ($row = mysqli_fetch_assoc($result)) {	
			$invoiceId = htmlspecialchars($row['id'], ENT_QUOTES);
			$confirmId = "confirm-" . $invoiceId;
			print "\t<tr>
			<td><a class='btn btn-sm btn-outline-secondary' href='https://addinol-lubeoil.at/crm/index.php?module=Invoice&action=DetailView&record=".$invoiceId."' target='_blank'>CRM</a></td>
			<td>".$row['kundenname']."</td>
			<td>".$row['prefix'].$row['invoice_number']."</td><td>".$row['invoice_date']."</td>";
			$dueDate = $row['due_date'];
			$isOverdue = $dueDate !== '' && $dueDate < date('Y-m-d');
			$dueClass = $isOverdue ? "text-danger fw-semibold" : "";
			print "<td class='".$dueClass."'>".$dueDate."</td>";
			print "<td align='right' data-order='".$row['amount_due']."'>".number_format($row['amount_due'], 2, ',', '.')."</td>";
			print "<td><button type='button' class='btn btn-sm btn-outline-primary rechnung-details' data-bs-toggle='modal' data-bs-target='#rechnungDetailModal' data-invoice-id='".$invoiceId."'>Detail</button></td>";
			print "<td>";
			$amountDue = (float)$row['amount_due'];
			if ($amountDue < 2 && $amountDue > -2) {
				print "<div class='d-inline-flex align-items-center gap-2'>
					<button type='button' class='btn btn-sm btn-outline-danger confirm-toggle' data-confirm-id='".$confirmId."'>auf 0 setzen</button>
					<span id='".$confirmId."' class='d-none'>
						<a class='btn btn-sm btn-danger' href='?setnull=OK&invoice_id=".$invoiceId."' target='_self'>Ja</a>
						<button type='button' class='btn btn-sm btn-outline-secondary confirm-cancel'>Nein</button>
					</span>
				</div>";
			}
			print "</td>";
			print "</tr>\n";
			$account = $row['billing_account_id'];
		}
	}
	print "\t</tbody>\n";
	print "\t</table>\n";
	print "</div>\n";
	print "</div>\n";
	print "</div>\n";
	
?>
	</main>
	<div class="modal fade" id="rechnungDetailModal" tabindex="-1" aria-labelledby="rechnungDetailLabel" aria-hidden="true">
		<div class="modal-dialog modal-xl modal-dialog-scrollable">
			<div class="modal-content">
				<div class="modal-header">
					<h5 class="modal-title" id="rechnungDetailLabel">Rechnung Detail</h5>
					<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
				</div>
				<div class="modal-body p-0">
					<iframe id="rechnungDetailFrame" title="Rechnung Detail" style="width: 100%; height: 70vh; border: 0;"></iframe>
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
			$('#offene-rechnungen-table tfoot th').each(function () {
				var title = $(this).text();
				if (title) {
					$(this).html('<input type="text" class="form-control form-control-sm" placeholder="Filter ' + title + '" />');
				} else {
					$(this).html('');
				}
			});

			var table = new DataTable('#offene-rechnungen-table', {
				pageLength: 25,
				lengthMenu: [10, 25, 50, 100],
				order: [[4, 'asc']],
				columnDefs: [{ targets: [0,6,7], orderable: false, searchable: false }],
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

			document.addEventListener('click', function (event) {
				var toggle = event.target.closest('.confirm-toggle');
				if (toggle) {
					var targetId = toggle.getAttribute('data-confirm-id');
					var target = document.getElementById(targetId);
					if (target) {
						target.classList.remove('d-none');
						toggle.classList.add('d-none');
					}
					return;
				}

				var cancel = event.target.closest('.confirm-cancel');
				if (cancel) {
					var wrapper = cancel.closest('td');
					if (!wrapper) return;
					var toggleBtn = wrapper.querySelector('.confirm-toggle');
					var confirmBox = wrapper.querySelector('span[id^=\"confirm-\"]');
					if (confirmBox) confirmBox.classList.add('d-none');
					if (toggleBtn) toggleBtn.classList.remove('d-none');
				}
			});

			var modal = document.getElementById('rechnungDetailModal');
			modal.addEventListener('show.bs.modal', function (event) {
				var button = event.relatedTarget;
				var id = button.getAttribute('data-invoice-id');
				document.getElementById('rechnungDetailLabel').textContent = 'Rechnung Detail: ' + id;
				document.getElementById('rechnungDetailFrame').src = 'update_invoice.php?invoice_id=' + encodeURIComponent(id) + '&embed=1';
			});
			modal.addEventListener('hidden.bs.modal', function () {
				document.getElementById('rechnungDetailFrame').src = '';
			});
		})();
	</script>
</body>
</html>
