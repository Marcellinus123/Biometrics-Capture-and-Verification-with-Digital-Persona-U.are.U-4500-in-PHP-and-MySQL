<?php
// Test PHP connection to the fixed Flask server

function testFlaskConnection() {
    $apiUrl = 'http://127.0.0.1:5000/health';
    
    echo "<h2>Testing Flask Server Connection</h2>";
    
    try {
        // Test health endpoint
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $apiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'User-Agent: BioAttend-PHP-Client/1.0'
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        
        echo "<p><strong>Health Endpoint Test:</strong></p>";
        echo "<p>URL: $apiUrl</p>";
        echo "<p>HTTP Code: $httpCode</p>";
        
        if ($curlError) {
            echo "<p style='color: red;'>❌ cURL Error: $curlError</p>";
        } elseif ($httpCode == 200) {
            echo "<p style='color: green;'>✅ Connection successful!</p>";
            
            // Parse response
            $data = json_decode($response, true);
            if ($data) {
                echo "<p><strong>Server Response:</strong></p>";
                echo "<ul>";
                echo "<li>Status: " . ($data['status'] ?? 'N/A') . "</li>";
                echo "<li>Method: " . ($data['matching_method'] ?? 'N/A') . "</li>";
                echo "<li>Threshold: " . ($data['visual_threshold'] ?? 'N/A') . "</li>";
                echo "<li>Timestamp: " . ($data['timestamp'] ?? 'N/A') . "</li>";
                echo "</ul>";
            }
        } else {
            echo "<p style='color: red;'>❌ Connection failed with HTTP code: $httpCode</p>";
            if ($response) {
                echo "<p>Response: $response</p>";
            }
        }
        
    } catch (Exception $e) {
        echo "<p style='color: red;'>❌ Exception: " . $e->getMessage() . "</p>";
    }
}

function testFingerprintVerification() {
    $apiUrl = 'http://127.0.0.1:5000/verify';
    
    echo "<h2>Testing Fingerprint Verification</h2>";
    
    // Check if we have a test fingerprint file
    $testFile = 'students_fingerprint/4.png';
    if (!file_exists($testFile)) {
        echo "<p style='color: red;'>❌ Test fingerprint file not found: $testFile</p>";
        return;
    }
    
    echo "<p>Using test file: $testFile</p>";
    echo "<p>File size: " . filesize($testFile) . " bytes</p>";
    
    try {
        // Test verification endpoint
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $apiUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'User-Agent: BioAttend-PHP-Client/1.0'
        ]);
        
        // Create file upload
        $postFields = [
            'fingerprint' => new CURLFile($testFile, 'image/png', 'test_fingerprint.png')
        ];
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        
        echo "<p><strong>Verification Test:</strong></p>";
        echo "<p>HTTP Code: $httpCode</p>";
        
        if ($curlError) {
            echo "<p style='color: red;'>❌ cURL Error: $curlError</p>";
        } elseif ($httpCode == 200) {
            echo "<p style='color: green;'>✅ Verification successful!</p>";
            
            // Parse response
            $data = json_decode($response, true);
            if ($data) {
                echo "<p><strong>Verification Response:</strong></p>";
                echo "<ul>";
                echo "<li>Match: " . ($data['match'] ? 'Yes' : 'No') . "</li>";
                if (isset($data['best'])) {
                    echo "<li>Best Match: " . ($data['best']['full_name'] ?? 'N/A') . "</li>";
                    echo "<li>Score: " . ($data['best']['score'] ?? 'N/A') . "</li>";
                }
                echo "<li>Total Compared: " . ($data['total_compared'] ?? 'N/A') . "</li>";
                echo "<li>Method: " . ($data['method'] ?? 'N/A') . "</li>";
                echo "</ul>";
            }
        } else {
            echo "<p style='color: red;'>❌ Verification failed with HTTP code: $httpCode</p>";
            if ($response) {
                echo "<p>Response: $response</p>";
            }
        }
        
    } catch (Exception $e) {
        echo "<p style='color: red;'>❌ Exception: " . $e->getMessage() . "</p>";
    }
}

// Run tests
testFlaskConnection();
echo "<hr>";
testFingerprintVerification();
?>

