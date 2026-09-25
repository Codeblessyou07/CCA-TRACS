<?php
session_start();
require_once 'connect.php';

header('Content-Type: application/json');

if (!isset($_SESSION['id_number'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$student_id = isset($_GET['student_id']) ? $_GET['student_id'] : '';
$subject_code = isset($_GET['subject_code']) ? $_GET['subject_code'] : '';

if (empty($student_id) || empty($subject_code)) {
    echo json_encode(['success' => false, 'message' => 'Missing parameters']);
    exit();
}

// Get all absence records for this student in this subject
$sql = "SELECT attendance_date, status, subject_code, section 
        FROM attendance 
        WHERE student_id = '$student_id' 
        AND subject_code = '$subject_code' 
        AND status = 'Absent'
        ORDER BY attendance_date DESC";

$result = $conn->query($sql);

if (!$result) {
    echo json_encode(['success' => false, 'message' => 'Query error: ' . $conn->error]);
    exit();
}

$absences = [];
while ($row = $result->fetch_assoc()) {
    $absences[] = [
        'attendance_date' => $row['attendance_date'],
        'status' => $row['status'],
        'subject_code' => $row['subject_code'],
        'section' => $row['section']
    ];
}

echo json_encode([
    'success' => true,
    'absences' => $absences,
    'total_count' => count($absences)
]);

$conn->close();
?>