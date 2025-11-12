<?php
// Database credentials
$servername = "localhost";
$username = "root";
$password = ""; // Default password for WAMP is usually empty
$dbname = "blood"; // The database name you confirmed

// Create connection
$conn = mysqli_connect($servername, $username, $password, $dbname);

// Check connection and stop if it fails
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}
?>