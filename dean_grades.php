<?php
session_start();
require_once 'connect.php';

if (!isset($_SESSION['id_number']) || $_SESSION['role'] !== 'dean') {
    header("Location: login.php");
    exit();
}

$dean_name = $_SESSION['name'];

// Get all failed students
$failed_details = [];
$query = $conn->query("
    SELECT 
        g.student_id,
        s.name as student_name,
        g.subject as subject_code,
        subj.subject_name,
        g.midterm,
        g.final,
        g.average,
        g.remarks
    FROM grades g
    JOIN students s ON g.student_id = s.student_id
    LEFT JOIN subjects subj ON g.subject = subj.subject_code
    WHERE g.remarks = 'Failed'
    ORDER BY s.name, g.subject
");
if ($query && $query->num_rows > 0) {
    while ($row = $query->fetch_assoc()) {
        $failed_details[] = $row;
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Failed Students (Grades) - Dean</title>
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
        /* SIDEBAR - same as dashboard */
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
        .content-card {
            background: white;
            border-radius: 24px;
            padding: 30px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            transition: all 0.3s;
        }
        .page-header {
            margin-bottom: 25px;
            border-bottom: 3px solid #dc3545;
            padding-bottom: 15px;
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            flex-wrap: wrap;
            gap: 15px;
        }
        .page-header h2 {
            color: #004d00;
            font-size: 28px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .page-header h2 i {
            color: #dc3545;
        }
        .page-header p {
            color: #666;
            font-size: 14px;
        }
        .stats-badge {
            background: #dc3545;
            color: white;
            padding: 6px 15px;
            border-radius: 30px;
            font-weight: bold;
            font-size: 14px;
        }
        /* Table styling */
        .data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }
        .data-table th {
            background: linear-gradient(135deg, #004d00, #006d1f);
            color: white;
            font-weight: 600;
            padding: 14px 12px;
            text-align: left;
        }
        .data-table td {
            padding: 12px;
            border-bottom: 1px solid #eef2f0;
            vertical-align: middle;
        }
        .data-table tr {
            transition: background 0.2s;
        }
        .data-table tbody tr:hover {
            background: #f8fff8;
        }
        .data-table tr:last-child td {
            border-bottom: none;
        }
        .failed-remark {
            background: #dc3545;
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            font-weight: bold;
            font-size: 12px;
            display: inline-block;
            text-align: center;
            min-width: 70px;
        }
        .no-data {
            text-align: center;
            padding: 60px 20px;
            color: #7e9a90;
            font-size: 16px;
        }
        .no-data i {
            font-size: 48px;
            margin-bottom: 15px;
            opacity: 0.5;
        }
        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-top: 30px;
            background: #f0f4f2;
            padding: 10px 20px;
            border-radius: 40px;
            color: #004d00;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.2s;
        }
        .back-link:hover {
            background: #004d00;
            color: white;
            transform: translateX(-5px);
        }
        /* Responsive */
        @media (max-width: 900px) {
            .data-table {
                font-size: 12px;
            }
            .data-table th, .data-table td {
                padding: 8px 6px;
            }
        }
        @media (max-width: 700px) {
            .sidebar { width: 90px; }
            .main-content { margin-left: 90px; width: calc(100% - 90px); padding: 15px; }
            .content-card { padding: 20px; }
            .page-header h2 { font-size: 22px; }
            .data-table, .data-table tbody, .data-table tr, .data-table td {
                display: block;
                width: 100%;
            }
            .data-table thead { display: none; }
            .data-table tr {
                margin-bottom: 15px;
                border: 1px solid #ddd;
                border-radius: 12px;
                padding: 10px;
                background: white;
            }
            .data-table td {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 8px 10px;
                border-bottom: 1px solid #eee;
            }
            .data-table td:before {
                content: attr(data-label);
                font-weight: bold;
                width: 40%;
                color: #004d00;
            }
            .failed-remark { display: inline-block; }
        }
    </style>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>
    <div class="sidebar">
        <img src="logo1.png" alt="LOGO" class="logo">
        <div class="nav-icons">
            <div class="nav-item">
                <a href="dean_dashboard.php">
                    <img src="dashboard.png" alt="DASHBOARD">
                    <span>DASHBOARD</span>
                </a>
            </div>
            <div class="nav-item">
                <a href="dean_attendance.php">
                    <img src="attendance.png" alt="ATTENDANCE">
                    <span>ATTENDANCE</span>
                </a>
            </div>
            <div class="nav-item active">
                <a href="dean_grades.php">
                    <img src="grades.png" alt="GRADES">
                    <span>GRADES</span>
                </a>
            </div>
            <div class="nav-item">
                <a href="dean_settings.php">
                    <img src="settings.png" alt="SETTINGS">
                    <span>SETTINGS</span>
                </a>
            </div>
        </div>
    </div>

    <div class="main-content">
        <div class="content-card">
            <div class="page-header">
                <div>
                    <h2><i class="fas fa-exclamation-triangle"></i> Students with Failed Grades</h2>
                    <p>List of students who failed a subject – includes grades and subject details.</p>
                </div>
                <div class="stats-badge">
                    <i class="fas fa-users"></i> <?php echo count($failed_details); ?> failed record(s)
                </div>
            </div>
            <?php if (count($failed_details) > 0): ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Student ID</th>
                            <th>Student Name</th>
                            <th>Subject Code</th>
                            <th>Subject Name</th>
                            <th>Midterm</th>
                            <th>Final</th>
                            <th>Average</th>
                            <th>Remarks</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($failed_details as $failed): ?>
                            <tr>
                                <td data-label="Student ID"><?php echo htmlspecialchars($failed['student_id']); ?></td>
                                <td data-label="Student Name"><?php echo htmlspecialchars($failed['student_name']); ?></td>
                                <td data-label="Subject Code"><?php echo htmlspecialchars($failed['subject_code']); ?></td>
                                <td data-label="Subject Name"><?php echo htmlspecialchars($failed['subject_name'] ?? $failed['subject_code']); ?></td>
                                <td data-label="Midterm"><?php echo htmlspecialchars($failed['midterm']); ?></td>
                                <td data-label="Final"><?php echo htmlspecialchars($failed['final']); ?></td>
                                <td data-label="Average"><strong><?php echo htmlspecialchars($failed['average']); ?></strong></td>
                                <td data-label="Remarks"><span class="failed-remark"><?php echo htmlspecialchars($failed['remarks']); ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="no-data">
                    <i class="fas fa-smile-wink"></i>
                    <p>No failed students found. Great job!</p>
                </div>
            <?php endif; ?>
            <a href="dean_dashboard.php" class="back-link"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
        </div>
    </div>
</body>
</html>