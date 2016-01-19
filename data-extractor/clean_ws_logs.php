<?php
require_once(dirname(__FILE__) . '/../classes/WarOfNations2.class.php');
require_once(dirname(__FILE__) . '/../classes/data/DataLoad.class.php');

$won = new WarOfNations();
$rows_updated = DataLoadDAO::cleanupWebserviceLogs($won->db);

echo "Updated $rows_updated web service log records\r\n";
?>