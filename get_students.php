<?php
header('Content-Type: application/json');
session_start();
include("connection/config.php");

// Require authenticated session
if (!isset($_SESSION['user_type'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

try {
    // Simple query to get all students with fingerprint data
    // Adjust column names to match your actual table structure
    $query = "SELECT 
                 student_id, 
                 student_number, 
                full_name,
                fingerprint_data
              FROM students 
              WHERE fingerprint_data IS NOT NULL 
              AND fingerprint_data != '' 
              ORDER BY student_number ASC";

    // Using your $pdo connection
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Process fingerprint data for client-side use
    $processedStudents = [];
    foreach ($students as $student) {
        $fingerprintValue = $student['fingerprint_data'];

        $fingerprintBase64 = null;
        if (is_string($fingerprintValue)) {
            // If value looks like a file path to an image, read bytes and base64-encode
            $lower = strtolower($fingerprintValue);
            if (preg_match('/\.(png|jpg|jpeg|bmp|tiff)$/', $lower) && file_exists($fingerprintValue)) {
                $bytes = @file_get_contents($fingerprintValue);
                if ($bytes !== false) {
                    $fingerprintBase64 = base64_encode($bytes);
                }
            }

            // Otherwise, if it looks like base64 already, keep it
            if ($fingerprintBase64 === null) {
                $decoded = base64_decode($fingerprintValue, true);
                if ($decoded !== false && $decoded !== '') {
                    $fingerprintBase64 = $fingerprintValue;
                }
            }
        }

        $processedStudents[] = [
            'student_id' => (int)$student['student_id'],
            'student_number' => $student['student_number'],
            'full_name' => $student['full_name'],
            'fingerprint_template' => $fingerprintBase64
        ];
    }

    echo json_encode([
        'success' => true,
        'students' => $processedStudents,
        'count' => count($processedStudents),
        'timestamp' => date('c'),
        'debug' => [
            'total_rows' => count($students),
            'sample_student' => !empty($students) ? [
                'has_fingerprint' => !empty($students[0]['fingerprint_data']),
                'fingerprint_length' => strlen($students[0]['fingerprint_data'] ?? '')
            ] : null
        ]
    ]);

} catch (Exception $e) {
    error_log("Error in get_students.php: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage(),
        'debug_info' => [
            'pdo_available' => isset($pdo),
            'error_detail' => $e->getMessage()
        ]
    ]);
}
?>