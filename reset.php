<?php
session_start();
require 'db.php';

$response = array('status' => '', 'message' => '', 'data' => null);

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (empty($email) || empty($password) || empty($confirm_password)) {
        $response['status'] = 'error';
        $response['message'] = 'Email, password, and confirm password are required.';
    } elseif ($password !== $confirm_password) {
        $response['status'] = 'error';
        $response['message'] = 'Passwords do not match.';
    } else {
        $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows == 1) {
            //$hashedPassword = password_hash($password, PASSWORD_DEFAULT);

            $updateStmt = $conn->prepare("UPDATE users SET password = ? WHERE email = ?");
            $updateStmt->bind_param("ss", $password, $email);

            if ($updateStmt->execute()) {
                $response['status'] = 'success';
                $response['message'] = 'Password reset successful.';
            } else {
                $response['status'] = 'error';
                $response['message'] = 'Failed to reset password. Please try again.';
            }

            $updateStmt->close();
        } else {
            $response['status'] = 'error';
            $response['message'] = 'No user found with this email.';
        }

        $stmt->close();
    }
} else {
    $response['status'] = 'error';
    $response['message'] = 'Invalid request method.';
}

header('Content-Type: application/json');
echo json_encode($response);
?>
