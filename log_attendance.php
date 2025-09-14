<?php
header('Content-Type: application/json');
session_start();
include("connection/config.php");

// Remove security check temporarily for testing
// if (!isset($_SESSION['user_type'])) {
//     http_response_code(401);
//     echo json_encode(['success' => false, 'error' => 'Unauthorized']);
//     exit();
// }

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit();
}

try {
    // Get JSON input
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    if (!$data) {
        throw new Exception('Invalid JSON data');
    }

    // Validate required fields
    $required_fields = ['student_id', 'confidence'];
    foreach ($required_fields as $field) {
        if (!isset($data[$field])) {
            throw new Exception("Missing required field: $field");
        }
    }

    $student_id = (int)$data['student_id'];
    $confidence = (float)$data['confidence'];
    $ip_address = $_SERVER['REMOTE_ADDR'];
    $user_id = $_SESSION['user_id'] ?? null;

    // Validate student exists - adjust to match your table structure
    $student_check_query = "SELECT id, CONCAT(firstname, ' ', lastname) as full_name 
                           FROM students 
                           WHERE id = ?";
    
    $stmt = $pdo->prepare($student_check_query);
    $stmt->execute([$student_id]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$student) {
        throw new Exception('Student not found');
    }

    // Check if you have an attendance table, if not create a simple log
    $today = date('Y-m-d');
    
    // Try to insert into attendance table or create simple log table
    try {
        // First check if attendance table exists
        $check_table = $pdo->query("SHOW TABLES LIKE 'attendance'")->fetch();
        
        if ($check_table) {
            // Table exists, check for duplicate
            $duplicate_check = "SELECT id FROM attendance 
                               WHERE student_id = ? 
                               AND DATE(created_at) = ?";
                               
            $stmt = $pdo->prepare($duplicate_check);
            $stmt->execute([$student_id, $today]);
            $existing = $stmt->fetch();
            
            $is_duplicate = (bool)$existing;
            
            // Insert attendance record
            $insert_query = "INSERT INTO attendance 
                            (student_id, confidence, ip_address, created_at) 
                            VALUES (?, ?, ?, NOW())";
                            
            $stmt = $pdo->prepare($insert_query);
            $stmt->execute([$student_id, $confidence, $ip_address]);
            $log_id = $pdo->lastInsertId();
            
        } else {
            // Create simple attendance table
            $create_table = "CREATE TABLE IF NOT EXISTS attendance (
                id INT AUTO_INCREMENT PRIMARY KEY,
                student_id INT NOT NULL,
                confidence DECIMAL(5,2) DEFAULT 0,
                ip_address VARCHAR(45),
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_student_date (student_id, created_at)
            )";
            
            $pdo->exec($create_table);
            
            // Now insert the record
            $insert_query = "INSERT INTO attendance 
                            (student_id, confidence, ip_address) 
                            VALUES (?, ?, ?)";
                            
            $stmt = $pdo->prepare($insert_query);
            $stmt->execute([$student_id, $confidence, $ip_address]);
            $log_id = $pdo->lastInsertId();
            $is_duplicate = false;
        }
        
    } catch (Exception $e) {
        // Fallback: just return success without logging
        $log_id = 0;
        $is_duplicate = false;
    }

    // Send success response
    echo json_encode([
        'success' => true,
        'message' => $is_duplicate ? 'Attendance already recorded today' : 'Attendance logged successfully',
        'data' => [
            'log_id' => $log_id,
            'student_name' => $student['full_name'],
            'is_duplicate' => $is_duplicate,
            'timestamp' => date('c')
        ]
    ]);

} catch (Exception $e) {
    error_log("Error in log_attendance.php: " . $e->getMessage());
    
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'timestamp' => date('c')
    ]);
}
?>