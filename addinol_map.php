<!DOCTYPE html>
<html> 
<head> 
  <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
  <title>Google Maps Multiple Markers</title> 
  <link rel="stylesheet" href="assets/leaflet/leaflet.css" />
  <script src="assets/leaflet/leaflet.js"></script>
	<link href="styles.css" rel="stylesheet" type="text/css" />
	<link href="assets/bootstrap/bootstrap.min.css" rel="stylesheet" />
	<link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.5.0/css/all.css" integrity="sha384-B4dIYHKNBt8Bc12p+WXckhzcICo0wtJAoU8YZTY5qE0Id1GSseTk6S+L3BlXeVIU" crossorigin="anonymous">
</head> 
<body class="bg-light">
<?php require_once __DIR__ . '/navbar.php'; ?>
<main class="container-fluid py-3">
<?php
	$post = $_POST ?? [];
	$hasPost = isset($post['absenden']) || isset($post['hasFilter']);
	$defaultState = 'Tirol';
	$checked = function ($key) use ($post) {
		return isset($post[$key]) && $post[$key] === 'on';
	};
	$checkedAny = function (array $keys) use ($post) {
		foreach ($keys as $key) {
			if (isset($post[$key]) && $post[$key] === 'on') {
				return true;
			}
		}
		return false;
	};
?>		
  <div class="card shadow-sm mb-3">
		<div class="card-body">
		<form action='addinol_map.php' method='post' class="d-flex flex-wrap gap-2 align-items-center" id="map-filter-form">
			<input type="hidden" name="hasFilter" value="1">
			<span class="fw-semibold">Bundesland:</span>
			
				
