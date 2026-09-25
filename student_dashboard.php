<?php
session_start();
require_once 'connect.php';
if (!isset($_SESSION['id_number'])) {
    header("Location: login.php");
    exit();
}
$student_id = $_SESSION['id_number'];
$student_name = $_SESSION['name'];

// Attendance totals
$present_total = $conn->query("SELECT COUNT(*) as count FROM attendance WHERE student_id = '$student_id' AND status = 'Present'")->fetch_assoc()['count'];
$late_total = $conn->query("SELECT COUNT(*) as count FROM attendance WHERE student_id = '$student_id' AND status = 'Late'")->fetch_assoc()['count'];
$absent_total = $conn->query("SELECT COUNT(*) as count FROM attendance WHERE student_id = '$student_id' AND status = 'Absent'")->fetch_assoc()['count'];
$total_att = $present_total + $late_total + $absent_total;
$attendance_rate = $total_att > 0 ? round(($present_total + $late_total) / $total_att * 100) : 0;

// GPA
$gpa_query = $conn->query("SELECT AVG(average) as gpa FROM grades WHERE student_id = '$student_id'");
$gpa = $gpa_query ? $gpa_query->fetch_assoc()['gpa'] : 0;

// At-risk
$at_risk = $conn->query("SELECT COUNT(*) as cnt FROM attendance WHERE student_id = '$student_id' AND status = 'Absent' GROUP BY subject_code HAVING COUNT(*) >= 2")->num_rows > 0;

// Course progress
$courses = $conn->query("SELECT subject, midterm, final, average, remarks FROM grades WHERE student_id = '$student_id'");

