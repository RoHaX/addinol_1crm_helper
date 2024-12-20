<?php
	$link = mysqli_connect('localhost', 'addinol_usr', 'lwT1e99~', 'addinol_crm');
	mysqli_set_charset($link, "utf8");
?>
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1" />
<link href="styles.css" rel="stylesheet" type="text/css" />
</head>
<body>  
<h1>Lagerstand</h1>


<?php
$arrFiles = array();
function listFolderFiles($dir){
    $ffs = scandir($dir);
    $i = 0;
    $list = array();
    foreach ( $ffs as $ff ){
        if ( $ff != '.' && $ff != '..' ){
            if ( strlen($ff)>=5 ) {
                if ( substr($ff, -4) == '.pdf' && substr($ff, 0, 11) == 'Rechnung_RE') {
                    $list[] = $ff;
                    //echo dirname($ff) . $ff . "<br/>";
                    //echo $dir.'/'.$ff.'<br/>';
					echo $ff;
					$pname = explode("_", $ff);
					echo " - ".$pname(2);
					echo "<br/>";
					$arrFiles[] = $ff;
                }    
            }       
            if( is_dir($dir.'/'.$ff) ) 
                    listFolderFiles($dir.'/'.$ff);
        }
    }
    return $list;
}

$files = array();
$files = listFolderFiles(dirname("../files/upload/"));
		
print "XXXXX";

print_r($arrFiles);



	
?>


</body>
</html>