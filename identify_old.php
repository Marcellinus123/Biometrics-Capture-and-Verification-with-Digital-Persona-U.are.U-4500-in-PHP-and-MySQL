<?php
session_start();
include("connection/config.php");

// Security check
if (!isset($_SESSION['user_type'])) {
    header("Location: index.php");
    exit();
}

// Configuration
$flask_api_url = 'http://127.0.0.1:5000/verify'; // Fixed missing slash
$api_key = '351-885-514'; // Should match your Flask API key
$max_file_size = 5 * 1024 * 1024; // 5MB
$allowed_formats = ['png', 'jpg', 'jpeg'];

// Handle AJAX fingerprint verification
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['fingerprint_data'])) {
    try {
        $fingerprint_data = $_POST['fingerprint_data'];
        $format = $_POST['format'] ?? '4';
        
        // Validate format
        if (!in_array($format, ['4', 'png', 'jpg', 'jpeg'])) {
            throw new Exception("Unsupported image format");
        }

        // Extract and validate base64 data
        $base64_pattern = '/^data:image\/([a-zA-Z0-9]+);base64,(.+)$/';
        if (!preg_match($base64_pattern, $fingerprint_data, $matches)) {
            throw new Exception("Invalid image data format");
        }

        $image_type = strtolower($matches[1]);
        $base64_data = $matches[2];
        
        if (!in_array($image_type, $allowed_formats)) {
            throw new Exception("Unsupported image type: " . $image_type);
        }

        // Decode base64 data
        $imageData = base64_decode($base64_data);
        
        if (!$imageData) {
            throw new Exception("Failed to decode image data");
        }

        // Check file size
        if (strlen($imageData) > $max_file_size) {
            throw new Exception("Image file too large (max 5MB)");
        }

        // Create temp file with proper extension
        $temp_file = tempnam(sys_get_temp_dir(), 'fp_') . '.' . $image_type;
        if (!file_put_contents($temp_file, $imageData)) {
            throw new Exception("Failed to save fingerprint image");
        }

        // Validate the saved image
        $image_info = getimagesize($temp_file);
        if (!$image_info) {
            unlink($temp_file);
            throw new Exception("Invalid image file");
        }

        // Send to Flask API with enhanced error handling
        $ch = curl_init();
        // $cfile = new CURLFile($temp_file, "image/{$image_type}", 'fingerprint.' . $image_type);
            $cfile = new CURLFile($temp_file, "image/{$image_type}", 'students_fingerprint/9.png' . $image_type);

        
        curl_setopt_array($ch, [
            CURLOPT_URL => $flask_api_url,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => ['fingerprint' => $cfile],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'X-API-Key: ' . $api_key,
                'User-Agent: BioAttend-PHP-Client/1.0'
            ],
            CURLOPT_TIMEOUT => 60, // Increased timeout for processing
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => false, // Only for development
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3
        ]);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        $info = curl_getinfo($ch);
        curl_close($ch);
        
        // Clean up temp file
        unlink($temp_file);

        // Handle cURL errors
        if ($curl_error) {
            throw new Exception("Network error: " . $curl_error);
        }

        if ($http_code === 0) {
            throw new Exception("Unable to connect to verification service");
        }

        if ($http_code !== 200) {
            $error_msg = "API request failed (HTTP $http_code)";
            if ($response) {
                $error_response = json_decode($response, true);
                if (isset($error_response['error'])) {
                    $error_msg .= ": " . $error_response['error'];
                }
            }
            throw new Exception($error_msg);
        }

        // Parse response
        $result = json_decode($response, true);
        if (!$result) {
            throw new Exception("Invalid response from verification service");
        }

        // Log the verification attempt
        logVerificationAttempt($result);

        // Enhance response with additional data
        if (isset($result['student'])) {
            $result['student']['verified_at'] = date('Y-m-d H:i:s');
            $result['student']['ip_address'] = $_SERVER['REMOTE_ADDR'];
        }

        // Return JSON response
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'data' => $result,
            'processing_time' => $info['total_time'],
            'timestamp' => date('c')
        ]);
        exit();

    } catch (Exception $e) {
        // Log error
        error_log("Fingerprint verification error: " . $e->getMessage());
        
        header('Content-Type: application/json');
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage(),
            'timestamp' => date('c')
        ]);
        exit();
    }
}

