<?php
require 'db.php';

// Set headers for CORS and content type.
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight OPTIONS request.
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Check database connection from db.php.


// Get the category name from the GET request parameter.
$category = $_GET['category'] ?? ''; // Use null coalescing for cleaner access.

$courses_data = []; // Initialize an empty array.

// Use a prepared statement for security and performance.
if (!empty($category)) {
    // 1. Define the SQL query with a placeholder (?) for the category.
    $sql = "SELECT
                td.id AS course_id,
                u.id AS instructor_id,
                td.course_name,
                td.domain AS domain_name,
                td.description,
                u.name AS instructor_name,
                u.profile_picture AS avatar_url,
                u.learning_level AS level
            FROM
                teacher_details td
            JOIN
                users u ON td.user_email = u.email
            WHERE
                td.domain = ?;";

    // 2. Prepare the statement.
    if ($stmt = $conn->prepare($sql)) {
        // 3. Bind the parameter (s = string) to the placeholder.
        $stmt->bind_param("s", $category);

        // 4. Execute the statement.
        $stmt->execute();

        // 5. Get the result set.
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $base_image_url = "https://ngkjx5pt-80.inc1.devtunnels.ms/skillora-backend/";

            // 6. Loop through the results and build the data array.
            while($row = $result->fetch_assoc()) {
                // Construct the full image URL.
                $row['avatar_url'] = $base_image_url . $row['avatar_url'];
                $courses_data[] = $row;
            }
        }
        $stmt->close(); // Close the prepared statement.
    } else {
        error_log("SQL Prepare Error: " . $conn->error);
        $courses_data = ["error" => "Failed to prepare the query."];
    }
} else {
    $courses_data = ["message" => "No category specified."];
}

// 7. Output the JSON response.
echo json_encode($courses_data);

// 8. Close the database connection.
$conn->close();
?>