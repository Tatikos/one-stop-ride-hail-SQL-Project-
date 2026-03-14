<?php
// 1. CLEAR BUFFER (Crucial for clean JSON)
ob_start();
include 'db_connect.php';
ob_clean(); // Discard any warnings/text outputted by includes

header('Content-Type: application/json');

// 2. ERROR HANDLING
if(!isset($_GET['id']) || !isset($_GET['type'])) {
    echo json_encode(['error' => 'Missing parameters']);
    exit();
}

$id = $_GET['id'];
$type = $_GET['type']; // 'Driver' or 'Vehicle'

// 3. CALL PROCEDURE
$sql = "{call sp_Admin_GetDocuments(?, ?)}";
$params = array($id, $type);
$stmt = sqlsrv_query($conn, $sql, $params);

if($stmt === false) {
    // Return SQL error as JSON instead of crashing
    echo json_encode(['error' => print_r(sqlsrv_errors(), true)]);
    exit();
}

// 4. FETCH DATA
$docs = [];
while($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $date = $row['UploadDate'] ? $row['UploadDate']->format('Y-m-d') : 'N/A';
    
    $docs[] = [
        'category' => $row['Category'] ?? 'General',
        'type' => $row['DocumentType'],
        'path' => $row['FilePath'],
        'date' => $date
    ];
}

// 5. OUTPUT
echo json_encode($docs);
?>