<?php

include('classes/data/DatabaseFactory.class.php');
include('classes/data/Player.class.php');

$db = DatabaseFactory::getDatabase();

$array = PlayerDAO::getPlayerById($db, 10834);
var_dump($array);
$player = Player::FromDB($array);
var_dump($player);

?>