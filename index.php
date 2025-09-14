<?php
session_start();
include("connection/config.php");

$login_error = "";
$login_success = false;
$redirect_url = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    // Check admin table
    $stmt = $pdo->prepare("SELECT * FROM admins WHERE username = ?");
    $stmt->execute([$username]);
    $admin = $stmt->fetch();

    // Check lecturer table if not found in admin
    if (!$admin) {
        $stmt = $pdo->prepare("SELECT * FROM lecturers WHERE username = ?");
        $stmt->execute([$username]);
        $lecturer = $stmt->fetch();
    }

    // Verify password
    if ($admin && password_verify($password, $admin['password'])) {
        $_SESSION['user_type'] = 'admin';
        $_SESSION['user_id'] = $admin['admin_id'];
        $_SESSION['username'] = $admin['username'];
        $login_success = true;
        $redirect_url = "admin-dashboard.php";
    } elseif (isset($lecturer) && $lecturer && password_verify($password, $lecturer['password'])) {
        $_SESSION['user_type'] = 'lecturer';
        $_SESSION['user_id'] = $lecturer['lecturer_id'];
        $_SESSION['username'] = $lecturer['username'];
        $login_success = true;
        $redirect_url = "lecturer-dashboard.php";
    } else {
        $login_error = "Invalid username or password";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  <title>BioAttend</title>
  <link rel="stylesheet" href="vendors/iconfonts/font-awesome/css/all.min.css">
  <link rel="stylesheet" href="vendors/css/vendor.bundle.base.css">
  <link rel="stylesheet" href="vendors/css/vendor.bundle.addons.css">
  <link rel="stylesheet" href="css/style.css">
  <link rel="shortcut icon" href="images/LOGO.jpg" />
  <style>
    .toast {
      position: fixed;
      top: 100px;
      right: 30px;
      min-width: 250px;
      padding: 15px;
      background-color: #333;
      color: white;
      border-radius: 4px;
      box-shadow: 0 4px 12px rgba(0,0,0,0.15);
      z-index: 1000;
      display: none;
    }
    .toast.success {
      background-color: #28a745;
    }
    .toast.error {
      background-color: #dc3545;
    }
  </style>
</head>
<body>
  <?php if ($login_error): ?>
    <div class="toast error" id="errorToast"><?php echo $login_error; ?></div>
  <?php endif; ?>
  <?php if ($login_success): ?>
    <div class="toast success" id="successToast">Login successful! Redirecting...</div>
  <?php endif; ?>

  <div class="container-scroller">
    <div class="container-fluid page-body-wrapper full-page-wrapper">
      <div class="content-wrapper d-flex align-items-stretch auth auth-img-bg">
        <div class="row flex-grow">
          <div class="col-lg-6 d-flex align-items-center justify-content-center">
            <div class="auth-form-transparent text-left p-3">
              <div class="brand-logo">
                <img src="images/LOGO.jpg" alt="logo">
              </div>
              <h4>CKT BIO-ATTEND</h4>
              <h6 class="font-weight-light">Happy to see you again!</h6>
              <form class="pt-3" method="POST">
                <div class="form-group">
                  <label for="exampleInputEmail">Username</label>
                  <div class="input-group">
                    <div class="input-group-prepend bg-transparent">
                      <span class="input-group-text bg-transparent border-right-0">
                        <i class="fa fa-user text-primary"></i>
                      </span>
                    </div>
                    <input type="text" name="username" class="form-control form-control-lg border-left-0" id="exampleInputEmail" placeholder="Username" required>
                  </div>
                </div>
                <div class="form-group">
                  <label for="exampleInputPassword">Password</label>
                  <div class="input-group">
                    <div class="input-group-prepend bg-transparent">
                      <span class="input-group-text bg-transparent border-right-0">
                        <i class="fa fa-lock text-primary"></i>
                      </span>
                    </div>
                    <input type="password" name="password" class="form-control form-control-lg border-left-0" id="exampleInputPassword" placeholder="Password" required>                        
                  </div>
                </div>
                <div class="my-2 d-flex justify-content-between align-items-center">
                  <div class="form-check">
                    <label class="form-check-label text-muted">
                      <input type="checkbox" class="form-check-input">
                      Keep me signed in
                    </label>
                  </div>
                  
                </div>
                <div class="my-3">
                  <button type="submit" class="btn btn-block btn-primary btn-lg font-weight-medium auth-form-btn">LOGIN</button>
                </div>
              </form>
            </div>
          </div>
          <div class="col-lg-6 login-half-bg d-flex flex-row">
            <p class="text-white font-weight-medium text-center flex-grow align-self-end">Copyright &copy; By Theodora 2025  All rights reserved.</p>
          </div>
        </div>
      </div>
    </div>
  </div>
  <script src="vendors/js/vendor.bundle.base.js"></script>
  <script src="vendors/js/vendor.bundle.addons.js"></script>
  <script src="js/off-canvas.js"></script>
  <script src="js/hoverable-collapse.js"></script>
  <script src="js/misc.js"></script>
  <script src="js/settings.js"></script>
  <script src="js/todolist.js"></script>
  <script>
    // Show toast messages
    document.addEventListener('DOMContentLoaded', function() {
      const errorToast = document.getElementById('errorToast');
      const successToast = document.getElementById('successToast');

      if (errorToast) {
        errorToast.style.display = 'block';
        setTimeout(() => {
          errorToast.style.display = 'none';
        }, 3000);
      }

      if (successToast) {
        successToast.style.display = 'block';
        setTimeout(() => {
          window.location.href = '<?php echo $redirect_url; ?>';
        }, 3000);
      }
    });
  </script>
</body>
</html>