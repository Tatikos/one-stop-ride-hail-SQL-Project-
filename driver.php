<?php include 'db_connect.php'; 
// Ensure only Drivers can access
if(!isset($_SESSION['UserID'])) header("Location: index.php");

// Handle Accept
if(isset($_POST['accept_ride'])) {
    $tsql = "{call sp_Driver_AcceptRide(?, ?)}";
    $params = array($_POST['ride_id'], $_SESSION['UserID']);
    $stmt = sqlsrv_query($conn, $tsql, $params);
    if($stmt === false) echo "Error accepting: " . print_r(sqlsrv_errors(), true);
}

// Handle Complete
if(isset($_POST['complete_ride'])) {
    $tsql = "{call sp_Driver_CompleteRide(?)}";
    $params = array($_POST['ride_id']);
    $stmt = sqlsrv_query($conn, $tsql, $params);
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>OSRH - Driver Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body { background: #333; color: white; }
        .ride-card { background: #444; border-radius: 10px; padding: 15px; margin-bottom: 15px; border-left: 5px solid #28a745; }
        .btn-accept { width: 100%; font-weight: bold; padding: 10px; }
        .stat-box { background: #222; padding: 15px; border-radius: 8px; text-align: center; }
    </style>
</head>
<body>
<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="fas fa-taxi"></i> Driver Dashboard</h2>
        <a href="index.php" class="btn btn-outline-light btn-sm">Back to Map</a>
    </div>

    <?php
    // Get current driver ID
    $driverSql = "SELECT DriverID FROM Driver_Profile WHERE UserID = ?";
    $dStmt = sqlsrv_query($conn, $driverSql, array($_SESSION['UserID']));
    $dRow = sqlsrv_fetch_array($dStmt);
    
    if($dRow) {
        $driverID = $dRow['DriverID'];
        $activeSql = "SELECT * FROM Ride WHERE Driver_ID = ? AND Status = 'Accepted'";
        $aStmt = sqlsrv_query($conn, $activeSql, array($driverID));
        
        if($aRow = sqlsrv_fetch_array($aStmt)) {
            echo '
            <div class="alert alert-warning">
                <h4><i class="fas fa-clock"></i> Ride in Progress</h4>
                <p>Current Trip ID: #'.$aRow['Ride_id'].'</p>
                <form method="POST">
                    <input type="hidden" name="ride_id" value="'.$aRow['Ride_id'].'">
                    <button type="submit" name="complete_ride" class="btn btn-success btn-lg w-100">Complete Ride & Collect €'.number_format($aRow['EstimatedCost'],2).'</button>
                </form>
            </div>';
        }
    }
    ?>

    <h4>Available Requests</h4>
    <div class="row">
        <?php
        $sql = "{call sp_Driver_GetAvailableRides}";
        $stmt = sqlsrv_query($conn, $sql);
        $hasRides = false;

        while($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $hasRides = true;
            echo '
            <div class="col-md-6">
                <div class="ride-card">
                    <div class="d-flex justify-content-between">
                        <h5>'.$row['PassengerName'].'</h5>
                        <span class="badge bg-info text-dark">'.$row['ServiceType'].'</span>
                    </div>
                    <p class="mb-1"><small>Distance:</small> <b>'.$row['EstimatedDistance_km'].' km</b></p>
                    <p class="mb-2"><small>Earnings:</small> <b class="text-success">€'.number_format($row['EstimatedCost'] * 0.9, 2).'</b></p>
                    
                    <form method="POST">
                        <input type="hidden" name="ride_id" value="'.$row['Ride_id'].'">
                        <button type="submit" name="accept_ride" class="btn btn-light btn-accept">Accept Ride</button>
                    </form>
                </div>
            </div>';
        }
        if(!$hasRides) echo "<p class='text-muted'>No pending requests in your area.</p>";
        ?>
    </div>
</div>
</body>
</html>