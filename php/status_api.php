<?php
include 'db_connect.php';
header('Content-Type: application/json');
header("Cache-Control: no-store");

// Disable PHP errors affecting JSON output
error_reporting(0); 

if(isset($_GET['ride_id'])) {
    // Find the current active leg (First one that isn't Completed)
    $sql = "SELECT TOP 1 Status, Passenger_Started, Driver_Started, SequenceOrder 
            FROM RideSegment WHERE RideID = ? AND Status != 'Completed' 
            ORDER BY SequenceOrder ASC";
    
    $stmt = sqlsrv_query($conn, $sql, array($_GET['ride_id']));
    
    if($stmt && $row = sqlsrv_fetch_array($stmt)) {
        echo json_encode([
            'status' => $row['Status'],
            'p_started' => $row['Passenger_Started'],
            'driver_started' => $row['Driver_Started'],
            'leg' => $row['SequenceOrder']
        ]);
    } else {
        // If no segments found, it might be fully completed
        echo json_encode(['status' => 'Completed']);
    }
}
?>