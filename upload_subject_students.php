<?php
session_start();
require_once 'connect.php';

if (!isset($_SESSION['id_number'])) {
    header("Location: login.php");
    exit();
}

$subject_code = isset($_GET['subject_code']) ? $_GET['subject_code'] : '';
$subject_name = isset($_GET['subject_name']) ? $_GET['subject_name'] : '';
$section = isset($_GET['section']) ? $_GET['section'] : '';

$message = '';
$message_type = '';

// Handle CSV upload
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['csv_file'])) {
    $file = $_FILES['csv_file']['tmp_name'];
    $handle = fopen($file, "r");
    $is_header = true;
    $added_count = 0;
    $error_count = 0;
    
    while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
        if ($is_header) {
            $is_header = false;
            continue;
        }
        
        if (!empty($data) && count($data) >= 2) {
            $student_id = trim($data[0]);
            $student_name = trim($data[1]);
            
            // Check if student exists
            $check_student = $conn->query("SELECT student_id FROM students WHERE student_id = '$student_id'");
            if ($check_student->num_rows == 0) {
                $conn->query("INSERT INTO students (student_id, name, section) VALUES ('$student_id', '$student_name', '$section')");
            }
            
            // Add to subject_students
            $check_subject = $conn->query("SELECT * FROM subject_students WHERE student_id = '$student_id' AND subject_code = '$subject_code' AND section = '$section'");
            if ($check_subject->num_rows == 0) {
                $conn->query("INSERT INTO subject_students (student_id, subject_code, subject_name, section) VALUES ('$student_id', '$subject_code', '$subject_name', '$section')");
                $added_count++;
            } else {
                $added_count++;
            }
        }
    }
    fclose($handle);
    
    $message = "Upload complete! Added: $added_count students, Errors: $error_count";
    $message_type = $added_count > 0 ? "success" : "error";
}

// Get current enrolled students
$students_list = $conn->query("SELECT s.student_id, s.name, s.section FROM subject_students ss JOIN students s ON ss.student_id = s.student_id WHERE ss.subject_code = '$subject_code' AND ss.section = '$section' ORDER BY s.name");
?>

<!DOCTYPE html>
<html>
<head>
    <title>Upload Students - <?php echo htmlspecialchars($subject_name); ?></title>
    <style>
        body { font-family: 'Segoe UI', Arial, sans-serif; background: #f4f4f4; padding: 20px; }
        .container { max-width: 800px; margin: 0 auto; background: white; border-radius: 16px; padding: 25px; }
        h2 { color: #004d00; }
        .message { padding: 10px; border-radius: 8px; margin: 15px 0; }
        .message.success { background: #d4edda; color: #155724; }
        .message.error { background: #f8d7da; color: #721c24; }
        .btn { background: #004d00; color: white; padding: 10px 20px; border: none; border-radius: 8px; cursor: pointer; text-decoration: none; display: inline-block; }
        .btn-back { background: #6c757d; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #eef3f0; color: #004d00; }
    </style>
</head>
<body>
    <div class="container">
        <a href="attendance.php" class="btn btn-back" style="margin-bottom: 15px; display: inline-block;">← Back to Subjects</a>
        <h2>Upload Students for <?php echo htmlspecialchars($subject_code); ?></h2>
        
        <div style="background: #e8f5e9; padding: 15px; border-radius: 8px; margin: 15px 0;">
            <strong>CSV Format:</strong><br>
            student_id, name<br>
            Example:<br>
            S23-0325, Angelo Nacpil<br>
            23-5678, izzy padoc
        </div>
        
        <?php if ($message): ?>
            <div class="message <?php echo $message_type; ?>"><?php echo $message; ?></div>
        <?php endif; ?>
        
        <form method="POST" enctype="multipart/form-data">
            <input type="file" name="csv_file" accept=".csv" required style="margin: 10px 0;">
            <button type="submit" class="btn">Upload CSV</button>
        </form>
        
        <h3>Current Enrolled Students (<?php echo $students_list->num_rows; ?>)</h3>
        <table>
            <thead><tr><th>Student ID</th><th>Name</th><th>Section</th></tr></thead>
            <tbody>
                <?php while ($row = $students_list->fetch_assoc()): ?>
                    <tr><td><?php echo $row['student_id']; ?></td><td><?php echo $row['name']; ?></td><td><?php echo $row['section']; ?></td></tr>
                <?php endwhile; ?>
            </tbody>
        </table>
        
        <div style="margin-top: 20px;">
            <a href="list_of_students.php?subject_code=<?php echo urlencode($subject_code); ?>&subject_name=<?php echo urlencode($subject_name); ?>&section=<?php echo urlencode($section); ?>" class="btn">Take Attendance</a>
        </div>
    </div>
</body>
</html>