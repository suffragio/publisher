<?php
require_once '../php/database.php';
require_once '../php/endorsements.php';

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: content-type");
$mysqli = new mysqli($database_host, $database_username, $database_password, $database_name);
if ($mysqli->connect_errno)
  die("{\"error\":\"Failed to connect to MySQL database: $mysqli->connect_error ($mysqli->connect_errno)\"}");
$mysqli->set_charset('utf8mb4');
$key = $mysqli->escape_string($_POST['key']);
die(endorsements($mysqli, $key));
?>
