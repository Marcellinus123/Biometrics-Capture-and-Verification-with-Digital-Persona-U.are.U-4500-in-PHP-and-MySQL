<?php
session_start();
include("connection/config.php");

if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] != 'admin') {
    header("Location: index.php");
    exit();
}

// Handle CRUD operations for lecturers
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['save_lecturer'])) {
        $lecturer_id = $_POST['lecturer_id'] ?? 0;
        $username = trim($_POST['username']);
        $password = trim($_POST['password']);
        $full_name = trim($_POST['full_name']);
        $email = trim($_POST['email']);
        
        // Validate inputs
        if (!empty($username) && !empty($full_name) && !empty($email)) {
            try {
                if ($lecturer_id > 0) {
                    // Update existing lecturer
                    if (!empty($password)) {
                        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                        $stmt = $pdo->prepare("UPDATE lecturers SET username=?, password=?, full_name=?, email=? WHERE lecturer_id=?");
                        $stmt->execute([$username, $hashed_password, $full_name, $email, $lecturer_id]);
                    } else {
                        $stmt = $pdo->prepare("UPDATE lecturers SET username=?, full_name=?, email=? WHERE lecturer_id=?");
                        $stmt->execute([$username, $full_name, $email, $lecturer_id]);
                    }
                    $_SESSION['success'] = "Lecturer updated";
                } else {
                    // Create new lecturer
                    if (empty($password)) {
                        $_SESSION['error'] = "Password is required for new lecturers";
                    } else {
                        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                        $stmt = $pdo->prepare("INSERT INTO lecturers (username, password, full_name, email) VALUES (?,?,?,?)");
                        $stmt->execute([$username, $hashed_password, $full_name, $email]);
                        $lecturer_id = $pdo->lastInsertId();
                        $_SESSION['success'] = "Lecturer added";
                    }
                }
                
                // Handle course assignments if lecturer was created/updated successfully
                if (isset($_SESSION['success']) && isset($_POST['courses']) && is_array($_POST['courses'])) {
                    // First remove all existing assignments
                    $pdo->prepare("DELETE FROM lecturer_courses WHERE lecturer_id=?")->execute([$lecturer_id]);
                    
                    // Add new assignments
                    $stmt = $pdo->prepare("INSERT INTO lecturer_courses (lecturer_id, course_id, academic_year) VALUES (?,?,?)");
                    $academic_year = date('Y') . '/' . (date('Y')+1);
                    foreach ($_POST['courses'] as $course_id) {
                        $stmt->execute([$lecturer_id, $course_id, $academic_year]);
                    }
                }
            } catch (PDOException $e) {
                $_SESSION['error'] = "Database error: " . $e->getMessage();
            }
        } else {
            $_SESSION['error'] = "Please fill all required fields";
        }
        header("Location: manage-lecturers");
        exit();
    }
}

if (isset($_GET['delete'])) {
    try {
        $pdo->beginTransaction();
        
        // Remove course assignments first
        $pdo->prepare("DELETE FROM lecturer_courses WHERE lecturer_id=?")->execute([$_GET['delete']]);
        
        // Then delete the lecturer
        $pdo->prepare("DELETE FROM lecturers WHERE lecturer_id=?")->execute([$_GET['delete']]);
        
        $pdo->commit();
        $_SESSION['success'] = "Lecturer deleted";
    } catch (PDOException $e) {
        $pdo->rollBack();
        $_SESSION['error'] = "Delete failed: " . $e->getMessage();
    }
    header("Location: manage-lecturers");
    exit();
}

// Fetch all lecturers
$lecturers = $pdo->query("SELECT * FROM lecturers ORDER BY full_name")->fetchAll();

// Fetch all courses for assignment
$courses = $pdo->query("SELECT * FROM courses ORDER BY course_name")->fetchAll();

