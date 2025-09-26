<?php
// signup/signup.php - API to register a new user
require 'db.php';

header('Content-Type: application/json');

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(200);
    echo json_encode([
        'status' => 'error',
        'message' => 'Invalid request method. Only POST allowed.'
    ]);
    exit;
}

$name = $_POST['name'] ?? '';
$email = $_POST['email'] ?? '';
$password = $_POST['password'] ?? '';
$phone = $_POST['phone'] ?? '';

if (empty($name) || empty($email) || empty($password)) {
    http_response_code(200);
    echo json_encode([
        'status' => 'error',
        'message' => 'All fields (name, email, password) are required.'
    ]);
    exit;
}

// Check if user already exists
$check = $conn->prepare("SELECT id FROM users WHERE email = ?");
$check->bind_param("s", $email);
$check->execute();
$result = $check->get_result();

if ($result->num_rows > 0) {
    http_response_code(200);
    echo json_encode([
        'status' => 'error',
        'message' => 'Email is already registered.'
    ]);
    exit;
}

// Register user
//$hashed_password = password_hash($password, PASSWORD_BCRYPT);
$stmt = $conn->prepare("INSERT INTO users (name, email, password,phoneno) VALUES (?,?,?,?)");
$stmt->bind_param("ssss", $name, $email, $password,$phone);

if ($stmt->execute()) {
    echo json_encode([
        'status' => 'success',
        'message' => 'User registered successfully.'
    ]);
} else {
    http_response_code(200);
    echo json_encode([
        'status' => 'error',
        'message' => 'Registration failed. Please try again later.'
    ]);
}
?>