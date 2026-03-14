<?php 
include 'db_connect.php';

// 1. SECURITY & ACCESS CONTROL
if(!isset($_SESSION['UserID'])) { header("Location: login.php"); exit(); }

$isAdmin = false;
$isOperator = false;

// Check Roles
$roleSql = "SELECT r.Name FROM User_Role ur JOIN Role r ON ur.Role_id = r.Role_id WHERE ur.UserID = ?";
$stmt = sqlsrv_query($conn, $roleSql, array($_SESSION['UserID']));
if($stmt) {
    while($row = sqlsrv_fetch_array($stmt)) { 
        if($row['Name'] === 'Admin') $isAdmin = true; 
        if($row['Name'] === 'Operator') $isOperator = true; 
    }
}

// Allow access if either role is present
if(!$isAdmin && !$isOperator) { header("Location: index.php"); exit(); }

// 2. HANDLE ACTIONS
if(isset($_POST['verify_driver'])) {
    $sql = "{call sp_Admin_VerifyDriver(?, ?)}";
    sqlsrv_query($conn, $sql, array($_POST['driver_id'], $_SESSION['UserID']));
}
if(isset($_POST['verify_vehicle'])) {
    $sql = "{call sp_Admin_VerifyVehicle(?, ?)}";
    sqlsrv_query($conn, $sql, array($_POST['vehicle_id'], $_SESSION['UserID']));
}
// NEW: Promote User (Admin Only)
if(isset($_POST['promote_user']) && $isAdmin) {
    $sql = "{call sp_Admin_PromoteToOperator(?)}";
    sqlsrv_query($conn, $sql, array($_POST['email']));
    $msg = "User promoted successfully.";
}
// NEW: Add Service (Operator/Admin)
if(isset($_POST['add_service'])) {
    $sql = "{call sp_Operator_AddServiceType(?, ?, ?, ?)}";
    $params = array($_POST['svc_name'], $_POST['min_fare'], $_POST['rate'], $_POST['desc']);
    sqlsrv_query($conn, $sql, $params);
    $msg = "Service Type Added.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>OSRH - Management Console</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body { background-color: #f0f2f5; font-family: 'Segoe UI', Roboto, sans-serif; }
        .sidebar { background: #1a1a1a; min-height: 100vh; color: #888; position: fixed; width: 250px; top: 0; left: 0; padding: 20px; z-index: 1000; }
        .sidebar .brand { color: white; font-size: 24px; font-weight: bold; margin-bottom: 30px; display: block; text-decoration: none; }
        .nav-link { color: #999; margin-bottom: 8px; border-radius: 8px; padding: 12px 15px; cursor: pointer; text-decoration: none; display:block; }
        .nav-link:hover, .nav-link.active { background: #222; color: white; }
        .main-content { margin-left: 250px; padding: 30px; }
        .card { border: none; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.05); margin-bottom: 20px; }
    </style>
</head>
<body>

<!-- SIDEBAR -->
<div class="sidebar">
    <a href="#" class="brand">OSRH <span class="text-primary"><?= $isAdmin ? 'ADMIN' : 'OP' ?></span></a>
    
    <nav class="nav flex-column" role="tablist">
        <button class="nav-link active border-0 w-100 text-start" data-bs-toggle="tab" data-bs-target="#dashboard"><i class="fas fa-chart-pie me-2"></i> Dashboard</button>
        <button class="nav-link border-0 w-100 text-start" data-bs-toggle="tab" data-bs-target="#verifications"><i class="fas fa-check-double me-2"></i> Verifications</button>
        <button class="nav-link border-0 w-100 text-start" data-bs-toggle="tab" data-bs-target="#services"><i class="fas fa-taxi me-2"></i> Services</button>
        
        <?php if($isAdmin): ?>
        <button class="nav-link border-0 w-100 text-start text-warning" data-bs-toggle="tab" data-bs-target="#roles"><i class="fas fa-user-shield me-2"></i> User Roles</button>
        <?php endif; ?>

        <a href="reports.php" class="nav-link"><i class="fas fa-file-invoice-dollar me-2"></i> Reports</a>
        
        <div class="mt-5 pt-5 border-top border-secondary">
            <a href="index.php" class="nav-link"><i class="fas fa-mobile-alt me-2"></i> User App</a>
            <a href="logout.php" class="nav-link text-danger"><i class="fas fa-sign-out-alt me-2"></i> Logout</a>
        </div>
    </nav>
</div>

<div class="main-content">
    <?php if(isset($msg)) echo "<div class='alert alert-success'>$msg</div>"; ?>

    <div class="tab-content">
        
        <!-- DASHBOARD -->
        <div class="tab-pane fade show active" id="dashboard">
            <h3>System Overview</h3>
            <div class="row mt-4">
                <div class="col-md-8">
                    <div class="card p-3"><canvas id="activityChart" style="height:300px"></canvas></div>
                </div>
                <div class="col-md-4">
                    <div class="card p-4 bg-primary text-white">
                        <h5>Role Status</h5>
                        <p class="mb-0">You are logged in as:</p>
                        <h2 class="fw-bold"><?= $isAdmin ? 'Administrator' : 'Operator' ?></h2>
                        <small class="opacity-75">Admin rights: <?= $isAdmin ? 'Yes' : 'No' ?></small>
                    </div>
                </div>
            </div>
        </div>

        <!-- VERIFICATIONS (Shared) -->
        <div class="tab-pane fade" id="verifications">
            <h3>Pending Approvals</h3>
            
            <!-- Drivers -->
            <div class="card mt-3">
                <div class="card-header bg-warning bg-opacity-10 text-warning fw-bold">Pending Drivers</div>
                <div class="card-body p-0">
                    <table class="table mb-0">
                        <thead><tr><th>Name</th><th>Email</th><th>Action</th></tr></thead>
                        <tbody>
                            <?php
                            $sql = "SELECT d.DriverID, u.Firstname, u.Lastname, u.Email FROM Driver_Profile d JOIN [User] u ON d.UserID=u.UserID WHERE d.Status='Pending'";
                            $stmt = sqlsrv_query($conn, $sql);
                            while($r = sqlsrv_fetch_array($stmt)) {
                                echo "<tr><td>{$r['Firstname']} {$r['Lastname']}</td><td>{$r['Email']}</td>
                                <td><form method='POST' class='d-inline'><input type='hidden' name='driver_id' value='{$r['DriverID']}'><button name='verify_driver' class='btn btn-sm btn-dark'>Approve</button></form></td></tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Vehicles -->
            <div class="card mt-3">
                <div class="card-header bg-info bg-opacity-10 text-info fw-bold">Pending Vehicles</div>
                <div class="card-body p-0">
                    <table class="table mb-0">
                        <thead><tr><th>Model</th><th>Plate</th><th>Owner</th><th>Action</th></tr></thead>
                        <tbody>
                            <?php
                            $sql = "SELECT v.Vehicle_ID, v.Model, v.RegistrationPlate, u.Lastname FROM Vehicle v JOIN Driver_Profile d ON v.Owner_DriverID=d.DriverID JOIN [User] u ON d.UserID=u.UserID WHERE v.Status='Pending'";
                            $stmt = sqlsrv_query($conn, $sql);
                            while($r = sqlsrv_fetch_array($stmt)) {
                                echo "<tr><td>{$r['Model']}</td><td>{$r['RegistrationPlate']}</td><td>{$r['Lastname']}</td>
                                <td><form method='POST' class='d-inline'><input type='hidden' name='vehicle_id' value='{$r['Vehicle_ID']}'><button name='verify_vehicle' class='btn btn-sm btn-dark'>Approve</button></form></td></tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- SERVICE MANAGEMENT (Operator + Admin) -->
        <div class="tab-pane fade" id="services">
            <div class="d-flex justify-content-between align-items-center">
                <h3>Service Types</h3>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addServiceModal"><i class="fas fa-plus"></i> Add New Service</button>
            </div>
            <p class="text-muted">Define vehicle requirements and pricing tiers.</p>
            
            <div class="card mt-3">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light"><tr><th>Service Name</th><th>Base Fare</th><th>Rate/Km</th><th>Description</th></tr></thead>
                        <tbody>
                            <?php
                            $sSql = "SELECT * FROM Service_Type";
                            $sStmt = sqlsrv_query($conn, $sSql);
                            while($row = sqlsrv_fetch_array($sStmt)) {
                                echo "<tr>
                                    <td class='fw-bold'>{$row['Name']}</td>
                                    <td>€{$row['MinimumFare']}</td>
                                    <td>€{$row['PerKilometerRate']}</td>
                                    <td class='text-muted small'>{$row['Description']}</td>
                                </tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- USER ROLES (Admin Only) -->
        <?php if($isAdmin): ?>
        <div class="tab-pane fade" id="roles">
            <h3>User Management</h3>
            <div class="card p-4 mt-3 border-warning">
                <h5><i class="fas fa-crown text-warning me-2"></i> Promote to Operator</h5>
                <p class="small text-muted">Grant a user permissions to verify drivers and manage service types.</p>
                <form method="POST" class="row g-2">
                    <div class="col-md-8">
                        <input type="email" name="email" class="form-control" placeholder="Enter user email address" required>
                    </div>
                    <div class="col-md-4">
                        <button type="submit" name="promote_user" class="btn btn-warning w-100 fw-bold">Promote User</button>
                    </div>
                </form>
            </div>
        </div>
        <?php endif; ?>

    </div>
</div>

<!-- Add Service Modal -->
<div class="modal fade" id="addServiceModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add Service Type</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <div class="mb-3"><label>Name</label><input type="text" name="svc_name" class="form-control" placeholder="e.g. Robo-Taxi XL" required></div>
                    <div class="row g-2 mb-3">
                        <div class="col-6"><label>Min Fare (€)</label><input type="number" step="0.01" name="min_fare" class="form-control" required></div>
                        <div class="col-6"><label>Rate/Km (€)</label><input type="number" step="0.01" name="rate" class="form-control" required></div>
                    </div>
                    <div class="mb-3"><label>Requirements / Description</label><textarea name="desc" class="form-control" placeholder="e.g. Autonomous vehicles > 6 seats"></textarea></div>
                </div>
                <div class="modal-footer">
                    <button type="submit" name="add_service" class="btn btn-primary">Create Service</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>