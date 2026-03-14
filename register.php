<?php 
include 'db_connect.php'; 
$error = "";
$success = "";

if(isset($_POST['register'])) {
    // 1. Capture all fields from ER Diagram
    $fname = $_POST['fname'];
    $lname = $_POST['lname'];
    $email = $_POST['email'];
    $pass  = password_hash($_POST['pass'], PASSWORD_DEFAULT); // Secure Hash
    $dob   = $_POST['dob'];
    $gender = $_POST['gender'];
    $address = $_POST['address'];

    // 2. Call the updated Stored Procedure
    $tsql = "{call sp_RegisterUser(?, ?, ?, ?, ?, ?, ?)}";
    $params = array($fname, $lname, $email, $pass, $dob, $gender, $address);

    $stmt = sqlsrv_query($conn, $tsql, $params);

    if($stmt) {
        // Redirect to login after success
        header("Location: login.php?registered=1");
        exit();
    } else {
        // Handle duplicate email errors nicely
        $errors = sqlsrv_errors();
        if(strpos(print_r($errors, true), 'UNIQUE KEY') !== false) {
            $error = "That email is already registered.";
        } else {
            $error = "Registration failed. Please check your inputs.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Sign Up - OSRH</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <style>
        /* Small override to make the register box wider for the 2-column layout */
        .login-box { max-width: 500px; }
    </style>
</head>
<body>

    <div class="login-wrapper">
        <div class="login-box">
            <h2 class="fw-bold mb-2">Join OSRH</h2>
            <p class="text-muted mb-4">Create an account to get moving</p>

            <?php if($error): ?>
                <div class="alert alert-danger py-2 small"><?= $error ?></div>
            <?php endif; ?>

            <form method="POST">
                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <input type="text" name="fname" class="form-control form-control-lg" placeholder="First Name" required>
                    </div>
                    <div class="col-6">
                        <input type="text" name="lname" class="form-control form-control-lg" placeholder="Last Name" required>
                    </div>
                </div>

                <div class="mb-3">
                    <input type="email" name="email" class="form-control form-control-lg" placeholder="name@example.com" required>
                </div>
                <div class="mb-3">
                    <input type="password" name="pass" class="form-control form-control-lg" placeholder="Password" required>
                </div>

                <div class="mb-3">
                    <label class="form-label small text-muted fw-bold">Date of Birth</label>
                    <input type="date" name="dob" class="form-control" required>
                </div>

                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <select name="gender" class="form-select form-select-lg" required>
                            <option value="" disabled selected>Gender</option>
                            <option value="Male">Male</option>
                            <option value="Female">Female</option>
                            <option value="Non-Binary">Non-Binary</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    <div class="col-6">
                        <input type="text" name="address" class="form-control form-control-lg" placeholder="City / Address" required>
                    </div>
                </div>

                <button type="submit" name="register" class="btn btn-uber mt-2">Sign Up</button>
            </form>

            <div class="mt-4 text-center">
                <span class="text-muted">Already have an account?</span>
                <a href="login.php" class="text-decoration-none fw-bold text-dark">Log in</a>
            </div>
        </div>
    </div>

</body>
</html>