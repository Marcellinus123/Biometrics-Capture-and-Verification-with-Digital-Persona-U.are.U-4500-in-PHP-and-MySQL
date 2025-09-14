<?php
session_start();
include("connection/config.php");

if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] != 'admin') {
    header("Location: index.php");
    exit();
}

// Create students_fingerprint directory if it doesn't exist
$fingerprintDir = 'students_fingerprint';
if (!file_exists($fingerprintDir)) {
    mkdir($fingerprintDir, 0777, true);
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['save_fingerprint'])) {
        $student_id = $_POST['student_id'];
        $fingerprint_data = $_POST['fingerprint_data'];
        $format = $_POST['format']; 
        
        $stmt = $pdo->prepare("SELECT student_id FROM students WHERE student_id = ?");
        $stmt->execute([$student_id]);
        
        if ($stmt->rowCount() > 0) {
            if ($format == '4') { // PNG format
                // Decode the base64 image data
                $imageData = base64_decode($fingerprint_data);
                
                // Generate filename
                $filename = $fingerprintDir . '/' . $student_id . '.png';
                
                // Save the image file
                if (file_put_contents($filename, $imageData)) {
                    // Update database with filename instead of raw data
                    $update = $pdo->prepare("UPDATE students SET fingerprint_data = ? WHERE student_id = ?");
                    if ($update->execute([$filename, $student_id])) {
                        $_SESSION['success'] = "Fingerprint saved successfully!";
                    } else {
                        $_SESSION['error'] = "Failed to save fingerprint data to database.";
                        // Remove the file if DB update failed
                        unlink($filename);
                    }
                } else {
                    $_SESSION['error'] = "Failed to save fingerprint image file.";
                }
            } else {
                $_SESSION['error'] = "Only PNG format is supported.";
            }
        } else {
            $_SESSION['error'] = "Invalid student ID.";
        }
        
        header("Location: biometrics.php");
        exit();
    }
}

