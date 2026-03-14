<?php 
include 'db_connect.php'; 

// 1. SECURITY & DATA FETCHING
if(!isset($_SESSION['UserID'])) { header("Location: login.php"); exit(); }
$uid = $_SESSION['UserID'];

// Handle GDPR Delete
if(isset($_POST['delete_account'])) {
    sqlsrv_query($conn, "{call sp_GDPR_ForgetMe(?)}", array($uid));
    session_destroy();
    header("Location: login.php"); exit();
}

// A. Get User Details
$userSql = "SELECT Firstname, Lastname, Email, CreatedAt, Address FROM [User] WHERE UserID = ?";
$uStmt = sqlsrv_query($conn, $userSql, array($uid));
$user = sqlsrv_fetch_array($uStmt, SQLSRV_FETCH_ASSOC);

// B. Get Stats (Total Spent & Total Rides)
$statsSql = "SELECT COUNT(r.Ride_id) as TotalRides, ISNULL(SUM(p.Amount), 0) as TotalSpent 
             FROM Ride r 
             LEFT JOIN Payment p ON r.Ride_id = p.Ride_ID 
             WHERE r.Passenger_UserID = ? AND r.Status = 'Completed'";
$sStmt = sqlsrv_query($conn, $statsSql, array($uid));
$stats = sqlsrv_fetch_array($sStmt, SQLSRV_FETCH_ASSOC);

// C. Get Payment History
$paySql = "SELECT p.Payment_id, p.Amount, p.Date, p.PaymentStatus, st.Name as ServiceType, r.Ride_id 
           FROM Payment p 
           JOIN Ride r ON p.Ride_ID = r.Ride_id 
           JOIN Service_Type st ON r.Service_type_id = st.Service_type_id 
           WHERE r.Passenger_UserID = ? 
           ORDER BY p.Date DESC";
