<?php
session_start();
include("connection/config.php");

if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] != 'admin') {
    header("Location: index.php");
    exit();
}

// Get current admin data
$admin_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT * FROM admins WHERE admin_id = ?");
$stmt->execute([$admin_id]);
$admin = $stmt->fetch();

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $username = trim($_POST['username']);
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // Validate required fields
    if (empty($full_name) || empty($email) || empty($username)) {
        $error = "Please fill in all required fields";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address";
    } else {
        try {
            // Check if username or email already exists (excluding current admin)
            $stmt = $pdo->prepare("SELECT admin_id FROM admins WHERE (username = ? OR email = ?) AND admin_id != ?");
            $stmt->execute([$username, $email, $admin_id]);
            $existing_admin = $stmt->fetch();
            
            if ($existing_admin) {
                $error = "Username or email already exists";
            } else {
                // Handle password change if requested
                if (!empty($current_password)) {
                    if (empty($new_password) || empty($confirm_password)) {
                        $error = "Please fill in all password fields";
                    } elseif ($new_password !== $confirm_password) {
                        $error = "New passwords do not match";
                    } elseif (!password_verify($current_password, $admin['password'])) {
                        $error = "Current password is incorrect";
                    } else {
                        // Update with new password
                        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                        $stmt = $pdo->prepare("UPDATE admins SET full_name = ?, email = ?, username = ?, password = ? WHERE admin_id = ?");
                        $stmt->execute([$full_name, $email, $username, $hashed_password, $admin_id]);
                        $success = "Profile and password updated successfully";
                    }
                } else {
                    // Update without changing password
                    $stmt = $pdo->prepare("UPDATE admins SET full_name = ?, email = ?, username = ? WHERE admin_id = ?");
                    $stmt->execute([$full_name, $email, $username, $admin_id]);
                    $success = "Profile updated successfully";
                }
                
                // Refresh admin data
                $stmt = $pdo->prepare("SELECT * FROM admins WHERE admin_id = ?");
                $stmt->execute([$admin_id]);
                $admin = $stmt->fetch();
                
                // Update session username if changed
                $_SESSION['username'] = $username;
            }
        } catch (PDOException $e) {
            $error = "Database error: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  <title>BioAttend Admin - Profile</title>
  <link rel="stylesheet" href="vendors/iconfonts/font-awesome/css/all.min.css">
  <link rel="stylesheet" href="vendors/css/vendor.bundle.base.css">
  <link rel="stylesheet" href="vendors/css/vendor.bundle.addons.css">
  <link rel="stylesheet" href="css/style.css">
  <link rel="shortcut icon" href="images/LOGO.jpg" />
  <style>
    .toast {
      position: fixed;
      top: 20px;
      right: 20px;
      min-width: 250px;
      padding: 15px;
      color: white;
      border-radius: 4px;
      box-shadow: 0 4px 12px rgba(0,0,0,0.15);
      z-index: 9999;
      animation: fadeIn 0.5s, fadeOut 0.5s 2.5s;
    }
    .toast.success {
      background-color: #28a745;
    }
    .toast.error {
      background-color: #dc3545;
    }
    @keyframes fadeIn {
      from {opacity: 0; transform: translateY(-20px);}
      to {opacity: 1; transform: translateY(0);}
    }
    @keyframes fadeOut {
      from {opacity: 1;}
      to {opacity: 0;}
    }
    .password-toggle {
      cursor: pointer;
    }
  </style>
</head>
<body>
  <?php if (!empty($error)): ?>
    <div class="toast error"><?php echo htmlspecialchars($error); ?></div>
  <?php endif; ?>
  <?php if (!empty($success)): ?>
    <div class="toast success"><?php echo htmlspecialchars($success); ?></div>
  <?php endif; ?>
  
  <div class="container-scroller">
    <nav class="navbar col-lg-12 col-12 p-0 fixed-top d-flex flex-row default-layout-navbar">
      <div class="text-center navbar-brand-wrapper d-flex align-items-center justify-content-center">
        <a class="navbar-brand brand-logo-mini" href="admin-dashboard"><img src="images/LOGO.jpg" alt="logo"/></a>
      </div>
      <div class="navbar-menu-wrapper d-flex align-items-stretch">
        <button class="navbar-toggler navbar-toggler align-self-center" type="button" data-toggle="minimize">
          <span class="fas fa-bars"></span>
        </button>
        <ul class="navbar-nav">
          <li class="nav-item nav-search d-none d-md-flex">
            <div class="nav-link">
              <div class="input-group">
                <div class="input-group-prepend">
                  <span class="input-group-text"></span>
                </div>
              </div>
            </div>
          </li>
        </ul>
        <ul class="navbar-nav navbar-nav-right">
          <li class="nav-item d-none d-lg-flex">
            <a class="nav-link" href="manage-students">
              <span class="btn btn-primary">+ Create new</span>
            </a>
          </li>
          <li class="nav-item dropdown d-none d-lg-flex">
            <div class="nav-link">
              <span class="dropdown-toggle btn btn-outline-dark" id="languageDropdown" data-toggle="dropdown">Account</span>
              <div class="dropdown-menu navbar-dropdown" aria-labelledby="languageDropdown">
                <a class="dropdown-item font-weight-medium" href="profile">
                  Profile
                </a>
                <div class="dropdown-divider"></div>
                <a class="dropdown-item font-weight-medium" href="logout">
                  Logout
                </a>
                <div class="dropdown-divider"></div>
              </div>
            </div>
          </li>
        </ul>
        <button class="navbar-toggler navbar-toggler-right d-lg-none align-self-center" type="button" data-toggle="offcanvas">
          <span class="fas fa-bars"></span>
        </button>
      </div>
    </nav>
    
    <div class="container-fluid page-body-wrapper">
      <?php include('navbar.php'); ?>
      
      <div class="main-panel">
        <div class="content-wrapper">
          <div class="page-header">
            <h3 class="page-title">Admin Profile</h3>
          </div>
          
          <div class="row">
            <div class="col-md-6 grid-margin stretch-card">
              <div class="card">
                <div class="card-body">
                  <h4 class="card-title">
                    <i class="fas fa-user"></i>
                    Profile Information
                  </h4>
                  <form method="POST" class="forms-sample">
                    <div class="form-group">
                      <label for="full_name">Full Name *</label>
                      <input type="text" class="form-control" id="full_name" name="full_name" 
                             value="<?= htmlspecialchars($admin['full_name']) ?>" required>
                    </div>
                    <div class="form-group">
                      <label for="email">Email Address *</label>
                      <input type="email" class="form-control" id="email" name="email" 
                             value="<?= htmlspecialchars($admin['email']) ?>" required>
                    </div>
                    <div class="form-group">
                      <label for="username">Username *</label>
                      <input type="text" class="form-control" id="username" name="username" 
                             value="<?= htmlspecialchars($admin['username']) ?>" required>
                    </div>
                    
                    <h4 class="card-title mt-4">
                      <i class="fas fa-lock"></i>
                      Change Password
                    </h4>
                    <p class="text-muted">Leave password fields blank if you don't want to change your password</p>
                    
                    <div class="form-group">
                      <label for="current_password">Current Password</label>
                      <div class="input-group">
                        <input type="password" class="form-control" id="current_password" name="current_password">
                        <div class="input-group-append">
                          <span class="input-group-text password-toggle" data-target="current_password">
                            <i class="fas fa-eye"></i>
                          </span>
                        </div>
                      </div>
                    </div>
                    <div class="form-group">
                      <label for="new_password">New Password</label>
                      <div class="input-group">
                        <input type="password" class="form-control" id="new_password" name="new_password">
                        <div class="input-group-append">
                          <span class="input-group-text password-toggle" data-target="new_password">
                            <i class="fas fa-eye"></i>
                          </span>
                        </div>
                      </div>
                    </div>
                    <div class="form-group">
                      <label for="confirm_password">Confirm New Password</label>
                      <div class="input-group">
                        <input type="password" class="form-control" id="confirm_password" name="confirm_password">
                        <div class="input-group-append">
                          <span class="input-group-text password-toggle" data-target="confirm_password">
                            <i class="fas fa-eye"></i>
                          </span>
                        </div>
                      </div>
                    </div>
                    
                    <button type="submit" class="btn btn-primary mr-2">Update Profile</button>
                    <a href="admin-dashboard.php" class="btn btn-light">Cancel</a>
                  </form>
                </div>
              </div>
            </div>
            
            <div class="col-md-6 grid-margin stretch-card">
              <div class="card">
                <div class="card-body">
                  <h4 class="card-title">
                    <i class="fas fa-info-circle"></i>
                    Account Information
                  </h4>
                  <div class="row">
                    <div class="col-md-6">
                      <div class="form-group">
                        <label class="text-muted">Admin ID</label>
                        <p class="form-control-plaintext"><?= htmlspecialchars($admin['admin_id']) ?></p>
                      </div>
                    </div>
                    <div class="col-md-6">
                      <div class="form-group">
                        <label class="text-muted">Account Created</label>
                        <p class="form-control-plaintext">
                          <?= date('M j, Y', strtotime($admin['created_at'])) ?>
                        </p>
                      </div>
                    </div>
                  </div>
                  
                  <div class="form-group">
                    <label class="text-muted">User Role</label>
                    <p class="form-control-plaintext">
                      <span class="badge badge-success">Super Admin</span>
                    </p>
                  </div>
                  
                  <div class="form-group">
                    <label class="text-muted">Last Login</label>
                    <p class="form-control-plaintext">
                      <?= date('M j, Y g:i A') ?> (Current)
                    </p>
                  </div>
                  
                  <hr>
                  <h5 class="mb-3">Security Tips</h5>
                  <ul class="list-ticked">
                    <li>Use a strong, unique password</li>
                    <li>Never share your login credentials</li>
                    <li>Log out after each session</li>
                    <li>Change your password regularly</li>
                  </ul>
                </div>
              </div>
            </div>
          </div>
        </div>
        
        <?php include("footer.php"); ?>
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
    // Password toggle functionality
    document.querySelectorAll('.password-toggle').forEach(toggle => {
      toggle.addEventListener('click', function() {
        const targetId = this.getAttribute('data-target');
        const passwordInput = document.getElementById(targetId);
        const icon = this.querySelector('i');
        
        if (passwordInput.type === 'password') {
          passwordInput.type = 'text';
          icon.classList.remove('fa-eye');
          icon.classList.add('fa-eye-slash');
        } else {
          passwordInput.type = 'password';
          icon.classList.remove('fa-eye-slash');
          icon.classList.add('fa-eye');
        }
      });
    });
    
    // Automatically remove toasts after 3 seconds
    document.addEventListener('DOMContentLoaded', function() {
      const toasts = document.querySelectorAll('.toast');
      toasts.forEach(toast => {
        setTimeout(() => {
          toast.remove();
        }, 3000);
      });
    });
  </script>
</body>
</html>