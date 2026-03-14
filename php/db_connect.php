<?php
// CONFIGURATION - EDIT THESE LINES
$serverName = ""; // Check SSMS for your actual server name
$connectionOptions = array(
    "Database" => "", // CHANGE THIS to your specific DB name
    "Uid" => "",          // Your SQL Login
    "Pwd" => "",          // Your SQL Password
    "TrustServerCertificate" => true 
    // "LoginTimeout" => 30
); //Use an .env file or similar for better security in production!

// ESTABLISH CONNECTION
$conn = sqlsrv_connect($serverName, $connectionOptions);

if ($conn === false) {
    die(print_r(sqlsrv_errors(), true));
}

// Start session for login persistence
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>