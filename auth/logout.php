<?php
session_start();

$currentUserId = (int) ($_SESSION['id'] ?? 0);

$_SESSION = [];

if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
}

session_destroy();
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="refresh" content="0;url=login.php">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>ออกจากระบบ</title>
</head>
<body>
<script>
(function () {
    try {
        var userId = <?= json_encode($currentUserId) ?>;
        if (userId && window.localStorage) {
            var prefix = 'staff_main:' + userId + ':';
            Object.keys(window.localStorage).forEach(function (key) {
                if (key.indexOf(prefix) === 0) {
                    window.localStorage.removeItem(key);
                }
            });
        }
    } catch (error) {
        // ignore localStorage cleanup errors
    }

    window.location.replace('login.php');
})();
</script>
</body>
</html>
