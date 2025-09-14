<?php
session_start();
include("connection/config.php");

if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] != 'admin') {
    header("Location: index.php");
    exit();
}

// Handle CRUD operations for courses
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_course'])) {
    $course_id = $_POST['course_id'] ?? 0;
    $course_code = trim($_POST['course_code']);
    $course_name = trim($_POST['course_name']);
    $credit_hours = (int)$_POST['credit_hours'];
    $program_id = (int)$_POST['program_id'];
    $level = (int)$_POST['level'];
    $semester = (int)$_POST['semester'];

    if (!empty($course_code) && !empty($course_name) && $credit_hours > 0) {
        try {
            if ($course_id > 0) {
                $stmt = $pdo->prepare("UPDATE courses SET course_code=?, course_name=?, credit_hours=?, program_id=?, level=?, semester=? WHERE course_id=?");
                $stmt->execute([$course_code, $course_name, $credit_hours, $program_id, $level, $semester, $course_id]);
                $_SESSION['success'] = "Course updated";
            } else {
                $stmt = $pdo->prepare("INSERT INTO courses (course_code, course_name, credit_hours, program_id, level, semester) VALUES (?,?,?,?,?,?)");
                $stmt->execute([$course_code, $course_name, $credit_hours, $program_id, $level, $semester]);
                $_SESSION['success'] = "Course added";
            }
        } catch (PDOException $e) {
            $_SESSION['error'] = "Database error: " . $e->getMessage();
        }
    } else {
        $_SESSION['error'] = "Please fill all required fields";
    }
    header("Location: manage-courses");
    exit();
}

if (isset($_GET['delete'])) {
    try {
        $stmt = $pdo->prepare("DELETE FROM courses WHERE course_id=?");
        $stmt->execute([$_GET['delete']]);
        $_SESSION['success'] = "Course deleted";
    } catch (PDOException $e) {
        $_SESSION['error'] = "Delete failed: " . $e->getMessage();
    }
    header("Location: manage-courses");
    exit();
}

// Fetch all courses with program names
$courses = $pdo->query("
    SELECT c.*, p.program_name 
    FROM courses c 
    LEFT JOIN programs p ON c.program_id = p.program_id 
    ORDER BY c.course_name
")->fetchAll();

// Fetch all programs for dropdown
$programs = $pdo->query("SELECT * FROM programs ORDER BY program_name")->fetchAll();

$edit_course = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM courses WHERE course_id=?");
    $stmt->execute([$_GET['edit']]);
    $edit_course = $stmt->fetch();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  <title>BioAttend Admin - Manage Courses</title>
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
            <h3 class="page-title">Manage Courses</h3>
          </div>
          
          <div class="row">
            <div class="col-md-12 grid-margin stretch-card">
              <div class="card">
                <div class="card-body">
                  <h4 class="card-title">
                    <i class="fas fa-book"></i>
                    <?= $edit_course ? 'Edit' : 'Add' ?> Course
                  </h4>
                  <form method="POST" class="forms-sample">
                    <input type="hidden" name="course_id" value="<?= $edit_course['course_id'] ?? '' ?>">
                    
                    <div class="form-group">
                      <label>Course Code</label>
                      <input type="text" class="form-control" name="course_code" 
                             value="<?= htmlspecialchars($edit_course['course_code'] ?? '') ?>" required>
                    </div>
                    
                    <div class="form-group">
                      <label>Course Name</label>
                      <input type="text" class="form-control" name="course_name" 
                             value="<?= htmlspecialchars($edit_course['course_name'] ?? '') ?>" required>
                    </div>
                    
                    <div class="form-group">
                      <label>Credit Hours</label>
                      <input type="number" class="form-control" name="credit_hours" 
                             value="<?= htmlspecialchars($edit_course['credit_hours'] ?? '') ?>" min="1" required>
                    </div>
                    
                    <div class="form-group">
                      <label>Program</label>
                      <select class="form-control" name="program_id" required>
                        <option value="">Select Program</option>
                        <?php foreach ($programs as $program): ?>
                          <option value="<?= $program['program_id'] ?>" 
                            <?= ($edit_course && $edit_course['program_id'] == $program['program_id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($program['program_name']) ?>
                          </option>
                        <?php endforeach; ?>
                      </select>
                    </div>
                    
                    <div class="form-group">
                      <label>Level</label>
                      <select class="form-control" name="level" required>
                        <option value="">Select Level</option>
                        <?php for ($i = 1; $i <= 4; $i++): ?>
                          <option value="<?= $i ?>" 
                            <?= ($edit_course && $edit_course['level'] == $i) ? 'selected' : '' ?>>
                            Level <?= $i ?>00
                          </option>
                        <?php endfor; ?>
                      </select>
                    </div>
                    
                    <div class="form-group">
                      <label>Semester</label>
                      <select class="form-control" name="semester" required>
                        <option value="">Select Semester</option>
                        <?php for ($i = 1; $i <= 2; $i++): ?>
                          <option value="<?= $i ?>" 
                            <?= ($edit_course && $edit_course['semester'] == $i) ? 'selected' : '' ?>>
                            Semester <?= $i ?>
                          </option>
                        <?php endfor; ?>
                      </select>
                    </div>
                    
                    <button type="submit" name="save_course" class="btn btn-primary mr-2">
                      <?= $edit_course ? 'Update' : 'Save' ?>
                    </button>
                    <?php if ($edit_course): ?>
                      <a href="manage-courses.php" class="btn btn-light">Cancel</a>
                    <?php endif; ?>
                  </form>
                </div>
                
                <div class="card-body">
                  <h4 class="card-title"><i class="fas fa-list"></i> Course List</h4>
                  <div class="table-responsive">
                    <table class="table table-hover">
                      <thead>
                        <tr>
                          <th>Code</th>
                          <th>Name</th>
                          <th>Credits</th>
                          <th>Program</th>
                          <th>Level</th>
                          <th>Semester</th>
                          <th>Actions</th>
                        </tr>
                      </thead>
                      <tbody>
                        <?php if (count($courses) > 0): ?>
                          <?php foreach ($courses as $course): ?>
                            <tr>
                              <td><?= htmlspecialchars($course['course_code']) ?></td>
                              <td><?= htmlspecialchars($course['course_name']) ?></td>
                              <td><?= htmlspecialchars($course['credit_hours']) ?></td>
                              <td><?= htmlspecialchars($course['program_name'] ?? 'N/A') ?></td>
                              <td><?= htmlspecialchars($course['level']) ?>00</td>
                              <td>semester<?= htmlspecialchars($course['semester']) ?></td>
                              <td>
                                <a href="manage-courses?edit=<?= $course['course_id'] ?>" 
                                   class="btn btn-sm btn-primary">Edit</a>
                                <a href="manage-courses?delete=<?= $course['course_id'] ?>" 
                                   class="btn btn-sm btn-danger" 
                                   onclick="return confirm('Delete this course?')">Delete</a>
                              </td>
                            </tr>
                          <?php endforeach; ?>
                        <?php else: ?>
                          <tr>
                            <td colspan="7" class="text-center">No courses found</td>
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