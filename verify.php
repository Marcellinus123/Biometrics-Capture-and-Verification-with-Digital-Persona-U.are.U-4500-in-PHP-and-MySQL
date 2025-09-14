<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>DigitalPersona Fingerprint Verification</title>
    <link rel="stylesheet" href="uareu/css/bootstrap-min.css">
    <style>
        .verification-panel {
            min-height: 400px;
            border: 2px solid #ddd;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
        }
        
        .fingerprint-display {
            border: 1px solid #ccc;
            min-height: 200px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f9f9f9;
            margin: 10px 0;
        }
        
        .match-result {
            padding: 15px;
            margin: 10px 0;
            border-radius: 5px;
            font-weight: bold;
        }
        
        .match-success {
            background-color: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }
        
        .match-fail {
            background-color: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }
        
        .confidence-meter {
            width: 100%;
            height: 20px;
            background-color: #e9ecef;
            border-radius: 10px;
            overflow: hidden;
            margin: 10px 0;
        }
        
        .confidence-bar {
            height: 100%;
            transition: width 0.5s ease-in-out;
        }
        
        .spinner {
            border: 3px solid #f3f3f3;
            border-top: 3px solid #007bff;
            border-radius: 50%;
            width: 30px;
            height: 30px;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <nav class="navbar navbar-inverse">
            <div class="container-fluid">
                <div class="navbar-header">
                    <div class="navbar-brand">BioAttend - DigitalPersona Verification</div>
                </div>
            </div>
        </nav>

        <div class="row">
            <!-- Fingerprint Capture Panel -->
            <div class="col-md-6">
                <div class="panel panel-primary">
                    <div class="panel-heading">
                        <h3 class="panel-title">Fingerprint Capture</h3>
                    </div>
                    <div class="panel-body">
                        <div id="FingerprintSdkTest">
                            <div id="status"></div>
                            <div id="imagediv" class="fingerprint-display">
                                <p class="text-muted">Place finger on scanner</p>
                            </div>
                            
                            <div class="text-center">
                                <button type="button" class="btn btn-success" onclick="onStart()">
                                    Start Scanner
                                </button>
                                <button type="button" class="btn btn-warning" onclick="onStop()">
                                    Stop Scanner
                                </button>
                                <button type="button" class="btn btn-danger" onclick="onClear()">
                                    Clear
                                </button>
                            </div>
                            
                            <div class="form-group" style="margin-top: 20px;">
                                <label>
                                    <input type="checkbox" id="autoVerify" checked> 
                                    Auto-verify captured fingerprints
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Verification Results Panel -->
            <div class="col-md-6">
                <div class="panel panel-info">
                    <div class="panel-heading">
                        <h3 class="panel-title">Verification Results</h3>
                    </div>
                    <div class="panel-body">
                        <div id="verificationResults">
                            <p class="text-muted text-center">No verification performed yet</p>
                        </div>
                        
                        <div id="studentDatabase" style="max-height: 300px; overflow-y: auto; margin-top: 20px;">
                            <h5>Registered Students: <span id="studentCount">Loading...</span></h5>
                            <div id="studentList"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Debug Panel (for development) -->
        <div class="row">
            <div class="col-md-12">
                <div class="panel panel-default">
                    <div class="panel-heading">
                        <h3 class="panel-title">Debug Information</h3>
                    </div>
                    <div class="panel-body">
                        <div id="debugInfo" style="font-family: monospace; font-size: 12px; max-height: 200px; overflow-y: auto;">
                            <p>Debug information will appear here...</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- DigitalPersona SDK -->
    <script src="uareu/scripts/fingerprint.sdk.min.js"></script>
    <script src="uareu/scripts/jquery.min.js"></script>
    <script>
        // Global variables
        let currentCapture = null;
        let studentDatabase = [];
        let autoVerifyEnabled = true;
        let isVerifying = false;

        // Configuration
        const VERIFICATION_THRESHOLD = 65; // Adjust based on your needs (0-100)
        const MAX_STUDENTS_TO_CHECK = 50; // Limit for performance

        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function() {
            loadStudentDatabase();
            
            // Auto-verify checkbox handler
            document.getElementById('autoVerify').addEventListener('change', function() {
                autoVerifyEnabled = this.checked;
                debugLog(`Auto-verify ${this.checked ? 'enabled' : 'disabled'}`);
            });
        });

        // Load student fingerprints from database
        async function loadStudentDatabase() {
            try {
                debugLog('Loading student database...');
                
                const response = await fetch('get_students.php');
                const data = await response.json();
                
                if (data.success) {
                    studentDatabase = data.students;
                    updateStudentDisplay();
                    debugLog(`Loaded ${studentDatabase.length} students`);
                } else {
                    throw new Error(data.error || 'Failed to load students');
                }
            } catch (error) {
                debugLog(`Error loading students: ${error.message}`);
                showError('Failed to load student database');
            }
        }

        // Update student display
        function updateStudentDisplay() {
            document.getElementById('studentCount').textContent = studentDatabase.length;
            
            const listDiv = document.getElementById('studentList');
            if (studentDatabase.length === 0) {
                listDiv.innerHTML = '<p class="text-muted">No students found</p>';
                return;
            }

            const listHTML = studentDatabase.slice(0, 10).map(student => 
                `<div class="small" style="padding: 2px 0; border-bottom: 1px solid #eee;">
                    <strong>${student.full_name}</strong> (${student.student_number})
                </div>`
            ).join('');
            
            listDiv.innerHTML = listHTML + 
                (studentDatabase.length > 10 ? `<p class="text-muted small">... and ${studentDatabase.length - 10} more</p>` : '');
        }

        // Override the sampleAcquired function from DigitalPersona SDK
        if (typeof sampleAcquired === 'function') {
            const originalSampleAcquired = sampleAcquired;
            sampleAcquired = function(capture) {
                // Call original function to display image
                originalSampleAcquired(capture);
                
                // Store capture and auto-verify if enabled
                currentCapture = capture;
                debugLog('Fingerprint captured successfully');
                
                if (autoVerifyEnabled && !isVerifying) {
                    performVerification(capture);
                }
            };
        }

        // Perform fingerprint verification
        async function performVerification(capture) {
            if (isVerifying) {
                debugLog('Verification already in progress');
                return;
            }

            if (studentDatabase.length === 0) {
                showError('No students in database to verify against');
                return;
            }

            isVerifying = true;
            showVerificationStatus('Verifying fingerprint...');

            try {
                const samples = JSON.parse(capture.samples);
                const capturedTemplate = samples[0]; // Base64 fingerprint template
                
                debugLog(`Starting verification against ${studentDatabase.length} students`);
                
                let bestMatch = null;
                let highestScore = 0;
                let checkedCount = 0;

                // Check against each student (limit for performance)
                for (const student of studentDatabase.slice(0, MAX_STUDENTS_TO_CHECK)) {
                    try {
                        const score = await compareFingerprints(capturedTemplate, student.fingerprint_template);
                        checkedCount++;
                        
                        debugLog(`Student ${student.student_number}: Score ${score}`);
                        
                        if (score > highestScore && score >= VERIFICATION_THRESHOLD) {
                            highestScore = score;
                            bestMatch = student;
                        }
                    } catch (error) {
                        debugLog(`Error comparing with ${student.student_number}: ${error.message}`);
                    }
                }

                debugLog(`Verification complete. Checked ${checkedCount} students. Best score: ${highestScore}`);

                // Display results
                if (bestMatch && highestScore >= VERIFICATION_THRESHOLD) {
                    showVerificationSuccess(bestMatch, highestScore);
                } else {
                    showVerificationFailure(highestScore);
                }

            } catch (error) {
                debugLog(`Verification error: ${error.message}`);
                showError(`Verification failed: ${error.message}`);
            } finally {
                isVerifying = false;
            }
        }

        // Compare two fingerprint templates using DigitalPersona SDK
        async function compareFingerprints(template1, template2) {
            return new Promise((resolve, reject) => {
                try {
                    // Use DigitalPersona's comparison function
                    // This is a simplified version - you'll need to adapt based on your SDK version
                    
                    if (typeof Fingerprint !== 'undefined' && Fingerprint.compare) {
                        // Method 1: Direct SDK comparison
                        const result = Fingerprint.compare(template1, template2);
                        resolve(result.score || 0);
                    } else {
                        // Method 2: Simple comparison fallback
                        const similarity = calculateSimilarity(template1, template2);
                        resolve(similarity);
                    }
                } catch (error) {
                    reject(error);
                }
            });
        }

        // Fallback similarity calculation (basic implementation)
        function calculateSimilarity(template1, template2) {
            if (!template1 || !template2) return 0;
            
            try {
                // Convert base64 to bytes for comparison
                const bytes1 = atob(template1);
                const bytes2 = atob(template2);
                
                if (bytes1.length !== bytes2.length) return 0;
                
                let matches = 0;
                const totalBytes = bytes1.length;
                
                for (let i = 0; i < totalBytes; i++) {
                    if (bytes1.charCodeAt(i) === bytes2.charCodeAt(i)) {
                        matches++;
                    }
                }
                
                return Math.round((matches / totalBytes) * 100);
            } catch (error) {
                debugLog(`Similarity calculation error: ${error.message}`);
                return 0;
            }
        }

        // Display verification success
        function showVerificationSuccess(student, confidence) {
            const resultsDiv = document.getElementById('verificationResults');
            const confidenceColor = confidence >= 80 ? '#28a745' : confidence >= 70 ? '#ffc107' : '#fd7e14';
            
            resultsDiv.innerHTML = `
                <div class="match-result match-success">
                    <h4><i class="glyphicon glyphicon-ok-circle"></i> Match Found!</h4>
                    <p><strong>Student:</strong> ${student.full_name}</p>
                    <p><strong>ID:</strong> ${student.student_number}</p>
                    <p><strong>Course:</strong> ${student.course || 'N/A'}</p>
                    <div class="confidence-meter">
                        <div class="confidence-bar" style="width: ${confidence}%; background-color: ${confidenceColor}"></div>
                    </div>
                    <p><strong>Confidence:</strong> ${confidence}%</p>
                    <p class="small text-muted">Verified at: ${new Date().toLocaleString()}</p>
                </div>
            `;

            // Log attendance (you can implement this)
            logAttendance(student.student_id, confidence);
        }

        // Display verification failure
        function showVerificationFailure(bestScore) {
            const resultsDiv = document.getElementById('verificationResults');
            
            resultsDiv.innerHTML = `
                <div class="match-result match-fail">
                    <h4><i class="glyphicon glyphicon-remove-circle"></i> No Match Found</h4>
                    <p>Fingerprint not recognized in database.</p>
                    <p><strong>Best Score:</strong> ${bestScore}% (Required: ${VERIFICATION_THRESHOLD}%)</p>
                    <p class="small text-muted">Attempted at: ${new Date().toLocaleString()}</p>
                </div>
            `;
        }

        // Show verification status
        function showVerificationStatus(message) {
            const resultsDiv = document.getElementById('verificationResults');
            resultsDiv.innerHTML = `
                <div class="text-center">
                    <div class="spinner"></div>
                    <p class="text-info">${message}</p>
                </div>
            `;
        }

        // Show error message
        function showError(message) {
            const resultsDiv = document.getElementById('verificationResults');
            resultsDiv.innerHTML = `
                <div class="alert alert-danger">
                    <strong>Error:</strong> ${message}
                </div>
            `;
        }

        // Log attendance
        async function logAttendance(studentId, confidence) {
            try {
                const response = await fetch('log_attendance.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        student_id: studentId,
                        confidence: confidence,
                        timestamp: new Date().toISOString()
                    })
                });

                const data = await response.json();
                if (!data.success) {
                    debugLog(`Failed to log attendance: ${data.error}`);
                }
            } catch (error) {
                debugLog(`Error logging attendance: ${error.message}`);
            }
        }

        // Debug logging
        function debugLog(message) {
            const debugDiv = document.getElementById('debugInfo');
            const timestamp = new Date().toLocaleTimeString();
            debugDiv.innerHTML += `<div>[${timestamp}] ${message}</div>`;
            debugDiv.scrollTop = debugDiv.scrollHeight;
        }

        // Manual verification trigger
        function manualVerify() {
            if (currentCapture) {
                performVerification(currentCapture);
            } else {
                showError('No fingerprint captured. Please scan a fingerprint first.');
            }
        }

        // Enhanced scanner controls
        function onStart() {
            debugLog('Scanner started');
        }

        function onStop() {
            debugLog('Scanner stopped');
        }

        function onClear() {
            currentCapture = null;
            document.getElementById('verificationResults').innerHTML = '<p class="text-muted text-center">No verification performed yet</p>';
            debugLog('Scanner cleared');
        }
    </script>
</body>
</html>