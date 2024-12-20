<?php
	$link = mysqli_connect('localhost', 'addinol_usr', 'lwT1e99~', 'addinol_crm');
	mysqli_set_charset($link, "utf8");
	
	if (isset($_POST['absenden'])){
		$strJahr = $_POST['cmbJahr'];
	} else {
		$strJahr = date('Y');
	}

	
	/* Begin PIECHART 	*/
	$strSQL = "SELECT Sum(invoice.amount) as Brutto,  Sum(invoice.pretax) as Netto, invoice.deleted, accounts.name, YEAR(invoice_date) as Jahr
		FROM accounts 
		INNER JOIN invoice ON accounts.id = invoice.billing_account_id
		GROUP BY YEAR(invoice_date), accounts.name, invoice.deleted
		HAVING (((invoice.deleted)=0) AND (Jahr=".$strJahr.")) 
		ORDER BY Netto DESC;";
	if ($result = mysqli_query($link, $strSQL)) {
		while ($row = mysqli_fetch_assoc($result)) {	
			$arrPie[] = [
				'Name' => $row['name'],
				'Netto' => $row['Netto']			
			];
		}
	}
	
	/* 	END PIECHART */
	
?>
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1" />
<script type="text/javascript" src="https://www.gstatic.com/charts/loader.js"></script>
<script type="text/javascript">
  google.charts.load('current', {'packages':['corechart']});
  google.charts.setOnLoadCallback(drawChart);
  function drawChart() {
	var data = google.visualization.arrayToDataTable([

<?php
	print "['Firma', 'Netto']";
	foreach ($arrPie as $nr => $inhalt)
	{
		print ",";
		$Key  = $inhalt['Name'];
		$Netto  = $inhalt['Netto'];
		echo "['$Key', $Netto]";
	}
?>	
	]);

	var options = {
	  title: 'Monatsumsatz'
	};

	var chart = new google.visualization.PieChart(document.getElementById('piechart'));

	chart.draw(data, options);
	
  }
</script>
<link href="styles.css" rel="stylesheet" type="text/css" />
</head>
<body>  

	<div>
		<h1>Umsatzliste</h1> 
		<form action='umsatzliste.php' method='post'>
			<select name='cmbJahr'>
				<?php
				for ($i = 2015; $i <= 2025; $i++) {
					$selected = $strJahr == $i ? 'selected' : '';
					echo "<option value='$i' $selected>$i</option>";
				}
				?>		
			</select>
			<input type='submit' name='absenden' value='anzeigen'>
		</form>

	</div>
	<div id="piechart" style="float: right; margin: 0px; padding: 0px; width: 400px; height: 350px;"></div>	
<?php
	
	$SumReBetrag = 0;
	$SumZahlung = 0;
	$SumSkonto = 0;
	
	/* Ausgangsrechnungen */
	print "\t<table>\n";

	$strSQL = "SELECT Count(invoice.id) as AnzRe, Sum(invoice.amount) as Brutto,  Sum(invoice.pretax) as Netto, invoice.deleted, accounts.id, accounts.name, YEAR(invoice_date) as Jahr
		FROM accounts 
		INNER JOIN invoice ON accounts.id = invoice.billing_account_id
		GROUP BY YEAR(invoice_date), accounts.name, invoice.deleted
		HAVING (((invoice.deleted)=0) AND (Jahr=".$strJahr.")) 
		ORDER BY Netto DESC;";

	print "\t<tr><th width=370>Kunde</th><th width=60>Anz.RE</th><th width=90>Brutto</th><th width=90>Netto</th></tr>\n";
	if ($result = mysqli_query($link, $strSQL)) {
		while ($row = mysqli_fetch_assoc($result)) {	
			print "\t<tr>
			<td><a href='https://addinol-lubeoil.at/crm/index.php?module=Accounts&action=DetailView&record=".$row['id']."' target='_blank'>".$row['name']."</a></td>
			<td>".$row['AnzRe']."</td>
			<td align='right'>".number_format($row['Brutto'], 2, ',', '.')."</td>
			<td align='right'>".number_format($row['Netto'], 2, ',', '.')."</td>
			<td><a href='kunde_artikel.php?id=".$row['id']."' target='_blank'>Details...</a></td>";
			print "</tr>\n";
		}
	}
	
	print "\t</table>\n";
	print "\t<br>\n";
	print "\t<br>\n";

?>

</body>
</html>