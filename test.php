<html>
  <head>
    <script type="text/javascript" src="https://www.gstatic.com/charts/loader.js"></script>
<?php
		include("db.inc.php");
		db_open();
		$strSQL = "SELECT Sum(payments.total_amount) AS Zahlung, YEAR(payment_date) AS Jahr, MONTH(payment_date) AS Monat, payments.direction, payments.payment_type
			FROM payments
			GROUP BY YEAR(payment_date), MONTH(payment_date), direction, payment_type
			HAVING direction='incoming' AND payment_type<>'Skonto';";
		$result = db_query($strSQL);
		$arrUmsatz['2015-12'] = [
			'Jahr' => 2015,
			'Monat' => 12,
			'Umsatz' => 0,
			'Zahlung' => 0];			
		
		while ($row = mysql_fetch_array($result, MYSQL_ASSOC)) {
			$strKey = $row['Jahr']."-".$row['Monat'];
			$arrUmsatz[$strKey] = [
				'Jahr' => $row['Jahr'],
				'Monat' => $row['Monat'],
				'Zahlung' => $row['Zahlung']			
			];
		}
		
		$strSQL = "SELECT Sum(amount) as Umsatz, YEAR(invoice_date) AS Jahr, MONTH(invoice_date) AS Monat, deleted
			FROM invoice 
			GROUP BY YEAR(invoice_date), MONTH(invoice_date), deleted
			HAVING ((deleted)=0)";
			
		$result = db_query($strSQL);
		while ($row = mysql_fetch_array($result, MYSQL_ASSOC)) {
			$strKey = $row['Jahr']."-".$row['Monat'];
			$arrUmsatz[$strKey]['Umsatz'] = $row['Umsatz'];
		}
		
		$strSQL = "SELECT Sum(amount) as Rechnung, YEAR(bill_date) AS Jahr, MONTH(bill_date) AS Monat, deleted
			FROM bills 
			GROUP BY YEAR(bill_date), MONTH(bill_date), deleted
			HAVING ((deleted)=0)";
		$result = db_query($strSQL);
		while ($row = mysql_fetch_array($result, MYSQL_ASSOC)) {
			$strKey = $row['Jahr']."-".$row['Monat'];
			$arrUmsatz[$strKey]['Rechnung'] = $row['Rechnung'];
		}
	
		
?>

    <script type="text/javascript">
      google.charts.load('current', {'packages':['corechart']});
      google.charts.setOnLoadCallback(drawChart);
      function drawChart() {
        var data = google.visualization.arrayToDataTable([
<?php
		print "['Monat', 'Zahlungseingang', 'Umsatz', 'Eingangsrechnungen']";
		foreach ($arrUmsatz as $nr => $inhalt)
		{
			print ",";
			$Key  = $inhalt['Jahr']."-".$inhalt['Monat'];
			$Zahlung  = $inhalt['Zahlung'];
			$Umsatz  = $inhalt['Umsatz'];
			$Rechnung  = $inhalt['Rechnung'];
			echo "['$Key', $Zahlung, $Umsatz, $Rechnung]";
		}
?>	
		]);
        var options = {
          title: 'Übersicht',
          hAxis: {title: 'Monat',  titleTextStyle: {color: '#333'}},
          vAxis: {minValue: 0}
        };

        var chart = new google.visualization.AreaChart(document.getElementById('chart_div'));
        chart.draw(data, options);
      }
    </script>
  </head>
  <body>
	<?php 
		print_r ( $arrUmsatz ); 
		
	?>
    <div id="chart_div" style="width: 900px; height: 300px;"></div>
  </body>
</html>