$pStmt = sqlsrv_query($conn, $paySql, array($uid));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Profile</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background-color: #f4f7f6; font-family: 'Segoe UI', system-ui, sans-serif; }
        
        /* Profile Card */
        .profile-header { background: linear-gradient(135deg, #1e2024 0%, #2c3e50 100%); color: white; padding: 40px 20px; border-radius: 12px 12px 0 0; }
        .avatar-circle { width: 100px; height: 100px; border: 4px solid rgba(255,255,255,0.2); border-radius: 50%; background-image: url('https://ui-avatars.com/api/?name=<?= $user['Firstname'] ?>+<?= $user['Lastname'] ?>&background=random&size=128'); background-size: cover; margin: 0 auto 15px; }
        
        /* Navigation Pills */
        .nav-pills .nav-link { color: #555; font-weight: 600; padding: 12px 20px; border-radius: 8px; transition: 0.2s; }
        .nav-pills .nav-link.active { background-color: #000; color: white; }
        .nav-pills .nav-link:hover:not(.active) { background-color: #e9ecef; }
        
        /* Cards */
        .card-custom { border: none; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.03); background: white; margin-bottom: 20px; overflow: hidden; }
        .stat-box { padding: 20px; border-right: 1px solid #eee; text-align: center; }
        .stat-box:last-child { border-right: none; }
        .stat-value { font-size: 24px; font-weight: 800; color: #2c3e50; }
        .stat-label { font-size: 13px; text-transform: uppercase; color: #888; letter-spacing: 1px; }

        /* Payment Table */
        .table-custom th { background: #f8f9fa; font-size: 12px; text-transform: uppercase; color: #666; font-weight: 700; border-top: none; }
        .table-custom td { vertical-align: middle; padding: 15px; }
        .status-paid { background: #e6f4ea; color: #1e8e3e; padding: 4px 10px; border-radius: 20px; font-size: 12px; font-weight: bold; }
    </style>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark bg-black py-3 mb-4">
    <div class="container">
        <a class="navbar-brand fw-bold" href="index.php">OSRH</a>
        <a href="index.php" class="btn btn-sm btn-outline-light">Back to Map</a>
    </div>
</nav>

<div class="container pb-5">
    <div class="row">
        
        <div class="col-lg-4">
            <div class="card-custom">
                <div class="profile-header text-center">
                    <div class="avatar-circle"></div>
                    <h4 class="fw-bold mb-0"><?= $user['Firstname'] . ' ' . $user['Lastname'] ?></h4>
                    <p class="text-white-50 small mb-0"><i class="fas fa-envelope me-1"></i> <?= $user['Email'] ?></p>
                </div>
                <div class="card-body p-3">
                    <div class="nav flex-column nav-pills" id="v-pills-tab" role="tablist">
                        <button class="nav-link active text-start mb-1" data-bs-toggle="pill" data-bs-target="#overview">
                            <i class="fas fa-user-circle me-2"></i> Overview
                        </button>
                        <button class="nav-link text-start mb-1" data-bs-toggle="pill" data-bs-target="#payments">
                            <i class="fas fa-receipt me-2"></i> Payment Logs
                        </button>
                        <button class="nav-link text-start text-danger" data-bs-toggle="pill" data-bs-target="#settings">
                            <i class="fas fa-cog me-2"></i> Settings & GDPR
                        </button>
                    </div>
                </div>
            </div>

            <div class="card-custom">
                <div class="d-flex">
                    <div class="stat-box w-50">
                        <div class="stat-value"><?= $stats['TotalRides'] ?></div>
                        <div class="stat-label">Rides</div>
                    </div>
                    <div class="stat-box w-50">
                        <div class="stat-value">€<?= number_format($stats['TotalSpent'], 0) ?></div>
                        <div class="stat-label">Total Spent</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="tab-content">
                
                <div class="tab-pane fade show active" id="overview">
                    <div class="card-custom p-4">
                        <h5 class="fw-bold mb-4">Account Details</h5>
                        <form>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label text-muted small fw-bold">First Name</label>
                                    <input type="text" class="form-control" value="<?= $user['Firstname'] ?>" disabled>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label text-muted small fw-bold">Last Name</label>
                                    <input type="text" class="form-control" value="<?= $user['Lastname'] ?>" disabled>
                                </div>
                                <div class="col-md-12">
                                    <label class="form-label text-muted small fw-bold">Email Address</label>
                                    <input type="text" class="form-control" value="<?= $user['Email'] ?>" disabled>
                                </div>
                                <div class="col-md-12">
                                    <label class="form-label text-muted small fw-bold">Billing Address</label>
                                    <input type="text" class="form-control" value="<?= $user['Address'] ?>" disabled>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label text-muted small fw-bold">Member Since</label>
                                    <input type="text" class="form-control" value="<?= $user['CreatedAt']->format('M Y') ?>" disabled>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="tab-pane fade" id="payments">
                    <div class="card-custom">
                        <div class="card-header bg-white py-3 border-0">
                            <h5 class="fw-bold m-0"><i class="fas fa-file-invoice-dollar me-2"></i> Transaction History</h5>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-custom table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th class="ps-4">Date</th>
                                        <th>Description</th>
                                        <th>Status</th>
                                        <th class="text-end pe-4">Amount</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $hasPay = false;
                                    while($pay = sqlsrv_fetch_array($pStmt, SQLSRV_FETCH_ASSOC)) { 
                                        $hasPay = true;
                                    ?>
                                    <tr>
                                        <td class="ps-4 text-secondary small fw-bold"><?= $pay['Date']->format('d M, Y') ?></td>
                                        <td>
                                            <div class="fw-bold text-dark"><?= $pay['ServiceType'] ?></div>
                                            <div class="small text-muted">Ride #<?= $pay['Ride_id'] ?></div>
                                        </td>
                                        <td><span class="status-paid"><?= $pay['PaymentStatus'] ?></span></td>
                                        <td class="text-end fw-bold pe-4">-€<?= number_format($pay['Amount'], 2) ?></td>
                                        <td class="text-end"><button class="btn btn-sm btn-light text-muted"><i class="fas fa-print"></i></button></td>
                                    </tr>
                                    <?php } ?>
                                    
                                    <?php if(!$hasPay): ?>
                                    <tr>
                                        <td colspan="5" class="text-center py-5 text-muted">
                                            <i class="fas fa-receipt fa-2x mb-3"></i><br>No payments found.
                                        </td>
                                    </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="tab-pane fade" id="settings">
                    <div class="card-custom p-4 border border-danger border-opacity-25">
                        <div class="d-flex align-items-center mb-3 text-danger">
                            <i class="fas fa-shield-alt fa-2x me-3"></i>
                            <div>
                                <h5 class="fw-bold m-0">GDPR Zone</h5>
                                <small class="text-muted">Manage your data privacy rights.</small>
                            </div>
                        </div>
                        <p class="small text-secondary">
                            Under the General Data Protection Regulation (GDPR), you have the "Right to be Forgotten". 
                            This action will permanently anonymize your personal data and disable your login. 
                            <b>This cannot be undone.</b>
                        </p>
                        <form method="POST" onsubmit="return confirm('WARNING: Are you sure you want to delete your account? This is permanent.');">
                            <button name="delete_account" class="btn btn-outline-danger fw-bold">
                                <i class="fas fa-trash-alt me-2"></i> Delete My Account
                            </button>
                        </form>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>