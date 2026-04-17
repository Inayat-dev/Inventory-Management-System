<?php
    
    $host = "localhost";
    $username = "root";
    $password = "";
    $db = "rifat_db";

    $conn = mysqli_connect($host,$username,$password,$db);
    if($conn){
        // echo "Database connected successfully";
    } else {
        die("Database connection failed: " . mysqli_connect_error());
    }

?>