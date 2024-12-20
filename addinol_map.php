<!DOCTYPE html>
<html> 
<head> 
  <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
  <title>Google Maps Multiple Markers</title> 
  <script src="https://maps.google.com/maps/api/js?sensor=false" 
          type="text/javascript"></script>
	<link href="styles.css" rel="stylesheet" type="text/css" />
</head> 
<body>
<?php
	$strBundesland = 'Tirol';
	if (isset($_POST['absenden'])){
		$strBundesland = $_POST['cmbBundesland'];	
	}
	$strFilter = " AND shipping_address_state = '$strBundesland' ";
?>		
  <div>
		<form action='addinol_map.php' method='post'>
			<b>Bundesland:</b>
			
				
<?php		
		if ($_POST['chkAlle']=='on') {
			print "<label><input type='checkbox' name='chkAlle' checked><b>Alle</b></label>";
		} else {
			print "<label><input type='checkbox' name='chkAlle'><b>Alle</b></label>";
		}
		
		$strFilter = "AND (";
		$strTmp = "";
		if ($_POST['chkTirol']=='on') {
			$strTmp = "checked";
			$strFilter .= " shipping_address_state = 'Tirol' ";
		}			
		print "<label><input type='checkbox' name='chkTirol' $strTmp>Tirol</label>";
		
		
		$strTmp = "";
		if ($_POST['chkVorarlberg']=='on') {
			$strTmp = "checked";
			if ($strFilter <> "AND (") { 
				$strFilter .= " OR";
			};
			$strFilter .= " shipping_address_state = 'Vorarlberg' ";
		}			
		print "<label><input type='checkbox' name='chkVorarlberg' $strTmp>Vorarlberg</label>";
		
		$strTmp = "";
		if ($_POST['chkSalzburg']=='on') {
			$strTmp = "checked";
			if ($strFilter <> "AND (") { 
				$strFilter .= " OR";
			};

			$strFilter .= " shipping_address_state = 'Salzburg' ";
		}			
		print "<label><input type='checkbox' name='chkSalzburg' $strTmp>Salzburg</label>";
		
		$strTmp = "";
		if ($_POST['chkOberösterreich']=='on') {
			$strTmp = "checked";
			if ($strFilter <> "AND (") { 
				$strFilter .= " OR";
			};

			$strFilter .= " shipping_address_state = 'Oberösterreich' ";
		}			
		print "<label><input type='checkbox' name='chkOberösterreich' $strTmp>Oberösterreich</label>";
		
		$strTmp = "";
		if ($_POST['chkKärnten']=='on') {
			$strTmp = "checked";
			if ($strFilter <> "AND (") { 
				$strFilter .= " OR";
			};

			$strFilter .= " shipping_address_state = 'Kärnten' ";
		}			
		print "<label><input type='checkbox' name='chkKärnten' $strTmp>Kärnten</label>";
		
		$strTmp = "";
		if ($_POST['chkNiederösterreich']=='on') {
			$strTmp = "checked";
			if ($strFilter <> "AND (") { 
				$strFilter .= " OR";
			};

			$strFilter .= " shipping_address_state = 'Niederösterreich' ";
		}			
		print "<label><input type='checkbox' name='chkNiederösterreich' $strTmp>Niederösterreich</label>";
		
		$strTmp = "";
		if ($_POST['chkSteiermark']=='on') {
			$strTmp = "checked";
			if ($strFilter <> "AND (") { 
				$strFilter .= " OR";
			};

			$strFilter .= " shipping_address_state = 'Steiermark' ";
		}			
		print "<label><input type='checkbox' name='chkSteiermark' $strTmp>Steierer</label>";
				
		$strTmp = "";
		if ($_POST['chkBurgenland']=='on') {
			$strTmp = "checked";
			if ($strFilter <> "AND (") { 
				$strFilter .= " OR";
			};

			$strFilter .= " shipping_address_state = 'Burgenland' ";
		}			
		print "<label><input type='checkbox' name='chkBurgenland' $strTmp>Burgenland</label>";
		
		$strTmp = "";
		if ($_POST['chkWien']=='on') {
			$strTmp = "checked";
			if ($strFilter <> "AND (") { 
				$strFilter .= " OR";
			};

			$strFilter .= " shipping_address_state = 'Wien' ";
		}			
		$strFilter .= ") ";
		print "<label><input type='checkbox' name='chkWien' $strTmp>Wien</label>";
		
		if ($_POST['chkAlle']=='on') {
			$strFilter = "";
		}
		
		print "&nbsp;&nbsp;&nbsp;&nbsp;<b>Status:</b> ";
		$strFilterStatus = "AND (";
		
		$strTmp = "";
		if ($_POST['chkCustomer']=='on') {
			$strTmp = "checked";
			if ($strFilterStatus <> "AND (") { 
				$strFilterStatus .= " OR";
			};

			$strFilterStatus .= " account_type = 'Customer' ";
		}			
		print "<label><input type='checkbox' name='chkCustomer' $strTmp>Kunde</label>";
				
		$strTmp = "";
		if ($_POST['chkProspect']=='on') {
			$strTmp = "checked";
			if ($strFilterStatus <> "AND (") { 
				$strFilterStatus .= " OR";
			};

			$strFilterStatus .= " account_type = 'Prospect' ";
		}			
		print "<label><input type='checkbox' name='chkProspect' $strTmp>Zielkunde</label>";
		
		$strTmp = "";
		if ($_POST['chkAnalyst']=='on') {
			$strTmp = "checked";
			if ($strFilterStatus <> "AND (") { 
				$strFilterStatus .= " OR";
			};

			$strFilterStatus .= " account_type = 'Analyst' ";
		}			
		print "<label><input type='checkbox' name='chkAnalyst' $strTmp>Stammkunde</label>";
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
		$strTmp = "";
		if ($_POST['chkOther']=='on') {
			$strTmp = "checked";
			if ($strFilterStatus <> "AND (") { 
				$strFilterStatus .= " OR";
			};

			$strFilterStatus .= " account_type = 'Supplier' OR account_type = 'Competitor' OR account_type = 'Other' OR account_type = '' ";
		}			
		$strFilterStatus .= ") ";
		print "<label><input type='checkbox' name='chkOther' $strTmp>Andere</label>";
	
				
		if ($_POST['txtPLZ']=='') {
			print "&nbsp;&nbsp;&nbsp;<label>PLZ:</label><input type='text' size='6' name='txtPLZ'>";
			$strFilterPLZ = "";
		} else {			
			print "&nbsp;&nbsp;&nbsp;<label>PLZ:</label><input type='text' size='6' name='txtPLZ' value='".$_POST['txtPLZ']."'>";
			$strFilterPLZ = " AND shipping_address_postalcode LIKE '".$_POST['txtPLZ']."%' ";
		}
		
		
		
