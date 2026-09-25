<?php
session_start();
require_once 'connect.php';
/** @var mysqli $conn */
date_default_timezone_set('Asia/Manila');

$is_data_request = isset($_GET['data']);

if (!isset($_SESSION['id_number']) || ($_SESSION['role'] ?? '') !== 'dean') {
    if ($is_data_request) {
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'auth']);
        exit();
    }
    header("Location: login.php");
    exit();
}

// =====================================================
// All dashboard numbers come from here (used for first load AND live refresh)
// =====================================================
function getDashboardData(mysqli $conn): array
{
    $one = function (string $sql) use ($conn): int {
        $r = $conn->query($sql);
        return $r ? (int)($r->fetch_assoc()['cnt'] ?? 0) : 0;
    };

    // Students with 2+ absences in any one subject
    $atRiskSub = "SELECT student_id FROM attendance WHERE status = 'Absent' GROUP BY student_id, subject_code HAVING COUNT(*) >= 2";

    $total   = $one("SELECT COUNT(*) cnt FROM students");
    $atRisk  = $one("SELECT COUNT(DISTINCT student_id) cnt FROM ($atRiskSub) t");
    $retained = $one("SELECT COUNT(*) cnt FROM students s
        WHERE NOT EXISTS (SELECT 1 FROM ($atRiskSub) t WHERE t.student_id = s.student_id)
          AND NOT EXISTS (SELECT 1 FROM grades g WHERE g.student_id = s.student_id AND g.remarks = 'Failed')");

    $retention = $total > 0 ? round(($retained / $total) * 100, 1) : 0;
    $attrition = $total > 0 ? round(100 - $retention, 1) : 0;

    // Trend: last 5 months from real attendance records, latest point = live value
    $months = []; $ret = []; $att = [];
    for ($i = 5; $i >= 0; $i--) {
        $ts = strtotime("first day of -$i month");
        $months[] = date('M', $ts);
        if ($i === 0) {
            $ret[] = $retention;
            $att[] = $attrition;
            continue;
        }
        $ym = date('Y-m', $ts);
        $n = $one("SELECT COUNT(DISTINCT student_id) cnt FROM (
                SELECT student_id FROM attendance
                WHERE status = 'Absent' AND DATE_FORMAT(attendance_date, '%Y-%m') = '$ym'
                GROUP BY student_id, subject_code HAVING COUNT(*) >= 2) t");
        $r = $total > 0 ? round((1 - $n / $total) * 100, 1) : 0;
        $ret[] = $r;
        $att[] = $total > 0 ? round(100 - $r, 1) : 0;
    }

    // At-risk alerts
    $alerts = [];
    $q = $conn->query("SELECT a.student_id, s.name AS student_name, a.subject_code,
            COUNT(*) AS absent_count,
            GROUP_CONCAT(DISTINCT DATE(a.attendance_date) ORDER BY a.attendance_date SEPARATOR ', ') AS absent_dates
        FROM attendance a
        JOIN students s ON a.student_id = s.student_id
        WHERE a.status = 'Absent'
        GROUP BY a.student_id, s.name, a.subject_code
        HAVING COUNT(*) >= 2
        ORDER BY absent_count DESC, s.name");
    while ($q && $row = $q->fetch_assoc()) {
        $alerts[] = $row;
    }

    // Student list
    $students = [];
    $q = $conn->query("SELECT s.*,
            (SELECT AVG(average) FROM grades WHERE student_id = s.student_id) AS gpa,
            (SELECT COUNT(*) FROM attendance WHERE student_id = s.student_id AND status IN ('Present','Late')) AS attended,
            (SELECT COUNT(*) FROM attendance WHERE student_id = s.student_id) AS total_att
        FROM students s ORDER BY s.name LIMIT 50");
    while ($q && $row = $q->fetch_assoc()) {
        $rate = $row['total_att'] > 0 ? round(($row['attended'] / $row['total_att']) * 100) : 0;
        $students[] = [
            'id'     => $row['student_id'],
            'name'   => $row['name'],
            'year'   => $row['year_level'] ?? 'N/A',
            'gpa'    => number_format((float)($row['gpa'] ?? 0), 2),
            'rate'   => $rate,
            'status' => $rate < 70 ? 'At-Risk' : 'Retained',
        ];
    }

    return [
        'total' => $total, 'retained' => $retained, 'atRisk' => $atRisk,
        'retention' => $retention, 'attrition' => $attrition,
        'months' => $months, 'retTrend' => $ret, 'attTrend' => $att,
        'alerts' => $alerts, 'students' => $students,
        'updated' => date('h:i:s A'),
    ];
}

$data = getDashboardData($conn);

if ($is_data_request) {
    header('Content-Type: application/json');
    header('Cache-Control: no-store');
    echo json_encode($data);
    exit();
}
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Dean Dashboard – TOTALIS HUMANAE</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<style>
:root{--primary:#004d00;--gold:#C9A84C;--gold-light:#E8D5A3;--danger:#B22234;--bg:#F8F4F0;--card:#fff;--text:#1A1A1A;--muted:#6B6B6B;--border:#E8E0D8;--shadow:0 8px 32px rgba(26,26,26,.08);--r:16px;--font:'Inter','Segoe UI',Arial,sans-serif}
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:var(--font);display:flex;background:var(--bg);min-height:100vh}
body::before{content:"";position:fixed;inset:0;background:url('cca.webp') center/cover;opacity:.12;z-index:-1}

.sidebar{width:120px;background:linear-gradient(135deg,#004d00,#003300);height:100vh;position:fixed;display:flex;flex-direction:column;align-items:center;padding-top:30px;box-shadow:5px 0 25px rgba(0,77,0,.3);z-index:100}
.sidebar .logo{width:70px;margin-bottom:40px;padding:10px;background:rgba(255,255,255,.12);border-radius:50%;border:2px solid rgba(255,255,255,.1)}
.nav-icons{display:flex;flex-direction:column;align-items:center;gap:25px;flex:1;width:100%}
.nav-item{position:relative;width:100%;padding:8px 0}
.nav-item a{text-decoration:none;display:flex;flex-direction:column;align-items:center;gap:5px;color:rgba(255,255,255,.7)}
.nav-item img{width:42px;height:42px;object-fit:contain;filter:brightness(0) invert(1);opacity:.7;transition:.2s}
.nav-item span{font-size:10px;font-weight:600;letter-spacing:.5px;text-transform:uppercase}
.nav-item:hover a,.nav-item.active a{color:#fff}
.nav-item:hover img,.nav-item.active img{opacity:1;transform:translateY(-3px)}
.nav-item.active span{color:var(--gold-light);font-weight:700}
.nav-item.active::before{content:'';position:absolute;left:-20px;top:10px;width:4px;height:30px;background:var(--gold);border-radius:0 4px 4px 0}

.main{margin-left:120px;padding:25px 30px;width:calc(100% - 120px)}
.header{background:linear-gradient(135deg,#004d00,#003300);padding:1.4rem 2rem;border-radius:var(--r) var(--r) 0 0;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:15px}
.header h2{color:#fff;font-size:1.8rem;display:flex;align-items:center;gap:12px}
.header h2 i{color:var(--gold)}
.header-right{display:flex;align-items:center;gap:12px;flex-wrap:wrap}
.pill{background:rgba(255,255,255,.15);color:#fff;padding:8px 20px;border-radius:30px;font-weight:500;border:1px solid rgba(255,255,255,.1);font-size:.9rem}
.live{display:flex;align-items:center;gap:8px}
.dot{width:10px;height:10px;border-radius:50%;background:#4CAF50;animation:pulse 1.6s infinite}
.live.offline .dot{background:#ff9800;animation:none}
@keyframes pulse{0%{box-shadow:0 0 0 0 rgba(76,175,80,.7)}70%{box-shadow:0 0 0 9px rgba(76,175,80,0)}100%{box-shadow:0 0 0 0 rgba(76,175,80,0)}}

.stats{display:grid;grid-template-columns:repeat(4,1fr);gap:20px;margin:25px 0}
.stat{background:var(--card);border-radius:var(--r);padding:25px 20px;box-shadow:var(--shadow);border:1px solid var(--border);position:relative;overflow:hidden;transition:.2s}
.stat:hover{transform:translateY(-5px)}
.stat .label{font-size:.9rem;text-transform:uppercase;letter-spacing:1px;margin-bottom:8px}
.stat .value{font-size:2.5rem;font-weight:700}
.stat .icon{position:absolute;right:20px;top:20px;font-size:28px;opacity:.15}
.stat.green .label,.stat.green .value{color:#2E7D32}
.stat.danger .label,.stat.danger .value{color:var(--danger)}
.arrow{font-size:14px}

.charts{display:grid;grid-template-columns:2fr 1fr;gap:24px;margin-bottom:30px}
.card{background:var(--card);border-radius:var(--r);padding:20px;box-shadow:var(--shadow);border:1px solid var(--border);margin-bottom:30px}
.charts .card{margin-bottom:0}
.card-title{font-size:1.1rem;font-weight:600;color:var(--primary);margin-bottom:15px;display:flex;justify-content:space-between;align-items:center;gap:10px}
.tag{font-size:12px;background:var(--bg);padding:4px 12px;border-radius:20px;color:var(--text);font-weight:500}
canvas{max-height:220px}

.badge{background:var(--danger);color:#fff;padding:2px 12px;border-radius:20px;font-size:.85rem;margin-left:10px}
.alerts{max-height:320px;overflow-y:auto}
.alert-item{display:flex;align-items:center;gap:14px;padding:12px 16px;border-radius:10px;background:#FFF5F5;border-left:4px solid var(--danger);margin-bottom:10px;font-size:14px}
.alert-item strong{color:var(--danger)}
.alert-item .msg{flex:1}
.empty{color:var(--muted)}

.table-wrap{overflow-x:auto}
#searchInput{padding:6px 14px;border-radius:30px;border:1px solid var(--border);font-size:.9rem;outline:none}
#searchInput:focus{border-color:var(--primary);box-shadow:0 0 0 3px rgba(0,77,0,.1)}
table{width:100%;border-collapse:collapse;font-size:14px}
th{text-align:left;padding:12px 12px 12px 0;font-size:12px;text-transform:uppercase;letter-spacing:.5px;color:var(--muted);border-bottom:2px solid var(--border)}
td{padding:12px 12px 12px 0;border-bottom:1px solid var(--border)}
tbody tr:hover td{background:#FAFAFA}
.status{display:inline-block;padding:4px 14px;border-radius:50px;font-size:12px;font-weight:600}
.status.at-risk{background:var(--danger);color:#fff}
.status.retained{background:#E3F2FD;color:#0D47A1}
.btn-sm{padding:4px 14px;border-radius:20px;background:transparent;border:1.5px solid var(--primary);color:var(--primary);font-size:12px;font-weight:600;cursor:pointer}
.btn-sm:hover{background:var(--primary);color:#fff}

@media(max-width:768px){.sidebar{width:90px}.main{margin-left:90px;width:calc(100% - 90px);padding:15px}.stats{grid-template-columns:repeat(2,1fr)}.charts{grid-template-columns:1fr}.header{flex-direction:column;text-align:center}}
@media(max-width:480px){.sidebar{width:70px}.sidebar .logo{width:50px}.nav-item img{width:32px;height:32px}.nav-item span{font-size:8px}.main{margin-left:70px;width:calc(100% - 70px);padding:10px}.stats{grid-template-columns:1fr}}
</style>
</head>
<body>

<div class="sidebar">
    <img src="logo1.png" alt="LOGO" class="logo">
    <div class="nav-icons">
        <div class="nav-item active"><a href="dean_dashboard.php"><img src="dashboard.png" alt="DASHBOARD"><span>Dashboard</span></a></div>
        <div class="nav-item"><a href="dean_attendance.php"><img src="attendance.png" alt="ATTENDANCE"><span>Attendance</span></a></div>
        <div class="nav-item"><a href="dean_grades.php"><img src="grades.png" alt="GRADES"><span>Grades</span></a></div>
        <div class="nav-item"><a href="dean_settings.php"><img src="settings.png" alt="SETTINGS"><span>Settings</span></a></div>
    </div>
</div>

<div class="main">
    <div class="header">
        <h2><i class="fas fa-university"></i> Dean's Dashboard</h2>
        <div class="header-right">
            <div class="pill live" id="liveBadge"><span class="dot"></span><span id="liveText">LIVE · updated --</span></div>
            <div class="pill"><i class="far fa-calendar-alt"></i> <span id="clock"></span></div>
        </div>
    </div>

    <div class="stats">
        <div class="stat green"><span class="icon"><i class="fas fa-users"></i></span><div class="label">Total Students</div><div class="value" id="vTotal">–</div></div>
        <div class="stat green"><span class="icon"><i class="fas fa-check-circle"></i></span><div class="label">Retention Rate</div><div class="value"><span id="vRet">–</span>% <span class="arrow" id="aRet"></span></div></div>
        <div class="stat danger"><span class="icon"><i class="fas fa-user-slash"></i></span><div class="label">Attrition Rate</div><div class="value"><span id="vAttr">–</span>% <span class="arrow" id="aAttr"></span></div></div>
        <div class="stat danger"><span class="icon"><i class="fas fa-exclamation-triangle"></i></span><div class="label">At‑Risk Students</div><div class="value" id="vRisk">–</div></div>
    </div>

    <div class="charts">
        <div class="card">
            <div class="card-title">Retention &amp; Attrition Trends <span class="tag">Last 6 months</span></div>
            <canvas id="trendChart"></canvas>
        </div>
        <div class="card">
            <div class="card-title">Student Status <span class="tag">Live</span></div>
            <canvas id="statusChart"></canvas>
        </div>
    </div>

    <div class="card">
        <div class="card-title"><span><i class="fas fa-bell" style="color:var(--danger)"></i> At‑Risk Alerts <span class="badge" id="alertCount">0</span></span></div>
        <div class="alerts" id="alertList"></div>
    </div>

    <div class="card table-wrap">
        <div class="card-title">Student Retention List <input type="text" id="searchInput" placeholder="Search..."></div>
        <table>
            <thead><tr><th>ID</th><th>Name</th><th>Year</th><th>GPA</th><th>Attendance</th><th>Status</th><th>Action</th></tr></thead>
            <tbody id="studentTableBody"></tbody>
        </table>
    </div>
</div>

<script>
const REFRESH_MS = 10000;                       // how often the dashboard updates (10 seconds)
const initial = <?php echo json_encode($data, JSON_HEX_TAG | JSON_HEX_AMP); ?>;

const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
const $ = id => document.getElementById(id);

// ----- Charts -----
const trendChart = new Chart($('trendChart'), {
    type: 'line',
    data: { labels: [], datasets: [
        { label: 'Retention %', data: [], borderColor: '#2E7D32', backgroundColor: 'rgba(46,125,50,.1)', fill: true, tension: .3, pointRadius: 4 },
        { label: 'Attrition %', data: [], borderColor: '#B22234', backgroundColor: 'rgba(178,34,52,.1)', fill: true, tension: .3, borderDash: [5,5], pointRadius: 4 }
    ]},
    options: { responsive: true, plugins: { legend: { position: 'top', labels: { boxWidth: 14, font: { size: 11 } } } },
               scales: { y: { min: 0, max: 100, grid: { color: 'rgba(0,0,0,.05)' } }, x: { grid: { display: false } } } }
});
const statusChart = new Chart($('statusChart'), {
    type: 'doughnut',
    data: { labels: ['Retained', 'At-Risk'], datasets: [{ data: [0, 0], backgroundColor: ['#2E7D32', '#B22234'], borderColor: '#fff', borderWidth: 3 }] },
    options: { responsive: true, cutout: '60%', plugins: { legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 11 } } } } }
});

// ----- Render everything from one data object -----
let prev = null;
function arrow(now, before, goodWhenUp) {
    if (before === null || now === before) return '';
    const up = now > before, good = up === goodWhenUp;
    return `<span style="color:${good ? '#2E7D32' : '#B22234'}">${up ? '▲' : '▼'}</span>`;
}

function render(d) {
    $('vTotal').textContent = d.total;
    $('vRet').textContent   = d.retention;
    $('vAttr').textContent  = d.attrition;
    $('vRisk').textContent  = d.atRisk;
    $('aRet').innerHTML  = arrow(d.retention, prev ? prev.retention : null, true);
    $('aAttr').innerHTML = arrow(d.attrition, prev ? prev.attrition : null, false);

    trendChart.data.labels = d.months;
    trendChart.data.datasets[0].data = d.retTrend;
    trendChart.data.datasets[1].data = d.attTrend;
    trendChart.update('none');
    statusChart.data.datasets[0].data = [d.retained, d.atRisk];
    statusChart.update('none');

    $('alertCount').textContent = d.alerts.length;
    $('alertList').innerHTML = d.alerts.length
        ? d.alerts.map(a => `<div class="alert-item"><i class="fas fa-exclamation-triangle" style="color:var(--danger)"></i>
            <div class="msg"><strong>${esc(a.student_name)}</strong> (${esc(a.student_id)}) – ${esc(a.absent_count)} absences in ${esc(a.subject_code)}. Dates: ${esc(a.absent_dates)}</div></div>`).join('')
        : '<p class="empty">No at‑risk students at this time.</p>';

    $('studentTableBody').innerHTML = d.students.map(s => `<tr>
        <td>${esc(s.id)}</td><td>${esc(s.name)}</td><td>${esc(s.year)}</td><td>${esc(s.gpa)}</td><td>${esc(s.rate)}%</td>
        <td><span class="status ${s.status === 'At-Risk' ? 'at-risk' : 'retained'}">${esc(s.status)}</span></td>
        <td><button class="btn-sm" onclick="alert('View details')">View</button></td></tr>`).join('');
    applySearch();

    $('liveBadge').classList.remove('offline');
    $('liveText').textContent = 'LIVE · updated ' + d.updated;
    prev = d;
}

// ----- Search (keeps working after each refresh) -----
function applySearch() {
    const q = $('searchInput').value.toLowerCase();
    document.querySelectorAll('#studentTableBody tr').forEach(r => {
        r.style.display = r.textContent.toLowerCase().includes(q) ? '' : 'none';
    });
}
$('searchInput').addEventListener('input', applySearch);

// ----- Live refresh -----
async function refresh() {
    if (document.hidden) return;                // don't waste queries when the tab isn't visible
    try {
        const res = await fetch('dean_dashboard.php?data=1', { cache: 'no-store' });
        if (res.status === 401) { location.href = 'login.php'; return; }
        render(await res.json());
    } catch (e) {
        $('liveBadge').classList.add('offline');
        $('liveText').textContent = 'Reconnecting…';
    }
}
setInterval(refresh, REFRESH_MS);
document.addEventListener('visibilitychange', () => { if (!document.hidden) refresh(); });

// ----- Live clock -----
function tick() {
    $('clock').textContent = new Date().toLocaleString('en-PH', { dateStyle: 'medium', timeStyle: 'medium' });
}
setInterval(tick, 1000);
tick();

render(initial);
</script>
</body>
</html>