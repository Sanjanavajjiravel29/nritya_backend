<?php
// auth/signup.php - Public registration is disabled

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/helpers.php';

// Public registration is strictly disabled per academy security policy
respond(
    false, 
    "Public registration is disabled. Student and instructor accounts must be created directly by an Academy Administrator.", 
    403
);
?>
