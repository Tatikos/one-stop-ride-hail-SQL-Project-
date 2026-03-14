<?php
include 'db_connect.php';
header('Content-Type: application/json');

if(isset($_POST['action'])) {
    $act = $_POST['action'];
    
    // 1. PASSENGER CONFIRMATION
    if($act == 'passenger_confirm' && isset($_POST['ride_id'])) {
        $rideID = $_POST['ride_id'];
        
        $sql = "{call sp_ConfirmPickup_Passenger(?)}";
        $stmt = sqlsrv_query($conn, $sql, array($rideID));
        
        if($stmt === false) {
            echo json_encode(['status'=>'error', 'msg'=>print_r(sqlsrv_errors(), true)]);
        } else {
            echo json_encode(['status'=>'ok', 'mode'=>'passenger']);
        }
    }
    
    // 2. DRIVER CONFIRMATION
    elseif($act == 'driver_confirm' && isset($_POST['segment_id'])) {
        $segID = $_POST['segment_id'];
        
        $sql = "{call sp_ConfirmPickup_Driver(?)}";
        $stmt = sqlsrv_query($conn, $sql, array($segID));
        
        if($stmt === false) {
            echo json_encode(['status'=>'error', 'msg'=>print_r(sqlsrv_errors(), true)]);
        } else {
            echo json_encode(['status'=>'ok', 'mode'=>'driver']);
        }
    }
    else {
        echo json_encode(['status'=>'error', 'msg'=>'Missing parameters']);
    }
}
?>