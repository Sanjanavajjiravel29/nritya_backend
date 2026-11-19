<?php
// references/stream_reference.php - Authenticated, byte-range streaming proxy for reference videos

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/auth_middleware.php';

if ($_SERVER["REQUEST_METHOD"] !== "GET" && $_SERVER["REQUEST_METHOD"] !== "HEAD") {
    respond(false, "Only GET and HEAD requests are allowed for media streaming.", 405);
}

// Authenticate caller
$authUser = authenticate();

$id = isset($_GET["id"]) ? (int)$_GET["id"] : 0;
if ($id <= 0) {
    respond(false, "A valid reference video ID is required.", 400);
}

$stmt = $conn->prepare("SELECT * FROM reference_videos WHERE id = ? LIMIT 1");
$stmt->bind_param("i", $id);
$stmt->execute();
$ref = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$ref) {
    respond(false, "Reference video not found.", 404);
}

// Access Control
if ($ref["reference_type"] === "INSTRUCTOR_BATCH") {
    if ($authUser["role"] === "instructor") {
        if (!isBatchAssignedToInstructor($conn, $ref["batch_id"], $authUser["id"])) {
            respond(false, "Access denied. You can only stream reference videos for batches you teach.", 403);
        }
    } elseif ($authUser["role"] === "student") {
        if (!isStudentEnrolledInBatch($conn, $ref["batch_id"], $authUser["id"])) {
            respond(false, "Access denied. You do not belong to the batch associated with this reference video.", 403);
        }
    }
}

if ($authUser["role"] === "student" && $ref["status"] !== "Active") {
    respond(false, "Reference video is currently inactive.", 403);
}

// Resolve physical file
$cleanPath = ltrim($ref["video_path"], '/');
if (strpos($cleanPath, '..') !== false) {
    respond(false, "Access denied. Path traversal detected.", 403);
}

$filePath = __DIR__ . '/../' . $cleanPath;
if (!file_exists($filePath) || !is_file($filePath)) {
    respond(false, "Physical reference video file not found on server storage.", 404);
}

$fileSize = filesize($filePath);
$mimeType = $ref["mime_type"] ?: "video/mp4";

// Clear any buffered output
while (ob_get_level()) {
    ob_end_clean();
}

$fp = @fopen($filePath, 'rb');
if (!$fp) {
    respond(false, "Unable to open video file for streaming.", 500);
}

$start = 0;
$end = $fileSize - 1;

header("Content-Type: " . $mimeType);
header("Accept-Ranges: bytes");
header("ngrok-skip-browser-warning: true");

if (isset($_SERVER['HTTP_RANGE'])) {
    $range = $_SERVER['HTTP_RANGE'];
    if (preg_match('/bytes=\h*(\d+)-(\d*)[\D.*]?/i', $range, $matches)) {
        $start = intval($matches[1]);
        if (!empty($matches[2])) {
            $end = intval($matches[2]);
        }
    }

    if ($start > $end || $start >= $fileSize) {
        header("HTTP/1.1 416 Requested Range Not Satisfiable");
        header("Content-Range: bytes */" . $fileSize);
        fclose($fp);
        exit;
    }

    header("HTTP/1.1 206 Partial Content");
    header("Content-Range: bytes $start-$end/" . $fileSize);
    header("Content-Length: " . ($end - $start + 1));
} else {
    header("HTTP/1.1 200 OK");
    header("Content-Length: " . $fileSize);
}

if ($_SERVER["REQUEST_METHOD"] === "HEAD") {
    fclose($fp);
    exit;
}

fseek($fp, $start);
$bufferSize = 65536; // 64 KB buffers
$bytesRemaining = $end - $start + 1;

while (!feof($fp) && $bytesRemaining > 0 && !connection_aborted()) {
    $bytesToRead = min($bufferSize, $bytesRemaining);
    $data = fread($fp, $bytesToRead);
    echo $data;
    flush();
    $bytesRemaining -= strlen($data);
}

fclose($fp);
exit;
?>
