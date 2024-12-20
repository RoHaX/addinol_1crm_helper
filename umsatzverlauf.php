<?php
	$link = mysqli_connect('localhost', 'addinol_usr', 'lwT1e99~', 'addinol_crm');
	mysqli_set_charset($link, "utf8");
	
	if (isset($_POST['absenden'])){
		$strJahr = $_POST['cmbJahr'];
	} else {
		$strJahr = date('Y');
	}


?>
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1" />

    <script src="https://www.google.com/jsapi" type="text/javascript"></script> 
	<script type="text/javascript"> 
		google.load("jquery","1.4.4");
		google.load("jqueryui", "1.8.9");
	</script>
    

    <script type="text/javascript" src="jquery.sparkline.js"></script>

    <link rel="stylesheet" href="https://ajax.googleapis.com/ajax/libs/jqueryui/1.8.9/themes/ui-lightness/jquery-ui.css">
<link href="styles.css" rel="stylesheet" type="text/css" />
<script type="text/javascript"> 
    /* <![CDATA[ */
    var mdraw = 0;
    $(function() {

        // Line charts taking their values from the tag
        $('.line').sparkline();
		$('.bar').sparkline('html', {type: 'bar'});
		$.sparkline_display_visible(); 
 
    });
 
 
    /* ]]> */
    </script> 		
</head>
<body>  

	<div>
		<h1>Umsatzverlauf</h1> 


	</div>

<?php
	
	$SumReBetrag = 0;
	$SumZahlung = 0;
	$SumSkonto = 0;
	
	/* Ausgangsrechnungen */
	print "\t<table>\n";

	$strSQL = "SELECT Count(invoice.id) as AnzRe, Sum(invoice.amount) as Brutto,  Sum(invoice.pretax) as Netto, invoice.deleted, accounts.id, accounts.name
		FROM accounts 
		INNER JOIN invoice ON accounts.id = invoice.billing_account_id
		GROUP BY accounts.name, invoice.deleted
		HAVING ((invoice.deleted)=0)   
		ORDER BY Netto DESC;";		

	print "\t<tr><th width=370>Kunde</th><th width=60>Anz.RE</th><th width=90>Brutto</th><th width=90>Netto</th><th width=100>Jahre</th><th width=200>Monatsverlauf</th></tr>\n";
	if ($result = mysqli_query($link, $strSQL)) {
		while ($row = mysqli_fetch_assoc($result)) {	
	
			$strSQLDetail = "SELECT Sum(pretax) as Netto, billing_account_id, deleted, DATE_FORMAT(invoice_date, '%Y%m') as MonJahr
			FROM invoice
			GROUP BY MonJahr, billing_account_id, deleted
			HAVING (((deleted)=0) AND (billing_account_id='".$row['id']."'))
			ORDER BY MonJahr ASC;";
			
			$strTmp = "";
			if ($result2 = mysqli_query($link, $strSQLDetail)) {
				while ($row2 = mysqli_fetch_assoc($result2)) {	
					$strTmp = $strTmp.$row2['Netto'].",";
				}
			}
			$strSQLDetail = "SELECT Sum(pretax) as Netto, billing_account_id, deleted, DATE_FORMAT(invoice_date, '%Y') as Jahr
			FROM invoice
			GROUP BY Jahr, billing_account_id, deleted
			HAVING (((deleted)=0) AND (billing_account_id='".$row['id']."'))
			ORDER BY Jahr ASC;";
			
			$strTmpJahr = "";
			if ($result2 = mysqli_query($link, $strSQLDetail)) {
				while ($row2 = mysqli_fetch_assoc($result2)) {	
					$strTmpJahr = $strTmpJahr.$row2['Netto'].",";
				}
			}
			print "\t<tr>
			<td><a href='https://addinol-lubeoil.at/crm/index.php?module=Accounts&action=DetailView&record=".$row['id']."' target='_blank'>".$row['name']."</a></td>
			<td>".$row['AnzRe']."</td>
			<td align='right'>".number_format($row['Brutto'], 2, ',', '.')."</td>
			<td align='right'>".number_format($row['Netto'], 2, ',', '.')."</td>
			<td><span class='bar'>".$strTmpJahr."</span></td>
			<td><span class='line'>".$strTmp."</span></td>
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