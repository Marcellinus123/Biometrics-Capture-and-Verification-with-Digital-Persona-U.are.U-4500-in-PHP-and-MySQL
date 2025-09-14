<?php
session_start();
include("connection/config.php");

if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] != 'admin') {
    header("Location: index.php");
    exit();
}

// Function to generate random registration number
function generateRegistrationNumber() {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyz';
    $randomString = '';
    for ($i = 0; $i < 8; $i++) {
        $randomString .= $characters[rand(0, strlen($characters) - 1)];
    }
    return strtoupper($randomString) . date('Y');
}

// Handle CRUD operations for students
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['save_student'])) {
        $student_id = $_POST['student_id'] ?? 0;
        $registration_number = $student_id > 0 ? trim($_POST['registration_number']) : generateRegistrationNumber();
        $full_name = trim($_POST['full_name']);
        $student_number = trim($_POST['student_number']);
        $email = trim($_POST['email']);
        $program_id = (int)$_POST['program_id'];
        $level = (int)$_POST['level'];
        $fingerprint_data = $_POST['fingerprint_data'] ?? null;
        
        // Handle file upload
        $passport_photo = null;
        if (isset($_FILES['passport_photo']) && $_FILES['passport_photo']['error'] == UPLOAD_ERR_OK) {
            $passport_photo = file_get_contents($_FILES['passport_photo']['tmp_name']);
        } elseif ($student_id > 0) {
            // Keep existing photo if not uploading new one
            $stmt = $pdo->prepare("SELECT passport_photo FROM students WHERE student_id=?");
            $stmt->execute([$student_id]);
            $passport_photo = $stmt->fetchColumn();
        }

        if (!empty($registration_number) && !empty($full_name) && !empty($email) && $program_id > 0) {
            try {
                $pdo->beginTransaction();
                
                if ($student_id > 0) {
                    // Update existing student
                    if (!$fingerprint_data) {
                        $stmt = $pdo->prepare("UPDATE students SET registration_number=?,student_number=?, full_name=?, email=?, program_id=?, level=?, fingerprint_data=?, passport_photo=? WHERE student_id=?");
                        $stmt->execute([$registration_number,$student_number, $full_name, $email, $program_id, $level, $fingerprint_data, $passport_photo, $student_id]);
                    } else {
                        $stmt = $pdo->prepare("UPDATE students SET registration_number=?,student_number=?, full_name=?, email=?, program_id=?, level=?, passport_photo=? WHERE student_id=?");
                        $stmt->execute([$registration_number,$student_number, $full_name, $email, $program_id, $level, $passport_photo, $student_id]);
                    }
                    $_SESSION['success'] = "Student updated";
                } else {
                    // Create new student (fingerprint is required)
                    if ($fingerprint_data) {
                        $_SESSION['error'] = "This page is not allowed to record fingerprint";
                    } else {
                        $stmt = $pdo->prepare("INSERT INTO students (registration_number,student_number, full_name, email, program_id, level, passport_photo) VALUES (?,?,?,?,?,?,?)");
                        $stmt->execute([$registration_number,$student_number, $full_name, $email, $program_id, $level, $passport_photo]);
                        $student_id = $pdo->lastInsertId();
                        $_SESSION['success'] = "Student added";
                    }
                }
                
                // Handle course assignments if student was created/updated successfully
                if (!isset($_SESSION['error']) && isset($_POST['courses']) && is_array($_POST['courses'])) {
                    // First remove all existing assignments
                    $pdo->prepare("DELETE FROM student_courses WHERE student_id=?")->execute([$student_id]);
                    
                    // Add new assignments
                    $stmt = $pdo->prepare("INSERT INTO student_courses (student_id, course_id, academic_year) VALUES (?,?,?)");
                    $academic_year = date('Y') . '/' . (date('Y')+1);
                    foreach ($_POST['courses'] as $course_id) {
                        $stmt->execute([$student_id, $course_id, $academic_year]);
                    }
                }
                
                $pdo->commit();
            } catch (PDOException $e) {
                $pdo->rollBack();
                $_SESSION['error'] = "Database error: " . $e->getMessage();
            }
        } else {
            $_SESSION['error'] = "Please fill all required fields";
        }
        header("Location: manage-students.php");
        exit();
    }
}

