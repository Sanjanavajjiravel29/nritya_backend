<?php
// config/auth_middleware.php - Token Authentication & Role Authorization Middleware

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/helpers.php';

/**
 * Generate a cryptographically secure random authentication token
 * and store only its SHA-256 hash in the database with an expiration date.
 */
function generateAuthToken($conn, $userId, $expiryDays = 30) {
    // 32 cryptographically secure random bytes = 64 character hex string
    $rawToken = bin2hex(random_bytes(32));
    $tokenHash = hash('sha256', $rawToken);
    $expiresAt = date('Y-m-d H:i:s', strtotime("+$expiryDays days"));

    $stmt = $conn->prepare("INSERT INTO auth_tokens (user_id, token_hash, expires_at) VALUES (?, ?, ?)");
    if (!$stmt) {
        respond(false, "Failed to initialize auth token statement: " . $conn->error, 500);
    }
    $stmt->bind_param("iss", $userId, $tokenHash, $expiresAt);
    $stmt->execute();
    $stmt->close();

    return [
        "token" => $rawToken,
        "expires_at" => $expiresAt
    ];
}

/**
 * Extract Bearer token from incoming HTTP headers
 */
function getBearerToken() {
    $header = null;

    if (!empty($_SERVER['HTTP_AUTHORIZATION'])) {
        $header = $_SERVER['HTTP_AUTHORIZATION'];
    } elseif (!empty($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
        $header = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
    } elseif (function_exists('apache_request_headers')) {
        $headers = apache_request_headers();
        if (!empty($headers['Authorization'])) {
            $header = $headers['Authorization'];
        } elseif (!empty($headers['authorization'])) {
            $header = $headers['authorization'];
        }
    } elseif (function_exists('getallheaders')) {
        $headers = getallheaders();
        if (!empty($headers['Authorization'])) {
            $header = $headers['Authorization'];
        } elseif (!empty($headers['authorization'])) {
            $header = $headers['authorization'];
        }
    }

    if (!$header) {
        return null;
    }

    if (preg_match('/^Bearer\s+(.+)$/i', trim($header), $matches)) {
        return trim($matches[1]);
    }

    return null;
}

/**
 * Authenticate incoming request using the Bearer token.
 * Returns the authenticated user record on success; terminates with 401/403 on failure.
 */
function authenticate() {
    global $conn;

    $rawToken = getBearerToken();
    if (empty($rawToken)) {
        respond(false, "Authorization token is missing. Please provide a valid Bearer token.", 401);
    }

    $tokenHash = hash('sha256', $rawToken);

    $stmt = $conn->prepare(
        "SELECT t.id AS token_id, t.expires_at,
                u.id, u.full_name, u.username, u.mobile, u.email, u.role,
                u.staff_role, u.monthly_salary, u.batch_id, u.bio, u.status
         FROM auth_tokens t
         JOIN users u ON t.user_id = u.id
         WHERE t.token_hash = ?
         LIMIT 1"
    );

    if (!$stmt) {
        respond(false, "Authentication query error: " . $conn->error, 500);
    }

    $stmt->bind_param("s", $tokenHash);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$user) {
        respond(false, "Invalid authentication token. Please log in again.", 401);
    }

    // Check expiry
    if (strtotime($user["expires_at"]) < time()) {
        // Prune expired token
        $delStmt = $conn->prepare("DELETE FROM auth_tokens WHERE id = ?");
        $delStmt->bind_param("i", $user["token_id"]);
        $delStmt->execute();
        $delStmt->close();

        respond(false, "Authentication token has expired. Please log in again.", 401);
    }

    // Check user active status
    if ($user["status"] === "inactive") {
        respond(false, "This account is inactive. Please contact your academy administrator.", 403);
    }

    return $user;
}

/**
 * Enforce that the authenticated user possesses one of the allowed roles.
 * @param string|array $allowedRoles
 * @return array Authenticated user
 */
