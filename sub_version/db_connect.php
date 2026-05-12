<?php
/**
 * KIM INVENTORIES — db_connect.php
 * mysqli connection to MySQL.
 */
 
$conn = new mysqli("localhost", "root", "", "kim_inventories");
 
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}