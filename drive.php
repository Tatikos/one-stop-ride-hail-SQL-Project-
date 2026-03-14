<?php 
include 'db_connect.php'; 
if(!isset($_SESSION['UserID'])) header("Location: login.php");

// Check Status
$status = "None";
$type = "None";
$sql = "SELECT Status, IsAutonomous FROM Driver_Profile WHERE UserID = ?";
$stmt = sqlsrv_query($conn, $sql, array($_SESSION['UserID']));

if($r = sqlsrv_fetch_array($stmt)) {
    $status = $r['Status'];
    $type = ($r['IsAutonomous'] == 1) ? "Autonomous" : "Human";
}

// Redirect if Active
if($status == 'Active') {
    header("Location: driver_dashboard.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>OSRH - Drive with us</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
    <style>
        .choice-card { border: 2px solid #eee; border-radius: 15px; padding: 30px; text-align: center; transition: 0.3s; cursor: pointer; height: 100%; }
        .choice-card:hover { border-color: black; background: #f9f9f9; transform: translateY(-5px); }
        .icon-lg { font-size: 60px; margin-bottom: 20px; color: #333; }
    </style>
</head>
<body>

<nav class="navbar-custom mb-5">
    <a href="index.php" class="navbar-brand ms-3">OSRH</a>
    <a href="logout.php" class="nav-link me-3">Logout</a>
</nav>

<div class="container mt-5 pt-5">
    
    <?php if($status == 'Pending'): ?>
        <div class="text-center mt-5">
            <i class="fas fa-clock text-warning" style="font-size: 80px;"></i>
            <h2 class="mt-4">Application Under Review</h2>
            <p class="lead text-muted">Your documents are being verified by our team.</p>
            <div class="alert alert-info d-inline-block mt-2">
                Current Status: <strong>Pending Approval</strong>
            </div>
        </div>

    <?php else: ?>
        <div class="text-center mb-5">
            <h1 class="fw-bold">Choose how you want to earn</h1>
            <p class="text-muted">Select the option that matches your vehicle capabilities.</p>
        </div>

        <div class="row justify-content-center g-4">
            <div class="col-md-5">
                <a href="drive_human.php" class="text-decoration-none text-dark">
                    <div class="choice-card">
                        <div class="icon-lg"><i class="fas fa-user-tie"></i></div>
                        <h3>Human Driver</h3>
                        <p class="text-muted small">You drive your own car. Requires Driving License, ID, and Vehicle documents.</p>
                        <button class="btn btn-black mt-3">Register as Driver</button>
                    </div>
                </a>
            </div>

            <div class="col-md-5">
                <a href="drive_autonomous.php" class="text-decoration-none text-dark">
                    <div class="choice-card">
                        <div class="icon-lg"><i class="fas fa-robot"></i></div>
                        <h3>Autonomous Vehicle</h3>
                        <p class="text-muted small">Your vehicle drives itself. Requires Vehicle Registration & Safety Certificates only.</p>
                        <button class="btn btn-outline-dark mt-3">Register Autonomous Fleet</button>
                    </div>
                </a>
            </div>
        </div>
    <?php endif; ?>

</div>
</body>
</html>