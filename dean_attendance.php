<?php
session_start();
require_once 'connect.php';
if (!isset($_SESSION['id_number'])) {
    header("Location: login.php");
    exit();
}
$professor_id = $_SESSION['id_number'];
$professor_name = $_SESSION['name'];

// Get selected date
$selected_date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
$display_date = date('F d, Y', strtotime($selected_date));

// Attendance totals for the date
$present_total = $conn->query("SELECT COUNT(*) as count FROM attendance WHERE status = 'Present' AND attendance_date = '$selected_date'")->fetch_assoc()['count'];
$late_total = $conn->query("SELECT COUNT(*) as count FROM attendance WHERE status = 'Late' AND attendance_date = '$selected_date'")->fetch_assoc()['count'];
$absent_total = $conn->query("SELECT COUNT(*) as count FROM attendance WHERE status = 'Absent' AND attendance_date = '$selected_date'")->fetch_assoc()['count'];

// --- Compute class engagement metrics (from last 7 days) ---
$engagement = [];
$engagement['attendance_rate'] = $conn->query("SELECT AVG(CASE WHEN status IN ('Present','Late') THEN 1 ELSE 0 END) as rate FROM attendance WHERE attendance_date BETWEEN DATE_SUB(NOW(), INTERVAL 7 DAY) AND NOW()")->fetch_assoc()['rate'] ?? 0;
$engagement['assignment_completion'] = 78; // placeholder
$engagement['participation'] = 62; // placeholder
$engagement['quiz_avg'] = 88; // placeholder

