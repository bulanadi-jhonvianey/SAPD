<?php
$sname= "localhost";
$unmae= "root";
$password = "";
$db_name = "sapd_db"; // Make sure this matches your actual database name

$conn = mysqli_connect($sname, $unmae, $password, $db_name);

if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}
?>