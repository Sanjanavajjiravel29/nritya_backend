<?php
// profile/add_profile.php - Legacy unauthenticated profile creation is disabled

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/helpers.php';

// Disable legacy unauthenticated profile updates per academy security policy
respond(
    false, 
    "Legacy profile creation endpoint is disabled. Use authenticated profile/edit_profile.php instead.", 
    403
);
?>
