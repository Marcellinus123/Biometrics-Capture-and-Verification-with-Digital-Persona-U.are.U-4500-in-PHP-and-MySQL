<?php
session_start();
include("connection/config.php");

if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] != 'admin') {
    header("Location: index.php");
    exit();
}

// Function to verify fingerprint via API
function verifyFingerprintAPI($imageData) {
    $apiUrl = 'http://127.0.0.1:5000/verify';  // Updated to use port 5000
    
    // Create a temporary file with proper PNG extension
    $tempDir = sys_get_temp_dir();
    $tempFile = $tempDir . '/fp_' . uniqid() . '.png';
    
    // Decode base64 data and save as PNG file
    $decodedData = base64_decode($imageData);
    if ($decodedData === false) {
        return ['error' => 'Invalid base64 image data'];
    }
    
    if (file_put_contents($tempFile, $decodedData) === false) {
        return ['error' => 'Failed to create temporary file'];
    }
    
    // Verify the file was created and has content
    if (!file_exists($tempFile) || filesize($tempFile) == 0) {
        unlink($tempFile);
        return ['error' => 'Temporary file creation failed'];
    }
    
    // Prepare the cURL request
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $apiUrl);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    // Add the fingerprint file with proper MIME type
    $postFields = [
        'fingerprint' => new CURLFile($tempFile, 'image/png', 'fingerprint.png')
    ];
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
    
    // Execute the request
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    
    // Clean up
    curl_close($ch);
    unlink($tempFile);
    
    if ($curlError) {
        return ['error' => 'cURL error: ' . $curlError];
    }
    
    if ($httpCode == 200) {
        $decodedResponse = json_decode($response, true);
        if ($decodedResponse === null) {
            return ['error' => 'Invalid JSON response from API'];
        }
        return $decodedResponse;
    }
    
    // Try to get error details from response
    $errorDetails = '';
    if ($response) {
        $errorResponse = json_decode($response, true);
        if ($errorResponse && isset($errorResponse['error'])) {
            $errorDetails = ': ' . $errorResponse['error'];
        }
    }
    
    return ['error' => 'API request failed with code: ' . $httpCode . $errorDetails];
}

