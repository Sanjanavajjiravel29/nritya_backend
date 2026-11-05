<?php
// users/create_user.php - Admin-only user account creation

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/auth_middleware.php';

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    respond(false, "Only POST requests are allowed.", 405);
}

// Strictly restricted to authenticated Admin accounts only
$adminUser = requireRole('admin');
$creatorId = (int)$adminUser["id"];

$data = getRequestData();

$fullName = trim($data["full_name"] ?? "");
$email = strtolower(trim($data["email"] ?? ""));
$username = trim($data["username"] ?? "");
$mobile = trim($data["mobile"] ?? "");
$password = $data["password"] ?? "";
$role = strtolower(trim($data["role"] ?? "student"));
$batchId = !empty($data["batch_id"]) ? (int)$data["batch_id"] : null;
$batchName = trim($data["batch_name"] ?? "");
$staffRole = trim($data["staff_role"] ?? "");
$monthlySalary = trim($data["monthly_salary"] ?? "");
$bio = trim($data["bio"] ?? "");

// Explicitly validate role
$allowedRoles = ["student", "instructor", "admin"];
if (!in_array($role, $allowedRoles, true)) {
    respond(false, "Invalid role specified: '$role'. Allowed roles: " . implode(", ", $allowedRoles), 400);
}

if ($fullName === "" || $password === "") {
    respond(false, "Full name and password are required.", 400);
}

// Auto-generate email if missing
if ($email === "") {
    $cleanName = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $fullName));
    $email = $cleanName . random_int(100, 999) . "@nritahub.com";
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    respond(false, "Enter a valid email address.", 400);
}

// Fallback mobile
if ($mobile === "") {
    $mobile = "9" . random_int(100000000, 999999999);
}

// Check email uniqueness
$stmt = $conn->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
$stmt->bind_param("s", $email);
$stmt->execute();
if ($stmt->get_result()->fetch_assoc()) {
    $stmt->close();
    respond(false, "Email address already exists in the academy records.", 409);
}
$stmt->close();

// Resolve username
if ($username === "") {
    $username = generateUsername($conn, $fullName);
} else {
    $stmt = $conn->prepare("SELECT id FROM users WHERE username = ? LIMIT 1");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    if ($stmt->get_result()->fetch_assoc()) {
        $stmt->close();
        respond(false, "Username is already taken.", 409);
    }
    $stmt->close();
}

// If batchName is provided without batchId, search for matching batch
if ($batchId === null && $batchName !== "") {
    $batchStmt = $conn->prepare("SELECT id FROM batches WHERE batch_name LIKE ? LIMIT 1");
    $likeBatch = "%" . $batchName . "%";
    $batchStmt->bind_param("s", $likeBatch);
    $batchStmt->execute();
    $matched = $batchStmt->get_result()->fetch_assoc();
    $batchStmt->close();
    if ($matched) {
        $batchId = (int)$matched["id"];
    }
}

$hashedPassword = password_hash($password, PASSWORD_DEFAULT);

$stmt = $conn->prepare(
    "INSERT INTO users (full_name, username, mobile, email, password, role, staff_role, monthly_salary, batch_id, bio, created_by, status)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active')"
);

if (!$stmt) {
    respond(false, "Database error: " . $conn->error, 500);
}

$stmt->bind_param("ssssssssisi", $fullName, $username, $mobile, $email, $hashedPassword, $role, $staffRole, $monthlySalary, $batchId, $bio, $creatorId);

if ($stmt->execute()) {
    $newUserId = $conn->insert_id;
    $stmt->close();

    // If student and batch assigned, record in batch_students
    if ($role === "student" && $batchId !== null) {
        $enroll = $conn->prepare("INSERT IGNORE INTO batch_students (batch_id, student_id, status) VALUES (?, ?, 'Active')");
        if ($enroll) {
            $enroll->bind_param("ii", $batchId, $newUserId);
            $enroll->execute();
            $enroll->close();
        }
    }

    // If staff/instructor salary record should be initiated
    if ($role === "instructor" && !empty($monthlySalary)) {
        $feeStmt = $conn->prepare("INSERT INTO fees_records (record_type, user_id, batch_id, title, amount, status) VALUES ('staff_salary', ?, ?, 'Monthly Instructor Salary', ?, 'Calculated')");
        if ($feeStmt) {
            $feeStmt->bind_param("iis", $newUserId, $batchId, $monthlySalary);
            $feeStmt->execute();
            $feeStmt->close();
        }
    }

    respond(true, "User account created successfully.", 201, [
        "user" => [
            "id" => $newUserId,
            "full_name" => $fullName,
            "username" => $username,
            "email" => $email,
            "mobile" => $mobile,
            "role" => $role,
            "staff_role" => $staffRole,
            "monthly_salary" => $monthlySalary,
            "batch_id" => $batchId,
            "batch_name" => $batchName,
            "created_by" => $creatorId,
            "initials" => strtoupper(substr($fullName, 0, 1) . (strpos($fullName, ' ') !== false ? substr($fullName, strpos($fullName, ' ') + 1, 1) : '')),
            "status" => "active"
        ]
    ]);
}

$err = $stmt->error;
$stmt->close();
respond(false, "Could not create user account: " . $err, 500);
?>
