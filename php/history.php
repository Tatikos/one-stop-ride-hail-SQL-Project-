<?php
include 'db_connect.php';
if(!isset($_SESSION['UserID'])) header("Location: login.php");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Trips</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background-color: #f6f6f6; font-family: 'Segoe UI', Roboto, sans-serif; }
        .navbar-custom { background: white; padding: 15px 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); position: fixed; width: 100%; top: 0; z-index: 1000; }
        .navbar-brand { font-weight: 800; color: black; text-decoration: none; font-size: 20px; }
        .trip-card { background: white; border-radius: 12px; padding: 20px; margin-bottom: 16px; border: 1px solid #eee; transition: transform 0.2s, box-shadow 0.2s; cursor: default; }
        .trip-card:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(0,0,0,0.05); }
        .service-icon { width: 50px; height: 50px; background: #f1f1f1; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 20px; color: #333; }
        .trip-date { font-size: 12px; font-weight: 600; color: #666; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 5px; }
        .trip-price { font-size: 18px; font-weight: 700; color: black; }
        .driver-name { font-weight: 600; font-size: 15px; }
        .car-info { font-size: 13px; color: #888; }
        .status-badge { font-size: 11px; padding: 4px 8px; border-radius: 4px; font-weight: bold; background: #eee; color: #555; }
        .status-Completed { background: #e6f4ea; color: #1e8e3e; } 
        .status-Cancelled { background: #fce8e6; color: #c5221f; }
        .btn-rate { background: black; color: white; font-size: 13px; padding: 8px 16px; border-radius: 30px; text-decoration: none; font-weight: 600; transition: 0.2s; }
        .btn-rated { background: transparent; border: 1px solid #ddd; color: #888; font-size: 13px; padding: 8px 16px; border-radius: 30px; cursor: default; }
    </style>
</head>
<body>

<nav class="navbar-custom">
    <div class="container-fluid d-flex justify-content-between align-items-center">
        <a href="index.php" class="navbar-brand"><i class="fas fa-arrow-left me-2"></i> My Trips</a>
        <div class="fw-bold small"><?= date('M Y') ?></div>
    </div>
</nav>

<div class="container" style="margin-top: 80px; max-width: 700px;">
    
    <?php
    // UPDATED QUERY: LOOKS INTO 'RideSegment' TO FIND DRIVER INFO
    $sql = "SELECT r.Ride_id, r.Final_Cost, r.End_time, r.Status,
                   st.Name as ServiceType,
                   
                   -- Subquery to get the FIRST Driver Name associated with this ride
                   (SELECT TOP 1 u.Firstname + ' ' + u.Lastname 
                    FROM RideSegment rs 
                    JOIN Driver_Profile dp ON rs.DriverID = dp.DriverID 
                    JOIN [User] u ON dp.UserID = u.UserID 
                    WHERE rs.RideID = r.Ride_id AND rs.DriverID IS NOT NULL) as DriverName,

                   -- Subquery to get the Vehicle Model
                   (SELECT TOP 1 v.Model 
                    FROM RideSegment rs 
                    JOIN Vehicle v ON rs.VehicleID = v.Vehicle_ID 
                    WHERE rs.RideID = r.Ride_id AND rs.VehicleID IS NOT NULL) as Model,

                   (SELECT COUNT(*) FROM Review rev WHERE rev.Ride_id = r.Ride_id AND rev.Reviewer_UserID = ?) as IsReviewed

            FROM Ride r 
            JOIN Service_Type st ON r.Service_type_id = st.Service_type_id
            WHERE r.Passenger_UserID = ? 
            ORDER BY r.End_time DESC";
    
    $stmt = sqlsrv_query($conn, $sql, array($_SESSION['UserID'], $_SESSION['UserID']));
    
    if($stmt === false) {
        echo "<div class='alert alert-danger'>Error loading history: " . print_r(sqlsrv_errors(), true) . "</div>";
    }

    $count = 0;
    while($r = sqlsrv_fetch_array($stmt)) {
        $count++;
        $dateStr = $r['End_time'] ? $r['End_time']->format('D, M d • H:i') : 'In Progress';
        $status = $r['Status'];
        $driverName = $r['DriverName'] ?? 'Multiple Drivers';
        $carModel = $r['Model'] ?? 'Standard Vehicle';
        
        // Button Logic
        $actionBtn = "";
        if($status == 'Completed') {
            if($r['IsReviewed'] > 0) {
                $actionBtn = '<span class="btn-rated"><i class="fas fa-star text-warning"></i> Rated</span>';
            } else {
                $actionBtn = '<a href="review.php?ride_id='.$r['Ride_id'].'" class="btn-rate">Rate Ride</a>';
            }
        } else {
            $actionBtn = '<span class="status-badge status-'.$status.'">'.$status.'</span>';
            // Allow tracking if active
            if($status == 'Accepted' || $status == 'InProgress' || $status == 'Requested') {
                 $actionBtn = '<a href="track_ride.php?id='.$r['Ride_id'].'" class="btn btn-sm btn-dark">Track</a>';
            }
        }

        echo '
        <div class="trip-card">
            <div class="d-flex justify-content-between align-items-start mb-2">
                <div class="trip-date">'.$dateStr.'</div>
                <div class="trip-price">€'.number_format($r['Final_Cost'], 2).'</div>
            </div>
            
            <div class="d-flex align-items-center">
                <div class="service-icon me-3">
                    <i class="fas fa-car-side"></i>
                </div>
                <div class="flex-grow-1">
                    <div class="driver-name">'.$driverName.'</div>
                    <div class="car-info">'.$carModel.' • '.$r['ServiceType'].'</div>
                </div>
                <div>
                    '.$actionBtn.'
                </div>
            </div>
        </div>';
    }

    if($count == 0) {
        echo '<div class="text-center mt-5">
                <div class="mb-3"><i class="fas fa-route fa-3x text-muted"></i></div>
                <h5 class="text-muted">No rides yet</h5>
                <a href="index.php" class="btn btn-dark mt-2">Book a Ride</a>
              </div>';
    }
    ?>
    <div class="text-center py-4 text-muted small">End of list</div>
</div>
</body>
</html>