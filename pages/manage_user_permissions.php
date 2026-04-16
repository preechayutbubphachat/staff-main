<?php
require_once __DIR__ . '/../includes/auth.php';
app_require_permission('can_manage_user_permissions');
header('Location: manage_users.php');
exit;
