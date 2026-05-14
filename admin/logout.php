<?php
session_start();

$path   = '/';
$domain = '';
$secure = true;
$httponly = true;

$cookies = [
    'user_user_id',
    'user_username',
    'user_name',
    'user_initial',
    'user_store_id',
    'user_role'
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
