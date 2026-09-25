<?php
session_start();
require_once 'connect.php';

// Check if student is logged in
if (!isset($_SESSION['id_number'])) {
    header("Location: login.php");
    exit();
}

$student_id = $_SESSION['id_number'];

// Get student name
$student_query = $conn->query("SELECT name FROM students WHERE student_id = '$student_id'");
$student_name = "";
if ($student_query && $student_query->num_rows > 0) {
    $student = $student_query->fetch_assoc();
    $student_name = $student['name'];
}

// Get subjects where this student is enrolled
$subjects_result = $conn->query("SELECT DISTINCT subject_code, subject_name, section FROM subject_students WHERE student_id = '$student_id' ORDER BY subject_name ASC");

$subjects = [];
if ($subjects_result && $subjects_result->num_rows > 0) {
    while ($row = $subjects_result->fetch_assoc()) {
        $subjects[] = $row;
    }
}

// DEBUG: Check if there are grades in the table (visible in page source)
$debug_grades = $conn->query("SELECT * FROM grades WHERE student_id = '$student_id'");
$debug_count = $debug_grades ? $debug_grades->num_rows : 0;
echo "<!-- DEBUG: Found $debug_count grade records for student $student_id -->";

if ($debug_grades && $debug_grades->num_rows > 0) {
    while ($debug_row = $debug_grades->fetch_assoc()) {
        echo "<!-- DEBUG: Grade record - subject: " . $debug_row['subject'] . ", midterm: " . $debug_row['midterm'] . ", final: " . $debug_row['final'] . " -->";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>My Grades - CCA TRACS</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            display: flex;
            background-color: #f4f4f4;
            min-height: 100vh;
        }
        body::before {
            content: "";
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-image: url('cca.webp');
            background-size: cover;
            background-position: center;
            opacity: 0.3;
            z-index: -1;
        }
        
        /* SIDEBAR - Same as dashboard */
        .sidebar {
            width: 120px;
            background: linear-gradient(180deg, #004d00 0%, #003300 100%);
            height: 100vh;
            position: fixed;
            display: flex;
            flex-direction: column;
            align-items: center;
            padding-top: 30px;
            box-shadow: 5px 0 20px rgba(0,0,0,0.15);
            z-index: 100;
        }
        .sidebar .logo {
            width: 70px;
            margin-bottom: 40px;
            padding: 10px;
            background: rgba(255,255,255,0.1);
            border-radius: 50%;
            transition: all 0.3s;
        }
        .sidebar .logo:hover {
            transform: scale(1.05);
            background: rgba(255,255,255,0.2);
        }
        .sidebar .nav-icons {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 25px;
            flex: 1;
        }
        .sidebar .nav-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 5px;
            cursor: pointer;
            transition: all 0.2s;
            position: relative;
        }
        .sidebar .nav-item a {
            text-decoration: none;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 5px;
        }
        .sidebar .nav-item img {
            width: 45px;
            height: 45px;
            object-fit: contain;
            transition: transform 0.2s;
            filter: brightness(0) invert(1);
            opacity: 0.8;
        }
        .sidebar .nav-item span {
            color: rgba(255,255,255,0.8);
            font-size: 11px;
            font-weight: 500;
            letter-spacing: 0.5px;
        }
        .sidebar .nav-item:hover img {
            transform: translateY(-3px);
            opacity: 1;
        }
        .sidebar .nav-item:hover span {
            color: white;
        }
        .sidebar .nav-item.active img {
            opacity: 1;
            transform: scale(1.1);
        }
        .sidebar .nav-item.active span {
            color: white;
            font-weight: bold;
        }
        .sidebar .nav-item.active::before {
            content: '';
            position: absolute;
            left: -20px;
            top: 10px;
            width: 4px;
            height: 30px;
            background: #ffc107;
            border-radius: 0 4px 4px 0;
        }
        
        .main-content {
            margin-left: 120px;
            padding: 25px;
            width: calc(100% - 120px);
        }
        
        .header {
            background: linear-gradient(135deg, #004d00, #006d1f);
            padding: 1.4rem 2rem;
            border-radius: 1rem 1rem 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0;
        }
        .header h2 {
            color: white;
            font-size: 1.9rem;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .student-info {
            background-color: rgba(255,255,255,0.2);
            color: white;
            padding: 8px 20px;
            border-radius: 20px;
            font-weight: 600;
        }
        .student-info i { margin-right: 8px; }
        .back-btn {
            background-color: rgba(255,255,255,0.2);
            color: white;
            padding: 8px 25px;
            border: none;
            border-radius: 20px;
            cursor: pointer;
            text-decoration: none;
            font-size: 14px;
            transition: all 0.2s;
        }
        .back-btn:hover { background-color: rgba(255,255,255,0.3); }
        
        /* GRADES CONTAINER - NO BLUR, TRANSPARENT */
        .grades-container {
            background: transparent;
            border-radius: 0 0 1rem 1rem;
            padding: 25px;
        }
        
        .grid-container {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 25px;
            margin-top: 20px;
        }
        
        /* SUBJECT CARDS - SOLID WHITE (no blur) */
        .subject-card {
            background: white;
            border-radius: 16px;
            overflow: hidden;
            transition: transform 0.2s, box-shadow 0.2s;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }
        .subject-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.15);
        }
        .card-header {
            background: linear-gradient(135deg, #004d00, #006d1f);
            color: white;
            padding: 20px;
            text-align: center;
        }
        .subject-code { font-size: 20px; font-weight: bold; margin-bottom: 5px; }
        .subject-name { font-size: 16px; opacity: 0.9; }
        .card-body { padding: 20px; }
        .subject-section {
            color: #004d00;
            font-weight: 600;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid #e2eee9;
        }
        .grades-table { width: 100%; margin: 15px 0; }
        .grades-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid #f0f0f0;
        }
        .grades-label { font-weight: 600; color: #555; }
        .grades-value { font-weight: bold; font-size: 18px; color: #004d00; }
        
        /* AVERAGE BOX - SOLID LIGHT GREEN (no blur) */
        .average-box {
            background: #eef3f0;
            padding: 12px;
            border-radius: 10px;
            margin-top: 15px;
            text-align: center;
        }
        .average-label { font-weight: 600; color: #555; }
        .average-value { font-size: 24px; font-weight: bold; color: #004d00; }
        
        /* NO SUBJECTS MESSAGE - SOLID WHITE */
        .no-subjects {
            text-align: center;
            padding: 60px;
            background: white;
            border-radius: 16px;
            color: #2c5a2e;
        }
        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            margin-left: 10px;
        }
        .status-passed { background: #d4edda; color: #155724; }
        .status-failed { background: #f8d7da; color: #721c24; }
        .status-pending { background: #fff3cd; color: #856404; }
        
        @media (max-width: 700px) {
            .sidebar { width: 90px; }
            .main-content { margin-left: 90px; width: calc(100% - 90px); padding: 15px; }
            .sidebar .nav-item img { width: 35px; height: 35px; }
            .sidebar .nav-item span { font-size: 9px; }
            .grid-container { grid-template-columns: 1fr; }
            .header { flex-direction: column; gap: 15px; text-align: center; }
            .header h2 { font-size: 1.2rem; }
        }
    </style>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>
    <!-- SIDEBAR -->
    <div class="sidebar">
        <img src="logo1.png" alt="LOGO" class="logo">
        <div class="nav-icons">
            <div class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'student_dashboard.php' ? 'active' : ''; ?>">
                <a href="student_dashboard.php">
                    <img src="dashboard.png" alt="DASHBOARD">
                    <span>DASHBOARD</span>
                </a>
            </div>
            <div class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'student_attendance.php' ? 'active' : ''; ?>">
                <a href="student_attendance.php">
                    <img src="attendance.png" alt="ATTENDANCE">
                    <span>ATTENDANCE</span>
                </a>
            </div>
            <div class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'student_grades.php' ? 'active' : ''; ?>">
                <a href="student_grades.php">
                    <img src="grades.png" alt="GRADES">
                    <span>GRADES</span>
                </a>
            </div>
            <div class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'student_settings.php' ? 'active' : ''; ?>">
                <a href="student_settings.php">
                    <img src="settings.png" alt="SETTINGS">
                    <span>SETTINGS</span>
                </a>
            </div>
        </div>
    </div>

    <div class="main-content">
        <div class="header">
            <div>
                <h2><i class="fas fa-chart-line"></i> My Grades</h2>
                <div class="student-info">
                    <i class="fas fa-user-graduate"></i> Student: <?php echo htmlspecialchars($student_name); ?> | ID: <?php echo htmlspecialchars($student_id); ?>
                </div>
            </div>
            <a href="student_dashboard.php" class="back-btn">← Back to Dashboard</a>
        </div>
        
        <div class="grades-container">
            <div class="grid-container">
                <?php if (count($subjects) > 0): ?>
                    <?php foreach ($subjects as $subject): 
                        // Try multiple ways to find grades
                        $midterm = 'No grade';
                        $final = 'No grade';
                        $average = 'N/A';
                        $remarks = 'Pending';
                        $status_class = 'status-pending';
                        
                        // Try 1: Match by subject_code (from subject_students) and subject (from grades)
                        $grades_sql = "SELECT * FROM grades WHERE student_id = '$student_id' AND subject = '{$subject['subject_code']}'";
                        $grades_result = $conn->query($grades_sql);
                        
                        // If no result, Try 2: Match by subject_name
                        if (!$grades_result || $grades_result->num_rows == 0) {
                            $grades_sql = "SELECT * FROM grades WHERE student_id = '$student_id' AND subject = '{$subject['subject_name']}'";
                            $grades_result = $conn->query($grades_sql);
                        }
                        
                        // If still no result, Try 3: Get any grade for this student (first record)
                        if (!$grades_result || $grades_result->num_rows == 0) {
                            $grades_sql = "SELECT * FROM grades WHERE student_id = '$student_id' LIMIT 1";
                            $grades_result = $conn->query($grades_sql);
                        }
                        
                        if ($grades_result && $grades_result->num_rows > 0) {
                            $grades = $grades_result->fetch_assoc();
                            
                            if (isset($grades['midterm']) && $grades['midterm'] > 0) {
                                $midterm = $grades['midterm'];
                            }
                            if (isset($grades['final']) && $grades['final'] > 0) {
                                $final = $grades['final'];
                            }
                            if (isset($grades['average']) && $grades['average'] > 0) {
                                $average = number_format($grades['average'], 2);
                            }
                            if (isset($grades['remarks'])) {
                                $remarks = $grades['remarks'];
                                if ($remarks == 'Passed') {
                                    $status_class = 'status-passed';
                                } elseif ($remarks == 'Failed') {
                                    $status_class = 'status-failed';
                                }
                            }
                        }
                    ?>
                        <div class="subject-card">
                            <div class="card-header">
                                <div class="subject-code"><?php echo htmlspecialchars($subject['subject_code']); ?></div>
                                <div class="subject-name"><?php echo htmlspecialchars($subject['subject_name']); ?></div>
                            </div>
                            <div class="card-body">
                                <div class="subject-section">
                                    <i class="fas fa-users"></i> Section: <?php echo htmlspecialchars($subject['section']); ?>
                                </div>
                                
                                <div class="grades-table">
                                    <div class="grades-row">
                                        <span class="grades-label"><i class="fas fa-pen"></i> Midterm:</span>
                                        <span class="grades-value"><?php echo htmlspecialchars($midterm); ?></span>
                                    </div>
                                    <div class="grades-row">
                                        <span class="grades-label"><i class="fas fa-pen"></i> Final:</span>
                                        <span class="grades-value"><?php echo htmlspecialchars($final); ?></span>
                                    </div>
                                </div>
                                
                                <div class="average-box">
                                    <span class="average-label"><i class="fas fa-calculator"></i> Average:</span>
                                    <span class="average-value"><?php echo htmlspecialchars($average); ?></span>
                                    <span class="status-badge <?php echo $status_class; ?>"><?php echo htmlspecialchars($remarks); ?></span>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="no-subjects">
                        <i class="fas fa-folder-open" style="font-size: 48px; margin-bottom: 15px;"></i>
                        <p>You are not enrolled in any subjects yet.</p>
                        <p style="margin-top: 10px; font-size: 14px;">Please contact your instructor to add you to classes.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>