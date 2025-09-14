<?php
session_start();
include("connection/config.php");

if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] != 'admin') {
    header("Location: index.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_program'])) {
    $program_id = $_POST['program_id'] ?? 0;
    $program_name = trim($_POST['program_name']);
    $program_code = trim($_POST['program_code']);
    $duration_years = (int)$_POST['duration_years'];

    if (!empty($program_name) && !empty($program_code) && $duration_years > 0) {
        try {
            if ($program_id > 0) {
                $stmt = $pdo->prepare("UPDATE programs SET program_name=?, program_code=?, duration_years=? WHERE program_id=?");
                $stmt->execute([$program_name, $program_code, $duration_years, $program_id]);
                $_SESSION['success'] = "Program updated";
            } else {
                $stmt = $pdo->prepare("INSERT INTO programs (program_name, program_code, duration_years) VALUES (?,?,?)");
                $stmt->execute([$program_name, $program_code, $duration_years]);
                $_SESSION['success'] = "Program added";
            }
        } catch (PDOException $e) {
            $_SESSION['error'] = "Database error";
        }
    } else {
        $_SESSION['error'] = "Invalid data";
    }
    header("Location: manage-programmes.php");
    exit();
}

if (isset($_GET['delete'])) {
    try {
        $stmt = $pdo->prepare("DELETE FROM programs WHERE program_id=?");
        $stmt->execute([$_GET['delete']]);
        $_SESSION['success'] = "Program deleted";
    } catch (PDOException $e) {
        $_SESSION['error'] = "Delete failed";
    }
    header("Location: manage-programmes.php");
    exit();
}

$programs = $pdo->query("SELECT * FROM programs ORDER BY program_name")->fetchAll();

$edit_program = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM programs WHERE program_id=?");
    $stmt->execute([$_GET['edit']]);
    $edit_program = $stmt->fetch();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  <title>BioAttend Admin</title>
  <link rel="stylesheet" href="vendors/iconfonts/font-awesome/css/all.min.css">
  <link rel="stylesheet" href="vendors/css/vendor.bundle.base.css">
  <link rel="stylesheet" href="vendors/css/vendor.bundle.addons.css">
  <link rel="stylesheet" href="css/style.css">
  <!-- endinject -->
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
  </style>
</head>
<body>
      <?php if (isset($_SESSION['error'])): ?>
    <div class="toast error"><?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></div>
  <?php endif; ?>
  <?php if (isset($_SESSION['success'])): ?>
    <div class="toast success"><?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div>
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
                  <span class="input-group-text">
                  
                  </span>
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
      <?php include('navbar.php');?>
      
      <div class="main-panel">
        <div class="content-wrapper">
          <div class="page-header">
            <h3 class="page-title">Manage Programmes</h3>
          </div>
          
          <div class="row">
            <div class="col-md-12 grid-margin stretch-card">
              <div class="card">
                <div class="card-body">
                  <h4 class="card-title">
                    <i class="fas fa-graduation-cap"></i>
                    <?= $edit_program ? 'Edit' : 'Add' ?> Programme
                  </h4>
                  <form method="POST" class="forms-sample">
                    <input type="hidden" name="program_id" value="<?= $edit_program['program_id'] ?? '' ?>">
                    <div class="form-group">
                      <label>Programme Name</label>
                      <input type="text" class="form-control" name="program_name" 
                             value="<?= htmlspecialchars($edit_program['program_name'] ?? '') ?>" required>
                    </div>
                    <div class="form-group">
                      <label>Programme Code</label>
                      <input type="text" class="form-control" name="program_code" 
                             value="<?= htmlspecialchars($edit_program['program_code'] ?? '') ?>" required>
                    </div>
                    <div class="form-group">
                      <label>Duration (Years)</label>
                      <input type="number" class="form-control" name="duration_years" 
                             value="<?= htmlspecialchars($edit_program['duration_years'] ?? '') ?>" min="1" required>
                    </div>
                    <button type="submit" name="save_program" class="btn btn-primary mr-2">
                      <?= $edit_program ? 'Update' : 'Save' ?>
                    </button>
                    <?php if ($edit_program): ?>
                      <a href="manage-programmes.php" class="btn btn-light">Cancel</a>
                    <?php endif; ?>
                  </form>
                </div>
                <table class="table table-hover">
                      <thead>
                        <tr>
                          <th>Code</th>
                          <th>Name</th>
                          <th>Duration</th>
                          <th>Actions</th>
                        </tr>
                      </thead>
                      <tbody>
                        <?php foreach ($programs as $program): ?>
                        <tr>
                          <td><?= htmlspecialchars($program['program_code']) ?></td>
                          <td><?= htmlspecialchars($program['program_name']) ?></td>
                          <td><?= htmlspecialchars($program['duration_years']) ?> years</td>
                          <td>
                            <a href="manage-programmes.php?edit=<?= $program['program_id'] ?>" 
                               class="btn btn-sm btn-primary">Edit</a>
                            <a href="manage-programmes.php?delete=<?= $program['program_id'] ?>" 
                               class="btn btn-sm btn-danger" 
                               onclick="return confirm('Delete this programme?')">Delete</a>
                          </td>
                        </tr>
                        <?php endforeach; ?>
                      </tbody>
                    </table>


              </div>
              
              
            </div>
          </div>
        </div>
        
        <?php include("footer.php");?>
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
  <script src="js/dashboard.js"></script>
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
   <script>
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