<?php		
		$allStates = $checked('chkAlle');
		print "<label class='form-check form-check-inline m-0'><input class='form-check-input filter-refresh' type='checkbox' name='chkAlle' ".($allStates ? "checked" : "")."><span class='form-check-label fw-semibold'>Alle</span></label>";
		
		$states = [];
		$tirolChecked = !$hasPost ? true : $checked('chkTirol');
		if ($tirolChecked) { $states[] = $defaultState; }
		print "<label class='form-check form-check-inline m-0'><input class='form-check-input filter-refresh' type='checkbox' name='chkTirol' ".($tirolChecked ? "checked" : "")."><span class='form-check-label'>Tirol</span></label>";
		
		if ($checked('chkVorarlberg')) { $states[] = "Vorarlberg"; }
		print "<label class='form-check form-check-inline m-0'><input class='form-check-input filter-refresh' type='checkbox' name='chkVorarlberg' ".($checked('chkVorarlberg') ? "checked" : "")."><span class='form-check-label'>Vorarlberg</span></label>";
		
		if ($checked('chkSalzburg')) { $states[] = "Salzburg"; }
		print "<label class='form-check form-check-inline m-0'><input class='form-check-input filter-refresh' type='checkbox' name='chkSalzburg' ".($checked('chkSalzburg') ? "checked" : "")."><span class='form-check-label'>Salzburg</span></label>";
		
		if ($checked('chkOberösterreich')) { $states[] = "Oberösterreich"; }
		print "<label class='form-check form-check-inline m-0'><input class='form-check-input filter-refresh' type='checkbox' name='chkOberösterreich' ".($checked('chkOberösterreich') ? "checked" : "")."><span class='form-check-label'>Oberösterreich</span></label>";
		
		if ($checkedAny(['chkKärnten','chkKaernten'])) { $states[] = "Kärnten"; }
		print "<label class='form-check form-check-inline m-0'><input class='form-check-input filter-refresh' type='checkbox' name='chkKaernten' ".($checkedAny(['chkKärnten','chkKaernten']) ? "checked" : "")."><span class='form-check-label'>Kärnten</span></label>";
		
		if ($checked('chkNiederösterreich')) { $states[] = "Niederösterreich"; }
		print "<label class='form-check form-check-inline m-0'><input class='form-check-input filter-refresh' type='checkbox' name='chkNiederösterreich' ".($checked('chkNiederösterreich') ? "checked" : "")."><span class='form-check-label'>Niederösterreich</span></label>";
		
		if ($checked('chkSteiermark')) { $states[] = "Steiermark"; }
		print "<label class='form-check form-check-inline m-0'><input class='form-check-input filter-refresh' type='checkbox' name='chkSteiermark' ".($checked('chkSteiermark') ? "checked" : "")."><span class='form-check-label'>Steierer</span></label>";
				
		if ($checked('chkBurgenland')) { $states[] = "Burgenland"; }
		print "<label class='form-check form-check-inline m-0'><input class='form-check-input filter-refresh' type='checkbox' name='chkBurgenland' ".($checked('chkBurgenland') ? "checked" : "")."><span class='form-check-label'>Burgenland</span></label>";
		
		if ($checked('chkWien')) { $states[] = "Wien"; }
		print "<label class='form-check form-check-inline m-0'><input class='form-check-input filter-refresh' type='checkbox' name='chkWien' ".($checked('chkWien') ? "checked" : "")."><span class='form-check-label'>Wien</span></label>";
		
		if ($allStates) {
			$strFilter = "";
		} else {
			$stateParts = [];
			foreach ($states as $state) {
				if ($state === 'Kärnten') {
					$stateParts[] = "(shipping_address_state = 'Kärnten' OR shipping_address_state = 'Kaernten')";
					continue;
				}
				if ($state === 'Steiermark') {
					$stateParts[] = "(shipping_address_state = 'Steiermark' OR shipping_address_state = 'Steierer')";
					continue;
				}
				$stateParts[] = "shipping_address_state = '".$state."'";
			}
			$strFilter = $stateParts ? "AND (" . implode(" OR ", $stateParts) . ") " : "AND (1=0) ";
		}
		
		print "<span class='fw-semibold ms-3'>Status:</span> ";
		$statuses = [];
		
		if ($checked('chkCustomer')) { $statuses[] = "account_type = 'Customer'"; }
		print "<label class='form-check form-check-inline m-0'><input class='form-check-input filter-refresh' type='checkbox' name='chkCustomer' ".($checked('chkCustomer') ? "checked" : "")."><span class='form-check-label'>Kunde</span></label>";
				
		if ($checked('chkProspect')) { $statuses[] = "account_type = 'Prospect'"; }
		print "<label class='form-check form-check-inline m-0'><input class='form-check-input filter-refresh' type='checkbox' name='chkProspect' ".($checked('chkProspect') ? "checked" : "")."><span class='form-check-label'>Zielkunde</span></label>";
		
		if ($checked('chkAnalyst')) { $statuses[] = "account_type = 'Analyst'"; }
		print "<label class='form-check form-check-inline m-0'><input class='form-check-input filter-refresh' type='checkbox' name='chkAnalyst' ".($checked('chkAnalyst') ? "checked" : "")."><span class='form-check-label'>Stammkunde</span></label>";
/*		
		$strTmp = "";
		if ($_POST['chkSupplier']=='on') {
			$strTmp = "checked";
			if ($strFilterStatus <> "AND (") { 
				$strFilterStatus .= " OR";
			};

			$strFilterStatus .= " account_type = 'Supplier' ";
		}			
		print "<label><input type='checkbox' name='chkSupplier' $strTmp>Lieferant</label>";
	
		$strTmp = "";
		if ($_POST['chkCompetitor']=='on') {
			$strTmp = "checked";
			if ($strFilterStatus <> "AND (") { 
				$strFilterStatus .= " OR";
			};

			$strFilterStatus .= " account_type = 'Competitor' ";
		}			
		print "<label><input type='checkbox' name='chkCompetitor' $strTmp>Mitbewerber</label>";
	
	*/
		if ($checked('chkOther')) {
			$statuses[] = "account_type = 'Supplier' OR account_type = 'Competitor' OR account_type = 'Other' OR account_type = ''";
		}			
		print "<label class='form-check form-check-inline m-0'><input class='form-check-input filter-refresh' type='checkbox' name='chkOther' ".($checked('chkOther') ? "checked" : "")."><span class='form-check-label'>Andere</span></label>";

		$strFilterStatus = $statuses ? "AND (" . implode(" OR ", $statuses) . ") " : "";
	
				
		$plz = isset($post['txtPLZ']) ? $post['txtPLZ'] : '';
		if ($plz === '') {
			print "<span class='ms-3'><label class='me-1'>PLZ:</label><input class='form-control form-control-sm d-inline-block' style='width:90px' type='text' name='txtPLZ'></span>";
			$strFilterPLZ = "";
		} else {			
			print "<span class='ms-3'><label class='me-1'>PLZ:</label><input class='form-control form-control-sm d-inline-block' style='width:90px' type='text' name='txtPLZ' value='".$plz."'></span>";
			$strFilterPLZ = " AND shipping_address_postalcode LIKE '".$plz."%' ";
		}
		
		
		
