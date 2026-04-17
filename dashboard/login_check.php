<?php
    session_start();
    if(isset($_SESSION['user_id'])){
        // User is logged in, allow access
    } else {
        // User is not logged in, redirect to login page
        header("Location: ../index.php");
        exit();
    }
?>