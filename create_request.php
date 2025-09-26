<?php
include 'db.php';
header("Content-Type: application/json");

$data = json_decode(file_get_contents('php://input'), true);

$sender_id = $data['senderId'];
$receiver_id = $data['receiverId'];
$course_id = $data['courseId'];

$stmt = $conn->prepare("INSERT INTO swap_requests (sender_id, receiver_id, course_id) VALUES (?, ?, ?)");
$stmt->bind_param("iii", $sender_id, $receiver_id, $course_id);

if ($stmt->execute()) {
    echo json_encode(["status" => "success", "message" => "Swap request sent."]);
} else {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Failed to send request."]);
}

$stmt->close();
$conn->close();
?>