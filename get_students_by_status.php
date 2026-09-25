<?php
session_start();
require_once 'connect.php';

header('Content-Type: application/json');

if (!isset($_SESSION['id_number'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$status = isset($_GET['status']) ? $_GET['status'] : '';
$date = isset($_GET['date']) ? $_GET['date'] : '';
$section = isset($_GET['section']) ? $_GET['section'] : '';

if (empty($status) || empty($date)) {
    echo json_encode(['success' => false, 'message' => 'Missing parameters']);
    exit();
}

// SIMPLE DIRECT QUERY
$sql = "SELECT * FROM attendance WHERE status = '$status' AND attendance_date = '$date'";

if (!empty($section)) {
    $sql .= " AND section = '$section'";
}

$result = $conn->query($sql);

$students = [];

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        // Get student name
        $student_id = $row['student_id'];
        $nameResult = $conn->query("SELECT name FROM students WHERE student_id = '$student_id' LIMIT 1");
        $student_name = $student_id;
        if ($nameResult && $nameResult->num_rows > 0) {
            $student_name = $nameResult->fetch_assoc()['name'];
        }
        
        // Count total absences for this student in this subject
        $absentCount = 0;
        $subject_code = $row['subject_code'];
        $absentResult = $conn->query("SELECT COUNT(*) as cnt FROM attendance WHERE student_id = '$student_id' AND subject_code = '$subject_code' AND status = 'Absent'");
        if ($absentResult && $absentResult->num_rows > 0) {
            $absentCount = $absentResult->fetch_assoc()['cnt'];
        }
        
        $students[] = [
            'id_number' => $row['student_id'],
            'name' => $student_name,
            'section' => $row['section'] ?? 'N/A',
            'status' => $row['status'],
            'time_in' => $row['time_in'] ?? '',
            'subject_code' => $row['subject_code'],
            'absent_count' => $absentCount
        ];
    }
}

echo json_encode(['success' => true, 'students' => $students, 'total' => count($students)]);

$conn->close();
?>