$edit_lecturer = null;
$assigned_courses = [];
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM lecturers WHERE lecturer_id=?");
    $stmt->execute([$_GET['edit']]);
    $edit_lecturer = $stmt->fetch();
    
    if ($edit_lecturer) {
        $stmt = $pdo->prepare("SELECT course_id FROM lecturer_courses WHERE lecturer_id=?");
        $stmt->execute([$edit_lecturer['lecturer_id']]);
        $assigned_courses = $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  <title>BioAttend Admin - Manage Lecturers</title>
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
    .course-checkboxes {
      max-height: 300px;
      overflow-y: auto;
      border: 1px solid #ddd;
      padding: 10px;
      border-radius: 4px;
    }
    .course-checkbox-item {
      margin-bottom: 8px;
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
            <h3 class="page-title">Manage Lecturers</h3>
          </div>
          
          <div class="row">
            <div class="col-md-12 grid-margin stretch-card">
              <div class="card">
                <div class="card-body">
                  <h4 class="card-title">
                    <i class="fas fa-chalkboard-teacher"></i>
                    <?= $edit_lecturer ? 'Edit' : 'Add' ?> Lecturer
                  </h4>
                  <form method="POST" class="forms-sample">
                    <input type="hidden" name="lecturer_id" value="<?= $edit_lecturer['lecturer_id'] ?? '' ?>">
                    
                    <div class="form-group">
                      <label>Username</label>
                      <input type="text" class="form-control" name="username" 
                             value="<?= htmlspecialchars($edit_lecturer['username'] ?? '') ?>" required>
                    </div>
                    
                    <div class="form-group">
                      <label>Password</label>
                      <input type="password" class="form-control" name="password" 
                             placeholder="<?= $edit_lecturer ? 'Leave blank to keep current' : '' ?>" 
                             <?= !$edit_lecturer ? 'required' : '' ?>>
                    </div>
                    
                    <div class="form-group">
                      <label>Full Name</label>
                      <input type="text" class="form-control" name="full_name" 
                             value="<?= htmlspecialchars($edit_lecturer['full_name'] ?? '') ?>" required>
                    </div>
                    
                    <div class="form-group">
                      <label>Email</label>
                      <input type="email" class="form-control" name="email" 
                             value="<?= htmlspecialchars($edit_lecturer['email'] ?? '') ?>" required>
                    </div>
                    
                    <div class="form-group">
                      <label>Assigned Courses</label>
                      <div class="course-checkboxes">
                        <?php if (count($courses) > 0): ?>
                          <?php foreach ($courses as $course): ?>
                            <div class="course-checkbox-item">
                              <div class="form-check">
                                <label class="form-check-label">
                                  <input type="checkbox" class="form-check-input" name="courses[]" 
                                         value="<?= $course['course_id'] ?>"
                                         <?= in_array($course['course_id'], $assigned_courses) ? 'checked' : '' ?>>
                                  <?= htmlspecialchars($course['course_code'] . ' - ' . $course['course_name']) ?>
                                </label>
                              </div>
                            </div>
                          <?php endforeach; ?>
                        <?php else: ?>
                          <p>No courses available</p>
                        <?php endif; ?>
                      </div>
                    </div>
                    
                    <button type="submit" name="save_lecturer" class="btn btn-primary mr-2">
                      <?= $edit_lecturer ? 'Update' : 'Save' ?>
                    </button>
                    <?php if ($edit_lecturer): ?>
                      <a href="manage-lecturers.php" class="btn btn-light">Cancel</a>
                    <?php endif; ?>
                  </form>
                </div>
                
                <div class="card-body">
                  <h4 class="card-title"><i class="fas fa-list"></i> Lecturers List</h4>
                  <div class="table-responsive">
                    <table class="table table-hover">
                      <thead>
                        <tr>
                          <th>Username</th>
                          <th>Full Name</th>
                          <th>Email</th>
                          <th>Assigned Courses</th>
                          <th>Actions</th>
                        </tr>
                      </thead>
                      <tbody>
                        <?php if (count($lecturers) > 0): ?>
                          <?php foreach ($lecturers as $lecturer): ?>
                            <tr>
                              <td><?= htmlspecialchars($lecturer['username']) ?></td>
                              <td><?= htmlspecialchars($lecturer['full_name']) ?></td>
                              <td><?= htmlspecialchars($lecturer['email']) ?></td>
                              <td>
                                <?php
                                  $stmt = $pdo->prepare("
                                    SELECT COUNT(*) 
                                    FROM lecturer_courses 
                                    WHERE lecturer_id = ?
                                  ");
                                  $stmt->execute([$lecturer['lecturer_id']]);
                                  echo $stmt->fetchColumn();
                                ?>
                              </td>
                              <td>
                                <a href="manage-lecturers?edit=<?= $lecturer['lecturer_id'] ?>" 
                                   class="btn btn-sm btn-primary">Edit</a>
                                <a href="manage-lecturers?delete=<?= $lecturer['lecturer_id'] ?>" 
                                   class="btn btn-sm btn-danger" 
                                   onclick="return confirm('Delete this lecturer?')">Delete</a>
                              </td>
                            </tr>
                          <?php endforeach; ?>
                        <?php else: ?>
                          <tr>
                            <td colspan="5" class="text-center">No lecturers found</td>
                          </tr>
                        <?php endif; ?>
                      </tbody>
                    </table>
                  </div>
                </div>
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