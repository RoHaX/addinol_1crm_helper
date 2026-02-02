<?php
	$link = mysqli_connect('localhost', 'addinol_usr', 'lwT1e99~', 'addinol_crm');
	mysqli_set_charset($link, "utf8");
?>
<!doctype html>
<html lang="de">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Lagerstand</title>
<link href="styles.css" rel="stylesheet" type="text/css" />
<link href="assets/bootstrap/bootstrap.min.css" rel="stylesheet" />
<link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.5.0/css/all.css" integrity="sha384-B4dIYHKNBt8Bc12p+WXckhzcICo0wtJAoU8YZTY5qE0Id1GSseTk6S+L3BlXeVIU" crossorigin="anonymous">
<link rel="stylesheet" href="assets/datatables/dataTables.bootstrap5.min.css" />
</head>
<body class="bg-light">
	<?php require_once __DIR__ . '/navbar.php'; ?>
	<main class="container-fluid py-3">
		<h1 class="h3 mb-3">Lagerstand</h1>


<?php

	$strSQL = "SELECT company_addresses.id, company_addresses.name as lagername FROM company_addresses INNER JOIN products_warehouses ON products_warehouses.warehouse_id = company_addresses.id
	WHERE products_warehouses.deleted=0
	GROUP BY company_addresses.id;";
	
	$formLager = isset($_POST['absenden']) ? ($_POST['formLager'] ?? '') : '387dac2f-b390-9e3e-d5ee-566827893cfa';
	print "<form action='lagerstand.php' method='post' class='row g-2 align-items-end mb-3'>\n";
	print "<div class='col-12 col-md-4'>\n";
	print "<label class='form-label small text-muted' for='formLager'>Lager</label>\n";
	print "\t<select class='form-select form-select-sm' id='formLager' name='formLager'>\n";
	print "\t<option value=''".($formLager === '' ? " selected" : "").">alle Lager</option>\n";
	if ($result = mysqli_query($link, $strSQL)) 
	{
		while ($row = mysqli_fetch_assoc($result)) {		
			$selected = $formLager === $row['id'] ? " selected" : "";
			print "\t<option value='".$row['id']."'".$selected.">".$row['lagername']."</option>\n";
		}
	}
	print "\t</select>\n";
	print "</div>\n";
	print "<div class='col-12 col-md-2'>\n";
	print "<button class='btn btn-primary btn-sm w-100' type='submit' name='absenden' value='anzeigen'>anzeigen</button>\n";
	print "</div>\n";
	print "</form>\n";
	$strQuery = "";
