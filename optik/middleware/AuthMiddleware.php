<?php

class AuthMiddleware{
    public static function authCheck(){
        require_once __DIR__ . '/../config/session_start.php';

        if (empty($_SESSION['user_logged_in_optik'])){
            header('Location: /login');
            exit();
        }
    }
}
