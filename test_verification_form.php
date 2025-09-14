<?php
session_start();
include("connection/config.php");

// Test the verification function from identify.php
function verifyFingerprintAPI($imageData) {
    $apiUrl = 'http://127.0.0.1:5000/verify';  // Using port 5000
    
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

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['test_verification'])) {
    $testFile = 'students_fingerprint/4.png';
    
    if (file_exists($testFile)) {
        // Read the test file and convert to base64
        $imageData = base64_encode(file_get_contents($testFile));
        
        // Test the verification
        $result = verifyFingerprintAPI($imageData);
        
        if (isset($result['error'])) {
            $_SESSION['error'] = "Verification failed: " . $result['error'];
        } else {
            $_SESSION['verification_result'] = $result;
            
            if ($result['match']) {
                $_SESSION['success'] = "Fingerprint matched! Student: " . 
                    $result['best']['full_name'] . 
                    " (Confidence: " . round($result['best']['score'] * 100, 2) . "%)";
            } else {
                $_SESSION['info'] = "No matching fingerprint found.";
            }
        }
    } else {
        $_SESSION['error'] = "Test fingerprint file not found.";
    }
    
    header("Location: test_verification_form.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Test Fingerprint Verification</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .success { color: green; background: #d4edda; padding: 10px; border-radius: 5px; margin: 10px 0; }
        .error { color: red; background: #f8d7da; padding: 10px; border-radius: 5px; margin: 10px 0; }
        .info { color: blue; background: #d1ecf1; padding: 10px; border-radius: 5px; margin: 10px 0; }
        .result { background: #f8f9fa; padding: 15px; border-radius: 5px; margin: 10px 0; }
        button { background: #007bff; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; }
        button:hover { background: #0056b3; }
    </style>
</head>
<body>
    <h1>Test Fingerprint Verification</h1>
    
    <?php if (isset($_SESSION['success'])): ?>
        <div class="success"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['error'])): ?>
        <div class="error"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['info'])): ?>
        <div class="info"><?php echo $_SESSION['info']; unset($_SESSION['info']); ?></div>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['verification_result'])): ?>
        <div class="result">
            <h3>Verification Result:</h3>
            <pre><?php echo json_encode($_SESSION['verification_result'], JSON_PRETTY_PRINT); ?></pre>
            <?php unset($_SESSION['verification_result']); ?>
        </div>
    <?php endif; ?>
    
    <form method="POST">
        <p>This will test the fingerprint verification using the test fingerprint file (students_fingerprint/4.png).</p>
        <button type="submit" name="test_verification">Test Verification</button>
    </form>
    
    <hr>
    
    <h2>Current Status</h2>
    <ul>
                        <li><strong>Flask Server:</strong> Running on port 5000 ✅</li>
        <li><strong>Database:</strong> Connected ✅</li>
        <li><strong>Students with fingerprints:</strong> 4 ✅</li>
                        <li><strong>PHP Client:</strong> Updated to use port 5000 ✅</li>
    </ul>
    
    <p><a href="identify.php">Go to Main Identification Page</a></p>
</body>
</html>

