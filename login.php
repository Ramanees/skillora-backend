<?php
session_start();
require 'db.php';

header('Content-Type: application/json');

$response = [
    'status' => 'error',
    'message' => '',
    'data' => null
];

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    $response['message'] = 'Invalid request method. Only POST allowed.';
    echo json_encode($response);
    exit;
}

$email = $_POST['email'] ?? '';
$password = $_POST['password'] ?? '';

if (empty($email) || empty($password)) {
    http_response_code(400);
    $response['message'] = 'Email and password are required.';
    echo json_encode($response);
    exit;
}

$stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 1) {
    $user = $result->fetch_assoc();

    // Compare passwords (hashing recommended in production)
    if ($password === $user['password']) {
        // // Create session & set cookie
        $_SESSION['user_id'] = $user['id'];
        // $_SESSION['user_name'] = $user['name'];
        // $_SESSION['user_email'] = $user['email'];

        http_response_code(200);
        $response['status'] = 'success';
        $response['message'] = 'Login successful.';
        $response['data'] = [
            'user_id' => $user['id'],
            'user_name' => $user['name'],
            'user_email' => $user['email']
        ];
    } else {
        http_response_code(200);
        $response['message'] = 'Invalid password.';
    }
} else {
    http_response_code(200);
    $response['message'] = 'No user found with this email.';
}

echo json_encode($response);
?>
