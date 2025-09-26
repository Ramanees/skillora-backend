<?php
include 'db.php';
header("Content-Type: application/json");

if (!isset($_GET['user_id'])) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "User ID is required."]);
    exit();
}

$user_id = $_GET['user_id'];

// ✅ THIS QUERY IS NOW FIXED
$stmt = $conn->prepare("
    SELECT 
        sr.request_id, 
        sr.status,
        receiver.name AS receiver_name, 
        receiver.profile_picture AS avatar_url,
        td.course_name,
        td.domain,
        td.id AS course_id, -- Explicitly select course_id
        td.experience,
        td.description,
        receiver.id AS instructor_id, -- ADDED: The receiver's ID is the instructor's ID
        receiver.learning_level AS level -- ADDED: The course level from the instructor's profile
    FROM 
        swap_requests sr
    JOIN 
        users receiver ON sr.receiver_id = receiver.id
    JOIN 
        teacher_details td ON sr.course_id = td.id
    WHERE 
        sr.sender_id = ? 
    ORDER BY 
        sr.created_at DESC
");

$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

$requests = array();
// NOTE: Make sure this URL is correct for your setup
$base_image_url = "https://your-tunnel-url/skillora-backend/";

while($row = $result->fetch_assoc()) {
    $avatar = $row['avatar_url'];
    if ($avatar && !filter_var($avatar, FILTER_VALIDATE_URL)) {
        $row['avatar_url'] = $base_image_url . $avatar;
    }
    $requests[] = $row;
}

echo json_encode($requests);

$stmt->close();
$conn->close();
?>