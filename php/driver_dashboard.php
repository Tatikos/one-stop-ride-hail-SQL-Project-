<?php
include 'db_connect.php';
if(!isset($_SESSION['UserID'])) header("Location: login.php");

// --- 1. ACTION HANDLERS ---
if(isset($_POST['accept'])) {
    sqlsrv_query($conn, "{call sp_Driver_AcceptRide(?, ?)}", array($_POST['segment_id'], $_SESSION['UserID']));
    header("Refresh:0");
}
if(isset($_POST['complete'])) {
    sqlsrv_query($conn, "{call sp_Driver_CompleteRide(?)}", array($_POST['segment_id']));
    header("Refresh:0");
}
if(isset($_POST['toggle_status'])) {
    sqlsrv_query($conn, "{call sp_Driver_ToggleAvailability(?, ?)}", array($_SESSION['UserID'], $_POST['target_status']));
    header("Refresh:0");
}

// --- 2. FETCH DRIVER STATS (FIXED SCHEMA LOGIC) ---
$dID = 0; $myZone = 0; $wallet = 0; $isAvailable = 1;

// We now find the Zone from the Active Vehicle, not the Driver Profile directly
$sql = "SELECT TOP 1 d.DriverID, d.WalletBalance, d.IsAvailable, v.Home_GeofenceID 
        FROM Driver_Profile d
        LEFT JOIN Vehicle v ON d.DriverID = v.Owner_DriverID AND v.Status = 'Active'
        WHERE d.UserID = ?";

$stmt = sqlsrv_query($conn, $sql, array($_SESSION['UserID']));

if($stmt && $r=sqlsrv_fetch_array($stmt)) { 
    $dID = $r['DriverID']; 
    $myZone = $r['Home_GeofenceID'] ? $r['Home_GeofenceID'] : 0; 
    $wallet = $r['WalletBalance']; 
    $isAvailable = $r['IsAvailable']; 
}

