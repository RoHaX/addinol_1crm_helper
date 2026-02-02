<?php
	$link = mysqli_connect('localhost', 'addinol_usr', 'lwT1e99~', 'addinol_crm');
	mysqli_set_charset($link, "utf8");
	
?>
<!doctype html>
<html lang="de">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Offene Beträge Kunde</title>
<link href="styles.css" rel="stylesheet" type="text/css" />
<link href="assets/bootstrap/bootstrap.min.css" rel="stylesheet" />
<link rel="stylesheet" href="assets/datatables/dataTables.bootstrap5.min.css" />
<link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.5.0/css/all.css" integrity="sha384-B4dIYHKNBt8Bc12p+WXckhzcICo0wtJAoU8YZTY5qE0Id1GSseTk6S+L3BlXeVIU" crossorigin="anonymous">
</head>
<body class="<?php echo isset($_GET['embed']) ? 'bg-white' : 'bg-light'; ?>">
<?php if (!isset($_GET['embed'])) { ?>
	<?php require_once __DIR__ . '/navbar.php'; ?>
	<main class="container-fluid py-3">
		<h1 class="h3 mb-3">Offene Beträge Kunde</h1> 
<?php } else { ?>
	<main class="container-fluid py-2">
<?php } ?>
<?php
	
	if (!isset($_GET['embed']) && isset($_GET['account_id']) && isset($_GET['setnull'])) {
		$account_id = $_GET['account_id'];
		print "<div class='alert alert-warning'>Kunde: ".$account_id."</div>";
						
		$strUpdate = "UPDATE accounts 
			SET balance = '0'
			WHERE id = '$account_id'";
		echo "<div class='text-muted small mb-2'>".$strUpdate."</div>";
		$result = mysqli_query($link, $strUpdate);
		if ($result == 1) {
			echo "<div class='alert alert-success'><i>".$result." : Offener Betrag des Kunden wurde erfolgreich auf 0 gesetzt.</i></div>";
		} else {
			echo "<div class='alert alert-danger'><i>ACHTUNG-FEHLER: ".$result."</i></div>";
		}
	}

	if (isset($_GET['embed']) && isset($_GET['account_id'])) {
		$account_id = $_GET['account_id'];
		$strSQLInvoice = "SELECT * FROM invoice 
			WHERE billing_account_id = '" . $account_id . "'
			AND amount_due <> 0";
		print "<div class='card shadow-sm mx-auto' style='max-width: 1200px;'>\n";
		print "<div class='card-body'>\n";
		print "<div class='table-responsive'>\n";
		print "\t<table id='offene-kunde-invoices' class='table table-sm table-striped table-hover align-middle'>\n";
		print "\t<thead class='table-light'><tr><th>CRM</th><th>Rechnung</th><th>Fällig</th><th>Offen</th><th></th></tr></thead>\n";
		print "\t<tbody>\n";
		$current_date = date('Y-m-d');
		if ($resultI = mysqli_query($link, $strSQLInvoice)) {
			while ($rowI = mysqli_fetch_assoc($resultI)) {
				$due_date = $rowI['due_date'];
				$isOverdue = $due_date < $current_date;
				$dueClass = $isOverdue ? "text-danger fw-semibold" : "";
				print "\t<tr>
					<td><a class='btn btn-sm btn-outline-secondary' href='https://addinol-lubeoil.at/crm/index.php?module=Invoice&action=DetailView&record=".$rowI['id']."' target='_blank'>CRM</a></td>
					<td>" . $rowI['prefix'] . $rowI['invoice_number'] . "</td>
					<td class='" . $dueClass . "'>" . $rowI['due_date'] . "</td>
					<td align='right' data-order='".$rowI['amount_due']."'>".number_format($rowI['amount_due'], 2, ',', '.')."</td>
					<td><a class='btn btn-sm btn-outline-primary' href='update_invoice.php?invoice_id=".$rowI['id']."' target='_blank'>Detail</a></td>
				</tr>\n";
			}
		}
		print "\t</tbody>\n";
		print "\t</table>\n";
		print "</div>\n";
		print "</div>\n";
		print "</div>\n";
	} else {

	$strSQL = "SELECT * FROM accounts WHERE balance <> '0.00' ORDER BY balance;";
	
	print "<div class='card shadow-sm mx-auto' style='max-width: 1200px;'>\n";
	print "<div class='card-body'>\n";
	print "<div class='table-responsive'>\n";
	print "\t<table id='offene-betraege-table' class='table table-sm table-striped table-hover align-middle'>\n";
	print "\t<thead class='table-light'><tr><th>CRM</th><th>Kunde</th><th>Ort</th><th>Offener Betrag</th><th></th><th></th></tr></thead>\n";
	print "\t<tfoot class='table-light'><tr><th>CRM</th><th>Kunde</th><th>Ort</th><th>Offener Betrag</th><th></th><th></th></tr></tfoot>\n";
	print "\t<tbody>\n";

	if ($result = mysqli_query($link, $strSQL)) 
	{
		while ($row = mysqli_fetch_assoc($result)) {
			$accountId = htmlspecialchars($row['id'], ENT_QUOTES);
			$confirmId = "confirm-" . $accountId;
			print "\t<tr>
			<td><a class='btn btn-sm btn-outline-secondary' href='https://addinol-lubeoil.at/crm/index.php?module=Accounts&action=DetailView&record=".$accountId."' target='_blank'>CRM</a></td>
			<td><b>".$row['name']."</b></td>
			<td>".$row['billing_address_city']."</td>
			<td align='right' data-order='".$row['balance']."'>".number_format($row['balance'], 2, ',', '.')."</td>
			<td><button type='button' class='btn btn-sm btn-outline-primary konto-details' data-bs-toggle='modal' data-bs-target='#kontoDetailModal' data-account-id='".$accountId."' data-account-name=\"".htmlspecialchars($row['name'], ENT_QUOTES)."\">Details</button></td>
			<td>";
			$balance = (float)$row['balance'];
			if ($balance < 2 && $balance > -2) {
				print "<div class='d-inline-flex align-items-center gap-2'>
					<button type='button' class='btn btn-sm btn-outline-danger confirm-toggle' data-confirm-id='".$confirmId."'>auf 0 setzen</button>
					<span id='".$confirmId."' class='d-none'>
						<a class='btn btn-sm btn-danger' href='?setnull=OK&account_id=".$accountId."' target='_self'>Ja</a>
						<button type='button' class='btn btn-sm btn-outline-secondary confirm-cancel'>Nein</button>
					</span>
				</div>";
			}
			print "</td>";
			print "</tr>\n";
		}
	}
	print "\t</tbody>\n";
	print "\t</table>\n";
	print "</div>\n";
	print "</div>\n";
	print "</div>\n";
	}
	
