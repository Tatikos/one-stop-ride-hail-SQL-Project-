<?php 
include 'db_connect.php'; 

// 1. SECURITY CHECK
if (!isset($_SESSION['UserID'])) {
    header("Location: login.php");
    exit();
}

$userID = $_SESSION['UserID'];
$userName = "User";
$hasAccess = false; // Admin or Operator

// 2. GET USER DATA & CHECK ROLES
$sql = "SELECT u.Firstname, r.Name as RoleName 
        FROM [User] u
        LEFT JOIN User_Role ur ON u.UserID = ur.UserID
        LEFT JOIN Role r ON ur.Role_id = r.Role_id
        WHERE u.UserID = ?";

$stmt = sqlsrv_query($conn, $sql, array($userID));

// Loop through roles (User might have multiple)
while($row = sqlsrv_fetch_array($stmt)) {
    $userName = $row['Firstname']; 
    if($row['RoleName'] === 'Admin' || $row['RoleName'] === 'Operator') {
        $hasAccess = true;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>OSRH - Request Ride</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
</head>
<body>

    <nav class="navbar-custom">
        <a href="index.php" class="navbar-brand">OSRH</a>
        <div class="d-flex align-items-center">
            <span class="me-3 small text-light">Hello, <?= htmlspecialchars($userName) ?></span>
            
            <a href="drive.php" class="nav-link">Drive</a>
            
            <!-- SHOW MANAGEMENT BUTTON FOR ADMIN OR OPERATOR -->
            <?php if($hasAccess): ?>
                <a href="admin.php" class="nav-link text-warning fw-bold">
                    <i class="fas fa-crown me-1"></i> Management
                </a>
            <?php endif; ?>
            
            <a href="logout.php" class="nav-link text-danger fw-bold">Logout</a>
        </div>
    </nav>

    <div id="map"></div>

    <div class="sidebar">
        <div class="sidebar-header">
            <h4 class="m-0 fw-bold">Where to?</h4>
        </div>

        <div class="sidebar-content">
            <form action="book_ride.php" method="POST" id="rideForm">
                
                <div class="location-group mb-4">
                    <div class="d-flex align-items-center mb-2">
                        <i class="fas fa-circle text-black me-3 small"></i>
                        <input type="text" id="from_address" class="form-control form-control-clean" placeholder="Add a pickup location" required autocomplete="off">
                    </div>
                    <div class="dotted-line"></div>
                    <div class="d-flex align-items-center mt-2">
                        <i class="fas fa-square text-black me-3 small"></i>
                        <input type="text" id="to_address" class="form-control form-control-clean" placeholder="Enter destination" required autocomplete="off">
                    </div>
                </div>

                <input type="hidden" name="start_lat" id="start_lat">
                <input type="hidden" name="start_lon" id="start_lon">
                <input type="hidden" name="end_lat" id="end_lat">
                <input type="hidden" name="end_lon" id="end_lon">
                <input type="hidden" name="distance_km" id="distance_km">
                <input type="hidden" name="service_type" id="selected_service_type">

                <div id="route-stats" class="d-none mb-3 d-flex justify-content-between fw-bold px-2">
                    <span id="disp_time" class="badge bg-dark">0 min</span>
                    <span id="disp_dist" class="text-muted">0 km</span>
                </div>

                <div id="car-list" class="mb-3">
                    <p class="text-muted small mb-1">Select Service</p>
                    <?php
                    $sql = "SELECT * FROM Service_Type";
                    $stmt = sqlsrv_query($conn, $sql);
                    while($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                        echo "
                        <div class='car-option' 
                             onclick='selectCar(this, ".$row['Service_type_id'].")'
                             data-base='".$row['MinimumFare']."'
                             data-rate='".$row['PerKilometerRate']."'>
                            <div class='d-flex align-items-center'>
                                <div class='car-image-placeholder'><i class='fas fa-car fa-lg'></i></div>
                                <div>
                                    <div class='fw-bold'>".$row['Name']."</div>
                                    <div class='small text-muted'>".$row['Description']."</div>
                                </div>
                            </div>
                            <div class='fw-bold'>€<span class='dynamic-price'>".$row['MinimumFare']."</span></div>
                        </div>";
                    }
                    ?>
                </div>

                <button type="submit" class="btn-uber mb-3" id="bookBtn" disabled>Request Ride</button>

                <div class="border-top pt-3">
                    <div class="row g-2 mt-2">
                        <div class="col-6">
                            <a href="history.php" class="btn btn-outline-dark btn-sm w-100">
                                <i class="fas fa-history"></i> My Rides
                            </a>
                        </div>
                     <div class="col-6">
                        <a href="profile.php" class="btn btn-outline-dark btn-sm w-100">
                            <i class="fas fa-user"></i> Profile
                        </a>
                    </div>
                </div>
                    <div class="row g-2">
                        <div class="col-12">
                            <a href="drive.php" class="btn btn-outline-dark btn-sm w-100">
                                <i class="fas fa-taxi"></i> Driver App
                            </a>
                        </div>
                    </div>
                </div>

            </form>
        </div>
    </div>

    <script>
        let map, directionsService, directionsRenderer, geocoder;
        let activeInput = 'from';

        function initMap() {
            map = new google.maps.Map(document.getElementById("map"), {
                center: { lat: 35.1264, lng: 33.4299 },
                zoom: 9,
                disableDefaultUI: true,
                clickableIcons: false
            });

            directionsService = new google.maps.DirectionsService();
            directionsRenderer = new google.maps.DirectionsRenderer({ 
                map: map,
                polylineOptions: { strokeColor: "#000", strokeWeight: 5 }
            });
            geocoder = new google.maps.Geocoder();

            setupAutocomplete('from_address', 'start');
            setupAutocomplete('to_address', 'end');

            map.addListener("click", (e) => { handleMapClick(e.latLng); });

            document.getElementById('from_address').addEventListener('focus', () => activeInput = 'from');
            document.getElementById('to_address').addEventListener('focus', () => activeInput = 'to');
        }

        function handleMapClick(latLng) {
            const type = (activeInput === 'from') ? 'start' : 'end';
            document.getElementById(type + '_lat').value = latLng.lat();
            document.getElementById(type + '_lon').value = latLng.lng();

            geocoder.geocode({ location: latLng }, (results, status) => {
                if (status === "OK" && results[0]) {
                    document.getElementById(activeInput + '_address').value = results[0].formatted_address;
                    if(activeInput === 'from') {
                        activeInput = 'to';
                        document.getElementById('to_address').focus(); 
                    } else {
                        calculateRoute();
                    }
                }
            });
        }

        function setupAutocomplete(inputId, type) {
            const input = document.getElementById(inputId);
            const autocomplete = new google.maps.places.Autocomplete(input);
            autocomplete.bindTo("bounds", map);

            autocomplete.addListener("place_changed", () => {
                const place = autocomplete.getPlace();
                if (!place.geometry) return;
                document.getElementById(type + '_lat').value = place.geometry.location.lat();
                document.getElementById(type + '_lon').value = place.geometry.location.lng();
                if (type === 'start') {
                    document.getElementById('to_address').focus();
                    activeInput = 'to';
                } else {
                    calculateRoute();
                }
            });
        }

        function calculateRoute() {
            const startLat = document.getElementById('start_lat').value;
            const endLat = document.getElementById('end_lat').value;

            if(startLat && endLat) {
                const start = new google.maps.LatLng(startLat, document.getElementById('start_lon').value);
                const end = new google.maps.LatLng(endLat, document.getElementById('end_lon').value);
                const request = { origin: start, destination: end, travelMode: google.maps.TravelMode.DRIVING };

                directionsService.route(request, (result, status) => {
                    if (status == google.maps.DirectionsStatus.OK) {
                        directionsRenderer.setDirections(result);
                        const route = result.routes[0].legs[0];
                        const distKm = (route.distance.value / 1000).toFixed(2);

                        document.getElementById('route-stats').classList.remove('d-none');
                        document.getElementById('disp_dist').innerText = route.distance.text;
                        document.getElementById('disp_time').innerText = route.duration.text;
                        document.getElementById('distance_km').value = distKm;

                        updatePrices(distKm);
                        checkForm();
                    }
                });
            }
        }

        function updatePrices(km) {
            document.querySelectorAll('.car-option').forEach(opt => {
                const base = parseFloat(opt.getAttribute('data-base'));
                const rate = parseFloat(opt.getAttribute('data-rate'));
                let price = base + (rate * km);
                if(price < base) price = base;
                opt.querySelector('.dynamic-price').innerText = price.toFixed(2);
            });
        }

        function selectCar(el, id) {
            document.querySelectorAll('.car-option').forEach(c => c.classList.remove('selected'));
            el.classList.add('selected');
            document.getElementById('selected_service_type').value = id;
            checkForm();
        }

        function checkForm() {
            const km = document.getElementById('distance_km').value;
            const svc = document.getElementById('selected_service_type').value;
            if(km && svc) {
                document.getElementById('bookBtn').disabled = false;
            }
        }
    </script>

    <script async defer 
        src="https://maps.googleapis.com/maps/api/js?key=AIzaSyCj0Gp3U1CWFd6hadGEbjbSl1KeOAHVE9Q&libraries=places&callback=initMap"> //use .env file for API key in production!
    </script>

</body>
</html>