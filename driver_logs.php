<?php include 'db_connect.php'; 
if(!isset($_SESSION['UserID'])) header("Location: login.php");

// Fetch Summary Stats
$stats = ['TotalTrips'=>0, 'TotalGross'=>0, 'TotalNet'=>0];
$sql = "{call sp_Driver_GetStats(?)}";
$stmt = sqlsrv_query($conn, $sql, array($_SESSION['UserID']));
if($stmt && $r = sqlsrv_fetch_array($stmt)) {
    $stats = $r;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Earnings</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>body{background:#f8f9fa} .card{border:none;box-shadow:0 2px 10px rgba(0,0,0,0.05)}</style>
</head>
<body>

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="fas fa-wallet me-2"></i> Driver Logs</h2>
        <a href="driver_dashboard.php" class="btn btn-outline-dark">Back to Dashboard</a>
    </div>

    <!-- Summary Cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card p-3 border-start border-4 border-primary">
                <small class="text-muted">Total Trips</small>
                <h3 class="fw-bold"><?= $stats['TotalTrips'] ?></h3>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card p-3 border-start border-4 border-success">
                <small class="text-muted">Net Earnings</small>
                <h3 class="fw-bold text-success">€<?= number_format($stats['TotalNet'], 2) ?></h3>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card p-3 border-start border-4 border-secondary">
                <small class="text-muted">Total Revenue Generated</small>
                <h3 class="fw-bold">€<?= number_format($stats['TotalGross'], 2) ?></h3>
            </div>
        </div>
    </div>

    <!-- Detailed Table -->
    <div class="card">
        <div class="card-header bg-white py-3 fw-bold">Detailed Trip History</div>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Date</th>
                        <th>Ride ID</th>
                        <th>Vehicle</th>
                        <th>Service</th>
                        <th class="text-end">Gross</th>
                        <th class="text-end">Net Pay</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $sql = "{call sp_Driver_GetEarningsLog(?)}";
                    $stmt = sqlsrv_query($conn, $sql, array($_SESSION['UserID']));
                    
                    if($stmt) {
                        while($row = sqlsrv_fetch_array($stmt)) {
                            $date = $row['End_time']->format('d M Y, H:i');
                            echo "<tr>
                                <td>$date</td>
                                <td>#{$row['Ride_id']}</td>
                                <td>{$row['RegistrationPlate']} <span class='text-muted small'>({$row['Model']})</span></td>
                                <td><span class='badge bg-light text-dark border'>{$row['ServiceName']}</span></td>
                                <td class='text-end'>€".number_format($row['RideRevenue'], 2)."</td>
                                <td class='text-end fw-bold text-success'>€".number_format($row['DriverNetEarnings'], 2)."</td>
                            </tr>";
                        }
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

</body>
</html>