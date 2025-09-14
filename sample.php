<?php
session_start();
include("connection/config.php");

if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] != 'admin') {
    header("Location: index.php");
    exit();
}

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "bioattend_db_system";


$conn = mysqli_connect($servername, $username, $password, $dbname);

if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

// Your Gemini API Key - Replace with your actual key
//Here are no more using this. This only used to test the possibilities of gemini model
define('GEMINI_API_KEY', 'YOUR-API-KEY');

// Function to get all student fingerprints from database
function getStudentFingerprints($conn) {
    $students = [];
    $query = "SELECT student_id, full_name, student_number, registration_number, fingerprint_data, program_id, level 
              FROM students 
              WHERE fingerprint_data IS NOT NULL AND fingerprint_data != '' AND is_active = 1";
    
    $result = mysqli_query($conn, $query);
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $students[] = $row;
        }
    }
    return $students;
}

// Function to verify fingerprint using Gemini Flash 2.0
function verifyFingerprintWithGemini($capturedImageData, $conn) {
    $students = getStudentFingerprints($conn);
    
    if (empty($students)) {
        return ['error' => 'No student fingerprints found in database'];
    }
    
    // Prepare the API request
    $apiUrl = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash-exp:generateContent?key=' . GEMINI_API_KEY;
    
    // Create the parts array for the request
    $parts = [];
    
    // Add the instruction text
    $parts[] = [
        'text' => 'You are a biometric fingerprint matching expert. I will provide you with one captured fingerprint image followed by multiple reference fingerprint images from a student database. 

Your task is to:
1. Analyze the captured fingerprint against each reference fingerprint
2. Look for matching minutiae points, ridge patterns, and unique characteristics
3. Determine if there is a match with high confidence (above 80%)
4. If a match is found, return the student information

IMPORTANT: Respond ONLY with a valid JSON object in this exact format:

For a successful match:
{
  "match": true,
  "confidence": 0.95,
  "student_id": "8",
  "message": "Fingerprint matched successfully"
}

For no match:
{
  "match": false,
  "confidence": 0.0,
  "student_id": null,
  "message": "No matching fingerprint found"
}

For errors:
{
  "match": false,
  "confidence": 0.0,
  "student_id": null,
  "message": "Error description here"
}

Now analyzing the captured fingerprint:'
    ];
    
    // Add the captured fingerprint
    $parts[] = [
        'inline_data' => [
            'mime_type' => 'image/png',
            'data' => $capturedImageData
        ]
    ];
    
    // Add reference fingerprints with student info
    foreach ($students as $student) {
        $fingerprintPath = $student['fingerprint_data'];
        
        // Check if file exists
        if (file_exists($fingerprintPath)) {
            $fingerprintData = base64_encode(file_get_contents($fingerprintPath));
            
            $parts[] = [
                'text' => "Reference fingerprint for Student ID: {$student['student_id']}, Name: {$student['full_name']}, Student Number: {$student['student_number']}"
            ];
            
            $parts[] = [
                'inline_data' => [
                    'mime_type' => 'image/png',
                    'data' => $fingerprintData
                ]
            ];
        }
    }
    
    // Prepare the complete request body
    $requestBody = [
        'contents' => [
            [
                'parts' => $parts
            ]
        ],
        'generationConfig' => [
            'temperature' => 0.1,
            'topK' => 1,
            'topP' => 0.8,
            'maxOutputTokens' => 1000,
        ]
    ];
    
    // Make the API request
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $apiUrl);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($requestBody));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60); // Increased timeout for multiple images
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        return ['error' => 'cURL Error: ' . $error];
    }
    
    if ($httpCode !== 200) {
        return ['error' => 'API request failed with HTTP code: ' . $httpCode . ' Response: ' . $response];
    }
    
    // Parse the response
    $responseData = json_decode($response, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        return ['error' => 'Failed to parse API response: ' . json_last_error_msg()];
    }
    
    // Extract the generated content
    if (isset($responseData['candidates'][0]['content']['parts'][0]['text'])) {
        $generatedText = $responseData['candidates'][0]['content']['parts'][0]['text'];
        
        // Clean the response and extract JSON
        $generatedText = trim($generatedText);
        $generatedText = preg_replace('/```json\s*/', '', $generatedText);
        $generatedText = preg_replace('/\s*```/', '', $generatedText);
        
        $result = json_decode($generatedText, true);
        
        if (json_last_error() === JSON_ERROR_NONE) {
            // If match found, get full student details
            if ($result['match'] && $result['student_id']) {
                $studentId = $result['student_id'];
                $studentQuery = "SELECT s.*, p.program_name 
                               FROM students s 
                               LEFT JOIN programs p ON s.program_id = p.program_id 
                               WHERE s.student_id = ?";
                
                global $conn;
                $stmt = mysqli_prepare($conn, $studentQuery);
                mysqli_stmt_bind_param($stmt, "i", $studentId);
                mysqli_stmt_execute($stmt);
                $studentResult = mysqli_stmt_get_result($stmt);
                
                if ($studentData = mysqli_fetch_assoc($studentResult)) {
                    $result['student'] = $studentData;
                    
                    // Log the verification attempt
                    logVerification($conn, $studentId, true, $result['confidence']);
                } else {
                    return ['error' => 'Student data not found for matched ID'];
                }
            } else {
                // Log failed verification
                logVerification($conn, null, false, 0);
            }
            
            return $result;
        } else {
            return ['error' => 'Invalid JSON response from Gemini: ' . $generatedText];
        }
    } else {
        return ['error' => 'No valid response from Gemini API'];
    }
}


// Handle verification request
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['verify_fingerprint'])) {
        $fingerprint_data = $_POST['fingerprint_data'];
        
        if (empty($fingerprint_data)) {
            $_SESSION['error'] = "No fingerprint data received. Please capture a fingerprint first.";
            header("Location: verify.php");
            exit();
        }
        
        // Verify the fingerprint using Gemini
        $verificationResult = verifyFingerprintWithGemini($fingerprint_data, $conn);
        
        if (isset($verificationResult['error'])) {
            $_SESSION['error'] = "Verification failed: " . $verificationResult['error'];
        } else {
            $_SESSION['verification_result'] = $verificationResult;
            
            if ($verificationResult['match']) {
                $confidence = round($verificationResult['confidence'] * 100, 2);
                $_SESSION['success'] = "Fingerprint matched! Student: " . 
                    $verificationResult['student']['full_name'] . 
                    " (Confidence: " . $confidence . "%)";
            } else {
                $_SESSION['info'] = $verificationResult['message'];
            }
        }
        
        header("Location: match.php");
        exit();
    }
}
?>

