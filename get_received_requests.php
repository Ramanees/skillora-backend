<?php
include 'db.php';
header("Content-Type: application/json");

if (!isset($_GET['user_id'])) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "User ID is required."]);
    exit();
}

$user_id = $_GET['user_id'];

// This query joins the three tables to get all the info you need
// In get_received_requests.php

// This query now gets the sender's domain (role) and learning_level (experience)
$query = "
    SELECT 
        sr.request_id, 
        sr.status,
        sender.id AS sender_id,
        sender.name as sender_name, 
        sender.profile_picture as sender_avatar,
        sender.domain AS sender_role,
        sender.learning_level AS sender_experience,
        td.course_name,
        td.domain,
        td.*
    FROM 
        swap_requests sr
    JOIN 
        users sender ON sr.sender_id = sender.id
    JOIN 
        teacher_details td ON sr.course_id = td.id
    WHERE 
        sr.receiver_id = ? AND sr.status = 'pending'
    ORDER BY 
        sr.created_at DESC
";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

$requests = array();
while($row = $result->fetch_assoc()) {
    $requests[] = $row;
}

echo json_encode($requests);

$stmt->close();
$conn->close();
?>