<?php
// helpers.php - Shared API Utilities, CORS, and Response Formatter

// CORS headers
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    http_response_code(200);
    exit;
}

header("Content-Type: application/json; charset=UTF-8");

/**
 * Return JSON response and terminate script
 */
function respond($success, $message, $statusCode = 200, $extra = []) {
    global $conn;

    http_response_code($statusCode);

    $response = array_merge([
        "success" => (bool)$success,
        "message" => (string)$message
    ], $extra);

    echo json_encode($response, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    if (isset($conn) && $conn instanceof mysqli && $conn->ping()) {
        $conn->close();
    }

    exit;
}

/**
 * Get input data whether sent via application/json, form-data, or url-encoded
 */
function getRequestData() {
    $data = $_POST;

    // Check raw input for JSON body
    $raw = file_get_contents("php://input");
    if (!empty($raw)) {
        $json = json_decode($raw, true);
        if (is_array($json)) {
            $data = array_merge($data, $json);
        }
    }

    // Merge GET query params if POST data is empty (useful for flexible endpoints)
    if (empty($data) && !empty($_GET)) {
        $data = $_GET;
    }

    return $data;
}

/**
 * Get Base URL of the backend server
 */
function getBaseUrl() {
    $protocol = "http://";
    if ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || 
        (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https')) {
        $protocol = "https://";
    }
    $host = $_SERVER['HTTP_X_FORWARDED_HOST'] ?? ($_SERVER['HTTP_HOST'] ?? 'localhost');
    return $protocol . $host . "/nrityahub_backend/";
}

/**
 * Format a media/file path into a complete accessible URL
 */
function formatMediaUrl($path) {
    if (empty($path)) {
        return null;
    }
    if (preg_match('/^https?:\/\//i', $path)) {
        return $path;
    }
    return getBaseUrl() . ltrim($path, '/');
}

/**
 * Generate a clean unique username from a full name
 */
function generateUsername($conn, $fullName) {
    $base = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $fullName));
    if (empty($base)) {
        $base = "user";
    }

    for ($i = 0; $i < 10; $i++) {
        $candidate = $base . random_int(100, 9999);
        $stmt = $conn->prepare("SELECT id FROM users WHERE username = ? LIMIT 1");
        $stmt->bind_param("s", $candidate);
        $stmt->execute();
        $isTaken = $stmt->get_result()->num_rows > 0;
        $stmt->close();

        if (!$isTaken) {
            return $candidate;
        }
    }

    return $base . time();
}

/**
 * Asynchronously triggers local AI movement analysis microservice
 */
function triggerAiMovementAnalysis($submissionId, $referenceId, $studentVideoRelPath, $refVideoRelPath, $category = "Tatta Adavu") {
    $backendDir = realpath(__DIR__ . '/..');
    $studentAbs = $backendDir . '/' . ltrim($studentVideoRelPath, '/');
    $refAbs = $backendDir . '/' . ltrim($refVideoRelPath, '/');

    $payload = json_encode([
        "submission_id" => (int)$submissionId,
        "reference_id" => (int)$referenceId,
        "student_video_path" => str_replace('\\', '/', $studentAbs),
        "reference_video_path" => str_replace('\\', '/', $refAbs),
        "category" => $category
    ]);

    $ch = curl_init("http://127.0.0.1:8000/analyze");
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Content-Length: ' . strlen($payload)
    ]);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT_MS, 800);
    curl_setopt($ch, CURLOPT_TIMEOUT_MS, 1500);

    $res = curl_exec($ch);
    curl_close($ch);

    return !empty($res);
}
?>
