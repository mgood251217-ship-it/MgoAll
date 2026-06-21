<?php
session_start();

$path   = '/';
$domain = '';
$secure = true;
$httponly = true;

$cookies = [
    'admin_administrator_id',
    'admin_username',
    'admin_access'
];

foreach ($cookies as $cookie) {
    if (isset($_COOKIE[$cookie])) {
        setcookie(
            $cookie,
            '',
            time() - 3600,
            $path,
            $domain,
            $secure,
            $httponly
        );
    }
}

session_unset();
session_destroy();

header("Location: login");
exit;
