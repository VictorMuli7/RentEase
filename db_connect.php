<?php
if ($_SERVER['HTTP_HOST'] == 'localhost' || $_SERVER['HTTP_HOST'] == '127.0.0.1') {
    // Local XAMPP settings
    $host = "localhost";
    $user = "root";
    $password = "";
    $dbname = "rentease_db";
} else {
    // Live InfinityFree production settings (Your live database details)
    $host = "sqlXXX.infinityfree.com"; 
    $user = "if0_XXXXXXXX";          
    $password = "YOUR_LIVE_PASSWORD"; 
    $dbname = "if0_XXXXXXXX_rentease_db"; 
}

$conn = mysqli_connect($host, $user, $password, $dbname);

if (!$conn) {
    die("Database connection failed: " . mysqli_connect_error());
}
?>