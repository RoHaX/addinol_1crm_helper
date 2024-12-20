<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1" />
</head>
<body>

<?php
	include("db.inc.php");
	db_open();

if(isset($_POST['address']))
{
  $address =$_POST['address']; // Google HQ
  $prepAddr = str_replace(' ','+',$address);
  $id = $_POST['aid'];
  
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
