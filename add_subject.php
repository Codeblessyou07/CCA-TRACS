<?php
session_start();
require_once 'connect.php';

if (!isset($_SESSION['id_number'])) {
    header("Location: login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $subject_code = $conn->real_escape_string($_POST['subject_code']);
    $subject_name = $conn->real_escape_string($_POST['subject_name']);
    $section = $conn->real_escape_string($_POST['section']);
    
    // Check if subject already exists
    $check_sql = "SELECT * FROM subjects WHERE subject_code = ? AND section = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("ss", $subject_code, $section);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        $_SESSION['message'] = "Subject already exists!";
    } else {
        $insert_sql = "INSERT INTO subjects (subject_code, subject_name, section) VALUES (?, ?, ?)";
        $insert_stmt = $conn->prepare($insert_sql);
        $insert_stmt->bind_param("sss", $subject_code, $subject_name, $section);
        
        if ($insert_stmt->execute()) {
            $_SESSION['message'] = "Subject added successfully!";
        } else {
            $_SESSION['message'] = "Error adding subject!";
        }
        $insert_stmt->close();
    }
    $check_stmt->close();
}

header("Location: attendance.php");
exit();
?>