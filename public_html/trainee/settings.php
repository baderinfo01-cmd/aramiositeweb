<?php
require_once 'trainee_init.php';
require_once '../includes/profile.php';
profile_handle();
$dashTitle = d('إعداداتي', 'Settings');
require '../includes/partials/dash_header.php';
profile_render(false);
require '../includes/partials/dash_footer.php';
