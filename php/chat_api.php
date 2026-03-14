<?php
include 'db_connect.php';

// 1. SEND MESSAGE
if(isset($_POST['action']) && $_POST['action'] == 'send') {
    $rideID = $_POST['ride_id'];
    $userID = $_SESSION['UserID'];
    $text   = $_POST['text'];
    
    $sql = "{call sp_Chat_SendMessage(?, ?, ?)}";
    sqlsrv_query($conn, $sql, array($rideID, $userID, $text));
    exit();
}

// 2. GET MESSAGES
if(isset($_GET['ride_id'])) {
    $rideID = $_GET['ride_id'];
    
    $sql = "{call sp_Chat_GetMessages(?)}";
    $stmt = sqlsrv_query($conn, $sql, array($rideID));
    
    $msgs = [];
    if($stmt) {
        while($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $isMe = ($r['Firstname'] == $_SESSION['Name']) ? true : false;
            
            $msgs[] = [
                'user' => $r['Firstname'],
                'text' => $r['MessageText'],
                'time' => $r['SentAt']->format('H:i'),
                'isMe' => $isMe
            ];
        }
    }
    echo json_encode($msgs);
    exit();
}
?>