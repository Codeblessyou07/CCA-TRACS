<?php
// prof_dashboard.php
session_start();
require_once 'connect.php';

if (!isset($_SESSION['id_number'])) {
    header("Location: login.php");
    exit();
}

// Kasalukuyang petsa at linggo
$today = date('m/d/Y');
$week_start = date('Y-m-d', strtotime('monday this week'));
$week_end = date('Y-m-d', strtotime('saturday this week'));
$week_start_display = date('M d', strtotime('monday this week'));
$week_end_display = date('d', strtotime('saturday this week'));

// ─── Kunin ang weekly attendance data ──────────────────────────────
$days = [];
$current = strtotime('monday this week');
for ($i = 0; $i < 6; $i++) {
    $days[] = date('Y-m-d', $current);
    $current = strtotime('+1 day', $current);
}

$weekly_data = [];
$total_present = 0;
$total_late = 0;
$total_absent = 0;
$total_all = 0;

foreach ($days as $date) {
    $sql = "SELECT status, COUNT(*) as count FROM attendance WHERE attendance_date = '$date' GROUP BY status";
    $res = $conn->query($sql);
    $summary = ['Present' => 0, 'Late' => 0, 'Absent' => 0];
    if ($res && $res->num_rows > 0) {
        while ($row = $res->fetch_assoc()) {
            $summary[$row['status']] = (int)$row['count'];
        }
    }
    $day_total = $summary['Present'] + $summary['Late'] + $summary['Absent'];
    $day_percent = $day_total > 0 ? round(($summary['Present'] + $summary['Late']) / $day_total * 100, 1) : 0;
    $weekly_data[] = [
        'date' => $date,
        'present' => $summary['Present'],
        'late' => $summary['Late'],
        'absent' => $summary['Absent'],
        'total' => $day_total,
        'percent' => $day_percent
    ];
    $total_present += $summary['Present'];
    $total_late += $summary['Late'];
    $total_absent += $summary['Absent'];
    $total_all += $day_total;
}

$overall_percent = $total_all > 0 ? round(($total_present + $total_late) / $total_all * 100, 1) : 0;

$chart_labels = [];
$chart_values = [];
foreach ($weekly_data as $day) {
    $chart_labels[] = date('D', strtotime($day['date']));
    $chart_values[] = $day['percent'];
}

// ─── Kunin ang Activity Percentage ──────────────────────────────────
// Average ng activity column mula sa grades table
$activity_query = $conn->query("SELECT AVG(activity) as avg_activity FROM grades WHERE activity IS NOT NULL AND activity != 'N/A'");
$activity_percent = 0;
if ($activity_query && $activity_query->num_rows > 0) {
    $row = $activity_query->fetch_assoc();
    $activity_percent = round($row['avg_activity'] ?? 0, 1);
}

// ─── Kunin ang Assignment Percentage ──────────────────────────────────
// Average ng assignment column mula sa grades table
$assignment_query = $conn->query("SELECT AVG(assignment) as avg_assignment FROM grades WHERE assignment IS NOT NULL AND assignment != 'N/A'");
$assignment_percent = 0;
if ($assignment_query && $assignment_query->num_rows > 0) {
    $row = $assignment_query->fetch_assoc();
    $assignment_percent = round($row['avg_assignment'] ?? 0, 1);
}

// ─── Kunin ang Participation Percentage ──────────────────────────────
// Average ng participation column mula sa grades table
$participation_query = $conn->query("SELECT AVG(participation) as avg_participation FROM grades WHERE participation IS NOT NULL AND participation != 'N/A'");
$participation_percent = 0;
if ($participation_query && $participation_query->num_rows > 0) {
    $row = $participation_query->fetch_assoc();
    $participation_percent = round($row['avg_participation'] ?? 0, 1);
}