// Pass rate
$total_subjects = $courses ? $courses->num_rows : 0;
$passed = 0;
$failed = 0;
if ($courses) {
    $courses_data = [];
    while ($row = $courses->fetch_assoc()) {
        $courses_data[] = $row;
        if ($row['remarks'] == 'Passed') $passed++;
        else if ($row['remarks'] == 'Failed') $failed++;
    }
    $courses = $conn->query("SELECT subject, midterm, final, average, remarks FROM grades WHERE student_id = '$student_id'");
}
$pass_rate = $total_subjects > 0 ? round($passed / $total_subjects * 100) : 0;
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Student Dashboard – TOTALIS HUMANAE</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,400;14..32,500;14..32,600;14..32,700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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

        /* ─── Sidebar ─── */
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
        .header .date-badge {
            background: rgba(255,255,255,0.15);
            color: white;
            padding: 8px 22px;
            border-radius: 30px;
            font-weight: 500;
            border: 1px solid rgba(255,255,255,0.1);
        }

        /* ─── Cards (4 stats) ─── */
        .cards-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin: 25px 0;
        }
        .card {
            background: var(--card-bg);
            border-radius: var(--radius);
            padding: 25px 20px;
            text-align: center;
            box-shadow: var(--shadow);
            border: 1px solid var(--border);
            transition: 0.2s;
        }
        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(0, 77, 0, 0.12);
        }
        .card .label {
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: var(--text-muted);
            margin-bottom: 8px;
        }
        .card .percentage {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--text);
        }
        .card .percentage.zero { color: #bdc3c7; }
        .card .percentage.gpa-color { color: var(--gold); }
        .card .percentage.pass-color { color: var(--primary); }
        .card .percentage.status-color { color: var(--danger); }

        /* ─── This Week Section (may chart) ─── */
        .week-section {
            background: var(--card-bg);
            border-radius: var(--radius);
            padding: 25px 30px;
            box-shadow: var(--shadow);
            border: 1px solid var(--border);
            margin-bottom: 30px;
        }
        .week-section .week-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        .week-section .week-header h3 {
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--primary);
        }
        .week-section .week-header .week-range {
            color: var(--text-muted);
            font-size: 0.95rem;
        }
        .chart-container {
            position: relative;
            height: 200px;
            margin-top: 10px;
        }

        /* ─── Alerts ─── */
        .alerts-section {
            background: var(--card-bg);
            border-radius: var(--radius);
            padding: 22px 28px;
            box-shadow: var(--shadow);
            border: 1px solid var(--border);
            margin-bottom: 28px;
        }
        .alerts-section .alert-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 14px;
        }
        .alerts-section .alert-header h3 {
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--primary);
        }
        .alerts-section .alert-header h3 i { color: var(--gold); margin-right: 8px; }
        .alert-item {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 14px 18px;
            border-radius: var(--radius-sm);
            margin-bottom: 10px;
            border-left: 4px solid var(--danger);
            background: #FFF5F5;
            transition: 0.2s;
        }
        .alert-item:last-child { margin-bottom: 0; }
        .alert-item .alert-icon { font-size: 18px; flex-shrink: 0; }
        .alert-item .alert-text { flex: 1; font-size: 14px; line-height: 1.4; }
        .alert-item .alert-text strong { font-weight: 600; }
        .alert-item .btn-sm {
            padding: 6px 16px;
            border-radius: 50px;
            border: none;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            background: var(--danger);
            color: #fff;
            flex-shrink: 0;
            transition: 0.2s;
        }
        .alert-item .btn-sm:hover { background: #8B1A1A; transform: scale(1.02); }
        .alert-item.success { background: #E8F5E9; border-left-color: #2E7D32; }
        .alert-item.warning { background: #FFF8E1; border-left-color: var(--gold); }

        /* ─── Table ─── */
        .table-wrap {
            background: var(--card-bg);
            border-radius: var(--radius);
            padding: 22px 24px 24px;
            box-shadow: var(--shadow);
            border: 1px solid var(--border);
            overflow-x: auto;
        }
        .table-wrap .table-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 16px;
        }
        .table-wrap .table-header h3 {
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--primary);
        }
        .table-wrap .table-header h3 i { color: var(--gold); margin-right: 8px; }
        .table-wrap .table-header .sem-badge {
            background: var(--bg);
            padding: 4px 14px;
            border-radius: 50px;
            font-size: 12px;
            font-weight: 500;
            color: var(--text-muted);
        }
        table { width: 100%; border-collapse: collapse; font-size: 14px; }
        table thead th {
            text-align: left;
            padding: 12px 12px 12px 0;
            font-weight: 600;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--text-muted);
            border-bottom: 2px solid var(--border);
        }
        table tbody td {
            padding: 12px 12px 12px 0;
            border-bottom: 1px solid var(--border);
            color: var(--text);
        }
        table tbody tr:last-child td { border-bottom: none; }
        table tbody tr:hover td { background: #FAFAFA; }
        .status-badge {
            display: inline-block;
            padding: 4px 14px;
            border-radius: 50px;
            font-size: 12px;
            font-weight: 600;
        }
        .status-badge.passed { background: #E3F2FD; color: #0D47A1; }
        .status-badge.failed { background: var(--danger); color: #fff; }
        .status-badge.pending { background: #F3F4F6; color: var(--text-muted); }

        /* ─── Responsive ─── */
        @media (max-width: 768px) {
            .sidebar { width: 90px; }
            .main-content { margin-left: 90px; padding: 15px; }
            .cards-grid { grid-template-columns: repeat(2, 1fr); }
            .header { flex-direction: column; text-align: center; }
        }
        @media (max-width: 480px) {
            .sidebar { width: 70px; padding-top: 15px; }
            .sidebar .logo { width: 50px; margin-bottom: 20px; }
            .sidebar .nav-item img { width: 32px; height: 32px; }
            .sidebar .nav-item span { font-size: 8px; }
            .main-content { margin-left: 70px; padding: 10px; }
            .cards-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

    <!-- ═══ SIDEBAR ═══ -->
    <div class="sidebar">
        <img src="logo1.png" alt="LOGO" class="logo">
        <div class="nav-icons">
            <div class="nav-item active">
                <a href="student_dashboard.php">
                    <img src="dashboard.png" alt="DASHBOARD">
                    <span>Dashboard</span>
                </a>
            </div>
            <div class="nav-item">
                <a href="student_attendance.php">
                    <img src="attendance.png" alt="ATTENDANCE">
                    <span>Attendance</span>
                </a>
            </div>
            <div class="nav-item">
                <a href="student_grades.php">
                    <img src="grades.png" alt="GRADES">
                    <span>Grades</span>
                </a>
            </div>
            <div class="nav-item">
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
            <h2><i class="fas fa-user-graduate"></i> My Academic Progress</h2>
            <div class="date-badge"><i class="far fa-calendar-alt"></i> <?php echo date('m/d/Y'); ?></div>
        </div>

        <!-- 4 Stats Cards -->
        <div class="cards-grid">
            <div class="card">
                <div class="label">Attendance</div>
                <div class="percentage <?php echo ($attendance_rate == 0) ? 'zero' : ''; ?>">
                    <?php echo $attendance_rate . '%'; ?>
                </div>
            </div>
            <div class="card">
                <div class="label">GPA</div>
                <div class="percentage gpa-color"><?php echo number_format($gpa, 2); ?></div>
            </div>
            <div class="card">
                <div class="label">Pass Rate</div>
                <div class="percentage pass-color"><?php echo $pass_rate . '%'; ?></div>
            </div>
            <div class="card">
                <div class="label">Status</div>
                <div class="percentage status-color"><?php echo $at_risk ? '⚠️' : '✅'; ?></div>
            </div>
        </div>

        <!-- This Week (chart – Mon to Sat only) -->
        <div class="week-section">
            <div class="week-header">
                <h3><i class="fas fa-calendar-week"></i> This Week</h3>
                <span class="week-range">
                    <?php 
                    // Ipakita ang Mon - Sat range
                    $mon = date('M d', strtotime('monday this week'));
                    $sat = date('d', strtotime('saturday this week'));
                    echo $mon . ' - ' . $sat;
                    ?>
                </span>
            </div>
            <div class="chart-container">
                <canvas id="attendanceChart"></canvas>
            </div>
        </div>

        <!-- Alerts -->
        <div class="alerts-section">
            <div class="alert-header">
                <h3><i class="fas fa-bell"></i> My Alerts &amp; Recommendations</h3>
            </div>
            <?php if ($at_risk): ?>
                <div class="alert-item">
                    <span class="alert-icon"><i class="fas fa-exclamation-circle" style="color:var(--danger);"></i></span>
                    <span class="alert-text"><strong>Low engagement detected</strong> – You have 2+ absences in a subject. Please contact your advisor.</span>
                    <button class="btn-sm" onclick="alert('Advisor contacted')">Contact Advisor</button>
                </div>
            <?php else: ?>
                <div class="alert-item success">
                    <span class="alert-icon"><i class="fas fa-check-circle" style="color:#2E7D32;"></i></span>
                    <span class="alert-text"><strong>Good job!</strong> Your attendance is on track. Keep it up!</span>
                </div>
            <?php endif; ?>
            <div class="alert-item warning">
                <span class="alert-icon"><i class="fas fa-lightbulb" style="color:var(--gold);"></i></span>
                <span class="alert-text"><strong>Recommendation:</strong> Attend tutoring sessions for Database Systems every Friday.</span>
            </div>
        </div>

        <!-- Course Progress Table -->
        <div class="table-wrap">
            <div class="table-header">
                <h3><i class="fas fa-book"></i> Course Progress</h3>
                <span class="sem-badge"><i class="far fa-calendar-alt"></i> Current Sem</span>
            </div>
            <table>
                <thead>
                    <tr><th>Subject</th><th>Midterm</th><th>Final</th><th>Average</th><th>Remarks</th></tr>
                </thead>
                <tbody>
                    <?php if ($courses && $courses->num_rows > 0): ?>
                        <?php while ($row = $courses->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['subject']); ?></td>
                                <td><?php echo $row['midterm'] ?: '-'; ?></td>
                                <td><?php echo $row['final'] ?: '-'; ?></td>
                                <td><?php echo number_format($row['average'], 2); ?></td>
                                <td>
                                    <span class="status-badge <?php echo strtolower($row['remarks']); ?>">
                                        <?php echo htmlspecialchars($row['remarks']); ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="5" style="text-align:center;color:var(--text-muted);">No grades available.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

    </div>

    <!-- ═══ CHART SCRIPT (Mon – Sat only) ═══ -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const ctx = document.getElementById('attendanceChart').getContext('2d');

            <?php
            // Kunin ang attendance data mula Lunes hanggang Sabado (6 na araw)
            $days = [];
            $current = strtotime('monday this week');
            for ($i = 0; $i < 6; $i++) { // 6 days: Mon - Sat
                $days[] = date('Y-m-d', $current);
                $current = strtotime('+1 day', $current);
            }
            $weekly_data = [];
            foreach ($days as $date) {
                $sql = "SELECT status, COUNT(*) as count FROM attendance 
                        WHERE student_id = '$student_id' AND attendance_date = '$date' GROUP BY status";
                $res = $conn->query($sql);
                $summary = ['Present' => 0, 'Late' => 0, 'Absent' => 0];
                if ($res && $res->num_rows > 0) {
                    while ($row = $res->fetch_assoc()) {
                        $summary[$row['status']] = (int)$row['count'];
                    }
                }
                $day_total = $summary['Present'] + $summary['Late'] + $summary['Absent'];
                $day_percent = $day_total > 0 ? round(($summary['Present'] + $summary['Late']) / $day_total * 100, 1) : 0;
                $weekly_data[] = $day_percent;
            }
            $chart_labels = [];
            foreach ($days as $date) {
                $chart_labels[] = date('D', strtotime($date)); // Mon, Tue, Wed, Thu, Fri, Sat
            }
            ?>

            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: <?php echo json_encode($chart_labels); ?>,
                    datasets: [{
                        label: 'Attendance %',
                        data: <?php echo json_encode($weekly_data); ?>,
                        backgroundColor: 'rgba(0, 77, 0, 0.1)',
                        borderColor: '#004d00',
                        borderWidth: 3,
                        tension: 0.3,
                        fill: true,
                        pointBackgroundColor: '#C9A84C',
                        pointBorderColor: '#004d00',
                        pointRadius: 5,
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return context.parsed.y + '%';
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            max: 100,
                            ticks: {
                                callback: function(value) { return value + '%'; }
                            }
                        }
                    }
                }
            });
        });
    </script>

</body>
</html>