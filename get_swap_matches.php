<?php
require 'db.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

$matches = []; // The final array to hold data or errors

if ($conn->connect_error) {
    // If connection fails, return an array with an error object
    $matches[] = ["error" => "Database connection failed."];
} else {
    $user_email = $_GET['email'] ?? '';

    if (empty($user_email)) {
        $matches[] = ["error" => "User email not provided."];
    } else {
        // --- THIS IS THE CORRECTED SQL QUERY ---
        $sql = "SELECT
                    u2.name AS instructor_name,
                    td_b.id AS course_id,
                    u2.id AS instructor_id,
                    u2.profile_picture AS avatar_url,
                    td_b.domain AS domain_name,
                    td_b.course_name,
                    td_b.experience,
                    td_b.description,
                    u2.learning_level AS level
                FROM
                    users AS u1
                JOIN
                    teacher_details AS td_a ON u1.email = td_a.user_email
                JOIN
                    -- Find users (u2) who want to learn in the SAME DOMAIN that Chloe (u1) teaches.
                    users AS u2 ON u2.domain = td_a.domain
                JOIN
                    -- From that list, find users who TEACH in the SAME DOMAIN that Chloe (u1) wants to learn.
                    teacher_details AS td_b ON u2.email = td_b.user_email AND td_b.domain = u1.domain
                WHERE
                    u1.email = ? AND u2.email != ?;";

        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("ss", $user_email, $user_email);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows > 0) {
                $base_image_url = "https://ngkjx5pt-80.inc1.devtunnels.ms/skillora-backend/";

                while($row = $result->fetch_assoc()) {
                    $avatar = $row['avatar_url'];

                    if ($avatar && !filter_var($avatar, FILTER_VALIDATE_URL)) {
                        $row['avatar_url'] = $base_image_url . $avatar;
                    }
                    
                    $matches[] = $row;
                }
            }
            $stmt->close();
        } else {
            $matches[] = ["error" => "Failed to prepare the query."];
        }
    }
}
$conn->close();

echo json_encode($matches);