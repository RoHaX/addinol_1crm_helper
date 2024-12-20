<!DOCTYPE html>
<html lang="en">
<head>
  
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
 
    <title>Google Maps Geocoding</title>
     
    <style>
    body{
        font-family:arial;
        font-size:.8em;
    }
     
    input[type=text]{
        padding:0.5em;
        width:20em;
    }
     
    input[type=submit]{
        padding:0.4em;
    }
     
    #gmap_canvas{
        width:100%;
        height:30em;
    }
     
    #address-examples{
        margin:1em 0;
    }
    </style>
 
</head>
<body>
 
<?php
$link = mysqli_connect('localhost', 'addinol_usr', 'lwT1e99~', 'addinol_crm');
mysqli_set_charset($link, "utf8");

if(isset($_GET['address'])) {
	$strAddress = $_GET['address'];
	$cid = $_GET['cid'];
}
if($_POST){
 
    // get latitude, longitude and formatted address
    $data_arr = geocode($_POST['address']);
 
    // if able to geocode the address
    if($data_arr){
         
        $latitude = $data_arr[0];
        $longitude = $data_arr[1];
        $formatted_address = $data_arr[2];
                     
?>
	
	
 
    <!-- google map will be shown here -->
    <div id="gmap_canvas">Loading map...</div>
    
    <!-- JavaScript to show google map -->
    <script type="text/javascript" src="http://maps.google.com/maps/api/js"></script>    
    <script type="text/javascript">
        function init_map() {
            var myOptions = {
                zoom: 14,
                center: new google.maps.LatLng(<?php echo $latitude; ?>, <?php echo $longitude; ?>),
                mapTypeId: google.maps.MapTypeId.ROADMAP
            };
            map = new google.maps.Map(document.getElementById("gmap_canvas"), myOptions);
            marker = new google.maps.Marker({
                map: map,
                position: new google.maps.LatLng(<?php echo $latitude; ?>, <?php echo $longitude; ?>)
            });
            infowindow = new google.maps.InfoWindow({
                content: "<?php echo $formatted_address; ?>"
            });
            google.maps.event.addListener(marker, "click", function () {
                infowindow.open(map, marker);
            });
            infowindow.open(map, marker);
        }
        google.maps.event.addDomListener(window, 'load', init_map);
    </script>
 
    <?php
 
    // if unable to geocode the address
    }else{
        echo "No map found.";
    }
}
?>
 
<div id='address-examples'>
    <div>Address Beispiel:</div>
    <div>Oberfeld 67, 6351 Scheffau</div>
</div>
 
<!-- enter any address -->
<form action="" method="post">
    <input type='text' name='address' value='<?php print utf8_encode($strAddress); ?>' />
    <input type='submit' value='Geocode!' />
</form>
<?php

print "<form action='updcoord.php' method='post'>
			<input type='text' id='cid' name='cid' value='$cid'> 
			<input type='text' id='long' name='long' value='$longitude'> 
			<input type='text' id='lat' name='lat' value='$latitude'> 
			<button type='submit' name='action' value='eintragen'>eintragen</button>
		</form> ";
print "<div>lat: ".$latitude." long: ".$longitude."</div>";
 
// function to geocode address, it will return false if unable to geocode address
function geocode($address){
 
    // url encode the address
    $address = urlencode($address);
     
    // google map geocode api url
    $url = "http://maps.google.com/maps/api/geocode/json?address={$address}";
 
    // get the json response
    $resp_json = file_get_contents($url);
     
    // decode the json
    $resp = json_decode($resp_json, true);
 
    // response status will be 'OK', if able to geocode given address 
    if($resp['status']=='OK'){
 
        // get the important data
        $lati = $resp['results'][0]['geometry']['location']['lat'];
        $longi = $resp['results'][0]['geometry']['location']['lng'];
        $formatted_address = $resp['results'][0]['formatted_address'];
         
        // verify if data is complete
        if($lati && $longi && $formatted_address){
         
			print "<div>lat: ".$lati." long: ".$longi."</div>";
            // put the data in the array
            $data_arr = array();            
             
            array_push(
                $data_arr, 
                    $lati, 
                    $longi, 
                    $formatted_address
                );
             
            return $data_arr;
             
        }else{
            return false;
        }
         
    }else{
        return false;
    }
}

if(isset($_POST['long']))
{
	$long = $_POST['long'];
	$lat = $_POST['lat'];
	$cid = $_POST['cid'];
	
	print "<div>Eintragen lat: ".$lat." long: ".$long."</div>";
	 

	$strSQL = "SELECT * FROM accounts_cstm WHERE id_c = '" . $cid . "';";
	$result = mysqli_query($link, $strSQL);	
	
	/*while ($row = mysql_fetch_array($result, MYSQL_ASSOC)) {*/
	if ( mysqli_affected_rows($link) == 0 ) {
		$strSQL = "INSERT INTO accounts_cstm (id_c, latitude, longitude) VALUES ('" . $cid . "', '" . $lat . "', '" . $long . "');";
		print $strSQL."<br>";
		//$result = db_query($strSQL);
		$result = mysqli_query($link, $strSQL);
		print $result;
	} else {
		$strSQL = "UPDATE accounts_cstm SET latitude = '" . $lat . "', longitude = '" . $long . "' WHERE id_c = '" . $cid . "';";
		print $strSQL."<br>";
		$result = mysqli_query($link, $strSQL);
		print $result;
	}

}	 

?>


 
</body>
</html>