if (isset($_GET['delete'])) {
    try {
        $pdo->beginTransaction();
        
        // Remove course assignments first
        $pdo->prepare("DELETE FROM student_courses WHERE student_id=?")->execute([$_GET['delete']]);
        
        // Then delete the student
        $pdo->prepare("DELETE FROM students WHERE student_id=?")->execute([$_GET['delete']]);
        
        $pdo->commit();
        $_SESSION['success'] = "Student deleted";
    } catch (PDOException $e) {
        $pdo->rollBack();
        $_SESSION['error'] = "Delete failed: " . $e->getMessage();
    }
    header("Location: manage-students.php");
    exit();
}

// Fetch all students with program names and course count
$students = $pdo->query("
    SELECT s.*, p.program_name, 
           (SELECT COUNT(*) FROM student_courses sc WHERE sc.student_id = s.student_id) AS course_count
    FROM students s 
    LEFT JOIN programs p ON s.program_id = p.program_id 
    ORDER BY s.full_name
")->fetchAll();

// Fetch all programs for dropdown
$programs = $pdo->query("SELECT * FROM programs ORDER BY program_name")->fetchAll();

// Fetch all courses for assignment
$courses = $pdo->query("SELECT * FROM courses ORDER BY course_name")->fetchAll();

$edit_student = null;
$assigned_courses = [];
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM students WHERE student_id=?");
    $stmt->execute([$_GET['edit']]);
    $edit_student = $stmt->fetch();
    
    if ($edit_student) {
        $stmt = $pdo->prepare("SELECT course_id FROM student_courses WHERE student_id=?");
        $stmt->execute([$edit_student['student_id']]);
        $assigned_courses = $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  <title>BioAttend Admin - Manage Students</title>
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
    .fingerprint-container {
      border: 2px dashed #ccc;
      padding: 20px;
      text-align: center;
      margin-bottom: 20px;
      min-height: 150px;
      display: flex;
      flex-direction: column;
      justify-content: center;
      align-items: center;
    }
    .fingerprint-preview {
      max-width: 100px;
      max-height: 100px;
      margin-top: 10px;
      display: none;
    }
    .photo-preview {
      max-width: 100px;
      max-height: 100px;
      margin-top: 10px;
    }
    .course-checkboxes {
      max-height: 200px;
      overflow-y: auto;
      border: 1px solid #ddd;
      padding: 10px;
      border-radius: 4px;
    }
    .course-checkbox-item {
      margin-bottom: 8px;
    }
    .reg-number {
      font-family: monospace;
      font-weight: bold;
      color: #007bff;
    }
    #scanner-container {
      display: none;
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: rgba(0,0,0,0.8);
      z-index: 9999;
      flex-direction: column;
      align-items: center;
      justify-content: center;
    }
    #scanner-content {
      background: white;
      padding: 20px;
      border-radius: 5px;
      max-width: 800px;
      width: 90%;
    }
    #scanner-close {
      position: absolute;
      top: 20px;
      right: 20px;
      color: white;
      font-size: 30px;
      cursor: pointer;
    }
  </style>
