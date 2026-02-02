<!doctype html>
<html lang="de">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>1CRM Helfer</title>
<link href="styles.css" rel="stylesheet" type="text/css" />
<link href="assets/bootstrap/bootstrap.min.css" rel="stylesheet" />
<link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.5.0/css/all.css" integrity="sha384-B4dIYHKNBt8Bc12p+WXckhzcICo0wtJAoU8YZTY5qE0Id1GSseTk6S+L3BlXeVIU" crossorigin="anonymous">
</head>
<body class="bg-light">
	<?php require_once __DIR__ . '/navbar.php'; ?>

	<main class="container py-4">
		<div class="row g-3">
			<div class="col-12 col-md-6 col-lg-4">
				<a class="btn btn-outline-success w-100 d-flex align-items-center gap-2" href="bilanz.php">
					<i class="fas fa-chart-line"></i>
					<span>Bilanz</span>
				</a>
			</div>
			<div class="col-12 col-md-6 col-lg-4">
				<a class="btn btn-outline-success w-100 d-flex align-items-center gap-2" href="lagerstand.php">
					<i class="fas fa-warehouse"></i>
					<span>Lagerstand</span>
				</a>
			</div>
			<div class="col-12 col-md-6 col-lg-4">
				<a class="btn btn-outline-success w-100 d-flex align-items-center gap-2" href="artikelliste.php">
					<i class="fas fa-oil-can"></i>
					<span>Artikelliste</span>
				</a>
			</div>
			<div class="col-12 col-md-6 col-lg-4">
				<a class="btn btn-outline-success w-100 d-flex align-items-center gap-2" href="umsatzliste.php">
					<i class="fas fa-money-check-alt"></i>
					<span>Umsatzliste</span>
				</a>
			</div>
			<div class="col-12 col-md-6 col-lg-4">
				<a class="btn btn-outline-success w-100 d-flex align-items-center gap-2" href="addinol_map.php">
					<i class="fas fa-map-marked"></i>
					<span>Addinol-Map</span>
				</a>
			</div>
			<div class="col-12 col-md-6 col-lg-4">
				<a class="btn btn-outline-success w-100 d-flex align-items-center gap-2" href="update_invoice.php">
					<i class="far fa-edit"></i>
					<span>Zahlungen korrigieren</span>
				</a>
			</div>
			<div class="col-12 col-md-6 col-lg-4">
				<a class="btn btn-outline-success w-100 d-flex align-items-center gap-2" href="offene_rechnungen.php">
					<i class="fas fa-money-bill"></i>
					<span>Offene Rechnungen</span>
				</a>
			</div>
			<div class="col-12 col-md-6 col-lg-4">
				<a class="btn btn-outline-success w-100 d-flex align-items-center gap-2" href="offene_betraege_kunde.php">
					<i class="fas fa-money-bill"></i>
					<span>Offene Beträge Kunde</span>
				</a>
			</div>
		</div>
	</main>

	<script src="assets/bootstrap/bootstrap.bundle.min.js"></script>
</body>
</html>
