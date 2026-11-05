<?php
// auth/logout.php - Revoke authenticated session token

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/auth_middleware.php';

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    respond(false, "Only POST requests are allowed.", 405);
}

$token = getBearerToken();
if (empty($token)) {
    respond(false, "Authorization token is missing.", 400);
}

$revoked = revokeToken($conn, $token);

respond(true, "Successfully logged out. Session has been revoked.", 200, [
    "revoked" => $revoked
]);
?>

