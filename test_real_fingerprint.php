<?php
/**
 * Test script for fingerprint verification API with real fingerprint images
 */

function testRealFingerprint() {
    $apiUrl = 'http://127.0.0.1:5000/verify';
    
    // Use a real fingerprint image from the students_fingerprint directory
    $fingerprintFile = 'students_fingerprint/4.png';
    
    if (!file_exists($fingerprintFile)) {
        echo "Error: Fingerprint file not found: $fingerprintFile\n";
        return;
    }
    
    echo "Testing API with real fingerprint: $fingerprintFile\n";
    echo "File size: " . filesize($fingerprintFile) . " bytes\n";
    echo "File exists: " . (file_exists($fingerprintFile) ? 'Yes' : 'No') . "\n\n";
    
    // Test the API
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $apiUrl);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    
    // Add the real fingerprint file
    $postFields = [
        'fingerprint' => new CURLFile($fingerprintFile, 'image/png', 'fingerprint.png')
    ];
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
    
    echo "Sending request...\n";
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    
    echo "HTTP Code: $httpCode\n";
    if ($curlError) {
        echo "cURL Error: $curlError\n";
    }
    
    if ($response) {
        echo "Response:\n";
        echo $response . "\n";
        
        // Try to decode JSON response
        $decoded = json_decode($response, true);
        if ($decoded) {
            echo "Decoded response:\n";
            print_r($decoded);
        }
    }
    
    curl_close($ch);
    echo "\nTest completed.\n";
}

// Run test
echo "=== Testing with Real Fingerprint Image ===\n\n";
testRealFingerprint();
?>



