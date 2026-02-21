<?php
	$current = basename($_SERVER['PHP_SELF']);
	$basePath = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
	$rootPath = preg_replace('~/middleware$~', '', $basePath);
	$links = [
		['bilanz.php', 'fas fa-chart-line', 'Bilanz'],
		['lagerstand.php', 'fas fa-warehouse', 'Lagerstand'],
		['artikelliste.php', 'fas fa-oil-can', 'Artikelliste'],
		['umsatzliste.php', 'fas fa-money-check-alt', 'Umsatzliste'],
		['addinol_map.php', 'fas fa-map-marked', 'Addinol-Map'],
		['middleware/firmen.php', 'fas fa-address-book', 'Firmen'],
		['update_invoice.php', 'far fa-edit', 'Zahlungen korrigieren'],
		['middleware/mailboard.php', 'fas fa-inbox', 'Mailboard'],
		['offene_rechnungen.php', 'fas fa-money-bill', 'Offene Rechnungen'],
		['offene_betraege_kunde.php', 'fas fa-money-bill', 'Offene Beträge Kunde'],
	];
?>
<nav class="navbar navbar-expand-lg navbar-dark bg-success shadow-sm">
	<div class="container-fluid">
		<a class="navbar-brand" href="index.php">1CRM Helfer</a>
		<div class="d-flex flex-wrap gap-2">
			<?php foreach ($links as $navLink): ?>
				<?php
					$href = $navLink[0];
					$icon = $navLink[1];
					$label = $navLink[2];
					$isActive = $current === basename($href);
					$fullHref = $rootPath . '/' . $href;
				?>
				<a class="btn btn-sm <?php echo $isActive ? 'btn-light' : 'btn-outline-light'; ?>" href="<?php echo $fullHref; ?>">
					<i class="<?php echo $icon; ?>"></i> <?php echo $label; ?>
				</a>
			<?php endforeach; ?>
		</div>
	</div>
</nav>
