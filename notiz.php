<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
	<link href="styles.css" rel="stylesheet" type="text/css" />
</head>
<body>
 
<?php
	$link = mysqli_connect('localhost', 'addinol_usr', 'lwT1e99~', 'addinol_crm');
	

if(isset($_GET['cid'])) {
	
	$cid = $_GET['cid'];
	$strSQLNotes = "SELECT description 
		FROM notes 
		WHERE name LIKE 'Hist%' AND account_id = '" . $cid . "'";
	
	$iCount = 0;
	$strNote = "";
	if ($resultNotes = mysqli_query($link, $strSQLNotes)) {
		while ($rowNote = mysqli_fetch_assoc($resultNotes)) {
			
			$strNote = $strNote.$rowNote['description'];
			print nl2br(utf8_encode($rowNote['description']));
		}		
	}	
}

?>


 
</body>
</html>