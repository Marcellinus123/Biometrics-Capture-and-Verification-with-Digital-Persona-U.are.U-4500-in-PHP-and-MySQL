<?php
include("connection/config.php");

echo "<h2>Database Check</h2>";

try {
    // Check if students table exists and has data
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM students");
    $result = $stmt->fetch();
    echo "<p><strong>Total students:</strong> " . $result['total'] . "</p>";
    
    // Check students with fingerprint data
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM students WHERE fingerprint_data IS NOT NULL AND fingerprint_data != ''");
    $result = $stmt->fetch();
    echo "<p><strong>Students with fingerprint data:</strong> " . $result['total'] . "</p>";
    
    // Show sample students
    $stmt = $pdo->query("SELECT student_id, full_name, fingerprint_data FROM students LIMIT 5");
    $students = $stmt->fetchAll();
    
    echo "<h3>Sample Students:</h3>";
    foreach ($students as $student) {
        echo "<p><strong>ID:</strong> " . $student['student_id'] . 
             ", <strong>Name:</strong> " . $student['full_name'] . 
             ", <strong>Fingerprint:</strong> " . 
             (empty($student['fingerprint_data']) ? 'None' : 'Has data (' . strlen($student['fingerprint_data']) . ' chars)') . 
             "</p>";
    }
    
    // Check if fingerprint_data column exists
    $stmt = $pdo->query("DESCRIBE students");
    $columns = $stmt->fetchAll();
    echo "<h3>Students Table Structure:</h3>";
    foreach ($columns as $column) {
        echo "<p><strong>" . $column['Field'] . ":</strong> " . $column['Type'] . "</p>";
    }
    
} catch (Exception $e) {
    echo "<p><strong>Error:</strong> " . $e->getMessage() . "</p>";
}
?>

