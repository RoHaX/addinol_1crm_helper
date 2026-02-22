<?php
	$current = basename($_SERVER['PHP_SELF']);
	$basePath = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
	$rootPath = preg_replace('~/middleware$~', '', $basePath);
	$menuGroups = [
		[
			'key' => 'dashboard',
			'label' => 'Dashboard',
			'icon' => 'fas fa-chart-pie',
			'default' => 'bilanz.php',
			'items' => [
				['bilanz.php', 'fas fa-chart-line', 'Bilanz'],
				['umsatzliste.php', 'fas fa-money-check-alt', 'Umsatzliste'],
			],
		],
		[
			'key' => 'verkauf',
			'label' => 'Verkauf',
			'icon' => 'fas fa-shopping-cart',
			'default' => 'middleware/firmen.php',
			'items' => [
				['artikelliste.php', 'fas fa-oil-can', 'Artikelliste'],
				['artikel_kunde.php', 'fas fa-users', 'Artikel -> Kunde'],
				['kunde_artikel.php', 'fas fa-user-tag', 'Kunde -> Artikel'],
				['addinol_map.php', 'fas fa-map-marked-alt', 'Addinol-Map'],
				['middleware/firmen.php', 'fas fa-address-book', 'Firmen'],
			],
		],
		[
			'key' => 'lager',
			'label' => 'Lager & Einkauf',
			'icon' => 'fas fa-warehouse',
			'default' => 'middleware/lieferstatus.php',
			'items' => [
				['lagerstand.php', 'fas fa-boxes', 'Lagerstand'],
				['middleware/lieferstatus.php', 'fas fa-truck', 'Lieferstatus'],
			],
		],
		[
			'key' => 'finanzen',
			'label' => 'Finanzen',
			'icon' => 'fas fa-file-invoice-dollar',
			'default' => 'offene_rechnungen.php',
			'items' => [
				['offene_rechnungen.php', 'fas fa-money-bill', 'Offene Rechnungen'],
				['offene_betraege_kunde.php', 'fas fa-hand-holding-usd', 'Offene Beträge Kunde'],
				['update_invoice.php', 'far fa-edit', 'Zahlungen korrigieren'],
			],
		],
		[
			'key' => 'automation',
			'label' => 'Automationen',
			'icon' => 'fas fa-cogs',
			'default' => 'middleware/jobs.php',
			'items' => [
				['middleware/jobs.php', 'fas fa-tasks', 'Jobs'],
				['middleware/mailboard.php', 'fas fa-inbox', 'Mailboard'],
			],
		],
	];

	function nav_full_href(string $rootPath, string $href): string {
		return rtrim($rootPath, '/') . '/' . ltrim($href, '/');
	}
?>
<nav class="navbar navbar-expand-lg navbar-dark shadow-sm sticky-top app-navbar">
	<div class="container-fluid">
		<a class="navbar-brand fw-semibold" href="<?php echo htmlspecialchars(nav_full_href($rootPath, 'index.php'), ENT_QUOTES); ?>">
			<img src="https://addinol-lubeoil.at/files/addinol-logo.svg" alt="Addinol" class="app-logo me-2">
			<span>1CRM Helfer</span>
		</a>

		<button class="navbar-toggler d-lg-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#navMobile" aria-controls="navMobile" aria-label="Navigation öffnen">
			<span class="navbar-toggler-icon"></span>
		</button>

		<ul class="navbar-nav ms-auto d-none d-lg-flex align-items-lg-center gap-lg-2">
			<?php foreach ($menuGroups as $group): ?>
				<?php
					$groupActive = false;
					foreach ($group['items'] as $item) {
						if ($current === basename($item[0])) {
							$groupActive = true;
							break;
						}
					}
					$dropdownId = 'navDrop_' . preg_replace('/[^a-z0-9_]/i', '', (string)$group['key']);
					$groupHref = nav_full_href($rootPath, (string)($group['default'] ?? $group['items'][0][0]));
				?>
				<li class="nav-item dropdown nav-hover-group">
					<a class="nav-link dropdown-toggle px-2 <?php echo $groupActive ? 'active fw-semibold' : ''; ?>" href="<?php echo htmlspecialchars($groupHref, ENT_QUOTES); ?>" id="<?php echo htmlspecialchars($dropdownId, ENT_QUOTES); ?>" role="button" aria-expanded="false">
						<i class="<?php echo htmlspecialchars((string)$group['icon'], ENT_QUOTES); ?> me-1"></i><?php echo htmlspecialchars((string)$group['label']); ?>
					</a>
					<ul class="dropdown-menu dropdown-menu-end">
						<?php foreach ($group['items'] as $item): ?>
							<?php
								$itemHref = nav_full_href($rootPath, (string)$item[0]);
								$itemActive = $current === basename((string)$item[0]);
							?>
							<li>
								<a class="dropdown-item <?php echo $itemActive ? 'active' : ''; ?>" href="<?php echo htmlspecialchars($itemHref, ENT_QUOTES); ?>">
									<i class="<?php echo htmlspecialchars((string)$item[1], ENT_QUOTES); ?> me-2"></i><?php echo htmlspecialchars((string)$item[2]); ?>
								</a>
							</li>
						<?php endforeach; ?>
					</ul>
				</li>
			<?php endforeach; ?>
		</ul>
	</div>
