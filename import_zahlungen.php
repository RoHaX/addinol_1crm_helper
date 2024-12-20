<?php
include("db.inc.php");
db_open();
?>
<html>
<head>
</head>
<body>
<table>
<form action="<?php echo $_SERVER["PHP_SELF"]; ?>" method="post" enctype="multipart/form-data">

<tr>
<td width="20%">Datei</td>
<td width="80%"><input type="file" name="file" id="file" /></td>
</tr>

<tr>
<td>Bestätigen</td>
<td><input type="submit" name="submit" /></td>
</tr>

</form>
</table>

<?php

if ( isset($_POST["submit"]) ) {

   if ( isset($_FILES["file"])) {

            //if there was an error uploading the file
        if ($_FILES["file"]["error"] > 0) {
            echo "Return Code: " . $_FILES["file"]["error"] . "<br />";

        }
        else {
                 //Print file details
             echo "Upload: " . $_FILES["file"]["name"] . "<br />";
             echo "Type: " . $_FILES["file"]["type"] . "<br />";
             echo "Groesse: " . ($_FILES["file"]["size"] / 1024) . " Kb<br />";
             echo "Temp Datei: " . $_FILES["file"]["tmp_name"] . "<br />";

                 //if file already exists
             if (file_exists("upload/" . $_FILES["file"]["name"])) {
            echo $_FILES["file"]["name"] . " already exists. ";
             }
             else {
                    //Store file in directory "upload" with the name of "uploaded_file.txt"
            $storagename = "uploaded_file.txt";
            move_uploaded_file($_FILES["file"]["tmp_name"], "upload/" . $storagename);
            echo "Gespeichert unter: " . "upload/" . $_FILES["file"]["name"] . "<br />";
            }
        }
     } else {
             echo "Keine Datei ausgewählt <br />";
     }
}


        if(($handle = fopen( "upload/" . $storagename , r )) !== FALSE) {
            // necessary if a large csv file
            set_time_limit(0);

			$pid = 0;
            $row = 0;
			$strSQL = "SELECT MAX(payment_id) FROM payments WHERE prefix='PID-'";
			$result = db_query($strSQL);
			$rowC = mysql_fetch_row($result);
			$pid = $rowC[0];

            while(($data = fgetcsv($handle, 1000, ';')) !== FALSE) {
                // number of fields in the csv
                $col_count = count($data);
				
				// Zeile hochzählen, erste Zeile auslassen
                $row++;

				$termin = $data[0];
				$grund = trim($data[7]);
				$betrag = $data[9];
				$betrag = str_replace('.', '', $betrag);
				$betrag = str_replace(',', '.', $betrag);
				
				$strSQL = "SELECT * FROM bills WHERE invoice_reference='".$grund."'";
				
				$result = db_query($strSQL);
				$rowB = mysql_fetch_assoc($result);
				//echo $rowB['supplier_id'];
				$terminformat = date_create_from_format('d/m/Y', $termin);
				$userid = "7ad50b69-112c-4aa4-8470-56682d8c9ef3";

				if (mysql_num_rows($result)>0) {
					$pid++;
					$strSQL = "INSERT INTO payments (id, date_entered, date_modified, assigned_user_id, created_by, modified_user_id, amount, amount_usdollar, total_amount, total_amount_usdollar, payment_date, prefix, payment_id, customer_reference, payment_type, direction, account_id, related_invoice_id)
					VALUES ('PID-" . $pid . "', NOW(), NOW(), '".$userid."', '".$userid."', '".$userid."', ".$betrag.", ".$betrag.", ".$betrag.", ".$betrag.", '" . date_format($terminformat, 'Y-m-d') . " 08:00:00', 'PID-', ".$pid.", '".$grund."', 'Wire Transfer', 'outgoing', '".$rowB['supplier_id']."', '".$rowB['id']."');";
					//echo $strSQL."---<br>";
					echo "Ausgehende Zahlung:".$grund." verbucht.<br>";
					db_query($strSQL);
					
					$strSQL = "INSERT INTO bills_payments (date_modified, bill_id, payment_id, amount, amount_usdollar)
					VALUES (NOW(), '".$rowB['id']."', 'PID-".$pid."', ".$betrag.", ".$betrag.");";
					//echo $strSQL."---<br>";
					db_query($strSQL);
				} else {
					echo "<b>ACHTUNG: Konnte keine Eingangsrechnung zu ".$grund." Betrag: € ".$betrag." Zahlungsdatum: ".$termin." finden!</b><br>";
				}
            }
            fclose($handle);
        }
		
	
?>
</body>
</html>