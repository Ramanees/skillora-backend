<?php
$host = "localhost";
$port = 3307;               // Check if this is the correct MySQL port
$db   = "skill";
$user = "root";
$pass = "";                 // Update this with your MySQL password if needed

// Create connection with port
$conn = new mysqli($host, $user, $pass, $db, $port);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
//  else {
//     echo "Connected successfully!";
// }
?>