?>			
			<input type='submit' name='absenden' value='anzeigen'>
		</form>
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
	
	var bounds = new google.maps.LatLngBounds();
    var map = new google.maps.Map(document.getElementById('map'), {
      zoom: 7,
      center: new google.maps.LatLng(47.26, 11.44),
      mapTypeId: google.maps.MapTypeId.ROADMAP
    });

    var infowindow = new google.maps.InfoWindow();
	
    var marker, i;

    for (i = 0; i < locations.length; i++) {  
		var title = locations[i][0];
		var address = locations[i][4];
		var urlcrm = locations[i][5];
		var cid = locations[i][6];
		
		marker = new google.maps.Marker({
			icon: locations[i][3],
			position: new google.maps.LatLng(locations[i][1], locations[i][2]),
			map: map,
			title: title,
			address: address,
			url: urlcrm,
			animation: google.maps.Animation.DROP
		});
		//infoWindow(marker, map, title, address, url);

		bounds.extend(marker.getPosition());
		map.fitBounds(bounds);
	  
		google.maps.event.addListener(marker, 'click', (function(marker, i) {
			return function() {
				var title = locations[i][0];
				var address = locations[i][4];
				var urlcrm = locations[i][5];
				var cid = locations[i][6];				
				var html1 = "<div><h3>" + title + "</h3><p><iframe width='600' height='200' name='notiz' style='border:1px solid lightgrey;' src='notiz.php?cid=" + cid + "'></iframe></a></p><p><a href='https://www.google.at/maps/place/" + address + "' target='_blank'>GoogleMaps</a>&nbsp;&nbsp;<a href='updcoord.php?address=" + address + "&cid=" + cid + "' target='_blank'>update</a><br></div><a href='" + urlcrm + "' target='_blank'>1CRM</a></p></div>";
				infowindow.setContent(html1);
				infowindow.open(map, marker);
			}
		})(marker, i));
	}
	

  </script>
  <div>
<?php

	print "Zähler: ".$iCount;
	//$strFilter = "AND shipping_address_state <> 'Oberösterreich' ";
	$strSQL = "SELECT accounts.*, accounts_cstm.longitude 
		FROM accounts 
		LEFT JOIN accounts_cstm ON accounts.id = accounts_cstm.id_c 
		WHERE longitude = '' LIMIT 1000";

	print "<table>";
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
</body>
</html>