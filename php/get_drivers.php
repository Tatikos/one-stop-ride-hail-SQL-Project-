<?php
include 'db_connect.php';
header('Content-Type: application/json');

if(isset($_GET['lat']) && isset($_GET['lon'])) {
    $lat = $_GET['lat'];
    $lon = $_GET['lon'];
    $isAuto = isset($_GET['type']) && $_GET['type'] === 'auto' ? 1 : 0;

    $sql = "{call sp_GetNearbyDrivers(?, ?, ?)}";
    $stmt = sqlsrv_query($conn, $sql, array($lat, $lon, $isAuto));

    $drivers = [];
    if($stmt) {
        while($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $drivers[] = [
                'id' => $row['DriverID'],
                'name' => $isAuto ? $row['Model'] : $row['Firstname'] . ' ' . substr($row['Lastname'], 0, 1) . '.',
                'car' => $row['Model'],
                'plate' => $row['RegistrationPlate'],
                'service' => $row['ServiceType']
            ];
        }
    }
    echo json_encode($drivers);
}
?>