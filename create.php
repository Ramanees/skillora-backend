<?php
session_start();
require 'db.php';
header('Content-Type: application/json'); // Set header to indicate JSON response


// Check if the request method is POST
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get the raw POST data
    $json_data = file_get_contents("php://input");
    $data = json_decode($json_data, true); // Decode JSON string to associative array

    // Validate and sanitize input
    $email = isset($data['email']) ? $conn->real_escape_string($data['email']) : '';
    $name = isset($data['name']) ? $conn->real_escape_string($data['name']) : '';
    $profile_picture_url = isset($data['profile_picture_url']) ? $conn->real_escape_string($data['profile_picture_url']) : null; // Can be null if not uploaded
    $domain = isset($data['domain']) ? $conn->real_escape_string($data['domain']) : '';
    $skills_to_learn = isset($data['skills_to_learn']) ? $conn->real_escape_string($data['skills_to_learn']) : '';
    $learning_level = isset($data['learning_level']) ? $conn->real_escape_string($data['learning_level']) : '';

    // Check for essential data
    if (empty($email) || empty($name) || empty($domain) || empty($skills_to_learn) || empty($learning_level)) {
        echo json_encode(["success" => false, "message" => "Missing required fields."]);
        $conn->close();
        exit();
    }

    // Construct the UPDATE query
    // We update based on 'email' as it's passed from the previous activity and assumed to be unique.
    $sql = "UPDATE users SET
                name = ?,
                profile_picture = ?,
                domain = ?,
                skills_to_learn = ?,
                learning_level = ?
            WHERE email = ?";

    // Prepare the statement
    $stmt = $conn->prepare($sql);

    if ($stmt === false) {
        echo json_encode(["success" => false, "message" => "Prepare failed: " . $conn->error]);
        $conn->close();
        exit();
    }

    // Bind parameters
    // 's' for string, 'b' for blob (for profile_picture, though we're storing URL as string here), or 's' for string if storing URL
    // For profile_picture_url, we're assuming you'll store the URL as a string. If you're uploading the actual image file to the server, you'd need a separate file upload handler and then store the path.
    $stmt->bind_param("ssssss", $name, $profile_picture_url, $domain, $skills_to_learn, $learning_level, $email);

    // Execute the statement
    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            echo json_encode(["success" => true, "message" => "Personal details updated successfully."]);
        } else {
            // This might mean the email didn't match any existing record, or the data was the same
            echo json_encode(["success" => false, "message" => "No record found for this email or no changes made."]);
        }
    } else {
        echo json_encode(["success" => false, "message" => "Error updating record: " . $stmt->error]);
    }

    // Close statement
    $stmt->close();

} else {
    echo json_encode(["success" => false, "message" => "Invalid request method."]);
}

// Close database connection
$conn->close();
?>