</nav>

<div class="offcanvas offcanvas-start d-lg-none" tabindex="-1" id="navMobile" aria-labelledby="navMobileLabel">
	<div class="offcanvas-header">
		<h5 class="offcanvas-title" id="navMobileLabel"><i class="fas fa-bars me-2"></i>Navigation</h5>
		<button type="button" class="btn-close text-reset" data-bs-dismiss="offcanvas" aria-label="Schließen"></button>
	</div>
	<div class="offcanvas-body">
		<div class="accordion accordion-flush" id="navMobileAccordion">
			<?php foreach ($menuGroups as $group): ?>
				<?php
					$groupActive = false;
					foreach ($group['items'] as $item) {
						if ($current === basename($item[0])) {
							$groupActive = true;
							break;
						}
					}
					$collapseId = 'collapse_' . preg_replace('/[^a-z0-9_]/i', '', (string)$group['key']);
					$headingId = 'heading_' . preg_replace('/[^a-z0-9_]/i', '', (string)$group['key']);
				?>
				<div class="accordion-item">
					<h2 class="accordion-header" id="<?php echo htmlspecialchars($headingId, ENT_QUOTES); ?>">
						<button class="accordion-button <?php echo $groupActive ? '' : 'collapsed'; ?>" type="button" data-bs-toggle="collapse" data-bs-target="#<?php echo htmlspecialchars($collapseId, ENT_QUOTES); ?>" aria-expanded="<?php echo $groupActive ? 'true' : 'false'; ?>" aria-controls="<?php echo htmlspecialchars($collapseId, ENT_QUOTES); ?>">
							<i class="<?php echo htmlspecialchars((string)$group['icon'], ENT_QUOTES); ?> me-2"></i><?php echo htmlspecialchars((string)$group['label']); ?>
						</button>
					</h2>
					<div id="<?php echo htmlspecialchars($collapseId, ENT_QUOTES); ?>" class="accordion-collapse collapse <?php echo $groupActive ? 'show' : ''; ?>" aria-labelledby="<?php echo htmlspecialchars($headingId, ENT_QUOTES); ?>" data-bs-parent="#navMobileAccordion">
						<div class="accordion-body p-0">
							<div class="list-group list-group-flush">
								<?php foreach ($group['items'] as $item): ?>
									<?php
										$itemHref = nav_full_href($rootPath, (string)$item[0]);
										$itemActive = $current === basename((string)$item[0]);
									?>
									<a class="list-group-item list-group-item-action <?php echo $itemActive ? 'active' : ''; ?>" href="<?php echo htmlspecialchars($itemHref, ENT_QUOTES); ?>">
										<i class="<?php echo htmlspecialchars((string)$item[1], ENT_QUOTES); ?> me-2"></i><?php echo htmlspecialchars((string)$item[2]); ?>
									</a>
								<?php endforeach; ?>
							</div>
						</div>
					</div>
				</div>
			<?php endforeach; ?>
		</div>
	</div>
</div>

<style>
@media (min-width: 992px) {
	.nav-hover-group {
		position: relative;
	}
	.nav-hover-group:hover > .dropdown-menu,
	.nav-hover-group:focus-within > .dropdown-menu {
		display: block;
		margin-top: 0;
	}
}
</style>
