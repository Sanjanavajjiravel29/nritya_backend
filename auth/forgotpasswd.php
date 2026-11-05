<?php
// auth/forgotpasswd.php - Public self-service password recovery is disabled

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/helpers.php';

// Public self-service password recovery is disabled per academy security policy
respond(
    false, 
    "Public password reset is disabled. Please contact your academy administrator to reset your credentials.", 
    403
);
?>