/*	$strSQL = "SELECT products.name as productname, products.id as productid, products.cost, sum(products_warehouses.in_stock) as lagerstand, products_warehouses.deleted, products_warehouses.created_by, products_warehouses.product_id,  products_warehouses.date_modified as aenderung, products_cstm.mindestbestand
		FROM (products LEFT JOIN products_cstm ON products.id = products_cstm.id_c) INNER JOIN products_warehouses ON products.id = products_warehouses.product_id
		GROUP BY products.name, products.id, products_warehouses.product_id, products.description, products_warehouses.warehouse_id, products_warehouses.in_stock, products_cstm.Mindestbestand";
*/		
	$strSQL = "SELECT products.name AS productname, products.id AS productid, Sum(products.cost) as cost, Sum(products_warehouses.in_stock) AS lagerstand, products_warehouses.product_id, products_cstm.mindestbestand, products_warehouses.date_modified as aenderung
			FROM (products INNER JOIN products_cstm ON products.id = products_cstm.id_c) INNER JOIN products_warehouses ON products.id = products_warehouses.product_id
			WHERE (((products_warehouses.deleted)=0))
			GROUP BY products.name, products.id, products_warehouses.product_id, products_cstm.mindestbestand;";
			
	if (isset($_POST['absenden'])){
		if ($_POST['formLager'] == '') { 
			//$strQuery = "";
/*			$strSQL = "SELECT products.name as productname, products.id as productid, products.cost, sum(products_warehouses.in_stock) as lagerstand, products_warehouses.deleted, products_warehouses.created_by, products_warehouses.product_id,  products_warehouses.date_modified as aenderung, products_cstm.mindestbestand
				FROM (products LEFT JOIN products_cstm ON products.id = products_cstm.id_c) INNER JOIN products_warehouses ON products.id = products_warehouses.product_id
				GROUP BY products.name, products.id, products_warehouses.product_id, products.description, products_warehouses.warehouse_id, products_warehouses.in_stock, products_cstm.Mindestbestand";
				$strSQL = "SELECT products.name AS productname, products.id AS productid, Sum(products_warehouses.in_stock) AS lagerstand, products_warehouses.product_id, products_cstm.mindestbestand
				FROM (products INNER JOIN products_cstm ON products.id = products_cstm.id_c) INNER JOIN products_warehouses ON products.id = products_warehouses.product_id
				WHERE (((products_warehouses.deleted)=0))
				GROUP BY products.name, products.id, products_warehouses.product_id, products_cstm.mindestbestand;";
				*/
			$formLager = '';
			$strSQL = "SELECT products.name AS productname, products.id AS productid, Sum(products.cost) as cost, Sum(products_warehouses.in_stock) AS lagerstand, products_warehouses.product_id, products_cstm.mindestbestand, products_warehouses.date_modified as aenderung 
				FROM (products 
				INNER JOIN products_cstm ON products.id = products_cstm.id_c) 
				INNER JOIN products_warehouses ON products.id = products_warehouses.product_id 
				WHERE (((products_warehouses.deleted)=0)) 
				GROUP BY products.name, products.id, products_warehouses.product_id, products_cstm.mindestbestand;";
		} else {
			$formLager = $_POST['formLager'];
					
			$strSQL = "SELECT products.name AS productname, products.id as productid, products.cost as cost, 
				products_warehouses.product_id, products_warehouses.warehouse_id, products_warehouses.in_stock as lagerstand, products_cstm.mindestbestand, products_warehouses.date_modified as aenderung
				FROM (products INNER JOIN products_warehouses ON products.id = products_warehouses.product_id) LEFT JOIN products_cstm ON products.id = products_cstm.id_c
				GROUP BY products.name, products.id, products_warehouses.product_id, products.description, products_warehouses.warehouse_id, products_warehouses.in_stock, products_cstm.Mindestbestand
				HAVING (((products_warehouses.warehouse_id)='".$_POST['formLager']."') AND (Not (products_warehouses.in_stock)=0));";
		}
	} else {
		$formLager = '387dac2f-b390-9e3e-d5ee-566827893cfa';
	}
	/*
	$strSQL = "SELECT products.name as productname, products.id as productid, products_warehouses.in_stock as lagerstand, company_addresses.name as lager, products_warehouses.deleted, products_warehouses.created_by, products_warehouses.product_id, products_warehouses.warehouse_id, products_warehouses.date_modified as aenderung, products_cstm.mindestbestand
	FROM products_cstm RIGHT JOIN (company_addresses INNER JOIN (products_warehouses INNER JOIN products ON products_warehouses.product_id = products.id) ON company_addresses.id = products_warehouses.warehouse_id) ON products_cstm.id_c = products.id
	WHERE (((products_warehouses.deleted)=0) ".$strQuery." AND ((products_warehouses.in_stock != 0) OR (products_cstm.mindestbestand != 0)))
	ORDER BY products.name;";
	*/


    //print $strSQL;
	
	print "<div class='card shadow-sm mx-auto' style='max-width: 1200px;'>\n";
	print "<div class='card-body'>\n";
	print "<div class='table-responsive'>\n";
	print "\t<table id='lagerstand-table' class='table table-sm table-striped table-hover align-middle'>\n";
	print "\t<thead class='table-light'><tr><th>Produkt</th><th>Lagerstand</th><th>EK €/Stk</th><th>EK €</th><th>Mindestbestand</th><th>Änderungsdatum</th></tr></thead>\n";
	print "\t<tfoot class='table-light'><tr><th>Produkt</th><th>Lagerstand</th><th>EK €/Stk</th><th>EK €</th><th>Mindestbestand</th><th>Änderungsdatum</th></tr></tfoot>\n";
	print "\t<tbody>\n";
	$mailtext = "\r\n";
	$icount = 0;
	
	$gesamtwert = 0;
	if ($result = mysqli_query($link, $strSQL)) 
	{
		while ($row = mysqli_fetch_assoc($result)) {		
			print "\t<tr><td><a href='https://addinol-lubeoil.at/crm/index.php?module=ProductCatalog&action=DetailView&record=".$row['productid']."' target='_blank'>".$row['productname']."</a></td><td>".$row['lagerstand']."</td>";
			print "<td align=right>".number_format($row['cost'], 2, ',', '.')."</td>";
			$wert = $row['cost'] * $row['lagerstand'];
			print "<td align=right>".number_format($wert, 2, ',', '.')."</td>";
			$gesamtwert += $wert;
			
			if ($row['mindestbestand'] > $row['lagerstand']) {
				$negcls = " class='neg'";
				$icount = $icount + 1;
				$info = $row['mindestbestand']." *NACHBESTELLEN!";
				$mailtext .= "Produkt: ".$row['productname']."\r\nLagerstand: ".$row['lagerstand']."\r\nMindestbestand: ".$row['mindestbestand']."\r\n\r\n";
			} else {
				$negcls = "";
				$info = $row['mindestbestand'];
			}
			
			print "<td".$negcls.">".$info."</td><td>".$row['aenderung']."</td></tr>\n";
		}
	}
	print "\t</tbody>\n";
	print "\t</table>\n";
	print "</div>\n";
	print "<div class='d-flex flex-wrap gap-2 align-items-center mt-3'>\n";
	print "<div class='alert alert-info py-2 px-3 mb-0'>Lagerwert: € ".number_format($gesamtwert, 2, ',', '.')."</div>\n";
	print "<a class='btn btn-outline-success btn-sm' href='lagerstand_export.php?id=".$formLager."'>Export Lagerstand</a>\n";
	print "</div>\n";
	print "</div>\n";
	print "</div>\n";

	
