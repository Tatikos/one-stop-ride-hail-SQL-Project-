<?php include 'db_connect.php'; ?>
<!DOCTYPE html>
<html>
<head>
    <title>Advanced Reporting</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light p-4">

<div class="container">
    <a href="admin.php" class="btn btn-outline-dark mb-3">&larr; Dashboard</a>
    <h2><i class="fas fa-chart-line"></i> Advanced Earnings Report</h2>
    
    <form class="card p-3 mb-4 shadow-sm" method="GET">
        <div class="row g-3">
            <div class="col-md-3">
                <label>Start Date</label>
                <input type="date" name="start" class="form-control">
            </div>
            <div class="col-md-3">
                <label>End Date</label>
                <input type="date" name="end" class="form-control">
            </div>
            <div class="col-md-3">
                <label>Service Type</label>
                <select name="service" class="form-select">
                    <option value="">All</option>
                    <option value="1">Standard</option>
                    <option value="2">Luxury</option>
                </select>
            </div>
            <div class="col-md-3 d-flex align-items-end">
                <button type="submit" class="btn btn-primary w-100">Filter Report</button>
            </div>
        </div>
    </form>

    <table class="table table-striped bg-white border">
        <thead><tr><th>Driver</th><th>Service</th><th>Rides</th><th>Revenue</th><th>Platform Fee</th></tr></thead>
        <tbody>
            <?php
            if(isset($_GET['start'])) {
                // Handle inputs (allow nulls)
                $s = empty($_GET['start']) ? null : $_GET['start'];
                $e = empty($_GET['end']) ? null : $_GET['end'];
                $sv = empty($_GET['service']) ? null : $_GET['service'];

                $sql = "{call sp_Report_AdvancedEarnings(?, ?, NULL, ?)}";
                $stmt = sqlsrv_query($conn, $sql, array($s, $e, $sv));
                
                if($stmt) {
                    while($r = sqlsrv_fetch_array($stmt)) {
                        echo "<tr>
                            <td>{$r['Firstname']} {$r['Lastname']}</td>
                            <td>{$r['Service']}</td>
                            <td>{$r['TotalRides']}</td>
                            <td>€".number_format($r['TotalGross'],2)."</td>
                            <td class='text-success'>+€".number_format($r['PlatformRevenue'],2)."</td>
                        </tr>";
                    }
                }
            }
            ?>
        </tbody>
    </table>
</div>
</body>
</html>