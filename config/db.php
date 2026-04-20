<?php
require_once __DIR__ . '/app.php';

$host = app_config('db_host', 'localhost');
$db = app_config('db_name', 'arnaut');
$user = app_config('db_user', 'root');
$pass = app_config('db_pass', '');

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>
