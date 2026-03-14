<?php 
// book_ride.php
ini_set('display_errors', 1);
error_reporting(E_ALL);
include 'db_connect.php'; 

if(isset($_POST['service_type']) && isset($_SESSION['UserID'])) {
    
    $specificDriver = isset($_POST['driver_id']) && !empty($_POST['driver_id']) ? $_POST['driver_id'] : null;

    $params = array(
        $_SESSION['UserID'], 
        $_POST['service_type'], 
        $_POST['start_lat'], $_POST['start_lon'], 
        $_POST['end_lat'], $_POST['end_lon'], 
        $_POST['distance_km'],
        $specificDriver
    );

    $tsql = "{call sp_BookRide_Geo(?, ?, ?, ?, ?, ?, ?, ?)}";
    $stmt = sqlsrv_query($conn, $tsql, $params);

    if( $stmt === false ) die("SQL Error: " . print_r(sqlsrv_errors(), true));

    $rideID = null;
    do {
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            if (isset($row['RideID'])) {
                $rideID = $row['RideID'];
                break 2; 
            }
        }
    } while (sqlsrv_next_result($stmt));

    if ($rideID) {
        header("Location: track_ride.php?id=" . $rideID);
        exit();
    } else {
        die("Error: No Ride ID returned.");
    }
} else {
    header("Location: index.php");
}
?>