// Function to log verification attempts
function logVerificationAttempt($result) {
    global $pdo; // Assuming PDO connection from config.php
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO verification_attempts 
            (user_id, student_id, success, confidence, ip_address, created_at) 
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        
        $student_id = isset($result['student']) ? $result['student']['student_id'] : null;
        $success = isset($result['match']) ? $result['match'] : false;
        $confidence = isset($result['student']) ? $result['student']['confidence'] : 0;
        
        $stmt->execute([
            $_SESSION['user_id'] ?? null,
            $student_id,
            $success,
            $confidence,
            $_SERVER['REMOTE_ADDR']
        ]);
    } catch (Exception $e) {
        error_log("Failed to log verification attempt: " . $e->getMessage());
    }
}

// Handle API health check
if ($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['health_check'])) {
    try {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => str_replace('/verify', '/health', $flask_api_url),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['X-API-Key: ' . $api_key],
            CURLOPT_TIMEOUT => 5
        ]);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        header('Content-Type: application/json');
        echo json_encode([
            'api_available' => $http_code === 200,
            'response' => $response ? json_decode($response, true) : null
        ]);
        exit();
    } catch (Exception $e) {
        header('Content-Type: application/json');
        echo json_encode(['api_available' => false, 'error' => $e->getMessage()]);
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Fingerprint Identification - BioAttend</title>
    <link rel="stylesheet" href="uareu/css/bootstrap-min.css">
    <link rel="stylesheet" href="uareu/app.css">
    <style>
        .verification-status {
            min-height: 60px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .spinner {
            border: 4px solid #f3f3f3;
            border-top: 4px solid #3498db;
            border-radius: 50%;
            width: 30px;
            height: 30px;
            animation: spin 1s linear infinite;
            display: inline-block;
            margin-right: 10px;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .student-info {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin: 10px 0;
        }
        
        .confidence-bar {
            width: 100%;
            background-color: #e0e0e0;
            border-radius: 3px;
            overflow: hidden;
        }
        
        .confidence-fill {
            height: 20px;
            background-color: #28a745;
            transition: width 0.3s ease;
        }
        
        .api-status {
            position: fixed;
            top: 70px;
            right: 20px;
            z-index: 1000;
        }
    </style>
</head>
<body>
    <div id="Container">
        <nav class="navbar navbar-inverse">
            <div class="container-fluid">
                <div class="navbar-header">
                    <div class="navbar-brand">BioAttend</div>
                </div>
                <ul class="nav navbar-nav">
                    <li><a href="admin-dashboard.php">Dashboard</a></li>
                    <li><a href="#" onclick="showSection('content-reader')">Reader</a></li>
                    <li class="active"><a href="#" onclick="showSection('content-capture')">Identify</a></li>
                </ul>
                <ul class="nav navbar-nav navbar-right">
                    <li><a href="#" onclick="checkAPIHealth()">API Status</a></li>
                </ul>
            </div>
        </nav>

        <div id="api-status" class="api-status"></div>
        <div id="notification-area"></div>

        <div id="content-capture">
            <div class="row">
                <div class="col-md-8">
                    <div class="panel panel-primary">
                        <div class="panel-heading">
                            <h3 class="panel-title">Fingerprint Scanner</h3>
                        </div>
                        <div class="panel-body">
                            <div id="status" class="verification-status"></div>
                            <div id="imagediv" class="text-center"></div>
                            
                            <div class="text-center" style="margin-top: 20px;">
                                <button type="button" class="btn btn-success btn-lg" onclick="onStart()">
                                    <span class="glyphicon glyphicon-play"></span> Start Scanner
                                </button>
                                <button type="button" class="btn btn-warning btn-lg" onclick="onStop()">
                                    <span class="glyphicon glyphicon-stop"></span> Stop Scanner
                                </button>
                                <button type="button" class="btn btn-danger btn-lg" onclick="onClear()">
                                    <span class="glyphicon glyphicon-trash"></span> Clear
                                </button>
                            </div>
                            
                            <div class="text-center" style="margin-top: 15px;">
                                <label>
                                    <input type="checkbox" id="autoVerify" checked> Auto-verify captured fingerprints
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4">
                    <div class="panel panel-info">
                        <div class="panel-heading">
                            <h3 class="panel-title">Verification Results</h3>
                        </div>
                        <div class="panel-body" id="verification-results">
                            <p class="text-muted">No verification results yet.</p>
                        </div>
                    </div>
                    
                    <div class="panel panel-default">
                        <div class="panel-heading">
                            <h3 class="panel-title">System Status</h3>
                        </div>
                        <div class="panel-body" id="system-status">
                            <p><strong>Scanner:</strong> <span id="scanner-status">Disconnected</span></p>
                            <p><strong>API:</strong> <span id="api-status-text">Checking...</span></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div id="content-reader" style="display:none;">
            <!-- Reader selection UI -->
            <div class="panel panel-primary">
                <div class="panel-heading">Reader Selection</div>
                <div class="panel-body">
                    <p>Reader selection functionality would go here.</p>
                </div>
            </div>
        </div>
    </div>

    <script src="uareu/scripts/jquery.min.js"></script>
    <script src="uareu/scripts/bootstrap.min.js"></script>
    <script src="uareu/scripts/fingerprint.sdk.min.js"></script>
    <script>
        let lastCapture = null;
        let autoSubmitEnabled = true;
        let verificationInProgress = false;
        
        // Check API health on page load
        document.addEventListener('DOMContentLoaded', function() {
            checkAPIHealth();
            
            // Update auto-verify setting when checkbox changes
            document.getElementById('autoVerify').addEventListener('change', function() {
                autoSubmitEnabled = this.checked;
            });
        });
        
        // Modified sampleAcquired to automatically submit
        if (typeof sampleAcquired === 'function') {
            const originalSampleAcquired = sampleAcquired;
            sampleAcquired = function(s) {
                originalSampleAcquired(s);
                lastCapture = s;
                
                if (autoSubmitEnabled && !verificationInProgress) {
                    autoVerifyFingerprint(s);
                }
            };
        }
        
        function autoVerifyFingerprint(capture) {
            if (verificationInProgress) {
                return;
            }
            
            verificationInProgress = true;
            
            try {
                const samples = JSON.parse(capture.samples);
                const base64Data = 'data:image/png;base64,' + Fingerprint.b64UrlTo64(samples[0]);
                
                // Show loading state
                showVerificationStatus('processing');
                
                // Prepare form data
                const formData = new FormData();
                formData.append('fingerprint_data', base64Data);
                formData.append('format', '4');
                
                // Send to server with enhanced error handling
                fetch(window.location.pathname, {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                    }
                    return response.json();
                })
                .then(data => {
                    if (!data.success) {
                        throw new Error(data.error || 'Verification failed');
                    }
                    
                    const result = data.data;
                    if (result.match && result.student) {
                        const student = result.student;
                        showVerificationSuccess(student);
                        showNotification(
                            `Student identified: ${student.full_name} (ID: ${student.student_number})`,
                            'success'
                        );
                    } else {
                        showVerificationFailure();
                        showNotification("No matching fingerprint found", 'warning');
                    }
                })
                .catch(error => {
                    console.error('Verification error:', error);
                    showVerificationError(error.message);
                    showNotification("Verification failed: " + error.message, 'danger');
                })
                .finally(() => {
                    verificationInProgress = false;
                });
                
            } catch (error) {
                console.error('Processing error:', error);
                showVerificationError(error.message);
                verificationInProgress = false;
            }
        }
        
        function showVerificationStatus(status) {
            const statusDiv = document.getElementById('status');
            const resultsDiv = document.getElementById('verification-results');
            
            switch (status) {
                case 'processing':
                    statusDiv.innerHTML = '<div class="spinner"></div><span class="text-info">Processing fingerprint...</span>';
                    resultsDiv.innerHTML = '<p class="text-info">Processing...</p>';
                    break;
                default:
                    statusDiv.innerHTML = '';
            }
        }
        
        function showVerificationSuccess(student) {
            const confidence = Math.round(student.confidence * 100);
            const resultsDiv = document.getElementById('verification-results');
            
            resultsDiv.innerHTML = `
                <div class="student-info">
                    <h4><span class="glyphicon glyphicon-ok-circle text-success"></span> Match Found</h4>
                    <p><strong>Name:</strong> ${student.full_name}</p>
                    <p><strong>ID:</strong> ${student.student_number}</p>
                    <p><strong>Confidence:</strong> ${confidence}%</p>
                    <div class="confidence-bar">
                        <div class="confidence-fill" style="width: ${confidence}%"></div>
                    </div>
                    <p><small class="text-muted">Verified at: ${new Date().toLocaleString()}</small></p>
                </div>
            `;
        }
        
        function showVerificationFailure() {
            const resultsDiv = document.getElementById('verification-results');
            resultsDiv.innerHTML = `
                <div class="alert alert-warning">
                    <h4><span class="glyphicon glyphicon-exclamation-sign"></span> No Match</h4>
                    <p>Fingerprint not found in database.</p>
                    <p><small class="text-muted">Attempted at: ${new Date().toLocaleString()}</small></p>
                </div>
            `;
        }
        
        function showVerificationError(message) {
            const resultsDiv = document.getElementById('verification-results');
            resultsDiv.innerHTML = `
                <div class="alert alert-danger">
                    <h4><span class="glyphicon glyphicon-remove-circle"></span> Error</h4>
                    <p>${message}</p>
                    <p><small class="text-muted">Error at: ${new Date().toLocaleString()}</small></p>
                </div>
            `;
        }
        
        function checkAPIHealth() {
            fetch(window.location.pathname + '?health_check=1')
                .then(response => response.json())
                .then(data => {
                    const statusSpan = document.getElementById('api-status-text');
                    if (data.api_available) {
                        statusSpan.innerHTML = '<span class="text-success">Online</span>';
                    } else {
                        statusSpan.innerHTML = '<span class="text-danger">Offline</span>';
                    }
                })
                .catch(error => {
                    document.getElementById('api-status-text').innerHTML = '<span class="text-danger">Error</span>';
                });
        }
        
        function showNotification(message, type) {
            const notificationArea = document.getElementById('notification-area');
            const alertDiv = document.createElement('div');
            alertDiv.className = `alert alert-${type} alert-dismissible fade in`;
            alertDiv.innerHTML = `
                ${message}
                <button type="button" class="close" data-dismiss="alert">&times;</button>
            `;
            notificationArea.appendChild(alertDiv);
            
            // Auto-remove after 5 seconds
            setTimeout(() => {
                if (alertDiv.parentNode) {
                    alertDiv.remove();
                }
            }, 5000);
        }
        
        function showSection(sectionId) {
            document.querySelectorAll('#Container > div[id^="content-"]').forEach(div => {
                div.style.display = div.id === sectionId ? 'block' : 'none';
            });
            
            // Update active nav item
            document.querySelectorAll('.nav li').forEach(li => li.classList.remove('active'));
            event.target.closest('li').classList.add('active');
        }
        
        // Enhanced fingerprint scanner event handlers
        function onStart() {
            document.getElementById('scanner-status').innerHTML = '<span class="text-success">Connected</span>';
            showNotification('Scanner started successfully', 'info');
        }
        
        function onStop() {
            document.getElementById('scanner-status').innerHTML = '<span class="text-warning">Stopped</span>';
            showNotification('Scanner stopped', 'info');
        }
        
        function onClear() {
            document.getElementById('verification-results').innerHTML = '<p class="text-muted">No verification results yet.</p>';
            document.getElementById('status').innerHTML = '';
            showNotification('Results cleared', 'info');
        }
        
        // Error handling for fingerprint SDK
        window.addEventListener('error', function(e) {
            console.error('JavaScript error:', e.error);
            if (e.error.message.includes('fingerprint')) {
                showNotification('Fingerprint scanner error: ' + e.error.message, 'danger');
            }
        });
    </script>
</body>
</html>