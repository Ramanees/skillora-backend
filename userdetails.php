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

    // --- 1) Validate email and get user_id and current name/profile picture ---
    if (!isset($_POST['email']) || trim($_POST['email']) === '') {
        echo json_encode(["status" => false, "message" => "Email is required."]);
        exit;
    }
    $email = trim($_POST['email']);

    // Fetch user's ID, current name, and existing profile picture path
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
    $check_user_stmt->bind_result($user_id, $current_user_name, $existing_profile_picture_path); // Use $user_id and $current_user_name for clarity
    $check_user_stmt->fetch();
    $check_user_stmt->close();


    // --- 2) Collect and validate all relevant fields from POST ---
    // Fields for users table (general profile) - now strictly from PersDetails1Activity
    $name_from_post    = trim($_POST['name'] ?? ''); // This is the name submitted from Android form (pd1_edit_name)
    $occupation        = trim($_POST['occupation'] ?? '');
    $skills_to_learn   = trim($_POST['skills_to_learn'] ?? ''); // What user wants to learn (from PersDetails1Activity)
    $learning_level    = trim($_POST['learning_level'] ?? '');
    $want_to_teach     = trim($_POST['want_to_teach'] ?? ''); // 'Yes' | 'No'

    // Fields for courses table (specific teaching offering) - from PersDetails2Activity
    $teach_category    = trim($_POST['teach_category'] ?? ''); // pd2_spinner_category
    $skills_good_at    = trim($_POST['skills_good_at'] ?? ''); // pd2_edit_skills_teach (course_name)
    $experience        = trim($_POST['experience'] ?? '');     // pd2_edit_experience (teacher_experience for THIS course)
    $description       = trim($_POST['description'] ?? '');    // pd2_edit_description (course_description)

    // Basic validation for mandatory fields (for users table update)
    if (empty($name_from_post) || empty($occupation) || empty($skills_to_learn) || empty($learning_level) || empty($want_to_teach)) {
        echo json_encode(["status" => false, "message" => "Basic user details (name, occupation, skills_to_learn, learning_level, want_to_teach) are required."]);
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
            if ($existing_profile_picture_path && file_exists($existing_profile_picture_path)) {
                unlink($existing_profile_picture_path);
            }
        } else {
            echo json_encode(["status" => false, "message" => "Failed to save uploaded image."]);
            exit;
        }
    }

    // --- 4) Update the 'users' table (general profile details) ---
    // Removed skills_good_at, experience, description, teach_category from users table update
    // as they now specifically belong to the courses table.
    $update_user_stmt = $conn->prepare("UPDATE users SET
        name = ?,
        profile_picture = ?,
        occupation = ?,
        skills_to_learn = ?,
        learning_level = ?,
        want_to_teach = ?
    WHERE id = ?");

    $update_user_stmt->bind_param(
        "ssssssi", // 6 strings + 1 integer
        $name_from_post, // Use the name from the POST data for user profile update
        $profile_path_for_db,
        $occupation,
        $skills_to_learn,
        $learning_level,
        $want_to_teach,
        $user_id
    );
    $update_user_stmt->execute();

    if ($update_user_stmt->affected_rows > 0) {
        $user_update_message = "User profile updated successfully.";
    } else {
        $user_update_message = "User profile submitted, but no changes detected or update failed.";
    }
    $update_user_stmt->close();


    // --- 5) Logic for 'courses' table insertion/update (specific teaching offering) ---
    $course_action_message = ""; // Initialize message for course action

    if ($want_to_teach === "Yes") {
        // Validate fields crucial for course insertion
        if (empty($skills_good_at) || empty($description) || empty($experience) || empty($teach_category)) {
            $course_action_message = "Skipping course creation: User wants to teach, but essential course details (Skill Name, Description, Experience, Category) are missing.";
        } else {
            // Get domain_id from skill_domains table
            $get_domain_id_stmt = $conn->prepare("SELECT id FROM skill_domains WHERE name = ?");
            $get_domain_id_stmt->bind_param("s", $teach_category);
            $get_domain_id_stmt->execute();
            $get_domain_id_stmt->bind_result($domain_id);
            $get_domain_id_stmt->fetch();
            $get_domain_id_stmt->close();

            if ($domain_id === null) {
                $course_action_message = "Skipping course creation: Selected teaching category '{$teach_category}' not found in skill_domains. Please check category name.";
            } else {
                // Check if a course with this instructor_id, domain_id, and course_name already exists
                $check_course_stmt = $conn->prepare("SELECT course_id FROM course WHERE instructor_id = ? AND domain_id = ? AND course_name = ?"); // Corrected: instructor_id
                $check_course_stmt->bind_param("iis", $user_id, $domain_id, $skills_good_at);
                $check_course_stmt->execute();
                $check_course_stmt->store_result();

                if ($check_course_stmt->num_rows > 0) {
                    // Course exists, update it
                    $check_course_stmt->bind_result($existing_course_id);
                    $check_course_stmt->fetch();
                    $check_course_stmt->close(); // Close before new prepare

                    $update_course_stmt = $conn->prepare("UPDATE course SET
                        instructor_name = ?,
                        course_description = ?,
                        teacher_experience = ?
                    WHERE course_id = ?");
                    // Assuming $current_user_name holds the name from the users table, as fetched earlier
                    // Bind parameters: string, string, string, int
                    $update_course_stmt->bind_param("sssi", $current_user_name, $description, $experience, $existing_course_id);
                    $update_course_stmt->execute();

                    if ($update_course_stmt->affected_rows > 0) {
                        $course_action_message = "Existing course details updated successfully.";
                    } else {
                        $course_action_message = "Course details submitted, but no changes detected for existing course.";
                    }
                    $update_course_stmt->close();
                } else {
                    // Course does not exist, insert new course
                    $check_course_stmt->close(); // Close before new prepare

                    $insert_course_stmt = $conn->prepare("INSERT INTO course (domain_id, instructor_id, instructor_name, course_name, course_description, teacher_experience) VALUES (?, ?, ?, ?, ?, ?)"); // Corrected: instructor_id
                    // Bind parameters: int, int, string, string, string, string
                    $insert_course_stmt->bind_param("iissis", $domain_id, $user_id, $current_user_name, $skills_good_at, $description, $experience);
                    $insert_course_stmt->execute();

                    if ($insert_course_stmt->affected_rows > 0) {
                        $course_action_message = "New course added successfully.";
                    } else {
                        $course_action_message = "Failed to add new course.";
                    }
                    $insert_course_stmt->close();
                }
            }
        }
    } else {
        // If user explicitly selects "No" for "want_to_teach",
        // you might want to remove any existing courses they were teaching.
        // This is a business logic decision.
        $course_action_message = "User does not wish to teach; no course action taken for teaching courses.";
    }

    // --- 6) Final success response ---
    echo json_encode([
        "status"      => true,
        "message"     => "{$user_update_message} {$course_action_message}",
        "user_id"     => $user_id,
        "profile_url" => $profile_path_for_db
    ]);

} catch (mysqli_sql_exception $e) {
    http_response_code(500);
    echo json_encode(["status" => false, "message" => "Database error: " . $e->getMessage()]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(["status" => false, "message" => "Server error: " . $e->getMessage()]);
} finally {
    if (isset($conn)) {
        $conn->close();
    }
}
?>