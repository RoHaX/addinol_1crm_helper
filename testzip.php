<?php
	$zipname = 'rechnungen.zip';
    $zip = new ZipArchive;
    $zip->open($zipname, ZipArchive::CREATE);
    if ($handle = opendir('pdfexport/')) {
      while (false !== ($entry = readdir($handle))) {
        if ($entry != "." && $entry != ".." && !strstr($entry,'.php') && !strstr($entry,'.zip')) {
            $zip->addFile('pdfexport/'.$entry);
			echo $entry;
        }
      }
      closedir($handle);
    }

    $zip->close();

    header('Content-Type: application/zip');
    header("Content-Disposition: attachment; filename='rechnungen.zip'");
    header('Content-Length: ' . filesize($zipname));
    header("Location: rechnungen.zip");
	
?>