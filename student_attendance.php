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
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>My Attendance – TOTALIS HUMANAE</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,400;14..32,500;14..32,600;14..32,700&display=swap" rel="stylesheet">
    <style>
        /* ─── BASE (kinuha mula sa prof_dashboard) ─── */
        :root {
            --primary: #004d00;
            --primary-dark: #003300;
            --primary-light: #4c7a4c;
            --primary-gradient: linear-gradient(135deg, #004d00 0%, #003300 100%);
            --gold: #C9A84C;
            --gold-light: #E8D5A3;
            --danger: #B22234;
            --danger-light: #D4A0A8;
            --bg: #F8F4F0;
            --card-bg: #FFFFFF;
            --text: #1A1A1A;
            --text-muted: #6B6B6B;
            --border: #E8E0D8;
            --shadow: 0 8px 32px rgba(26, 26, 26, 0.08);
            --radius: 16px;
            --radius-sm: 10px;
            --font: 'Inter', 'Segoe UI', Arial, sans-serif;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: var(--font);
            display: flex;
            background: var(--bg);
            min-height: 100vh;
        }
        body::before {
            content: "";
            position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
            background-image: url('cca.webp');
            background-size: cover; background-position: center;
            opacity: 0.12; z-index: -1;
        }

        /* ─── Sidebar (eksaktong kopya mula sa prof_dashboard) ─── */
        .sidebar {
            width: 120px;
            background: var(--primary-gradient);
            height: 100vh;
            position: fixed;
            display: flex;
            flex-direction: column;
            align-items: center;
            padding-top: 30px;
            box-shadow: 5px 0 25px rgba(0, 77, 0, 0.3);
            z-index: 100;
        }
        .sidebar .logo {
            width: 70px;
            margin-bottom: 40px;
            padding: 10px;
            background: rgba(255,255,255,0.12);
            border-radius: 50%;
            transition: 0.3s;
            border: 2px solid rgba(255,255,255,0.1);
        }
        .sidebar .logo:hover {
            transform: scale(1.05);
            background: rgba(255,255,255,0.2);
            border-color: var(--gold);
        }
        .sidebar .nav-icons {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 25px;
            flex: 1;
            width: 100%;
        }
        .sidebar .nav-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 5px;
            transition: 0.2s;
            position: relative;
            width: 100%;
            padding: 8px 0;
        }
        .sidebar .nav-item a {
            text-decoration: none;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 5px;
            color: rgba(255,255,255,0.7);
        }
        .sidebar .nav-item img {
            width: 42px;
            height: 42px;
            object-fit: contain;
            transition: 0.2s;
            filter: brightness(0) invert(1);
            opacity: 0.7;
        }
        .sidebar .nav-item span {
            color: rgba(255,255,255,0.7);
            font-size: 10px;
            font-weight: 600;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }
        .sidebar .nav-item:hover a { color: white; }
        .sidebar .nav-item:hover img { transform: translateY(-3px); opacity: 1; }
        .sidebar .nav-item:hover span { color: white; }
        .sidebar .nav-item.active a { color: white; }
        .sidebar .nav-item.active img { opacity: 1; transform: scale(1.1); }
        .sidebar .nav-item.active span { color: var(--gold-light); font-weight: 700; }
        .sidebar .nav-item.active::before {
            content: '';
            position: absolute;
            left: -20px;
            top: 10px;
            width: 4px;
            height: 30px;
            background: var(--gold);
            border-radius: 0 4px 4px 0;
            box-shadow: 0 0 12px rgba(201, 168, 76, 0.5);
        }

        /* ─── Main Content ─── */
        .main-content {
            margin-left: 120px;
            padding: 25px 30px;
            width: calc(100% - 120px);
            min-height: 100vh;
        }

        .header {
            background: var(--primary-gradient);
            padding: 1.4rem 2rem;
            border-radius: var(--radius) var(--radius) 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }
        .header h2 {
            color: white;
            font-size: 1.8rem;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .header h2 i { color: var(--gold); }
        .header .student-info {
            background: rgba(255,255,255,0.15);
            color: white;
            padding: 6px 18px;
            border-radius: 30px;
            font-weight: 500;
            font-size: 0.9rem;
            border: 1px solid rgba(255,255,255,0.1);
        }
        .header .student-info i { margin-right: 8px; }
        .back-btn {
            background: rgba(255,255,255,0.15);
            color: white;
            padding: 8px 25px;
            border: none;
            border-radius: 30px;
            cursor: pointer;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            transition: 0.2s;
            border: 1px solid rgba(255,255,255,0.1);
        }
        .back-btn:hover {
            background: rgba(255,255,255,0.25);
        }

        /* ─── Grid Container ─── */
        .grid-container {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(400px, 1fr));
            gap: 25px;
            margin-top: 25px;
        }

        .subject-card {
            background: var(--card-bg);
            border-radius: var(--radius);
            overflow: hidden;
            transition: 0.2s;
            box-shadow: var(--shadow);
            border: 1px solid var(--border);
        }
        .subject-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(0, 77, 0, 0.12);
        }

        .card-header {
            background: var(--primary-gradient);
            color: white;
            padding: 20px;
            text-align: center;
        }
        .subject-code {
            font-size: 20px;
            font-weight: 700;
            margin-bottom: 5px;
        }
        .subject-name {
            font-size: 16px;
            opacity: 0.9;
        }

        .card-body {
            padding: 20px;
        }

        .subject-section {
            color: var(--primary);
            font-weight: 600;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid var(--border);
        }

        .attendance-summary {
            margin-bottom: 15px;
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 0;
            border-bottom: 1px solid #f0f0f0;
        }
        .summary-label {
            font-weight: 600;
            color: #555;
        }
        .present-count { color: #28a745; font-weight: bold; font-size: 18px; }
        .late-count { color: #ffc107; font-weight: bold; font-size: 18px; }
        .absent-count { color: #dc3545; font-weight: bold; font-size: 18px; }

        /* Attendance List */
        .attendance-list {
            margin-top: 15px;
            border-top: 1px solid var(--border);
            padding-top: 15px;
        }
        .attendance-list h4 {
            color: var(--primary);
            margin-bottom: 10px;
            font-size: 14px;
        }
        .attendance-items {
            max-height: 200px;
            overflow-y: auto;
        }
        .attendance-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 10px;
            border-bottom: 1px solid #f0f0f0;
            font-size: 13px;
        }
        .attendance-item:hover {
            background: #f9f9f9;
        }
        .attendance-date {
            color: #666;
        }
        .attendance-status {
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
        }
        .status-present {
            background: #d4edda;
            color: #155724;
        }
        .status-late {
            background: #fff3cd;
            color: #856404;
        }
        .status-absent {
            background: #f8d7da;
            color: #721c24;
        }

        .no-attendance {
            text-align: center;
            padding: 20px;
            color: #999;
            font-size: 13px;
        }

        .no-subjects {
            text-align: center;
            padding: 60px;
            background: var(--card-bg);
            border-radius: var(--radius);
            color: #7e9a90;
            box-shadow: var(--shadow);
            border: 1px solid var(--border);
        }

        /* ─── Responsive ─── */
        @media (max-width: 768px) {
            .sidebar { width: 90px; }
            .main-content { margin-left: 90px; padding: 15px; }
            .grid-container { grid-template-columns: 1fr; }
            .header { flex-direction: column; text-align: center; }
        }
        @media (max-width: 480px) {
            .sidebar { width: 70px; padding-top: 15px; }
            .sidebar .logo { width: 50px; margin-bottom: 20px; }
            .sidebar .nav-item img { width: 32px; height: 32px; }
            .sidebar .nav-item span { font-size: 8px; }
            .main-content { margin-left: 70px; padding: 10px; }
        }
    </style>
</head>
<body>

    <!-- ═══ SIDEBAR (Eksaktong kopya mula sa prof_dashboard) ═══ -->
    <div class="sidebar">
        <img src="logo1.png" alt="LOGO" class="logo">
        <div class="nav-icons">
            <div class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'student_dashboard.php' ? 'active' : ''; ?>">
                <a href="student_dashboard.php">
                    <img src="dashboard.png" alt="DASHBOARD">
                    <span>Dashboard</span>
                </a>
            </div>
            <div class="nav-item active">
                <a href="student_attendance.php">
                    <img src="attendance.png" alt="ATTENDANCE">
                    <span>Attendance</span>
                </a>
            </div>
            <div class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'student_grades.php' ? 'active' : ''; ?>">
                <a href="student_grades.php">
                    <img src="grades.png" alt="GRADES">
                    <span>Grades</span>
                </a>
            </div>
            <div class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'student_settings.php' ? 'active' : ''; ?>">
                <a href="student_settings.php">
                    <img src="settings.png" alt="SETTINGS">
                    <span>Settings</span>
                </a>
            </div>
        </div>
    </div>

    <!-- ═══ MAIN CONTENT ═══ -->
    <div class="main-content">

        <!-- Header -->
        <div class="header">
            <div>
                <h2><i class="fas fa-calendar-check"></i> My Attendance</h2>
                <div class="student-info">
                    <i class="fas fa-user-graduate"></i> <?php echo htmlspecialchars($student_name); ?> | ID: <?php echo htmlspecialchars($student_id); ?>
                </div>
            </div>
            <a href="student_dashboard.php" class="back-btn">← Back to Dashboard</a>
        </div>

        <!-- Attendance Cards -->
        <div class="grid-container">
            <?php if (count($subjects) > 0): ?>
                <?php foreach ($subjects as $subject): 
                    // Get attendance statistics for this subject
                    $present_sql = "SELECT COUNT(*) as count FROM attendance WHERE student_id = '$student_id' AND subject_code = '{$subject['subject_code']}' AND status = 'Present'";
                    $late_sql = "SELECT COUNT(*) as count FROM attendance WHERE student_id = '$student_id' AND subject_code = '{$subject['subject_code']}' AND status = 'Late'";
                    $absent_sql = "SELECT COUNT(*) as count FROM attendance WHERE student_id = '$student_id' AND subject_code = '{$subject['subject_code']}' AND status = 'Absent'";
                    
                    $present_result = $conn->query($present_sql);
                    $late_result = $conn->query($late_sql);
                    $absent_result = $conn->query($absent_sql);
                    
                    $present_count = $present_result ? $present_result->fetch_assoc()['count'] : 0;
                    $late_count = $late_result ? $late_result->fetch_assoc()['count'] : 0;
                    $absent_count = $absent_result ? $absent_result->fetch_assoc()['count'] : 0;
                    
                    // Get detailed attendance records with dates
                    $attendance_sql = "SELECT attendance_date, status FROM attendance WHERE student_id = '$student_id' AND subject_code = '{$subject['subject_code']}' ORDER BY attendance_date DESC";
                    $attendance_result = $conn->query($attendance_sql);
                    $attendance_records = [];
                    if ($attendance_result && $attendance_result->num_rows > 0) {
                        while ($row = $attendance_result->fetch_assoc()) {
                            $attendance_records[] = $row;
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
                            <div class="attendance-summary">
                                <div class="summary-row">
                                    <span class="summary-label"><i class="fas fa-check-circle" style="color: #28a745;"></i> Present:</span>
                                    <span class="present-count"><?php echo $present_count; ?></span>
                                </div>
                                <div class="summary-row">
                                    <span class="summary-label"><i class="fas fa-clock" style="color: #ffc107;"></i> Late:</span>
                                    <span class="late-count"><?php echo $late_count; ?></span>
                                </div>
                                <div class="summary-row">
                                    <span class="summary-label"><i class="fas fa-times-circle" style="color: #dc3545;"></i> Absent:</span>
                                    <span class="absent-count"><?php echo $absent_count; ?></span>
                                </div>
                            </div>
                            
                            <!-- Attendance List with Dates -->
                            <div class="attendance-list">
                                <h4><i class="fas fa-list"></i> Attendance Records</h4>
                                <div class="attendance-items">
                                    <?php if (count($attendance_records) > 0): ?>
                                        <?php foreach ($attendance_records as $record): 
                                            $status_class = '';
                                            if ($record['status'] == 'Present') {
                                                $status_class = 'status-present';
                                            } elseif ($record['status'] == 'Late') {
                                                $status_class = 'status-late';
                                            } else {
                                                $status_class = 'status-absent';
                                            }
                                        ?>
                                            <div class="attendance-item">
                                                <span class="attendance-date">
                                                    <i class="fas fa-calendar-alt"></i> <?php echo date('F d, Y', strtotime($record['attendance_date'])); ?>
                                                </span>
                                                <span class="attendance-status <?php echo $status_class; ?>">
                                                    <?php echo $record['status']; ?>
                                                </span>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <div class="no-attendance">
                                            <i class="fas fa-info-circle"></i> No attendance records yet.
                                        </div>
                                    <?php endif; ?>
                                </div>
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

</body>
</html>