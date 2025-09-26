<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Content-Type: application/json");

// Database connection
$host = "localhost";
$port = "3307"; // Your custom port
$db_name = "skill";
$username = "root";
$password = "";
$conn = null;

try {
    $conn = new PDO("mysql:host=$host;port=$port;dbname=$db_name;charset=utf8mb4", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $exception) {
    echo json_encode([
        "status" => false,
        "message" => "Database connection error: " . $exception->getMessage()
    ]);
    exit;
}

// Handle POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Required fields for update
    $required = ['user_id', 'skills_good_at', 'experience', 'description'];
    foreach ($required as $field) {
        if (!isset($_POST[$field]) || $_POST[$field] === '') {
            echo json_encode(["status" => false, "message" => "$field is required."]);
            exit;
        }
    }

    $user_id = $_POST['user_id'];
    $skills_good_at = $_POST['skills_good_at'];
    $experience = $_POST['experience'];
    $description = $_POST['description'];

    // Check if user_id exists in user_details table
    $user_check = $conn->prepare("SELECT user_id FROM user_details WHERE user_id = :user_id");
    $user_check->bindParam(':user_id', $user_id);
    $user_check->execute();

    if ($user_check->rowCount() == 0) {
        echo json_encode(["status" => false, "message" => "No details found for this user_id to update."]);
        exit;
    }

    // Update query
    $sql = "UPDATE user_details SET skills_good_at = :skills_good_at, experience = :experience, description = :description WHERE user_id = :user_id";
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':skills_good_at', $skills_good_at);
    $stmt->bindParam(':experience', $experience);
    $stmt->bindParam(':description', $description);
    $stmt->bindParam(':user_id', $user_id);

    if ($stmt->execute()) {
        echo json_encode(["status" => true, "message" => "User details updated successfully."]);
    } else {
        echo json_encode(["status" => false, "message" => "Failed to update user details."]);
    }
} else {
    echo json_encode(["status" => false, "message" => "Only POST requests are allowed."]);
}
?>