</head>
<body onload = "onBodyLoad()">
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
                <a class="dropdown-item font-weight-medium" href="profile">Profile</a>
                <div class="dropdown-divider"></div>
                <a class="dropdown-item font-weight-medium" href="logout">Logout</a>
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
            <h3 class="page-title">Manage Students</h3>
          </div>
          
          <div class="row">
            <div class="col-md-12 grid-margin stretch-card">
              <div class="card">
                <div class="card-body">
                  <h4 class="card-title">
                    <i class="fas fa-user-graduate"></i>
                    <?= $edit_student ? 'Edit' : 'Add' ?> Student
                  </h4>
                  <form method="POST" class="forms-sample" enctype="multipart/form-data" id="studentForm">
                    <input type="hidden" name="student_id" value="<?= $edit_student['student_id'] ?? '' ?>">
                    <input type="hidden" name="fingerprint_data" id="fingerprint_data" value="<?= $edit_student['fingerprint_data'] ?? '' ?>">
                    
                    <div class="form-group">
                      <label>Registration Number</label>
                      <input type="text" class="form-control reg-number" name="registration_number" 
                             value="<?= htmlspecialchars($edit_student['registration_number'] ?? '') ?>" 
                             <?= $edit_student ? '' : 'readonly' ?> required>
                      <?php if (!$edit_student): ?>
                        <small class="text-muted">Automatically generated</small>
                      <?php endif; ?>
                    </div>
                    <div class="form-group">
                      <label>Student Id</label>
                      <input type="text" class="form-control" name="student_number" 
                             value="<?= htmlspecialchars($edit_student['student_number'] ?? '') ?>" required>
                    </div>
                    <div class="form-group">
                      <label>Full Name</label>
                      <input type="text" class="form-control" name="full_name" 
                             value="<?= htmlspecialchars($edit_student['full_name'] ?? '') ?>" required>
                    </div>
                    
                    <div class="form-group">
                      <label>Email</label>
                      <input type="email" class="form-control" name="email" 
                             value="<?= htmlspecialchars($edit_student['email'] ?? '') ?>" required>
                    </div>
                    
                    <div class="form-group">
                      <label>Program</label>
                      <select class="form-control" name="program_id" required>
                        <option value="">Select Program</option>
                        <?php foreach ($programs as $program): ?>
                          <option value="<?= $program['program_id'] ?>" 
                            <?= ($edit_student && $edit_student['program_id'] == $program['program_id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($program['program_name']) ?>
                          </option>
                        <?php endforeach; ?>
                      </select>
                    </div>
                    
                    <div class="form-group">
                      <label>Level</label>
                      <select class="form-control" name="level" required>
                        <option value="">Select Level</option>
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                          <option value="<?= $i ?>" 
                            <?= ($edit_student && $edit_student['level'] == $i) ? 'selected' : '' ?>>
                            Level <?= $i ?>
                          </option>
                        <?php endfor; ?>
                      </select>
                    </div>
                    
                    <div class="form-group">
                      <label>Passport Photo</label>
                      <input type="file" class="form-control" name="passport_photo" accept="image/*" id="photo_upload">
                      <?php if ($edit_student && $edit_student['passport_photo']): ?>
                        <img src="data:image/jpeg;base64,<?= base64_encode($edit_student['passport_photo']) ?>" 
                             class="photo-preview" id="photo_preview">
                      <?php else: ?>
                        <img src="" class="photo-preview" id="photo_preview" style="display: none;">
                      <?php endif; ?>
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
                    
                    <button type="submit" name="save_student" class="btn btn-primary mr-2" id="submitBtn">
                      <?= $edit_student ? 'Update' : 'Save' ?>
                    </button>
                    <?php if ($edit_student): ?>
                      <a href="manage-students.php" class="btn btn-light">Cancel</a>
                    <?php endif; ?>
                  </form>
                </div>
                
                <div class="card-body">
                  <h4 class="card-title"><i class="fas fa-list"></i> Students List</h4>
                  <div class="table-responsive">
                    <table class="table table-hover">
                      <thead>
                        <tr>
                          <th>Student ID.</th>
                          <th>Name</th>
                          <th>Program</th>
                          <th>Level</th>
                          <th>Courses</th>
                          <th>Fingerprint</th>
                          <th>Actions</th>
                        </tr>
                      </thead>
                      <tbody>
                        <?php if (count($students) > 0): ?>
                          <?php foreach ($students as $student): ?>
                            <tr>
                              <td class="reg-number"><?= htmlspecialchars($student['student_number']) ?></td>
                              <td><?= htmlspecialchars($student['full_name']) ?></td>
                              <td><?= htmlspecialchars($student['program_name'] ?? 'N/A') ?></td>
                              <td><?= htmlspecialchars($student['level']) ?></td>
                              <td><?= htmlspecialchars($student['course_count']) ?></td>
                              <td><?= $student['fingerprint_data'] ? '✔️' : '❌' ?></td>
                              <td>
                                <a href="manage-students.php?edit=<?= $student['student_id'] ?>" 
                                   class="btn btn-sm btn-primary">Edit</a>
                                <a href="manage-students.php?delete=<?= $student['student_id'] ?>" 
                                   class="btn btn-sm btn-danger" 
                                   onclick="return confirm('Delete this student?')">Delete</a>
                              </td>
                            </tr>
                          <?php endforeach; ?>
                        <?php else: ?>
                          <tr>
                            <td colspan="7" class="text-center">No students found</td>
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
  <script src="scan/scandemo.js"></script>
  
  <script>
    // Photo preview
    document.getElementById('photo_upload').addEventListener('change', function(e) {
      const file = e.target.files[0];
      if (file) {
        const reader = new FileReader();
        reader.onload = function(event) {
          const preview = document.getElementById('photo_preview');
          preview.src = event.target.result;
          preview.style.display = 'block';
        };
        reader.readAsDataURL(file);
      }
    });


    // Auto-generate registration number for new students
    document.addEventListener('DOMContentLoaded', function() {
      const toasts = document.querySelectorAll('.toast');
      toasts.forEach(toast => {
        setTimeout(() => {
          toast.remove();
        }, 3000);
      });
      
      <?php if (!isset($_GET['edit'])): ?>
        document.querySelector('input[name="registration_number"]').value = "<?= generateRegistrationNumber() ?>";
      <?php endif; ?>
    });
  </script>
</body>
</html>