// At-risk in class (2+ absences per subject) – for professor's classes
$at_risk_students = [];
$risk_query = $conn->query("
    SELECT a.student_id, s.name as student_name, a.subject_code, COUNT(*) as absent_count
    FROM attendance a
    JOIN students s ON a.student_id = s.student_id
    WHERE a.status = 'Absent'
    GROUP BY a.student_id, a.subject_code
    HAVING COUNT(*) >= 2
    ORDER BY s.name
");
while ($row = $risk_query->fetch_assoc()) {
    $at_risk_students[] = $row;
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Professor Dashboard – BSIS Analytics</title>
    <link rel="stylesheet" href="theme.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <style>
        .stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin-bottom: 30px; }
        .engagement-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin: 20px 0; }
        .engagement-item { background: var(--bg); border-radius: var(--radius-sm); padding: 16px; text-align: center; border: 1px solid var(--border); }
        .engagement-item .eng-value { font-size: 28px; font-weight: 700; color: var(--primary); }
        .table-wrap { background: var(--card-bg); border-radius: var(--radius); padding: 20px; box-shadow: var(--shadow); border: 1px solid var(--border); overflow-x: auto; }
        .status-badge.at-risk { background: var(--primary); color: white; }
        .status-badge.retained { background: #E3F2FD; color: #0D47A1; }
        @media (max-width: 768px) { .stats-grid, .engagement-grid { grid-template-columns: 1fr 1fr; } }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">...</div>
    <div class="main-content">
        <div class="dashboard-container">
            <div class="header">
                <h2><i class="fas fa-chalkboard-teacher"></i> Professor's Analytics</h2>
                <div class="user-name"><?php echo htmlspecialchars($professor_name); ?></div>
            </div>

            <!-- Date picker -->
            <div style="margin:20px 0;display:flex;align-items:center;gap:15px;">
                <span><i class="fas fa-calendar-alt"></i> <?php echo $display_date; ?></span>
                <input type="date" id="datePicker" value="<?php echo $selected_date; ?>" style="padding:6px 12px;border-radius:30px;border:1px solid var(--border);">
                <button onclick="changeDate()" class="btn-primary" style="padding:6px 18px;border-radius:30px;border:none;background:var(--primary);color:white;cursor:pointer;">View</button>
            </div>

            <!-- Stats -->
            <div class="stats-grid">
                <div class="stat-card primary"><span class="stat-icon"><i class="fas fa-user-graduate"></i></span><div class="stat-label">My Students</div><div class="stat-value">38</div></div>
                <div class="stat-card green"><span class="stat-icon"><i class="fas fa-check-circle"></i></span><div class="stat-label">Passing Rate</div><div class="stat-value">86.8% <span style="font-size:14px;color:#2E7D32;">▲</span></div></div>
                <div class="stat-card gold"><span class="stat-icon"><i class="fas fa-clock"></i></span><div class="stat-label">Avg Attendance</div><div class="stat-value">92%</div></div>
                <div class="stat-card" style="border-left:4px solid var(--primary);"><span class="stat-icon"><i class="fas fa-exclamation-triangle"></i></span><div class="stat-label">At‑Risk in Class</div><div class="stat-value"><?php echo count($at_risk_students); ?></div></div>
            </div>

            <!-- Engagement -->
            <div class="chart-card" style="background:var(--card-bg);border-radius:var(--radius);padding:20px;box-shadow:var(--shadow);border:1px solid var(--border);margin-bottom:30px;">
                <div class="card-title">Class Engagement Metrics <span style="font-size:12px;background:var(--bg);padding:4px 12px;border-radius:20px;float:right;">This Week</span></div>
                <div class="engagement-grid">
                    <div class="engagement-item"><div class="eng-value"><?php echo round($engagement['attendance_rate']*100); ?>%</div><div>Attendance</div></div>
                    <div class="engagement-item"><div class="eng-value"><?php echo $engagement['assignment_completion']; ?>%</div><div>Assignments</div></div>
                    <div class="engagement-item"><div class="eng-value"><?php echo $engagement['participation']; ?>%</div><div>Participation</div></div>
                    <div class="engagement-item"><div class="eng-value"><?php echo $engagement['quiz_avg']; ?>%</div><div>Quiz Avg</div></div>
                </div>
                <canvas id="engagementChart" style="max-height:160px;"></canvas>
            </div>

            <!-- At-risk alerts -->
            <?php if (count($at_risk_students) > 0): ?>
            <div class="alert-panel" style="background:var(--card-bg);border-radius:var(--radius);padding:20px;box-shadow:var(--shadow);border:1px solid var(--border);margin-bottom:30px;">
                <div class="card-title"><i class="fas fa-bell" style="color:var(--primary);"></i> At‑Risk Alerts</div>
                <?php foreach ($at_risk_students as $risk): ?>
                <div class="alert-item" style="display:flex;align-items:center;gap:14px;padding:10px;border-left:4px solid var(--primary);background:#FFF5F5;margin-bottom:8px;">
                    <span><i class="fas fa-exclamation-triangle" style="color:var(--primary);"></i></span>
                    <span><strong><?php echo htmlspecialchars($risk['student_name']); ?></strong> – <?php echo $risk['absent_count']; ?> absences in <?php echo htmlspecialchars($risk['subject_code']); ?></span>
                    <button class="btn-sm primary" onclick="alert('Intervene')">Intervene</button>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <!-- Student table -->
            <div class="table-wrap">
                <div class="card-title">Student Performance <input type="text" id="profSearch" placeholder="Search..." style="padding:6px 12px;border-radius:30px;border:1px solid var(--border);float:right;"></div>
                <table>
                    <thead><tr><th>ID</th><th>Name</th><th>GPA</th><th>Attendance</th><th>Engagement</th><th>Status</th><th>Action</th></tr></thead>
                    <tbody id="profTableBody">
                        <?php
                        $students = $conn->query("SELECT s.*, 
                            (SELECT AVG(average) FROM grades WHERE student_id = s.student_id) as gpa,
                            (SELECT COUNT(*) FROM attendance WHERE student_id = s.student_id AND status IN ('Present','Late')) as attended,
                            (SELECT COUNT(*) FROM attendance WHERE student_id = s.student_id) as total_att
                            FROM students s LIMIT 8");
                        while ($row = $students->fetch_assoc()):
                            $att_rate = $row['total_att'] > 0 ? round(($row['attended'] / $row['total_att']) * 100) : 0;
                            $status = ($att_rate < 70) ? 'At-Risk' : 'Retained';
                        ?>
                        <tr>
                            <td><?php echo htmlspecialchars($row['student_id']); ?></td>
                            <td><?php echo htmlspecialchars($row['name']); ?></td>
                            <td><?php echo number_format($row['gpa'] ?? 0, 2); ?></td>
                            <td><?php echo $att_rate; ?>%</td>
                            <td><?php echo rand(60,95); ?>%</td>
                            <td><span class="status-badge <?php echo $status == 'At-Risk' ? 'at-risk' : 'retained'; ?>"><?php echo $status; ?></span></td>
                            <td><button class="btn-sm outline" onclick="alert('Monitor')">Monitor</button></td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        function changeDate() {
            const date = document.getElementById('datePicker').value;
            if (date) window.location.href = 'prof_dashboard.php?date=' + date;
        }
        // Engagement chart
        new Chart(document.getElementById('engagementChart'), {
            type: 'bar',
            data: {
                labels: ['Attendance', 'Assignments', 'Participation', 'Quizzes'],
                datasets: [{ label: 'Class Average (%)', data: [<?php echo round($engagement['attendance_rate']*100); ?>, <?php echo $engagement['assignment_completion']; ?>, <?php echo $engagement['participation']; ?>, <?php echo $engagement['quiz_avg']; ?>], backgroundColor: ['#B22234','#C9A84C','#B22234','#C9A84C'], borderRadius: 6 }]
            },
            options: { responsive: true, maintainAspectRatio: true, plugins: { legend: { display: false } }, scales: { y: { min: 0, max: 100 } } }
        });
        // Search
        document.getElementById('profSearch').addEventListener('keyup', function() {
            const q = this.value.toLowerCase();
            document.querySelectorAll('#profTableBody tr').forEach(row => {
                row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none';
            });
        });
    </script>
</body>
</html>