?>			
			<button class='btn btn-primary btn-sm ms-2' type='submit' name='absenden' value='anzeigen'>anzeigen</button>
		</form>
		</div>
  </div>
  <div id="map" style="border: 2px solid #3872ac;min-height: 700px"></div>

<script type="text/javascript">
var locations = [
<?php
	$link = mysqli_connect('localhost', 'addinol_usr', 'lwT1e99~', 'addinol_crm');
	mysqli_set_charset($link, "utf8");
	
	/*$strSQL = "SELECT accounts.*, accounts_cstm.longitude, accounts_cstm.latitude 
		FROM accounts 
		INNER JOIN accounts_cstm ON accounts.id = accounts_cstm.id_c 
		WHERE accounts_cstm.longitude <> '' " . $strFilter . " " . $strFilterStatus . $strFilterPLZ . " LIMIT 2000";
		*/
		$strSQL = "SELECT accounts.*, accounts_cstm.longitude, accounts_cstm.latitude 
		FROM accounts
		INNER JOIN accounts_cstm ON accounts.id = accounts_cstm.id_c 
		WHERE accounts_cstm.longitude <> '' " . $strFilter . " " . $strFilterStatus . $strFilterPLZ . " LIMIT 2000;";
	$iCount = 0;
	
	if ($result = mysqli_query($link, $strSQL)) 
	{
		while ($row = mysqli_fetch_assoc($result)) {
			$aid = $row['id'];
			$strName = str_replace("'","´",$row['name']); 
			$strAddress = $row['shipping_address_postalcode'] . " " . $row['shipping_address_city'] . " " . $row['shipping_address_street'] . " " . $row['shipping_address_state'];
			//$strGoogle = "https://www.google.at/maps/place/".$row['shipping_address_postalcode']."+".$row['shipping_address_city'];
			//$strGoogle = "https://www.google.at/maps/place/".str_replace("\n", "", $strAddress);
			$strGoogle = str_replace("\n", "", $strAddress);
			$strOrt = $row['shipping_address_city'];
			$strLng = $row['longitude'];
			$strLat = $row['latitude'];
			$strTyp = $row['account_type'].".png";		
			$iCount++;
			print "['$strName', '$strLat', '$strLng', '$strTyp', '$strGoogle', 'https://addinol-lubeoil.at/crm/index.php?module=Accounts&action=DetailView&record=$aid', '$aid'], \n";
		}
	};
?>
];
	
	var map = L.map('map').setView([47.26, 11.44], 7);
	L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
		maxZoom: 19,
		attribution: '&copy; OpenStreetMap contributors'
	}).addTo(map);
	var bounds = L.latLngBounds([]);
	var marker, i;
	var iconCache = {};

	for (i = 0; i < locations.length; i++) {
		var title = locations[i][0];
		var address = locations[i][4];
		var urlcrm = locations[i][5];
		var cid = locations[i][6];
		var lat = parseFloat(locations[i][1]);
		var lng = parseFloat(locations[i][2]);
		if (isNaN(lat) || isNaN(lng)) {
			continue;
		}
		var iconPath = locations[i][3];
		if (!iconCache[iconPath]) {
			iconCache[iconPath] = L.icon({
				iconUrl: iconPath,
				iconSize: [24, 24],
				iconAnchor: [12, 24],
				popupAnchor: [0, -22]
			});
		}
		marker = L.marker([lat, lng], { icon: iconCache[iconPath] }).addTo(map);
		var html1 = "<div style='max-width: 860px;'><h3>" + title + "</h3><p><iframe width='820' height='240' name='notiz' style='border:1px solid lightgrey;' src='notiz.php?cid=" + cid + "'></iframe></a></p><p><a href='https://www.openstreetmap.org/search?query=" + encodeURIComponent(address) + "' target='_blank'>OpenStreetMap</a>&nbsp;&nbsp;<a href='updcoord.php?address=" + address + "&cid=" + cid + "' target='_blank'>update</a><br></div><a href='" + urlcrm + "' target='_blank'>1CRM</a></p></div>";
		marker.bindPopup(html1, { maxWidth: 900, minWidth: 700 });
		bounds.extend([lat, lng]);
	}

	if (bounds.isValid()) {
		map.fitBounds(bounds, { padding: [20, 20] });
	}
	

  </script>
  <div class="mt-3">
