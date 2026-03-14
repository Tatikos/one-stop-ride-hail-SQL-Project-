<?php
include 'db_connect.php';
if(!isset($_SESSION['UserID'])) { header("Location: login.php"); exit(); }
$error = "";

if(isset($_POST['submit_application'])) {
    // A. Create Profile
    $sql = "{call sp_Driver_RegisterProfile(?, 0)}"; 
    $stmt = sqlsrv_query($conn, $sql, array($_SESSION['UserID']));
    $driverID = null;
    if($stmt) {
        do { while($row=sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) if(isset($row['DriverID'])) { $driverID=$row['DriverID']; break 2; } } while(sqlsrv_next_result($stmt));
    }

    if ($driverID) {
        // B. Register Vehicle (WITH ZONE)
        $vSql = "{call sp_RegisterVehicle_Full_Geo(?, ?, ?, ?, ?, ?, ?, ?, ?)}";
        $vParams = array(
            $driverID, $_POST['plate'], $_POST['model'], $_POST['v_type'], 
            $_POST['seats'], $_POST['vol'], $_POST['weight'], $_POST['service_type'],
            $_POST['home_geofence'] // <--- IMPORTANT
        );
        $vStmt = sqlsrv_query($conn, $vSql, $vParams);
        
        if($vStmt) {
            $vehicleID = null;
            do { while($r=sqlsrv_fetch_array($vStmt, SQLSRV_FETCH_ASSOC)) if(isset($r['VehicleID'])) { $vehicleID=$r['VehicleID']; break 2; } } while(sqlsrv_next_result($vStmt));

            // C. Uploads
            $docs = ['license', 'id_card', 'medical', 'criminal', 'psych'];
            foreach($docs as $k) {
                if(!empty($_FILES[$k]['name'])) {
                    $path = "uploads/d_".$driverID."_".$k.".pdf";
                    move_uploaded_file($_FILES[$k]['tmp_name'], $path);
                    sqlsrv_query($conn, "{call sp_Upload_Strict_Doc(?, 'Driver', ?, ?, ?, ?)}", array($driverID, $k, $_POST[$k.'_num'], $_POST[$k.'_exp'], $path));
                }
            }
            $vdocs = ['mot', 'insurance', 'road_tax'];
            if($vehicleID) foreach($vdocs as $k) {
                if(!empty($_FILES[$k]['name'])) {
                    $path = "uploads/v_".$vehicleID."_".$k.".pdf";
                    move_uploaded_file($_FILES[$k]['tmp_name'], $path);
                    sqlsrv_query($conn, "{call sp_Upload_Strict_Doc(?, 'Vehicle', ?, ?, ?, ?)}", array($vehicleID, $k, $_POST[$k.'_num'], $_POST[$k.'_exp'], $path));
                }
            }
            header("Location: drive.php"); exit();
        } else { $error = "Error registering vehicle."; }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head><title>Driver Registration</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet"><link rel="stylesheet" href="style.css"></head>
<body class="bg-light">
<div class="container mt-5 mb-5"><div class="login-box mx-auto pb-5" style="max-width:800px;">
    <a href="drive.php" class="btn btn-outline-secondary btn-sm mb-3">Back</a>
    <h3>Complete Driver Registration</h3>
    <?php if($error) echo "<div class='alert alert-danger'>$error</div>"; ?>
    
    <form method="POST" enctype="multipart/form-data">
        <div class="card mb-3 p-3">
            <h5>1. Personal Documents</h5>
            <?php foreach(['id_card'=>'ID','license'=>'License','criminal'=>'Criminal','medical'=>'Medical','psych'=>'Psychiatric'] as $k=>$l) echo "<div class='row mb-2'><div class='col-3'>$l</div><div class='col-3'><input type='text' name='{$k}_num' class='form-control form-control-sm' required></div><div class='col-3'><input type='date' name='{$k}_exp' class='form-control form-control-sm' required></div><div class='col-3'><input type='file' name='$k' class='form-control form-control-sm' required></div></div>"; ?>
        </div>

        <div class="card mb-3 p-3">
            <h5>2. Vehicle Details</h5>
            <div class="row g-2 mb-2">
                <div class="col-4"><input type="text" name="plate" class="form-control" placeholder="Plate" required></div>
                <div class="col-4"><input type="text" name="model" class="form-control" placeholder="Model" required></div>
                <div class="col-4"><select name="v_type" class="form-select"><option>Sedan</option><option>Van</option><option>Truck</option></select></div>
            </div>
            <div class="row g-2">
                <div class="col-4">
                    <label class="small fw-bold">Operating Zone</label>
                    <select name="home_geofence" class="form-select">
                        <?php 
                        $g=sqlsrv_query($conn, "SELECT * FROM Geofence");
                        while($r=sqlsrv_fetch_array($g)) echo "<option value='{$r['GeofenceID']}'>{$r['Name']}</option>"; 
                        ?>
                    </select>
                </div>
                <div class="col-4">
                    <label class="small fw-bold">Service</label>
                    <select name="service_type" class="form-select">
                        <?php 
                        $s=sqlsrv_query($conn, "SELECT * FROM Service_Type");
                        while($r=sqlsrv_fetch_array($s)) echo "<option value='{$r['Service_type_id']}'>{$r['Name']}</option>"; 
                        ?>
                    </select>
                </div>
                <div class="col-4"><label class="small fw-bold">Seats</label><input type="number" name="seats" class="form-control" value="4"></div>
                <div class="col-6"><label class="small fw-bold">Volume (m³)</label><input type="number" name="vol" class="form-control" value="0"></div>
                <div class="col-6"><label class="small fw-bold">Weight (kg)</label><input type="number" name="weight" class="form-control" value="0"></div>
            </div>
        </div>

        <div class="card mb-3 p-3">
            <h5>3. Vehicle Documents</h5>
            <?php foreach(['mot'=>'MOT','insurance'=>'Insurance','road_tax'=>'Road Tax'] as $k=>$l) echo "<div class='row mb-2'><div class='col-3'>$l</div><div class='col-3'><input type='text' name='{$k}_num' class='form-control form-control-sm' required></div><div class='col-3'><input type='date' name='{$k}_exp' class='form-control form-control-sm' required></div><div class='col-3'><input type='file' name='$k' class='form-control form-control-sm' required></div></div>"; ?>
        </div>

        <button type="submit" name="submit_application" class="btn btn-black w-100 py-3">Submit</button>
    </form>
</div></div>
</body></html>