function requireRole($allowedRoles) {
    $user = authenticate();
    $roles = (array)$allowedRoles;

    if (!in_array($user["role"], $roles, true)) {
        respond(false, "Access denied. Role '{$user["role"]}' is not authorized to perform this operation.", 403);
    }

    return $user;
}

/**
 * Revoke/delete an active token (used on logout).
 */
function revokeToken($conn, $rawToken) {
    if (empty($rawToken)) {
        return false;
    }
    $tokenHash = hash('sha256', $rawToken);
    $stmt = $conn->prepare("DELETE FROM auth_tokens WHERE token_hash = ?");
    $stmt->bind_param("s", $tokenHash);
    $stmt->execute();
    $affected = $stmt->affected_rows;
    $stmt->close();
    return $affected > 0;
}

/**
 * Revoke all active tokens for a specific user.
 */
function revokeAllUserTokens($conn, $userId) {
    $stmt = $conn->prepare("DELETE FROM auth_tokens WHERE user_id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $stmt->close();
    return true;
}

/**
 * Check whether a batch is assigned to an instructor
 */
function isBatchAssignedToInstructor($conn, $batchId, $instructorId) {
    if (!$batchId || !$instructorId) return false;
    $stmt = $conn->prepare("SELECT id FROM batches WHERE id = ? AND instructor_id = ? LIMIT 1");
    $stmt->bind_param("ii", $batchId, $instructorId);
    $stmt->execute();
    $found = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return (bool)$found;
}

/**
 * Check whether a student is actively enrolled in a batch
 */
function isStudentEnrolledInBatch($conn, $batchId, $studentId) {
    if (!$batchId || !$studentId) return false;
    $stmt = $conn->prepare("SELECT id FROM batch_students WHERE batch_id = ? AND student_id = ? AND status = 'Active' LIMIT 1");
    $stmt->bind_param("ii", $batchId, $studentId);
    $stmt->execute();
    $found = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return (bool)$found;
}

/**
 * Verify that the caller has access to the specified batch.
 * - Admin: Allowed for all batches.
 * - Instructor: Allowed ONLY if the batch is assigned to them (HTTP 403 otherwise).
 * - Student: Allowed ONLY if enrolled in the batch (HTTP 403 otherwise).
 */
function requireBatchOwnership($conn, $batchId, $authUser) {
    if (!$batchId || $batchId < 1) {
        respond(false, "A valid batch ID is required.", 400);
    }

    if ($authUser["role"] === "admin") {
        return true;
    }

    if ($authUser["role"] === "instructor") {
        if (!isBatchAssignedToInstructor($conn, $batchId, $authUser["id"])) {
            respond(false, "Access denied. You are not assigned to instruct this batch.", 403);
        }
        return true;
    }

    if ($authUser["role"] === "student") {
        if (!isStudentEnrolledInBatch($conn, $batchId, $authUser["id"])) {
            respond(false, "Access denied. You are not enrolled in this batch.", 403);
        }
        return true;
    }

    respond(false, "Access denied. Unauthorized role.", 403);
}

/**
 * Returns list of batch IDs assigned to an instructor.
 */
function getInstructorBatchIds($conn, $instructorId) {
    $stmt = $conn->prepare("SELECT id FROM batches WHERE instructor_id = ?");
    $stmt->bind_param("i", $instructorId);
    $stmt->execute();
    $res = $stmt->get_result();
    $ids = [];
    while ($row = $res->fetch_assoc()) {
        $ids[] = (int)$row["id"];
    }
    $stmt->close();
    return $ids;
}

/**
 * Returns list of batch IDs a student is enrolled in.
 */
function getStudentBatchIds($conn, $studentId) {
    $stmt = $conn->prepare("SELECT batch_id FROM batch_students WHERE student_id = ? AND status = 'Active'");
    $stmt->bind_param("i", $studentId);
    $stmt->execute();
    $res = $stmt->get_result();
    $ids = [];
    while ($row = $res->fetch_assoc()) {
        $ids[] = (int)$row["batch_id"];
    }
    $stmt->close();
    return $ids;
}
?>
