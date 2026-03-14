<?php 
// 1. ENABLE ERROR REPORTING
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include 'db_connect.php'; 

// 2. CHECK RIDE ID
if(!isset($_GET['id'])) {
    die("Error: No Ride ID provided. <a href='index.php'>Go Back</a>");
}

$rideID = $_GET['id'];
$userName = $_SESSION['Name'] ?? 'Me';

// Fetch Driver Name for the Chat Title
$driverName = "Driver";
$sql = "SELECT TOP 1 u.Firstname 
        FROM RideSegment rs
        JOIN Driver_Profile dp ON rs.DriverID = dp.DriverID
        JOIN [User] u ON dp.UserID = u.UserID
        WHERE rs.RideID = ? AND rs.DriverID IS NOT NULL";
$stmt = sqlsrv_query($conn, $sql, array($rideID));
if($r = sqlsrv_fetch_array($stmt)) {
    $driverName = $r['Firstname'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Tracking Ride #<?= $rideID ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body { background: #f3f3f3; font-family: 'Segoe UI', sans-serif; }
        .tracking-card { max-width: 500px; margin: 30px auto; background: white; padding: 30px; border-radius: 15px; box-shadow: 0 10px 30px rgba(0,0,0,0.1); }
        .radar { width: 60px; height: 60px; background: #000; border-radius: 50%; margin: 0 auto 15px; animation: pulse 2s infinite; display: flex; align-items: center; justify-content: center; color: white; font-size: 24px; }
        @keyframes pulse { 0% { box-shadow: 0 0 0 0 rgba(0,0,0,0.4); } 70% { box-shadow: 0 0 0 20px rgba(0,0,0,0); } 100% { box-shadow: 0 0 0 0 rgba(0,0,0,0); } }
        .leg-badge { font-size: 12px; background: #eee; padding: 5px 10px; border-radius: 20px; display: inline-block; margin-bottom: 10px; font-weight: bold; color: #555; }
        
        /* Chat Styles */
        .chat-box { height: 300px; overflow-y: auto; background: #f9f9f9; padding: 15px; border-radius: 8px; margin-bottom: 10px; color: black; text-align: left; }
        .msg { margin-bottom: 10px; padding: 8px 12px; border-radius: 15px; font-size: 14px; max-width: 80%; }
        .msg-me { background: #00ADB5; color: white; margin-left: auto; border-bottom-right-radius: 2px; }
        .msg-other { background: #e0e0e0; color: black; margin-right: auto; border-bottom-left-radius: 2px; }
        .btn-chat-float { position: fixed; bottom: 20px; right: 20px; width: 60px; height: 60px; border-radius: 50%; background: #00ADB5; color: white; font-size: 24px; border: none; box-shadow: 0 4px 15px rgba(0,0,0,0.2); z-index: 1000; }
        .btn-chat-float:hover { transform: scale(1.1); background: #00939b; }
        
        .driver-info { display: flex; align-items: center; justify-content: center; margin-bottom: 20px; }
        .driver-avatar { width: 40px; height: 40px; background: #eee; border-radius: 50%; margin-right: 10px; display: flex; align-items: center; justify-content: center; }
    </style>
</head>
<body>

<div class="container">
    <div class="tracking-card text-center">
        <div class="d-flex justify-content-between mb-3">
             <a href="index.php" class="btn btn-sm btn-outline-secondary">&larr; Map</a>
             <span class="text-muted small">Ride #<?= $rideID ?></span>
        </div>

        <div id="leg-indicator" class="leg-badge">Initializing...</div>

        <div id="status-icon" class="radar"><i class="fas fa-search"></i></div>
        <h4 id="status-text" class="mt-3">Connecting...</h4>
        <p id="sub-status" class="text-muted small">Checking ride status</p>
        
        <div class="progress mb-3" style="height: 5px;">
            <div id="prog-bar" class="progress-bar bg-dark progress-bar-striped progress-bar-animated" style="width: 100%"></div>
        </div>

        <div id="action-area" style="display:none;">
            <div class="alert alert-success border-0 shadow-sm mb-3 text-start">
                <div class="d-flex align-items-center">
                    <div class="driver-avatar"><i class="fas fa-user"></i></div>
                    <div>
                        <strong><?= $driverName ?> Arrived!</strong><br>
                        <small>Please confirm when you are inside.</small>
                    </div>
                </div>
            </div>
            
            <button id="confirmBtn" class="btn btn-success w-100 py-3 fw-bold" onclick="confirmPickup()">
                <i class="fas fa-thumbs-up me-2"></i> I AM IN THE CAR
            </button>
            
            <div id="waitingMsg" class="alert alert-warning small mt-2" style="display:none;">
                <i class="fas fa-spinner fa-spin"></i> Waiting for driver confirmation...
            </div>
        </div>
        
        <button class="btn btn-outline-dark w-100 mt-3" onclick="openChat()">
            <i class="fas fa-comments me-2"></i> Message Driver
        </button>
    </div>
</div>

<div class="modal fade" id="chatModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title">Chat with Driver</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="chatBox" class="chat-box"></div>
                <div class="input-group">
                    <input type="text" id="chatInput" class="form-control" placeholder="Type a message...">
                    <button class="btn btn-dark" onclick="sendMsg()"><i class="fas fa-paper-plane"></i></button>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
const rideId = <?= $rideID ?>;
let currentLeg = 0;
let chatInterval;

// --- STATUS POLLING ---
setInterval(() => {
    fetch(`status_api.php?ride_id=${rideId}&t=${Date.now()}`)
        .then(response => {
            if (!response.ok) { throw new Error("Network response was not ok"); }
            return response.json();
        })
        .then(data => {
            if(data.status === 'Completed') {
                document.body.innerHTML = `
                <div class='container mt-5'><div class='tracking-card text-center'>
                    <div class='mb-3 text-success'><i class='fas fa-check-circle fa-4x'></i></div>
                    <h3>Destination Reached!</h3>
                    <p class='text-muted'>Thank you for riding with OSRH.</p>
                    <a href='index.php' class='btn btn-dark w-100 mt-3'>Book New Ride</a>
                </div></div>`;
                return;
            }

            // Update Leg Info
            if(data.leg && data.leg !== currentLeg) {
                currentLeg = data.leg;
                document.getElementById('leg-indicator').innerText = `CURRENT LEG: ${currentLeg}`;
            }

            // UI Logic
            if(data.status === 'Accepted') {
                updateUI(`Driver Arrived`, '#28a745', '<i class="fas fa-taxi"></i>');
                document.getElementById('action-area').style.display = 'block';
                document.getElementById('prog-bar').className = 'progress-bar bg-success w-100';
                
                if(data.p_started == 0) {
                    document.getElementById('confirmBtn').style.display = 'block';
                    document.getElementById('waitingMsg').style.display = 'none';
                } else {
                    document.getElementById('confirmBtn').style.display = 'none';
                    document.getElementById('waitingMsg').innerHTML = "Waiting for driver to start Leg " + currentLeg;
                    document.getElementById('waitingMsg').style.display = 'block';
                }
            }
            else if(data.status === 'InProgress') {
                updateUI(`En Route`, '#0d6efd', '<i class="fas fa-route"></i>');
                document.getElementById('action-area').style.display = 'none';
                document.getElementById('prog-bar').className = 'progress-bar bg-primary w-100';
            }
            else if(data.status === 'Requested') {
                updateUI(`Finding Driver...`, '#ffc107', '<i class="fas fa-search"></i>');
                document.getElementById('action-area').style.display = 'none';
                document.getElementById('prog-bar').className = 'progress-bar bg-warning progress-bar-striped progress-bar-animated w-100';
            }
        })
        .catch(err => console.error("Fetch Error:", err));
}, 2000);

function updateUI(text, color, icon) {
    document.getElementById('status-text').innerText = text;
    const ico = document.getElementById('status-icon');
    ico.style.background = color; 
    ico.innerHTML = icon;
    ico.style.animation = 'none';
}

function confirmPickup() {
    const btn = document.getElementById('confirmBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';

    fetch('api_ride_action.php', { 
        method: 'POST', 
        body: new URLSearchParams({action:'passenger_confirm', ride_id:rideId}) 
    })
    .then(r => r.json())
    .then(data => {
        location.reload(); 
    });
}

// --- CHAT LOGIC ---
function openChat() {
    const modal = new bootstrap.Modal(document.getElementById('chatModal'));
    modal.show();
    loadMessages();
    if(chatInterval) clearInterval(chatInterval);
    chatInterval = setInterval(loadMessages, 2000);
}

function loadMessages() {
    fetch(`chat_api.php?ride_id=${rideId}`)
        .then(r => r.json())
        .then(data => {
            let h = '';
            data.forEach(m => {
                h += `<div class="msg ${m.isMe ? 'msg-me' : 'msg-other'}">
                        <small class="d-block text-muted" style="font-size:10px">${m.user}</small>
                        ${m.text}
                      </div>`;
            });
            const box = document.getElementById('chatBox');
            box.innerHTML = h;
            box.scrollTop = box.scrollHeight;
        });
}

function sendMsg() {
    const txt = document.getElementById('chatInput').value;
    if(!txt) return;
    
    fetch('chat_api.php', {
        method: 'POST',
        body: new URLSearchParams({
            action: 'send',
            ride_id: rideId,
            text: txt
        })
    }).then(() => {
        document.getElementById('chatInput').value = '';
        loadMessages();
    });
}

// Allow Enter key to send
document.getElementById('chatInput').addEventListener('keypress', function (e) {
    if (e.key === 'Enter') sendMsg();
});
</script>
</body>
</html>