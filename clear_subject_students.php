<?php
session_start();
require_once 'connect.php';

$subject_code = $_GET['subject_code'];
$subject_name = $_GET['subject_name'];
$section = $_GET['section'];

// Delete all students from this subject
$conn->query("DELETE FROM subject_students WHERE subject_code = '$subject_code'");

header("Location: list_of_students.php?subject_code=$subject_code&subject_name=$subject_name&section=$section");
exit();
?>