<?php

	print "Zähler: ".$iCount;
	//$strFilter = "AND shipping_address_state <> 'Oberösterreich' ";
	$strSQL = "SELECT accounts.*, accounts_cstm.longitude 
		FROM accounts 
		LEFT JOIN accounts_cstm ON accounts.id = accounts_cstm.id_c 
		WHERE longitude = '' LIMIT 1000";

	print "<table class='table table-sm table-striped'>";
	if ($result = mysqli_query($link, $strSQL)) 
	{
		while ($row = mysqli_fetch_assoc($result)) {		
			$id = $row['id'];
			$strName = $row['name']. " " . $row['longitude'];
			/* $strAddress = $row['shipping_address_street'] . ",	 " . $row['shipping_address_postalcode'] . " " . $row['shipping_address_city'] . " " . $row['shipping_address_state']; */
			$strAddress = $row['shipping_address_street'] . ",	 " . $row['shipping_address_postalcode'] . " " . $row['shipping_address_city'];
			$prepAddr = str_replace(' ','+',$strAddress);
			print "<tr><td><a href='updcoord.php?cid=$id&address=$strAddress' target='_blank'>$strName</a></td><td><a href='https://addinol-lubeoil.at/crm/index.php?module=Accounts&action=DetailView&record=".$row['id']."' target='_blank'>CRM</a></td></td></tr>";
		}
	};
	
	print "</table>";

if(isset($_GET['address']))
{
  $address =$_GET['address']; // Google HQ
  $prepAddr = str_replace(' ','+',$address);
  $id = $_GET['aid'];
  
  print $prepAddr;
	$url = "http://maps.google.com/maps/api/geocode/json?address=$prepAddr";
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($ch, CURLOPT_PROXYPORT, 3128);
	curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
	$response = curl_exec($ch);
	curl_close($ch);
	$response_a = json_decode($response);
	echo $lat = $response_a->results[0]->geometry->location->lat;
	echo "<br />";
	echo $long = $response_a->results[0]->geometry->location->lng;

/*	$strSQL = "SELECT * FROM accounts_cstm WHERE id_c = '" . $id . "';";
	$result = db_query($strSQL);	
*/
	/*while ($row = mysql_fetch_array($result, MYSQL_ASSOC)) {*/
/*	if ( mysql_affected_rows() == 0 ) {
		print "anfügen<br>";
		$strSQL = "INSERT INTO accounts_cstm (id_c, latitude, longitude) VALUES ('" . $id . "', '" . $lat . "', '" . $long . "');";
		print $strSQL."<br>";
		$result = db_query($strSQL);
		print $result;
	} else {
		print "update<br>";
		$strSQL = "UPDATE accounts_cstm SET latitude = '" . $lat . "', longitude = '" . $long . "' WHERE id_c = '" . $id . "';";
		print $strSQL."<br>";
		$result = db_query($strSQL);
		print $result;
	}
	*/
	/*
	print $strSQL."<br>";
	
	$result = db_query($strSQL);
	
	*/
	print $result;
	}
?>
  </div>
  <script src="assets/bootstrap/bootstrap.bundle.min.js"></script>
  <script>
  (function () {
    var form = document.getElementById('map-filter-form');
    if (!form) return;
    form.querySelectorAll('.filter-refresh').forEach(function (el) {
      el.addEventListener('change', function () {
        form.submit();
      });
    });
  })();
  </script>
</main>
</body>
</html>
