<?php
include 'db_connect.php';

// 1. Security & Validation
if(!isset($_SESSION['UserID'])) { header("Location: login.php"); exit(); }
$ride_id = $_GET['ride_id'] ?? $_POST['ride_id'];
if(!$ride_id) { header("Location: history.php"); exit(); }

// 2. Fetch Ride & Driver Info (UPDATED FOR MULTI-LEG)
// We take the driver from the LAST segment to rate the final experience
$driverName = "Driver"; $car = "Vehicle"; $price = "0.00"; $date = ""; $revieweeID = 0;

$sql = "SELECT TOP 1 u.UserID as DriverUserID, u.Firstname, u.Lastname, v.Model, r.Final_Cost, r.End_time
        FROM RideSegment rs
        JOIN Ride r ON rs.RideID = r.Ride_id
        JOIN Driver_Profile dp ON rs.DriverID = dp.DriverID
        JOIN [User] u ON dp.UserID = u.UserID
        JOIN Vehicle v ON rs.VehicleID = v.Vehicle_ID
        WHERE rs.RideID = ? AND rs.DriverID IS NOT NULL
        ORDER BY rs.SequenceOrder DESC"; // Get the last driver

$stmt = sqlsrv_query($conn, $sql, array($ride_id));

if($row = sqlsrv_fetch_array($stmt)) {
    $driverName = $row['Firstname'];
    $car = $row['Model'];
    $price = number_format($row['Final_Cost'], 2);
    $date = $row['End_time'] ? $row['End_time']->format('M d, H:i') : 'Recently';
    $revieweeID = $row['DriverUserID'];
}

// 3. Handle Submit
$error = "";
if(isset($_POST['submit'])) {
    if(!empty($_POST['rating']) && $revieweeID != 0) {
        // Insert Review
        $sql = "INSERT INTO Review (Ride_id, Reviewer_UserID, Reviewee_UserID, Rating, Comment) VALUES (?, ?, ?, ?, ?)";
        $params = array($ride_id, $_SESSION['UserID'], $revieweeID, $_POST['rating'], $_POST['comment']);
        
        if(sqlsrv_query($conn, $sql, $params)) {
            header("Location: history.php"); exit();
        } else { $error = "System error. Please try again."; }
    } else { $error = "Please tap a star to rate."; }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Rate Driver</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f9f9f9; font-family: 'Segoe UI', sans-serif; display: flex; align-items: center; justify-content: center; height: 100vh; margin: 0; }
        .review-card { background: white; width: 100%; max-width: 420px; padding: 40px; border-radius: 24px; box-shadow: 0 10px 40px rgba(0,0,0,0.08); text-align: center; }
        .avatar { width: 80px; height: 80px; background-color: #eee; border-radius: 50%; margin: 0 auto 15px; background-image: url('https://ui-avatars.com/api/?name=<?= $driverName ?>&background=000&color=fff'); background-size: cover; }
        .trip-info { font-size: 13px; color: #888; margin-bottom: 25px; padding-bottom: 25px; border-bottom: 1px solid #f0f0f0; }
        .rating-group { display: flex; justify-content: center; flex-direction: row-reverse; gap: 5px; margin-bottom: 25px; }
        .rating-group input { display: none; }
        .rating-group label { font-size: 40px; color: #e0e0e0; cursor: pointer; transition: 0.2s; }
        .rating-group label:hover, .rating-group label:hover ~ label { color: #ffc107; transform: scale(1.1); }
        .rating-group input:checked ~ label { color: #ffc107; }
        .form-control { background: #f8f8f8; border: none; border-radius: 12px; padding: 15px; resize: none; }
        .btn-black { background: black; color: white; width: 100%; padding: 15px; border-radius: 12px; font-weight: bold; border: none; margin-top: 20px; }
        .btn-black:hover { background: #333; }
        .btn-skip { color: #999; text-decoration: none; font-size: 14px; display: block; margin-top: 15px; }
    </style>
</head>
<body>

<div class="review-card">
    <div class="avatar"></div>
    <h3 class="fw-bold mb-1">Rate <?= $driverName ?></h3>
    <div class="trip-info"><?= $car ?> • €<?= $price ?> • <?= $date ?></div>

    <?php if($error): ?>
        <div class="alert alert-danger py-2 small mb-3"><?= $error ?></div>
    <?php endif; ?>

    <form method="POST">
        <input type="hidden" name="ride_id" value="<?= $ride_id ?>">
        
        <div class="rating-group">
            <input type="radio" id="star5" name="rating" value="5"><label for="star5">★</label>
            <input type="radio" id="star4" name="rating" value="4"><label for="star4">★</label>
            <input type="radio" id="star3" name="rating" value="3"><label for="star3">★</label>
            <input type="radio" id="star2" name="rating" value="2"><label for="star2">★</label>
            <input type="radio" id="star1" name="rating" value="1"><label for="star1">★</label>
        </div>

        <textarea name="comment" class="form-control" rows="3" placeholder="How was your ride?"></textarea>
        <button type="submit" name="submit" class="btn-black">Submit Review</button>
        <a href="history.php" class="btn-skip">Skip</a>
    </form>
</div>
</body>
</html>