// Fetch Zone Name
$zoneName = "Unknown";
if($myZone) {
    $zStmt = sqlsrv_query($conn, "SELECT Name FROM Geofence WHERE GeofenceID=?", array($myZone));
    if($zR = sqlsrv_fetch_array($zStmt)) $zoneName = $zR['Name'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Driver Cockpit</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --bg-dark: #121212; --card-bg: #1e1e1e; --accent: #00ADB5; --text-light: #EEEEEE; }
        body { background-color: var(--bg-dark); color: var(--text-light); font-family: 'Segoe UI', Roboto, sans-serif; padding-bottom: 80px; }
        .navbar-custom { background: #000; padding: 15px; border-bottom: 1px solid #333; }
        .back-btn { background: rgba(255,255,255,0.1); color: white; border: none; border-radius: 30px; padding: 5px 15px; font-size: 14px; text-decoration: none; }
        
        /* Stats & Logs Bar */
        .stats-bar { display: flex; gap: 15px; overflow-x: auto; }
        .stat-pill { background: var(--card-bg); border: 1px solid #333; border-radius: 12px; padding: 10px 20px; min-width: 140px; display: flex; flex-direction: column; justify-content: center; }
        .stat-val { font-size: 18px; font-weight: 700; color: white; }
        
        /* Cards */
        .job-card { background: var(--card-bg); border-radius: 16px; padding: 20px; margin-bottom: 20px; border: 1px solid #333; box-shadow: 0 4px 20px rgba(0,0,0,0.3); }
        .active-job { border: 2px solid #f0ad4e; }
        .feed-card { border-left: 4px solid var(--accent); }
        
        /* Buttons */
        .btn-icon { width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; border: none; transition: 0.2s; }
        .btn-map { background: rgba(255,255,255,0.1); color: #fff; }
        .btn-map:hover { background: var(--accent); color: black; }
        .btn-chat { background: rgba(255,255,255,0.1); color: #fff; }
        .btn-chat:hover { background: #0d6efd; }
        .btn-action { width: 100%; padding: 12px; font-weight: 700; border-radius: 12px; text-transform: uppercase; margin-top: 10px; border:none; }
        .btn-accept { background: var(--accent); color: black; }
        .btn-complete { background: #d9534f; color: white; }
        
        /* Chat & Map */
        .chat-box { height: 300px; overflow-y: auto; background: #f9f9f9; padding: 15px; border-radius: 8px; margin-bottom: 10px; color: black; }
        .msg { margin-bottom: 10px; padding: 8px 12px; border-radius: 15px; font-size: 14px; max-width: 80%; }
        .msg-me { background: #00ADB5; color: white; margin-left: auto; border-bottom-right-radius: 2px; }
        .msg-other { background: #e0e0e0; color: black; margin-right: auto; border-bottom-left-radius: 2px; }
        #map-canvas { height: 400px; width: 100%; border-radius: 12px; }
        
        /* Status Toggles */
        .status-on { color: #5cb85c; border: 1px solid #5cb85c; padding: 5px 12px; border-radius: 20px; font-size: 12px; background:transparent; }
        .status-off { color: #d9534f; border: 1px solid #d9534f; padding: 5px 12px; border-radius: 20px; font-size: 12px; background:transparent; }
    </style>
</head>
<body>

<nav class="navbar-custom d-flex justify-content-between align-items-center">
    <div class="d-flex align-items-center">
        <a href="index.php" class="back-btn me-3"><i class="fas fa-arrow-left"></i> Map</a>
        <h5 class="m-0 fw-bold">Driver <span style="color:var(--accent)">App</span></h5>
    </div>
    <form method="POST" class="m-0">
        <input type="hidden" name="target_status" value="<?= $isAvailable ? '0' : '1' ?>">
        <button type="submit" name="toggle_status" class="btn btn-sm <?= $isAvailable ? 'status-on' : 'status-off' ?>">
            <i class="fas fa-power-off me-1"></i> <?= $isAvailable ? 'ONLINE' : 'BUSY' ?>
        </button>
    </form>
</nav>

<div class="container mt-3">
    
    <!-- STATS & LOGS BUTTON -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div class="stats-bar m-0">
            <div class="stat-pill"><span class="small text-muted">ZONE</span><span class="stat-val"><?= $zoneName ?></span></div>
            <div class="stat-pill"><span class="small text-muted">EARNINGS</span><span class="stat-val">€<?= number_format($wallet, 2) ?></span></div>
        </div>
        
        <!-- NEW BUTTON FOR LOGS -->
        <a href="driver_logs.php" class="btn btn-outline-light px-3 py-2">
            <i class="fas fa-list-alt me-2"></i> View Logs
        </a>
    </div>

    <?php
    // --- ACTIVE JOBS QUERY ---
    $sql = "SELECT rs.*, r.Passenger_UserID, u.Firstname, u.Lastname,
                   rs.Start_Lat, rs.Start_Lon, rs.End_Lat, rs.End_Lon, r.Ride_id 
            FROM RideSegment rs
            JOIN Ride r ON rs.RideID = r.Ride_id
            JOIN [User] u ON r.Passenger_UserID = u.UserID
            WHERE rs.DriverID = ? AND rs.Status IN ('Accepted', 'InProgress')";
    
    $stmt = sqlsrv_query($conn, $sql, array($dID));
    $hasActive = false;

    if($stmt) {
        while($row = sqlsrv_fetch_array($stmt)) {
            $hasActive = true;
            $isDriving = ($row['Status'] == 'InProgress') || ($row['Driver_Started'] == 1 && $row['Passenger_Started'] == 1);
        ?>
            <div class="job-card active-job">
                <div class="d-flex justify-content-between mb-3">
                    <span class="badge bg-warning text-dark">LEG <?= $row['SequenceOrder'] ?></span>
                    <div class="d-flex gap-2">
                        <button class="btn-icon btn-map" onclick="showRoute(<?= $row['Start_Lat'] ?>, <?= $row['Start_Lon'] ?>, <?= $row['End_Lat'] ?>, <?= $row['End_Lon'] ?>)"><i class="fas fa-route"></i></button>
                        <button class="btn-icon btn-chat" onclick="openChat(<?= $row['Ride_id'] ?>, '<?= $row['Firstname'] ?>')"><i class="fas fa-comment-alt"></i></button>
                    </div>
                </div>
                <h3 class="fw-bold"><?= $row['Firstname'] . ' ' . $row['Lastname'] ?></h3>
                <?php if($isDriving): ?>
                    <div class="alert alert-success bg-opacity-25 border-0 text-white p-2 small mb-3"><i class="fas fa-car-side me-2"></i> Passenger Onboard</div>
                    <form method="POST"><input type="hidden" name="segment_id" value="<?= $row['SegmentID'] ?>"><button name="complete" class="btn btn-action btn-complete">Complete Leg</button></form>
                <?php else: ?>
                    <?php if($row['Driver_Started'] == 0): ?>
                        <button onclick="confirmArr(<?= $row['SegmentID'] ?>)" class="btn btn-action" style="background:#f0ad4e; color:black;">I Have Arrived</button>
                    <?php else: ?>
                        <div class="text-center small text-info mt-2"><i class="fas fa-spinner fa-spin"></i> Waiting for Passenger...</div>
                        <button onclick="location.reload()" class="btn btn-sm btn-outline-secondary w-100 mt-2">Refresh</button>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        <?php 
        } 
    }
    ?>

    <?php if($isAvailable && !$hasActive): ?>
        <h6 class="text-muted small fw-bold mb-3">AVAILABLE REQUESTS</h6>
        <?php
        // sp_Driver_GetAvailableRides handles the Geofence Logic internally now
        $sql = "{call sp_Driver_GetAvailableRides(?)}";
        $stmt = sqlsrv_query($conn, $sql, array($_SESSION['UserID']));
        
        $cnt = 0;
        if($stmt) {
            while($row = sqlsrv_fetch_array($stmt)) { $cnt++; ?>
                <div class="job-card feed-card">
                    <div class="d-flex justify-content-between mb-2">
                        <span class="badge bg-secondary">Leg <?= $row['SequenceOrder'] ?></span>
                        <div class="d-flex gap-2">
                            <button class="btn-icon btn-map" onclick="showLocation(<?= $row['Start_Lat'] ?>, <?= $row['Start_Lon'] ?>)"><i class="fas fa-map-marker-alt"></i></button>
                            <button class="btn-icon btn-chat" onclick="openChat(<?= $row['Ride_id'] ?>, '<?= $row['Firstname'] ?>')"><i class="fas fa-comment-dots"></i></button>
                        </div>
                    </div>
                    <h5 class="fw-bold"><?= $row['PassengerName'] ?></h5>
                    <p class="text-muted small mb-3">Pickup: <?= $row['ZoneName'] ?></p>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="text-success fw-bold h5 m-0">€<?= number_format($row['EstimatedCost'], 2) ?></span>
                        <form method="POST" class="flex-grow-1 ms-3"><input type="hidden" name="segment_id" value="<?= $row['SegmentID'] ?>"><button name="accept" class="btn btn-action btn-accept mt-0">Accept</button></form>
                    </div>
                </div>
            <?php } 
        }
        if($cnt==0) echo "<div class='text-center py-5 opacity-50'><i class='fas fa-road fa-3x mb-3'></i><br>No requests nearby.</div>"; ?>
    <?php endif; ?>
    <?php if(!$isAvailable): ?><div class="text-center py-5 opacity-50"><h4><i class="fas fa-moon mb-2"></i> Busy</h4><p>Go Online to see jobs.</p></div><?php endif; ?>

</div>

<!-- Modals -->
<div class="modal fade" id="mapModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered"><div class="modal-content bg-dark text-white"><div class="modal-header border-secondary"><h5 class="modal-title">Location</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div><div class="modal-body p-0"><div id="map-canvas"></div></div></div></div>
</div>

<div class="modal fade" id="chatModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered"><div class="modal-content bg-dark text-white"><div class="modal-header border-secondary"><h5 class="modal-title">Chat with <span id="chatUser">User</span></h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div><div class="modal-body"><div id="chatBox" class="chat-box"></div><div class="input-group"><input type="text" id="chatInput" class="form-control" placeholder="Type a message..."><button class="btn btn-primary" onclick="sendMsg()"><i class="fas fa-paper-plane"></i></button></div></div></div></div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
let map, marker, directionsRenderer, directionsService;
let currentChatRideId = 0;
let chatInterval;

function initMap() {
    directionsService = new google.maps.DirectionsService();
    directionsRenderer = new google.maps.DirectionsRenderer();
    map = new google.maps.Map(document.getElementById("map-canvas"), { center: { lat: 35.1264, lng: 33.4299 }, zoom: 10, disableDefaultUI: true });
    directionsRenderer.setMap(map);
}

function showLocation(lat, lon) {
    const modal = new bootstrap.Modal(document.getElementById('mapModal'));
    modal.show();
    setTimeout(() => {
        const pt = { lat: parseFloat(lat), lng: parseFloat(lon) };
        map.setCenter(pt); map.setZoom(14); directionsRenderer.setDirections({routes: []});
        if(marker) marker.setMap(null);
        marker = new google.maps.Marker({ position: pt, map: map, animation: google.maps.Animation.DROP });
    }, 500);
}

function showRoute(startLat, startLon, endLat, endLon) {
    const modal = new bootstrap.Modal(document.getElementById('mapModal'));
    modal.show();
    setTimeout(() => {
        if(marker) marker.setMap(null);
        directionsService.route({ origin: { lat: parseFloat(startLat), lng: parseFloat(startLon) }, destination: { lat: parseFloat(endLat), lng: parseFloat(endLon) }, travelMode: google.maps.TravelMode.DRIVING }, (result, status) => { if (status == 'OK') directionsRenderer.setDirections(result); });
    }, 500);
}

function openChat(rideId, name) {
    currentChatRideId = rideId; document.getElementById('chatUser').innerText = name;
    const modal = new bootstrap.Modal(document.getElementById('chatModal')); modal.show();
    loadMessages(); if(chatInterval) clearInterval(chatInterval); chatInterval = setInterval(loadMessages, 2000);
}

function loadMessages() {
    if(!currentChatRideId) return;
    fetch(`chat_api.php?ride_id=${currentChatRideId}`).then(r => r.json()).then(data => {
        let h = ''; data.forEach(m => { h += `<div class="msg ${m.isMe ? 'msg-me' : 'msg-other'}"><small class="d-block text-muted" style="font-size:10px">${m.user}</small>${m.text}</div>`; });
        const box = document.getElementById('chatBox'); box.innerHTML = h; box.scrollTop = box.scrollHeight;
    });
}

function sendMsg() {
    const txt = document.getElementById('chatInput').value; if(!txt) return;
    fetch('chat_api.php', { method: 'POST', body: new URLSearchParams({ action: 'send', ride_id: currentChatRideId, text: txt }) }).then(() => { document.getElementById('chatInput').value = ''; loadMessages(); });
}

function confirmArr(id) { fetch('api_ride_action.php', { method: 'POST', body: new URLSearchParams({action:'driver_confirm', segment_id:id}) }).then(() => setTimeout(() => location.reload(), 500)); }
</script>
<script async defer src="https://maps.googleapis.com/maps/api/js?key=AIzaSyCj0Gp3U1CWFd6hadGEbjbSl1KeOAHVE9Q&callback=initMap"></script>
</body>
</html>