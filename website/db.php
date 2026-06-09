<?php
// Database configuration
$host = 'localhost';
$user = 'root';
$pass = ''; // Leave blank for default XAMPP/WAMP
$db   = 'ComputerPartsInventoryDB';

// Create a new connection to the database
$conn = new mysqli($host, $user, $pass, $db);

// Check if the connection was successful
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>