<?php

require_once __DIR__ . '/../db.inc.php';

class MwDb
{
	public static function getMysqli()
	{
		global $mysqli;
		return $mysqli;
	}
}
