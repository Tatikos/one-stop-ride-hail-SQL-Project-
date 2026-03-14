<?php
include 'db_connect.php';

if(!isset($_SESSION['UserID'])) { header("Location: login.php"); exit(); }
$error = "";

if(isset($_POST['submit_av'])) {
    // 1. Register Profile as AUTONOMOUS (1)
    $sql = "{call sp_Driver_RegisterProfile(?, 1)}";
    $stmt = sqlsrv_query($conn, $sql, array($_SESSION['UserID']));
    
    $driverID = null;
    if($stmt) {
        // Fetch the ID
        do { while($row=sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) if(isset($row['DriverID'])) { $driverID=$row['DriverID']; break 2; } } while(sqlsrv_next_result($stmt));
    }

    if($driverID) {
        // 2. AUTO-ACTIVATE PROFILE (Robots don't need ID verification)
        $updSql = "UPDATE Driver_Profile SET Status = 'Active' WHERE DriverID = ?";
        sqlsrv_query($conn, $updSql, array($driverID));

        // 3. Register Vehicle (With Geofence & Specs)
        $vSql = "{call sp_RegisterVehicle_Full_Geo(?, ?, ?, ?, ?, ?, ?, ?, ?)}";
        $vParams = array(
            $driverID, 
            $_POST['plate'], 
            $_POST['model'], 
            'Autonomous', // Type
            4, // Seats
            0, // Vol
            0, // Weight
            $_POST['service_type'],
            $_POST['home_geofence']
        );
        $vStmt = sqlsrv_query($conn, $vSql, $vParams);
        
        $vehicleID = null;
        if($vStmt) {
            do { while($r=sqlsrv_fetch_array($vStmt, SQLSRV_FETCH_ASSOC)) if(isset($r['VehicleID'])) { $vehicleID=$r['VehicleID']; break 2; } } while(sqlsrv_next_result($vStmt));

            // 4. Upload Vehicle Docs (Using Standard Proc)
            $docs = ['safety_cert' => 'Safety Cert', 'insurance' => 'Insurance', 'mot' => 'MOT'];
            
            foreach($docs as $inputName => $docType) {
                if(!empty($_FILES[$inputName]['name'])) {
                    $path = "uploads/av_".$vehicleID."_".$inputName.".pdf";
                    move_uploaded_file($_FILES[$inputName]['tmp_name'], $path);
                    $docSql = "{call sp_Upload_Strict_Doc(?, 'Vehicle', ?, ?, ?, ?)}";
                    sqlsrv_query($conn, $docSql, array($vehicleID, $docType, 'AV-DOC', '2030-01-01', $path));
                }
            }

            header("Location: drive.php");
            exit();
        } else {
            $error = "Error registering vehicle.";
        }
    } else {
        $error = "Error creating profile.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Autonomous Fleet Registration</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
</head>
<body class="bg-light">

<div class="container mt-5 mb-5">
    <div class="login-box mx-auto" style="max-width: 800px;">
        
        <div class="d-flex align-items-center mb-4">
            <a href="drive.php" class="btn btn-outline-secondary btn-sm me-3">&larr; Back</a>
            <h3 class="m-0 fw-bold"><i class="fas fa-robot"></i> Autonomous Fleet Setup</h3>
        </div>

        <div class="alert alert-info d-flex align-items-center">
            <i class="fas fa-info-circle fa-2x me-3"></i>
            <div>
                <strong>Streamlined Verification</strong><br>
                <small>Autonomous profiles are automatically approved. We only verify the vehicle's safety compliance.</small>
            </div>
        </div>

        <?php if($error): ?>
            <div class="alert alert-danger"><?= $error ?></div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data">
            
            <div class="card mb-3 p-3">
                <h5 class="card-title fw-bold mb-3">1. Vehicle Configuration</h5>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="small fw-bold text-muted">Registration Plate</label>
                        <input type="text" name="plate" class="form-control" placeholder="e.g., AV-900" required>
                    </div>
                    <div class="col-md-6">
                        <label class="small fw-bold text-muted">System Model</label>
                        <input type="text" name="model" class="form-control" placeholder="e.g., Waymo Gen-5" required>
                    </div>
                    <div class="col-md-6">
                        <label class="small fw-bold text-muted">Operating Zone</label>
                        <select name="home_geofence" class="form-select">
                            <?php 
                            $g=sqlsrv_query($conn, "SELECT * FROM Geofence");
                            while($r=sqlsrv_fetch_array($g)) echo "<option value='{$r['GeofenceID']}'>{$r['Name']}</option>"; 
                            ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="small fw-bold text-muted">Service Class</label>
                        <select name="service_type" class="form-select">
                            <?php
                            $sql = "SELECT * FROM Service_Type";
                            $stmt = sqlsrv_query($conn, $sql);
                            while($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                                echo "<option value='".$row['Service_type_id']."'>".$row['Name']."</option>";
                            }
                            ?>
                        </select>
                    </div>
                </div>
            </div>

            <div class="card mb-3 p-3">
                <h5 class="card-title fw-bold mb-3">2. Safety & Compliance Documents</h5>
                <div class="row g-3">
                    <div class="col-12">
                        <label class="small fw-bold text-muted">Safety Calibration Certificate</label>
                        <input type="file" name="safety_cert" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label class="small fw-bold text-muted">Insurance Policy</label>
                        <input type="file" name="insurance" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label class="small fw-bold text-muted">MOT / Roadworthiness</label>
                        <input type="file" name="mot" class="form-control" required>
                    </div>
                </div>
            </div>

            <button type="submit" name="submit_av" class="btn btn-black w-100 py-3 fw-bold">Register Fleet Vehicle</button>
        </form>
    </div>
</div>

</body>
</html>