<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1" />
</head>
<body>
<iframe name="i_update" src="update.php" width=600 height=100></iframe> 
<?php

	include("db.inc.php");
	db_open();

	$strFilter = "";
	//"AND shipping_address_state <> 'Oberösterreich' ";
	$strSQL = "SELECT accounts.*, accounts_cstm.longitude 
		FROM accounts 
		LEFT JOIN accounts_cstm ON accounts.id = accounts_cstm.id_c 
		WHERE isNull(longitude) LIMIT 900";
		
		
		
	$result = db_query($strSQL);	
	print "<table>";
	while ($row = mysql_fetch_array($result, MYSQL_ASSOC)) {
		$id = $row['id'];
		$strName = $row['name']. " " . $row['longitude'];
		/* $strAddress = $row['shipping_address_street'] . ", " . $row['shipping_address_postalcode'] . " " . $row['shipping_address_city'] . " " . $row['shipping_address_state']; */
		$strAddress = $row['shipping_address_street'] . ", " . $row['shipping_address_postalcode'] . " " . $row['shipping_address_city'];
		$prepAddr = str_replace(' ','+',$strAddress);
		$iCount++;
		print "<tr><td><a href='xml2.php?aid=$id&address=$strAddress'>$strName</a></td>
		<td><a href='https://addinol-lubeoil.at/crm/index.php?module=Accounts&action=DetailView&record=".$row['id']."' target='_blank'>$iCount; CRM</a></td>
		<td><a href='https://www.google.at/maps/place/$prepAddr' target='_blank'>Google Maps</a></td>
		<td><form action='update.php' method='post' target='i_update' accept-charset='ISO-8859-1'>
			<input type='hidden' id='address' name='address' value='$prepAddr'>  
			<input type='hidden' id='aid' name='aid' value='$id'> 
			<button type='submit' name='action' value='0'>aktualisieren</button>
		</form></td>
		</tr>";
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

	$strSQL = "SELECT * FROM accounts_cstm WHERE id_c = '" . $id . "';";
	$result = db_query($strSQL);	
	
	/*while ($row = mysql_fetch_array($result, MYSQL_ASSOC)) {*/
	if ( mysql_affected_rows() == 0 ) {
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
	/*
	print $strSQL."<br>";
	
	$result = db_query($strSQL);
	*/
	print $result;
	}

?>
 
</body>
</html>
