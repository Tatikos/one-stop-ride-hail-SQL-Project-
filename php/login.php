<?php 
include 'db_connect.php'; 
$error = "";

// Handle Login Logic
if(isset($_POST['login'])) {
    $email = $_POST['email'];
    $pass  = $_POST['pass']; // Capture raw password

    // 1. Fetch the Hash from DB
    $sql = "SELECT UserID, PasswordHash, Firstname FROM [User] WHERE Email = ? AND IsDeleted = 0";
    $stmt = sqlsrv_query($conn, $sql, array($email));
    
    if ($stmt === false) {
        $error = "System Error: " . print_r(sqlsrv_errors(), true);
    } 
    else {
        // 2. Check if user exists
        if ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $dbHash = $row['PasswordHash'];
            if (password_verify($pass, $dbHash)) {
                // SUCCESS
                $_SESSION['UserID'] = $row['UserID'];
                $_SESSION['Name'] = $row['Firstname'];
                header("Location: index.php");
                exit();
            } 
            elseif ($dbHash === '$2y$10$DummyHashForSpeed' && $pass === 'password') {
                 $_SESSION['UserID'] = $row['UserID'];
                 $_SESSION['Name'] = $row['Firstname'];
                 header("Location: index.php");
                 exit();
            }
            else {
                $error = "Incorrect password.";
            }
        } else {
            $error = "We couldn't find an account with that email.";
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Sign In - OSRH</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="login-wrapper">
        <div class="login-box">
            <h2 class="fw-bold mb-4">OSRH</h2>
            <h4 class="mb-3">Sign In</h4>
            
            <?php if($error): ?>
                <div class="alert alert-danger py-2 small"><?= $error ?></div>
            <?php endif; ?>

            <?php if(isset($_GET['registered'])): ?>
                <div class="alert alert-success py-2 small">Account created! Please log in.</div>
            <?php endif; ?>

            <form method="POST">
                <div class="mb-3">
                    <input type="email" name="email" class="form-control form-control-lg" placeholder="name@example.com" required autofocus>
                </div>
                <div class="mb-4">
                    <input type="password" name="pass" class="form-control form-control-lg" placeholder="Password" required>
                </div>
                
                <button type="submit" name="login" class="btn btn-uber">Continue</button>
            </form>
            
            <div class="mt-4 text-center">
                <span class="text-muted">New here?</span>
                <a href="register.php" class="text-decoration-none fw-bold text-dark">Create account</a>
            </div>
            
            <div class="mt-3 text-center small text-muted">
                (Dev Note: Generated users use password: <b>password</b>)
            </div>
        </div>
    </div>
</body>
</html>
