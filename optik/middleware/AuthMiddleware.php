<?php

class AuthMiddleware{
    public static function authCheck(){
        if (session_status() === PHP_SESSION_NONE){
            session_start();
        }

        if (empty($_SESSION['user_logged_in'])){
            header('Location: /login');
            exit();
        }
    }
}
