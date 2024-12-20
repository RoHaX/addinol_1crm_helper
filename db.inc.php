<?php

	$mysqli = mysqli_connect('localhost', 'addinol_usr', 'lwT1e99~', 'addinol_crm');
	
	function db_query($sql) {

		$result = mysqli_query($mysqli, $sql);
		if ($result === false) {
				print "\t<b>SQL-Fehler:</b><br>".htmlentities($sql)."<br>\n";
				db_err();
		}
		return $result;
	}

	function db_err() {
			print "\t<b>SQL-Fehler:</b><br>\n";
			print "\t".mysqli_error()."<br>\n";
	}

	function db_close() {
			/* schliessen der Verbinung */
			// noch fragen.... mysqli_close($link);
	}
	function my_money_format($value) { 
		return number_format($value, 2, ',', '.'); 
	}
?>
