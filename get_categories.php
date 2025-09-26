<?php
session_start();
require 'db.php';

header('Content-Type: application/json'); // Set header to indicate JSON response
header('Access-Control-Allow-Origin: *'); // Allow requests from any origin (for development, restrict in production)
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');




// SQL query to fetch categories - CHANGED HERE
$sql = "SELECT id, name, image FROM skill_domains ORDER BY name ASC";
$result = $conn->query($sql);

$categories = array();

if ($result->num_rows > 0) {
    // Output data of each row
    while($row = $result->fetch_assoc()) {
        $categories[] = $row; // Add each row as an associative array to the categories array
    }
} else {
    // No categories found
    echo json_encode(["message" => "No categories found"]);
    exit(); // Exit to prevent sending empty array below
}

// Encode the array to JSON and output it
echo json_encode($categories);

$conn->close();
?>