// Handle verification request
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['verify_fingerprint'])) {
        $fingerprint_data = $_POST['fingerprint_data'];
        
        // Verify the fingerprint using the API
        $verificationResult = verifyFingerprintAPI($fingerprint_data);
        
        if (isset($verificationResult['error'])) {
            $_SESSION['error'] = "Verification failed: " . $verificationResult['error'];
        } else {
            $_SESSION['verification_result'] = $verificationResult;
            
            if ($verificationResult['match']) {
                $_SESSION['success'] = "Fingerprint matched! Student: " . 
                    $verificationResult['best']['full_name'] . 
                    " (Confidence: " . round($verificationResult['best']['score'] * 100, 2) . "%)";
            } else {
                $_SESSION['info'] = "No matching fingerprint found.";
            }
        }
        
        header("Location: identify.php");
        exit();
    }
}
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
        .verification-result {
            margin: 20px 0;
            padding: 15px;
            border-radius: 5px;
        }
        .match {
            background-color: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }
        .no-match {
            background-color: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }
        .student-info {
            margin: 10px 0;
            padding: 10px;
            background-color: #f8f9fa;
            border-radius: 3px;
        }
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
                <a href="biometrics" style="color: white;">Biometrics</a>
              </li>
            </ul>
            <ul class="nav navbar-nav">
              <li id="Reader" class="">
                <a href="#" style="color: white;" onclick="toggle_visibility(['content-reader','content-capture']);setActive('Reader','Capture')">Reader</a>
              </li>
            </ul>
            <ul class="nav navbar-nav">
              <li id="Capture" class="">
                <a href="#" style="color: white;" onclick="toggle_visibility(['content-capture','content-reader']);setActive('Capture','Reader')">Verification</a>
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
       
       <?php if (isset($_SESSION['info'])): ?>
           <div class="alert alert-info"><?php echo $_SESSION['info']; unset($_SESSION['info']); ?></div>
       <?php endif; ?>
       
       <?php if (isset($_SESSION['verification_result'])): 
           $result = $_SESSION['verification_result'];
           unset($_SESSION['verification_result']);
           
           // Debug: Show the raw result structure
           if (isset($_GET['debug'])) {
               echo '<div class="alert alert-info"><strong>Debug Info:</strong><pre>' . print_r($result, true) . '</pre></div>';
           }
       ?>
           <div class="verification-result <?php echo $result['match'] ? 'match' : 'no-match'; ?>">
               <h4>Verification Result</h4>
               <p><strong>Status:</strong> <?php echo $result['match'] ? 'MATCH FOUND' : 'NO MATCH'; ?></p>
               <p><strong>Confidence:</strong>
                <?php 
                if (isset($result['best']['score'])) {
                    echo number_format($result['best']['score'] * 100, 2) . '%';
                } elseif (isset($result['closest_match']['visual_similarity'])) {
                    echo number_format($result['closest_match']['visual_similarity'] * 100, 2) . '% (best candidate)';
                } else {
                    echo 'N/A';
                }
                ?>
               </p>
               <?php if (isset($result['method'])): ?>
                   <p><strong>Method:</strong> <?php echo $result['method']; ?></p>
               <?php elseif (isset($result['algorithm'])): ?>
                   <p><strong>Algorithm:</strong> <?php echo $result['algorithm']; ?></p>
               <?php endif; ?>
               <?php if (isset($result['processing_time'])): ?>
                   <p><strong>Processing Time:</strong> <?php echo $result['processing_time']; ?> seconds</p>
               <?php endif; ?>
               
               <?php if ($result['match'] && isset($result['best'])): ?>
                   <div class="student-info">
                       <h5>Matched Student</h5>
                       <p><strong>Name:</strong> <?php echo $result['best']['full_name']; ?></p>
                       <p><strong>Student ID:</strong> <?php echo $result['best']['student_id']; ?></p>
                       <p><strong>Match Score:</strong> <?php echo number_format($result['best']['score'] * 100, 2) . '%'; ?></p>
                       <p><strong>Quality Score:</strong> <?php echo number_format($result['quality_score'] * 100, 2) . '%'; ?></p>
                   </div>
               <?php endif; ?>
           </div>
       <?php endif; ?>
       
       <div id="Scores">
           <h5>Scan Quality : <input type="text" id="qualityInputBox" size="20" style="background-color:#DCDCDC;text-align:center;"></h5> 
       </div>
       
        <div id="content-capture" style="display : none;">    
            <div id="status"></div>            
            <div id="imagediv"></div>
            <div id="contentButtons">
                <form method="post" id="verifyForm">                    
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
                                <button type="button" class="btn btn-primary" id="send_to_api" onclick="verify()">VERIFY</button>
                            </td>
                        </tr>
                    </table>
                    
                    <input type="hidden" name="fingerprint_data" id="fingerprintData">
                    <input type="hidden" name="verify_fingerprint" value="1">
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
                            <td><input type="checkbox" name="Intermediate"  value="2" onclick="checkOnly(this)"> Feature Set<br></td>
                        </tr>
                        <tr data-toggle="tooltip" title="Will save data to a .wsq file.">
                            <td><input type="checkbox" name="Compressed" value="3" onclick="checkOnly(this)"> WSQ<br></td>
                        </tr>
                        <tr data-toggle="tooltip" title="Will save data to a .png file.">
                            <td><input type="checkbox" name="PngImage" value="4" checked="true"  onclick="checkOnly(this)"> PNG</td>
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
            
            // Update the image display immediately
            var samples = JSON.parse(s.samples);
            var data = Fingerprint.b64UrlTo64(samples[0]);
            
            // Show the captured image
            var img = document.createElement('img');
            img.src = 'data:image/png;base64,' + data;
            img.style.maxWidth = '300px';
            img.style.maxHeight = '300px';
            
            var imageDiv = document.getElementById('imagediv');
            imageDiv.innerHTML = '';
            imageDiv.appendChild(img);
        };

        function verify() {
            if (!lastFingerprintCapture) {
                alert("Please capture a fingerprint first");
                return;
            }
            
            // Force PNG format only
            document.querySelector('input[name="PngImage"]').checked = true;
            
            var format = '4'; // PNG format
            var samples = JSON.parse(lastFingerprintCapture.samples);
            var data = Fingerprint.b64UrlTo64(samples[0]);
            
            document.getElementById('fingerprintData').value = data;
            document.getElementById('verifyForm').submit();
        }
        
        // Enhanced toggle function
        function toggle_visibility(idsToShow) {
            // Hide all content divs
            var contentDivs = ['content-reader', 'content-capture'];
            contentDivs.forEach(function(id) {
                document.getElementById(id).style.display = 'none';
            });
            
            // Show the requested divs
            idsToShow.forEach(function(id) {
                document.getElementById(id).style.display = 'block';
            });
        }
        
        // Enhanced setActive function
        function setActive(activeId, inactiveId) {
            document.getElementById(activeId).classList.add('active');
            document.getElementById(inactiveId).classList.remove('active');
        }
    </script>
</body>
</html>