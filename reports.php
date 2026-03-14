<?php include 'db_connect.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>OSRH - Data Center</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>body { background-color: #f8f9fa; padding: 20px; }</style>
</head>
<body>

<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="fas fa-database me-2"></i> Data Center</h2>
        <a href="admin.php" class="btn btn-outline-dark">Back to Dashboard</a>
    </div>

    <!-- TABS NAVIGATION -->
    <ul class="nav nav-tabs mb-4" id="reportTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="analysis-tab" data-bs-toggle="tab" data-bs-target="#analysis" type="button">
                <i class="fas fa-chart-pie me-2"></i> Performance Analysis
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="finance-tab" data-bs-toggle="tab" data-bs-target="#finance" type="button">
                <i class="fas fa-wallet me-2"></i> Financial Ledger
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="audit-tab" data-bs-toggle="tab" data-bs-target="#audit" type="button">
                <i class="fas fa-shield-alt me-2"></i> System Audit
            </button>
        </li>
    </ul>

    <div class="tab-content" id="reportTabsContent">
        
        <!-- TAB 1: DYNAMIC REPORTS (Grading Requirement) -->
        <div class="tab-pane fade show active" id="analysis">
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-dark text-white">
                    <h5 class="mb-0">Report Criteria</h5>
                </div>
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label fw-bold">Filter by Start Date</label>
                            <input type="date" name="start_date" class="form-control" value="<?= $_GET['start_date'] ?? '' ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold">Filter by Service</label>
                            <select name="service_type" class="form-select">
                                <option value="">All Services</option>
                                <option value="1" <?= (isset($_GET['service_type']) && $_GET['service_type']=='1') ? 'selected' : '' ?>>Simple Route</option>
                                <option value="2" <?= (isset($_GET['service_type']) && $_GET['service_type']=='2') ? 'selected' : '' ?>>Luxury Route</option>
                                <option value="3" <?= (isset($_GET['service_type']) && $_GET['service_type']=='3') ? 'selected' : '' ?>>Light Cargo</option>
                                <option value="4" <?= (isset($_GET['service_type']) && $_GET['service_type']=='4') ? 'selected' : '' ?>>Heavy Cargo</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold text-primary">Group By</label>
                            <select name="group_by" class="form-select border-primary">
                                <option value="None">No Grouping (Total)</option>
                                <option value="Day" <?= (isset($_GET['group_by']) && $_GET['group_by']=='Day') ? 'selected' : '' ?>>Day</option>
                                <option value="Service" <?= (isset($_GET['group_by']) && $_GET['group_by']=='Service') ? 'selected' : '' ?>>Service Type</option>
                                <option value="Driver" <?= (isset($_GET['group_by']) && $_GET['group_by']=='Driver') ? 'selected' : '' ?>>Driver Name</option>
                            </select>
                        </div>
                        <div class="col-12 text-end pt-3">
                            <button type="submit" class="btn btn-dark px-4">Generate Report</button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card shadow-sm">
                <div class="card-body p-0">
                    <table class="table table-striped table-hover mb-0">
                        <thead class="table-dark">
                            <tr>
                                <th><?= isset($_GET['group_by']) && $_GET['group_by'] != 'None' ? htmlspecialchars($_GET['group_by']) : 'Summary' ?></th>
                                <th class="text-center">Total Rides</th>
                                <th class="text-end">Total Revenue</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $start = !empty($_GET['start_date']) ? $_GET['start_date'] : null;
                            $svc   = !empty($_GET['service_type']) ? $_GET['service_type'] : null;
                            $group = !empty($_GET['group_by']) ? $_GET['group_by'] : 'None';

                            $sql = "{call sp_Report_Flexible(?, ?, ?)}";
                            $stmt = sqlsrv_query($conn, $sql, array($start, $svc, $group));

                            if($stmt) {
                                $grandTotal = 0;
                                while($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                                    $grandTotal += $row['TotalRevenue'];
                                    $gName = $row['GroupKey'] ?: 'Unknown';
                                    echo "<tr><td class='fw-bold'>$gName</td><td class='text-center'>{$row['TotalRides']}</td><td class='text-end'>€".number_format($row['TotalRevenue'], 2)."</td></tr>";
                                }
                                echo "<tr class='table-active fw-bold border-top border-dark'><td>GRAND TOTAL</td><td>-</td><td class='text-end'>€".number_format($grandTotal, 2)."</td></tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- TAB 2: FINANCIAL LOGS (Restored) -->
        <div class="tab-pane fade" id="finance">
            <div class="card shadow-sm">
                <div class="card-header bg-success text-white fw-bold">Transaction History</div>
                <div class="table-responsive" style="max-height: 600px; overflow-y: auto;">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Timestamp</th>
                                <th>Method</th>
                                <th>Payer</th>
                                <th>Payee</th>
                                <th class="text-end">Total</th>
                                <th class="text-end">Fee</th>
                                <th class="text-end">Net</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $sql = "{call sp_Admin_GetPaymentLogs}";
                            $stmt = sqlsrv_query($conn, $sql);
                            if($stmt) {
                                while($r = sqlsrv_fetch_array($stmt)) {
                                    $time = $r['Timestamp']->format('d M Y, H:i');
                                    echo "<tr>
                                        <td class='text-muted small'>$time</td>
                                        <td><i class='fab fa-cc-visa text-primary'></i> {$r['PaymentMethod']}</td>
                                        <td>{$r['PayerName']}</td>
                                        <td>{$r['PayeeName']}</td>
                                        <td class='text-end fw-bold'>€".number_format($r['TotalAmount'], 2)."</td>
                                        <td class='text-end text-danger small'>-€".number_format($r['PlatformFee'], 2)."</td>
                                        <td class='text-end fw-bold text-success'>€".number_format($r['DriverEarnings'], 2)."</td>
                                    </tr>";
                                }
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- TAB 3: SYSTEM AUDIT LOGS (Restored) -->
        <div class="tab-pane fade" id="audit">
            <div class="card shadow-sm">
                <div class="card-header bg-secondary text-white fw-bold">Ride Audit Trail</div>
                <div class="table-responsive" style="max-height: 600px; overflow-y: auto;">
                    <table class="table table-striped mb-0 small">
                        <thead><tr><th>Time</th><th>Ride ID</th><th>Status Change</th><th>Actor</th></tr></thead>
                        <tbody>
                            <?php
                            $sql = "{call sp_Admin_GetRideLogs}";
                            $stmt = sqlsrv_query($conn, $sql);
                            if($stmt) {
                                while($r = sqlsrv_fetch_array($stmt)) {
                                    $t = $r['ChangeDate'] ? $r['ChangeDate']->format('H:i:s') : '-';
                                    echo "<tr>
                                        <td>{$t}</td>
                                        <td>#{$r['RideID']}</td>
                                        <td>{$r['OldStatus']} &rarr; <b>{$r['NewStatus']}</b></td>
                                        <td class='text-muted'>{$r['ChangedBy']}</td>
                                    </tr>";
                                }
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>