?>
<?php

	if ($icount > 0) {
//h.egger@addinol-lubeoil.at
//rh@bkh-kufstein.at
		if (isset($_GET['mailsend'])){
			mail("h.egger@addinol-lubeoil.at",
				"Es ist gegebenenfalls eine Lagerbestellung nötig",
				"Geschätzte Geschäftsführung,\r\n\r\nvielen Dank, jeatz muass i mi a schon vom Programmierer zsammscheissen lassen...\r\nbin also wieder mal ins Lager nachschaun, habe ja sonst nix zu tun! Mein Bestellvorschlag:\r\n ".$mailtext."\r\n\r\nHochachtungsvoll und untertänigst\r\nEuer Lagerheini\r\n\r\nPS: Betriebsrat für Bodenpersonal in Gründung...",
				"From: lagerheini@addinol-lubeoil.at");			
		}
		if (isset($_GET['romansend'])){
			mail("rh@bkh-kufstein.at",
				"Es ist gegebenenfalls eine Lagerbestellung nötig",
				"Geschätzte Geschäftsführung,\r\n\r\nvielen Dank, jeatz muass i mi a schon vom Programmierer zsammscheissen lassen...\r\nbin also wieder mal ins Lager nachschaun, habe ja sonst nix zu tun! Mein Bestellvorschlag:\r\n ".$mailtext."\r\n\r\nHochachtungsvoll und untertänigst\r\nEuer Lagerheini\r\n\r\nPS: Betriebsrat für Bodenpersonal in Gründung...",
				"From: lagerheini@addinol-lubeoil.at");			
		}
	}
	
		
?>
	</main>
	<script src="assets/datatables/jquery.min.js"></script>
	<script src="assets/datatables/jquery.dataTables.min.js"></script>
	<script src="assets/datatables/dataTables.bootstrap5.min.js"></script>
	<script src="assets/bootstrap/bootstrap.bundle.min.js"></script>
	<script>
		(function () {
			$('#lagerstand-table tfoot th').each(function () {
				var title = $(this).text();
				$(this).html('<input type=\"text\" class=\"form-control form-control-sm\" placeholder=\"Filter ' + title + '\" />');
			});

			var table = new DataTable('#lagerstand-table', {
				pageLength: 25,
				lengthMenu: [10, 25, 50, 100],
				order: [[0, 'asc']],
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
