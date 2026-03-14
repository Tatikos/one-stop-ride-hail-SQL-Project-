<?php
include 'db_connect.php';

if(!isset($_SESSION['UserID'])) die("Please login first.");

echo "<h3>Debugging & Fixing Your Account...</h3>";

// 1. GET DRIVER ID
$dID = 0;
$stmt = sqlsrv_query($conn, "SELECT DriverID FROM Driver_Profile WHERE UserID = ?", array($_SESSION['UserID']));
if($r = sqlsrv_fetch_array($stmt)) $dID = $r['DriverID'];

if($dID) {
    echo "Found Driver ID: $dID<br>";
    
    // 2. AUTO-FIX STUCK RIDES
    // Finds rides where both parties clicked 'Confirm' (1/1) but Status is still 'Accepted'
    $sql = "UPDATE Ride SET Status = 'InProgress', Start_time = GETDATE() 
            WHERE Driver_ID = ? AND Passenger_Started = 1 AND Driver_Started = 1 AND Status = 'Accepted'";
    $stmt = sqlsrv_query($conn, $sql, array($dID));
    if($stmt) {
        $rows = sqlsrv_rows_affected($stmt);
        if($rows > 0) echo "<b>FIXED:</b> Force-started $rows stuck rides that had both confirmations.<br>";
    }

    // 3. DETECT MULTIPLE ACTIVE RIDES (The cause of flickering)
    $sql = "SELECT Ride_id, Status, Passenger_Started, Driver_Started FROM Ride WHERE Driver_ID = ? AND Status IN ('Accepted', 'InProgress')";
    $stmt = sqlsrv_query($conn, $sql, array($dID));
    
    $count = 0;
    while($row = sqlsrv_fetch_array($stmt)) {
        $count++;
        echo "Active Ride #{$row['Ride_id']}: Status=[{$row['Status']}] Flags=[P:{$row['Passenger_Started']} / D:{$row['Driver_Started']}] <br>";
    }
    
    if($count > 1) {
        echo "<br><span style='color:red; font-weight:bold;'>WARNING: You have multiple active rides! This confuses the Dashboard.</span><br>";
        echo "<form method='POST'><button name='clear_all' style='padding:10px; background:red; color:white; border:none; cursor:pointer;'>Force Complete All Active Rides</button></form>";
    } elseif($count == 0) {
        echo "No active rides found for this driver.<br>";
    } else {
        echo "<span style='color:green'>All clear. Single active ride detected.</span>";
    }

} else {
    echo "You are not a driver.<br>";
}

// 4. PASSENGER CHECK
echo "<hr>Checking Passenger Status...<br>";
$sql = "SELECT Ride_id, Status FROM Ride WHERE Passenger_UserID = ? AND Status IN ('Requested', 'Accepted', 'InProgress')";
$stmt = sqlsrv_query($conn, $sql, array($_SESSION['UserID']));
while($row = sqlsrv_fetch_array($stmt)) {
    echo "You are Passenger in Ride #{$row['Ride_id']} ({$row['Status']})<br>";
}

// HANDLE CLEAR ACTION
if(isset($_POST['clear_all'])) {
    // Force complete everything to clean the slate
    sqlsrv_query($conn, "UPDATE Ride SET Status='Completed', End_time=GETDATE() WHERE Driver_ID=? AND Status IN ('Accepted','InProgress')", array($dID));
    echo "<script>window.location.reload();</script>";
}
?>