$students = $pdo->query("
    SELECT s.*, p.program_name, 
           (SELECT COUNT(*) FROM student_courses sc WHERE sc.student_id = s.student_id) AS course_count
    FROM students s 
    LEFT JOIN programs p ON s.program_id = p.program_id 
    ORDER BY s.full_name
")->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <title>BioAttend App</title>
    <link rel="stylesheet" href="uareu/css/bootstrap-min.css">
    <link rel="stylesheet" href="uareu/app.css" type="text/css" />
     <link rel="shortcut icon" href="images/LOGO.jpg" />
    <style>
        .container-fluid{
            background: linear-gradient(85deg, #392c70, #6a005b);
        }
    </style>
</head>
<body>
    <div id="Container">
        <nav class="navbar navbar-inverse">
          <div class="container-fluid">
            <div class="navbar-header">
              <div class="navbar-brand" href="#" style="color: white;">BioAttend</div>
            </div>
             <ul class="nav navbar-nav">
              <li id="home">
                <a href="admin-dashboard" style="color: white;">Dashboard</a>
              </li>
            </ul>
            <ul class="nav navbar-nav">
              <li id="home">
                <a href="identify" style="color: white;">Identify</a>
              </li>
            </ul>
            <ul class="nav navbar-nav">
              <li id="Reader" class="">
                <a href="#" style="color: white;" onclick="toggle_visibility(['content-reader','content-capture']);setActive('Reader','Capture')">Reader</a>
              </li>
            </ul>
            <ul class="nav navbar-nav">
              <li id="Capture" class="">
                <a href="#" style="color: white;" onclick="toggle_visibility(['content-capture','content-reader']);setActive('Capture','Reader')">Capture</a>
              </li>
            </ul>                           
          </div>
        </nav>
       
       <?php if (isset($_SESSION['success'])): ?>
           <div class="alert alert-success"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
       <?php endif; ?>
       
       <?php if (isset($_SESSION['error'])): ?>
           <div class="alert alert-danger"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
       <?php endif; ?>
       
       <div id="Scores">
           <h5>Scan Quality : <input type="text" id="qualityInputBox" size="20" style="background-color:#DCDCDC;text-align:center;"></h5> 
       </div>
       
        <div id="content-capture" style="display : none;">    
            <div id="status"></div>            
            <div id="imagediv"></div>
            <div id="contentButtons">
                <form method="post" id="fingerprintForm">
                    <select name="student_id" class="form-control" required>
                        <option value="">Select Student</option>
                        <?php foreach ($students as $student): ?>
                        <option value="<?= $student['student_id'] ?>">
                            <?= htmlspecialchars($student['full_name']) ?> (<?= $student['student_number'] ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                    
                    <table width=70% align="center">
                        <tr>
                            <td>
                                <input type="button" class="btn btn-primary" id="clearButton" value="Clear" onclick="onClear()">
                            </td>
                            <td>
                                <input type="button" class="btn btn-primary" id="start" value="Start" onclick="onStart()">
                            </td>
                            <td>
                               <input type="button" class="btn btn-primary" id="stop" value="Stop" onclick="onStop()">
                            </td>
                            <td>
                                <button type="button" class="btn btn-primary" id="saveToDb" onclick="saveToDatabase()">SAVE TO DB</button>
                            </td>
                        </tr>
                    </table>
                    
                    <input type="hidden" name="fingerprint_data" id="fingerprintData">
                    <input type="hidden" name="format" id="fingerprintFormat">
                    <input type="hidden" name="save_fingerprint" value="1">
                </form>
            </div>
       
            <div id="imageGallery"></div>
            <div id="deviceInfo"></div>

            <div id="saveAndFormats">
                <form name="myForm" style="border:solid grey;padding:5px;">
                    <b>Acquire Formats :</b><br>
                    <table>
                        <tr data-toggle="tooltip" title="Will save data to a .raw file.">
                            <td><input type="checkbox" name="Raw" value="1" onclick="checkOnly(this)"> RAW<br></td>
                        </tr>
                        <tr data-toggle="tooltip" title="Will save data to a Intermediate file">
                            <td><input type="checkbox" name="Intermediate" value="2" onclick="checkOnly(this)"> Feature Set<br></td>
                        </tr>
                        <tr data-toggle="tooltip" title="Will save data to a .wsq file.">
                            <td><input type="checkbox" name="Compressed" value="3" onclick="checkOnly(this)"> WSQ<br></td>
                        </tr>
                        <tr data-toggle="tooltip" title="Will save data to a .png file.">
                            <td><input type="checkbox" name="PngImage" value="4"  checked="true" onclick="checkOnly(this)"> PNG</td>
                        </tr>
                    </table>
                </form>
                <br>
                <input type="button" class="btn btn-primary" id="saveImagePng" value="Export" onclick="onImageDownload()">
            </div>
        </div>

        <div id="content-reader">  
            <h4>Select Reader :</h4>
            <select class="form-control" id="readersDropDown" onchange="selectChangeEvent()"></select>
            <div id="readerDivButtons">
                <table width=70% align="center">
                    <tr>
                        <td>
                            <input type="button" class="btn btn-primary" id="refreshList" value="Refresh List" 
                                onclick="readersDropDownPopulate(false)">
                        </td>
                        <td>
                            <input type="button" class="btn btn-primary" id="capabilities" value="Capabilities"
                            data-toggle="modal" data-target="#myModal" onclick="populatePopUpModal()">
                        </td>  
                    </tr>
                </table>

                <!-- Modal - Pop Up window content-->
                <div class="modal fade" id="myModal" role="dialog">
                    <div class="modal-dialog">
                        <div class="modal-content" id="modalContent">
                            <div class="modal-header">
                                <button type="button" class="close" data-dismiss="modal">&times;</button>
                                <h4 class="modal-title">Reader Information</h4>
                            </div>
                            <div class="modal-body" id="ReaderInformationFromDropDown"></div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="uareu/lib/jquery.min.js"></script> 
    <script src="uareu/lib/bootstrap.min.js"></script>
    <script src="uareu/scripts/es6-shim.js"></script>
    <script src="uareu/scripts/websdk.client.bundle.min.js"></script>
    <script src="uareu/scripts/fingerprint.sdk.min.js"></script>
    <script src="uareu/app.js"></script>
    <script>
        var lastFingerprintCapture = null;
        var originalSampleAcquired = sampleAcquired;
        sampleAcquired = function(s) {
            if (originalSampleAcquired) originalSampleAcquired(s);
            lastFingerprintCapture = s;
        };
        
        function saveToDatabase() {
            if (!lastFingerprintCapture) {
                alert("Please capture a fingerprint first");
                return;
            }
            
            const studentSelect = document.querySelector('select[name="student_id"]');
            if (!studentSelect.value) {
                alert("Please select a student");
                return;
            }
            
            // Force PNG format only
            document.querySelector('input[name="PngImage"]').checked = true;
            
            var format = '4'; // PNG format
            var samples = JSON.parse(lastFingerprintCapture.samples);
            var data = Fingerprint.b64UrlTo64(samples[0]);
            
            document.getElementById('fingerprintData').value = data;
            document.getElementById('fingerprintFormat').value = format;
            document.getElementById('fingerprintForm').submit();
        }
    </script>
</body>
</html>