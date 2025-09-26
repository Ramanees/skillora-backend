<?php
session_start();
require 'db.php'; // Assuming db.php contains your database connection variables ($host, $user, $pass, $db, $port)

// CORS & JSON headers
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json");

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Convert mysqli warnings/errors to exceptions for better error handling
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    // Connect to the database
    $conn = new mysqli($host, $user, $pass, $db, $port);
    $conn->set_charset("utf8mb4");

    // Only allow POST requests
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(["status" => false, "message" => "Only POST requests are allowed."]);
        exit;
    }

    // --- 1) Validate email and get user details from 'users' table ---
    if (!isset($_POST['email']) || trim($_POST['email']) === '') {
        echo json_encode(["status" => false, "message" => "Email is required."]);
        exit;
    }
    $email = trim($_POST['email']);

    // Fetch user's details including id, current name, and existing profile picture path from the 'users' table
    $check_user_stmt = $conn->prepare("SELECT id, name, profile_picture FROM users WHERE email = ?");
    $check_user_stmt->bind_param("s", $email);
    $check_user_stmt->execute();
    $check_user_stmt->store_result();

    if ($check_user_stmt->num_rows === 0) {
        echo json_encode([
            "status" => false,
            "message" => "No user found with the provided email: {$email}. Cannot update."
        ]);
        exit;
    }
    $check_user_stmt->bind_result($user_id, $current_user_name, $existing_profile_picture_path);
    $check_user_stmt->fetch();
    $check_user_stmt->close();


    // --- 2) Collect and validate all relevant fields from POST ---
    // Fields for 'users' table (general profile) - from PersDetailsActivity
    $name              = trim($_POST['name'] ?? '');
    // $phone_no          = trim($_POST['phoneno'] ?? ''); // Removed phoneno
    $user_domain       = trim($_POST['user_domain'] ?? ''); // Mapped from 'occupation' in your Android code
    $skills_to_learn   = trim($_POST['skills_to_learn'] ?? '');
    $learning_level    = trim($_POST['learning_level'] ?? '');
    // For 'want_to_teach', we'll infer it from the presence of teaching details,
    // or you could add a specific 'want_to_teach' boolean field to the users table
    // For now, it's driven by the presence of teaching data.

    // Fields for 'teacher_details' table - from PersDetails2Activity
    $teacher_domain    = trim($_POST['teacher_domain'] ?? ''); // Mapped from 'domain_category' in Android
    $course_name       = trim($_POST['course_name'] ?? '');   // Mapped from 'skills_good_at' in Android
    $experience        = trim($_POST['experience'] ?? '');
    $description       = trim($_POST['description'] ?? '');

    // Basic validation for mandatory fields for the 'users' table update
    // Assuming name, user_domain, skills_to_learn, learning_level are mandatory for basic profile
    if (empty($name) || empty($user_domain) || empty($skills_to_learn) || empty($learning_level)) {
        echo json_encode(["status" => false, "message" => "Basic user profile details (name, domain, skills to learn, learning level) are required."]);
        exit;
    }

    // --- 3) Handle optional profile_picture upload ---
    $profile_path_for_db = $existing_profile_picture_path; // Default to existing path

    if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = __DIR__ . DIRECTORY_SEPARATOR . "uploads" . DIRECTORY_SEPARATOR;
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $maxBytes = 5 * 1024 * 1024; // 5MB
        if ($_FILES['profile_picture']['size'] > $maxBytes) {
            echo json_encode(["status" => false, "message" => "Image too large (max 5MB)."]);
            exit;
        }

        $origName = $_FILES['profile_picture']['name'];
        $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

        if (!in_array($ext, $allowed, true)) {
            echo json_encode(["status" => false, "message" => "Only JPG, JPEG, PNG, GIF, WEBP allowed."]);
            exit;
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $_FILES['profile_picture']['tmp_name']);
        finfo_close($finfo);
        if (strpos($mime, 'image/') !== 0) {
            echo json_encode(["status" => false, "message" => "Uploaded file is not an image."]);
            exit;
        }

        $newName = time() . "_" . bin2hex(random_bytes(4)) . "." . $ext;
        $targetPath = $uploadDir . $newName;

        if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $targetPath)) {
            $profile_path_for_db = "uploads/" . $newName;

            // Delete old profile picture if a new one was uploaded and an old one existed
            // Ensure $existing_profile_picture_path is a relative path like "uploads/old_image.jpg"
            if ($existing_profile_picture_path && strpos($existing_profile_picture_path, 'uploads/') === 0) {
                $full_old_path = __DIR__ . DIRECTORY_SEPARATOR . $existing_profile_picture_path;
                if (file_exists($full_old_path) && is_file($full_old_path)) { // Added is_file check for safety
                    unlink($full_old_path);
                }
            }
        } else {
            echo json_encode(["status" => false, "message" => "Failed to save uploaded image."]);
            exit;
        }
    }

    // --- 4) Update the 'users' table (general profile details) ---
    // Ensure 'password' is handled. For this context, assuming password is not updated here.
    $update_user_stmt = $conn->prepare("UPDATE users SET
        name = ?,
        profile_picture = ?,
        domain = ?,          -- User's main domain/occupation
        skills_to_learn = ?,
        learning_level = ?
    WHERE email = ?"); // Update by email since it's unique and passed

    $update_user_stmt->bind_param(
        "ssssss", // 6 strings (removed one 's' for phoneno)
        $name,
        $profile_path_for_db,
        $user_domain,
        $skills_to_learn,
        $learning_level,
        $email
    );
    $update_user_stmt->execute();

    if ($update_user_stmt->affected_rows > 0) {
        $user_update_message = "User profile updated successfully.";
    } else {
        $user_update_message = "User profile submitted, but no changes detected or update failed.";
    }
    $update_user_stmt->close();


    // --- 5) Logic for 'teacher_details' table insertion/update (specific teaching offering) ---
    $teacher_action_message = ""; // Initialize message for teacher details action

    // Determine if user wants to teach based on presence of teaching details
    $wants_to_teach = !empty($teacher_domain) && !empty($course_name) && !empty($experience) && !empty($description);

    if ($wants_to_teach) {
        // UPSERT logic for teacher_details table
        // This assumes user_email is UNIQUE in teacher_details, or combined with course_name if one user can teach multiple distinct courses
        // For simplicity, let's assume one teacher_details entry per user_email for now, or update on (user_email, course_name)
        // Given your previous `add_teacherdetails.php` which used ON DUPLICATE KEY UPDATE on `user_email`,
        // let's stick to that for now, assuming a user provides details for one primary teaching offering.
        // If a user can teach multiple courses, the `teacher_details` table structure needs to be adjusted
        // to have a composite unique key (e.g., user_email, course_name) or a separate 'courses' table linked by user_email/id.
        // For this merged script, I'll modify `teacher_details` to use `user_email` as the primary identifier for UPSERT.

        $sql_teacher_upsert = "INSERT INTO teacher_details (user_email, domain, course_name, experience, description)
                               VALUES (?, ?, ?, ?, ?)
                               ON DUPLICATE KEY UPDATE
                               domain = VALUES(domain),
                               course_name = VALUES(course_name),
                               experience = VALUES(experience),
                               description = VALUES(description),
                               updated_at = CURRENT_TIMESTAMP"; // Ensure updated_at is handled

        $teacher_stmt = $conn->prepare($sql_teacher_upsert);
        if ($teacher_stmt === false) {
            throw new Exception("Failed to prepare teacher details statement: " . $conn->error);
        }

        $teacher_stmt->bind_param(
            "sssss",
            $email, // user_email from the 'users' table
            $teacher_domain, // 'domain' for teacher_details table
            $course_name,
            $experience,
            $description
        );
        $teacher_stmt->execute();

        if ($teacher_stmt->affected_rows > 0) {
            $teacher_action_message = "Teacher details saved/updated successfully.";
        } else {
            $teacher_action_message = "Teacher details submitted, but no changes detected for teaching details.";
        }
        $teacher_stmt->close();
    } else {
        // User is not providing teaching details, so no action on teacher_details table.
        // You might consider deleting existing teacher_details for this user if they explicitly state they no longer want to teach.
        // For now, no deletion is implemented.
        $teacher_action_message = "No teacher details provided or user does not wish to teach.";
    }

    // --- 6) Final success response ---
    echo json_encode([
        "status"      => true,
        "message"     => trim("{$user_update_message} {$teacher_action_message}"), // Combine and trim
        "user_id"     => $user_id,
        "profile_url" => $profile_path_for_db
    ]);

} catch (mysqli_sql_exception $e) {
    http_response_code(500);
    error_log("Database error in update_user_profile.php: " . $e->getMessage()); // Log error on server
    echo json_encode(["status" => false, "message" => "An internal database error occurred. Please try again later."]);
} catch (Throwable $e) {
    http_response_code(500);
    error_log("Server error in update_user_profile.php: " . $e->getMessage()); // Log error on server
    echo json_encode(["status" => false, "message" => "An unexpected server error occurred. Please try again later."]);
} finally {
    if (isset($conn)) {
        $conn->close();
    }
}
?>