?>
	</main>
	<?php if (!isset($_GET['embed'])) { ?>
	<div class="modal fade" id="kontoDetailModal" tabindex="-1" aria-labelledby="kontoDetailLabel" aria-hidden="true">
		<div class="modal-dialog modal-xl modal-dialog-scrollable">
			<div class="modal-content">
				<div class="modal-header">
					<h5 class="modal-title" id="kontoDetailLabel">Kunde Details</h5>
					<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
				</div>
				<div class="modal-body p-0">
					<iframe id="kontoDetailFrame" title="Kunde Details" style="width: 100%; height: 70vh; border: 0;"></iframe>
				</div>
			</div>
		</div>
	</div>
	<?php } ?>
	<script src="assets/datatables/jquery.min.js"></script>
	<script src="assets/datatables/jquery.dataTables.min.js"></script>
	<script src="assets/datatables/dataTables.bootstrap5.min.js"></script>
	<script src="assets/bootstrap/bootstrap.bundle.min.js"></script>
	<script>
		(function () {
			<?php if (!isset($_GET['embed'])) { ?>
			$('#offene-betraege-table tfoot th').each(function () {
				var title = $(this).text();
				if (title) {
					$(this).html('<input type="text" class="form-control form-control-sm" placeholder="Filter ' + title + '" />');
				} else {
					$(this).html('');
				}
			});

			var table = new DataTable('#offene-betraege-table', {
				pageLength: 25,
				lengthMenu: [10, 25, 50, 100],
				order: [[3, 'desc']],
				columnDefs: [{ targets: [0,4,5], orderable: false, searchable: false }],
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

			var modal = document.getElementById('kontoDetailModal');
			modal.addEventListener('show.bs.modal', function (event) {
				var button = event.relatedTarget;
				var id = button.getAttribute('data-account-id');
				var name = button.getAttribute('data-account-name');
				document.getElementById('kontoDetailLabel').textContent = 'Kunde Details: ' + name;
				document.getElementById('kontoDetailFrame').src = 'offene_betraege_kunde.php?account_id=' + encodeURIComponent(id) + '&embed=1';
			});
			modal.addEventListener('hidden.bs.modal', function () {
				document.getElementById('kontoDetailFrame').src = '';
			});
			<?php } else { ?>
			new DataTable('#offene-kunde-invoices', {
				pageLength: 25,
				lengthMenu: [10, 25, 50, 100],
				order: [[2, 'asc']],
				columnDefs: [{ targets: [0,4], orderable: false, searchable: false }],
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
			<?php } ?>
		})();
	</script>
</body>
</html>
