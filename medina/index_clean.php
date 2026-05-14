<?php
require_once 'config.php';

$bannerImage = BASE_URL . '/assets/img/banner_web.webp';
$bannerImageMobile = BASE_URL . '/assets/img/banner_web_potrait.webp';

$galleryImage = [(BASE_URL . "/assets/img/landscape.jpeg"),
                (BASE_URL . "/assets/img/landscape.jpeg"),
                (BASE_URL . "/assets/img/landscape.jpeg"),
                (BASE_URL . "/assets/img/landscape.jpeg"),
                (BASE_URL . "/assets/img/landscape.jpeg"),
                (BASE_URL . "/assets/img/landscape.jpeg"),
                (BASE_URL . "/assets/img/landscape.jpeg"),
                (BASE_URL . "/assets/img/landscape.jpeg"),
                (BASE_URL . "/assets/img/landscape.jpeg")];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <title>Medina Insan Qur'ani</title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/animate.min.css">
    <style>
        :root {
            --logo-color: #09a0a2;
            --logo-color-2: #ff6400;
            --primary-color: #3498db;
            --secondary-color: #2ecc71;
            --background-color: #ecf0f1;
            --text-color: #2c3e50;
        }

        * {
            font-family: system-ui, sans-serif;
            font-weight: 600;
            box-sizing: border-box;
        }

        body {
            margin: 0;
            background-color: #fff;

        }

        .content{
            min-height: 100vh;
            margin-top: 90px;
        }

        .title-body{
            display: flex;
            flex-direction: column;
            gap: 8px;
            text-align: center;
            margin-bottom: 30px;
            margin-top: 70px;
        }

        .button-title{
            background-color: var(--logo-color);
            color: white;
            padding: 8px 16px;
            border: none;
            border-radius: 25px;
            font-size: 14px;
            cursor: default;
            margin: auto;
            transition-duration: 0.3s;
            text-decoration: none;
        }

        .button-title:hover{
            transform: scale(1.2);
        }

        .title{
            text-align: center;
            margin: auto;
            color: var(--logo-color);
            transition-duration: 0.3s;
        }

        .title:hover{
            transform: scale(1.2);
        }

        .subtitle{
            display: flex;
            text-align: center;
            margin: auto;
            color: var(--logo-color);
            transition-duration: 0.3s;
        }

        .subtitle:hover{
            transform: scale(1.2);
        }

        .carousel-container{
            margin: 0 100px;
        }

        .carousel-wrapper {
            position: relative;
            width: 100%;
            overflow: hidden;
        }

        .carousel-track {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 20px;
            transition: transform 0.6s ease;
            padding: 0 100px;
        }

        .carousel-item {
            flex: 0 0 350px;
            transition: all 0.5s ease;
            opacity: 0.5;
            transform: scale(0.85);
        }

        .carousel-item img {
            width: 100%;
            border-radius: 12px;
            box-shadow: 0 6px 14px rgba(0,0,0,.2);
        }

        /* item tengah */
        .carousel-item.active {
            opacity: 1;
            transform: scale(1);
            z-index: 2;
        }

        /* tombol */
        .carousel-btn {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            background: rgba(0,0,0,0.6);
            color: white;
            border: none;
            width: 45px;
            height: 45px;
            border-radius: 50%;
            cursor: pointer;
            font-size: 26px;
            z-index: 5;
            transition: background 0.3s;
        }

        .carousel-btn:hover {
            background: rgba(0,0,0,0.85);
        }

        .carousel-btn.left {
            left: 15px;
        }

        .carousel-btn.right {
            right: 15px;
        }

        .carousel-review-container{
            margin: 0 100px;
            position: relative;
        }

        .carousel-review{
            position: relative;
            display: flex;
            align-items: center;
        }

        .carousel-review-viewport{
            overflow: hidden;
            width: 100%;
            padding: 20px 0; /* FIX height kepotong */
        }

        .review-track{
            display: flex;
            gap: 20px;
            align-items: stretch; /* FIX */
        }


        .review-item{
            flex: 0 0 calc((100% - 40px) / 3); /* 3 item */
            padding: 20px;
            /* box-shadow: 0 6px 14px rgba(0,0,0,.2); */
            outline: 1px solid var(--logo-color);
            border-radius: 12px;
            background-color: white;
        }

        .review-item p{
            font-weight: lighter;
        }

        /* BUTTON */
        .carousel-review-btn{
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            border: none;
            border-radius: 50%;
            cursor: pointer;
            font-size: 20px;
            z-index: 5;
            background: white;
            box-shadow: 0 4px 10px rgba(0,0,0,.2);
        }

        .carousel-review-btn.left{
            left: -25px;
        }
        .carousel-review-btn.right{
            right: -25px;
        }

        /* REVIEW CONTENT */
        .review-item-header{
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }

        .header-profile{
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .header-profile img{
            width: 50px;
            height: 50px;
            border-radius: 50%;
            object-fit: cover;
        }

        .name-rating p{
            margin: 0;
            font-weight: bold;
            color: var(--logo-color);
        }

        .name-rating .rating{
            font-size: small;
            color: gold;
        }

        .social-media img{
            width: 25px;
            height: 25px;
            filter: invert(0.5);
        }

        .simple-carousel-container{
            margin: 50px 100px;
            position: relative;
        }

        .simple-carousel{
            display: flex;
            align-items: center;
            position: relative;
        }

        .simple-carousel-viewport{
            overflow: hidden;
            width: 100%;
        }

        .simple-carousel-track{
            display: flex;
            width: 100%;
        }

        .simple-carousel-item{
            flex: 0 0 100%; /* satu item full layar */
        }

        .simple-carousel-item img{
            width: 100%;
            height: auto;
            object-fit: cover;
            border-radius: 12px;
        }

        .simple-carousel-btn{
            position: absolute;
            top: 50%;
            transform: translateY(-50%);