// ─── Kunin ang Quiz Average ──────────────────────────────────────────
// Average ng quizzes column mula sa grades table
$quiz_query = $conn->query("SELECT AVG(quizzes) as avg_quizzes FROM grades WHERE quizzes IS NOT NULL AND quizzes != 'N/A'");
$quiz_percent = 0;
if ($quiz_query && $quiz_query->num_rows > 0) {
    $row = $quiz_query->fetch_assoc();
    $quiz_percent = round($row['avg_quizzes'] ?? 0, 1);
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Professor Dashboard - TOTALIS HUMANAE</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,400;14..32,500;14..32,600;14..32,700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
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

        /* ─── Cards Grid (5 cards) ─── */
        .cards-grid {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
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
        .card .percentage.zero {
            color: #bdc3c7;
        }
        .card .percentage.gold-color {
            color: var(--gold);
        }
        .card .percentage.green-color {
            color: #2E7D32;
        }
        .card .percentage.blue-color {
            color: #1565C0;
        }
        .card .percentage.purple-color {
            color: #7B1FA2;
        }

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
        .no-data {
            text-align: center;
            padding: 30px 0;
            color: var(--text-muted);
        }
        .no-data .icon {
            font-size: 2.5rem;
            margin-bottom: 10px;
        }
        .no-data p {
            font-size: 1rem;
        }
        .no-data .sub {
            font-size: 0.9rem;
            color: #bdc3c7;
        }

        @media (max-width: 1200px) {
            .cards-grid {
                grid-template-columns: repeat(3, 1fr);
            }
        }
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

    <!-- ===== SIDEBAR ===== -->
    <div class="sidebar">
        <img src="logo1.png" alt="LOGO" class="logo">
        <div class="nav-icons">
            <div class="nav-item active">
                <a href="prof_dashboard.php">
                    <img src="dashboard.png" alt="DASHBOARD">
                    <span>Dashboard</span>
                </a>
            </div>
            <div class="nav-item">
                <a href="attendance.php">
                    <img src="attendance.png" alt="ATTENDANCE">
                    <span>Attendance</span>
                </a>
            </div>
            <div class="nav-item">
                <a href="grades.php">
                    <img src="grades.png" alt="GRADES">
                    <span>Grades</span>
                </a>
            </div>
            <div class="nav-item">
                <a href="settings.php">
                    <img src="settings.png" alt="SETTINGS">
                    <span>Settings</span>
                </a>
            </div>
        </div>
    </div>

    <!-- ===== MAIN CONTENT ===== -->
    <div class="main-content">
        <div class="header">
            <h2><i class="fas fa-chart-pie"></i> Professor's Dashboard</h2>
            <div class="date-badge"><i class="far fa-calendar-alt"></i> <?php echo $today; ?></div>
        </div>

        <!-- ─── 5 Cards (may Activity) ─── -->
        <div class="cards-grid">
            <div class="card">
                <div class="label">Attendance</div>
                <div class="percentage <?php echo ($overall_percent == 0) ? 'zero' : ''; ?>">
                    <?php echo $overall_percent . '%'; ?>
                </div>
            </div>
            <div class="card">
                <div class="label">Assignments</div>
                <div class="percentage <?php echo ($assignment_percent == 0) ? 'zero' : ''; ?> gold-color">
                    <?php echo $assignment_percent . '%'; ?>
                </div>
            </div>
            <div class="card">
                <div class="label">Participation</div>
                <div class="percentage <?php echo ($participation_percent == 0) ? 'zero' : ''; ?> green-color">
                    <?php echo $participation_percent . '%'; ?>
                </div>
            </div>
            <div class="card">
                <div class="label">Quiz Average</div>
                <div class="percentage <?php echo ($quiz_percent == 0) ? 'zero' : ''; ?> blue-color">
                    <?php echo $quiz_percent . '%'; ?>
                </div>
            </div>
            <div class="card">
                <div class="label">Activity</div>
                <div class="percentage <?php echo ($activity_percent == 0) ? 'zero' : ''; ?> purple-color">
                    <?php echo $activity_percent . '%'; ?>
                </div>
            </div>
        </div>

        <!-- ─── This Week Section ─── -->
        <div class="week-section">
            <div class="week-header">
                <h3><i class="fas fa-calendar-week"></i> This Week</h3>
                <span class="week-range"><?php echo $week_start_display . ' - ' . $week_end_display; ?></span>
            </div>

            <?php if (array_sum($chart_values) > 0): ?>
                <div class="chart-container">
                    <canvas id="attendanceChart"></canvas>
                </div>
                <script>
                    document.addEventListener('DOMContentLoaded', function() {
                        const ctx = document.getElementById('attendanceChart').getContext('2d');
                        new Chart(ctx, {
                            type: 'line',
                            data: {
                                labels: <?php echo json_encode($chart_labels); ?>,
                                datasets: [{
                                    label: 'Attendance %',
                                    data: <?php echo json_encode($chart_values); ?>,
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
            <?php else: ?>
                <div class="no-data">
                    <div class="icon">📊</div>
                    <p>No data available yet</p>
                    <div class="sub">Enter grades or attendance to see trends</div>
                </div>
            <?php endif; ?>
        </div>
    </div>

</body>
</html>