<?php include 'db_connect.php'; 
if(!isset($_SESSION['UserID'])) header("Location: login.php");
?>
<!DOCTYPE html>
<html>
<head><title>Driver History</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet"></head>
<body class="bg-light p-4">
<div class="container">
    <h3>Your Drive History</h3>
    <table class="table table-white shadow-sm">
        <thead><tr><th>Date</th><th>Service</th><th>Route</th><th>Earnings</th></tr></thead>
        <tbody>
            <?php
            $sql = "{call sp_Driver_GetHistory(?)}";
            $stmt = sqlsrv_query($conn, $sql, array($_SESSION['UserID']));
            while($r = sqlsrv_fetch_array($stmt)) {
                echo "<tr>
                    <td>{$r['End_time']->format('Y-m-d H:i')}</td>
                    <td>{$r['ServiceType']}</td>
                    <td>Start -> End</td>
                    <td class='fw-bold text-success'>€".number_format($r['Final_Cost'] * 0.9, 2)."</td>
                </tr>";
            }
            ?>
        </tbody>
    </table>
    <a href="driver_dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
</div>
</body>
</html>