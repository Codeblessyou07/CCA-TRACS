<?php
session_start();
require_once 'connect.php';

header('Content-Type: application/json');

if (!isset($_SESSION['id_number'])) {
    echo json_encode([]);
    exit();
}

$student_id = $_SESSION['id_number'];
$subject_code = isset($_GET['subject_code']) ? $_GET['subject_code'] : '';

if (empty($subject_code)) {
    echo json_encode([]);
    exit();
}

// Get attendance records for this student and subject only
$attendance_query = $conn->query("SELECT attendance_date as date, status FROM attendance WHERE student_id = '$student_id' AND subject_code = '$subject_code' ORDER BY attendance_date DESC");

$attendance_records = [];
while ($row = $attendance_query->fetch_assoc()) {
    $attendance_records[] = [
        'date' => date('F d, Y', strtotime($row['date'])),
        'status' => $row['status']
    ];
}

echo json_encode($attendance_records);
?>