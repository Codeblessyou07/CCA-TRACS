<?php
// =====================================================
// attendance.php
// TOTALIS HUMANAE - STUDENT ATTENDANCE SUMMARY + SUBJECT MANAGEMENT
// =====================================================

session_start();
require_once 'connect.php';
date_default_timezone_set('Asia/Manila');

if (!isset($_SESSION['id_number'])) {
    header("Location: login.php");
    exit();
}

$current_date = date('Y-m-d');

// ===== SELECTED DATE =====
$selected_date = isset($_GET['date']) && !empty($_GET['date']) ? $_GET['date'] : $current_date;
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $selected_date)) {
    $selected_date = $current_date;
}

// ===== ENROLLED COUNT HELPER =====
function getEnrolledCount($conn, $subject_code, $section) {
    $stmt = $conn->prepare("SELECT COUNT(*) AS total FROM subject_students WHERE subject_code = ? AND section = ?");
    if (!$stmt) return 0;
    $stmt->bind_param("ss", $subject_code, $section);
    $stmt->execute();
    $result = $stmt->get_result();
    $total = 0;
    if ($result) {
        $row = $result->fetch_assoc();
        if ($row && isset($row['total'])) $total = (int)$row['total'];
    }
    $stmt->close();
    return $total;
}

// ===== ACTIVE SUBJECTS =====
$subjects = [];
$subjects_result = $conn->query("SELECT subject_code, subject_name, section FROM subjects WHERE is_archived = 0 ORDER BY subject_name ASC");
if ($subjects_result && $subjects_result->num_rows > 0) {
    while ($row = $subjects_result->fetch_assoc()) {
        $row['total_enrolled'] = getEnrolledCount($conn, $row['subject_code'], $row['section']);
        $subjects[] = $row;
    }
}

// ===== ARCHIVED SUBJECTS =====
$archived_subjects = [];
$archived_result = $conn->query("SELECT subject_code, subject_name, section FROM subjects WHERE is_archived = 1 ORDER BY subject_name ASC");
if ($archived_result && $archived_result->num_rows > 0) {
    while ($row = $archived_result->fetch_assoc()) {
        $row['total_enrolled'] = getEnrolledCount($conn, $row['subject_code'], $row['section']);
        $archived_subjects[] = $row;
    }
}

// ===== DAILY SUMMARY =====
$summary = ['Present' => 0, 'Late' => 0, 'Absent' => 0];
$summary_stmt = $conn->prepare("SELECT status, COUNT(*) AS total FROM attendance WHERE attendance_date = ? GROUP BY status");
$summary_stmt->bind_param("s", $selected_date);
$summary_stmt->execute();
$summary_result = $summary_stmt->get_result();
if ($summary_result) {
    while ($row = $summary_result->fetch_assoc()) {
        if (isset($summary[$row['status']])) $summary[$row['status']] = (int)$row['total'];
    }
}
$summary_stmt->close();

$total_today = array_sum($summary);
$attendance_percentage = $total_today > 0 ? round((($summary['Present'] + $summary['Late']) / $total_today) * 100, 2) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Attendance - TOTALIS HUMANAE</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,400;14..32,500;14..32,600;14..32,700&display=swap" rel="stylesheet">
<style>
:root {
    --primary: #004d00; --primary-dark: #003300; --primary-light: #4c7a4c;
    --primary-gradient: linear-gradient(135deg, #004d00 0%, #003300 100%);
    --gold: #C9A84C; --gold-light: #E8D5A3; --gold-dark: #B8943A;
    --danger: #B22234; --danger-dark: #8B1A2B;
    --bg: #F8F4F0; --card-bg: #FFFFFF; --text: #1A1A1A; --text-muted: #6B6B6B;
    --border: #E8E0D8; --shadow: 0 8px 32px rgba(26,26,26,0.08);
    --radius: 16px; --radius-sm: 10px;
    --font: 'Inter', 'Segoe UI', Arial, sans-serif;
}
* { margin: 0; padding: 0; box-sizing: border-box; }
body { font-family: var(--font); display: flex; background: var(--bg); min-height: 100vh; }
body::before { content: ""; position: fixed; inset: 0; background-image: url('cca.webp'); background-size: cover; background-position: center; opacity: 0.12; z-index: -1; }

.sidebar { width: 120px; background: var(--primary-gradient); height: 100vh; position: fixed; top: 0; left: 0; display: flex; flex-direction: column; align-items: center; padding-top: 30px; box-shadow: 5px 0 25px rgba(0,77,0,0.3); z-index: 100; }
.sidebar .logo { width: 70px; margin-bottom: 40px; padding: 10px; background: rgba(255,255,255,0.12); border-radius: 50%; border: 2px solid rgba(255,255,255,0.1); }
.nav-icons { display: flex; flex-direction: column; align-items: center; gap: 25px; flex: 1; width: 100%; }
.nav-item { display: flex; flex-direction: column; align-items: center; gap: 5px; width: 100%; padding: 8px 0; position: relative; }
.nav-item a { text-decoration: none; display: flex; flex-direction: column; align-items: center; gap: 5px; color: rgba(255,255,255,0.7); }
.nav-item img { width: 42px; height: 42px; object-fit: contain; filter: brightness(0) invert(1); opacity: 0.7; transition: 0.2s; }
.nav-item span { color: rgba(255,255,255,0.7); font-size: 10px; font-weight: 600; letter-spacing: 0.5px; text-transform: uppercase; }
.nav-item:hover img { transform: translateY(-3px); opacity: 1; }
.nav-item.active img { opacity: 1; transform: scale(1.1); }
.nav-item.active span { color: var(--gold-light); font-weight: 700; }
.nav-item.active::before { content: ''; position: absolute; left: -20px; top: 10px; width: 4px; height: 30px; background: var(--gold); border-radius: 0 4px 4px 0; }

.main-content { margin-left: 120px; padding: 25px 30px; width: calc(100% - 120px); min-height: 100vh; }

.header { background: var(--primary-gradient); padding: 1.4rem 2rem; border-radius: var(--radius); margin-bottom: 25px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; }
.header h2 { color: white; font-size: 1.8rem; display: flex; align-items: center; gap: 12px; }
.header h2 i { color: var(--gold); }
.back-btn { background: rgba(255,255,255,0.15); color: white; padding: 8px 22px; border-radius: 30px; text-decoration: none; font-weight: 600; }

.summary-container { background: white; border-radius: var(--radius); padding: 20px 25px; margin-bottom: 25px; box-shadow: var(--shadow); border: 1px solid var(--border); }
.summary-header { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; margin-bottom: 15px; }
.summary-header h3 { font-size: 18px; color: var(--primary); display: flex; align-items: center; gap: 10px; }
.date-picker { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
.date-picker input { padding: 6px 14px; border: 1px solid var(--border); border-radius: 30px; font-family: var(--font); background: var(--bg); }
.view-attendance-link { background: var(--gold); color: #1A1A1A; padding: 6px 18px; border-radius: 30px; text-decoration: none; font-weight: 600; font-size: 14px; display: inline-flex; align-items: center; gap: 6px; }
.summary-stats { display: grid; grid-template-columns: repeat(4,1fr); gap: 15px; }
.summary-stat { background: var(--bg); border-radius: var(--radius-sm); padding: 12px 16px; text-align: center; border-left: 4px solid #ccc; }
.summary-stat .label { font-size: 12px; text-transform: uppercase; color: var(--text-muted); font-weight: 600; }
.summary-stat .value { font-size: 24px; font-weight: 700; margin-top: 2px; }
.summary-stat.present { border-left-color: #2E7D32; } .summary-stat.present .value { color: #2E7D32; }
.summary-stat.late { border-left-color: #F9A825; } .summary-stat.late .value { color: #F9A825; }
.summary-stat.absent { border-left-color: #D32F2F; } .summary-stat.absent .value { color: #D32F2F; }
.summary-stat.percentage { border-left-color: #1565C0; } .summary-stat.percentage .value { color: #1565C0; }

.add-subject-btn { background: var(--gold); color: #1A1A1A; padding: 10px 20px; border: none; border-radius: var(--radius-sm); cursor: pointer; font-size: 14px; font-weight: 600; display: inline-flex; align-items: center; gap: 8px; margin: 5px 0 20px; }

.grid-container { display: grid; grid-template-columns: repeat(auto-fill, minmax(350px,1fr)); gap: 25px; margin-top: 20px; }
.subject-card, .archived-card { background: white; border-radius: var(--radius); overflow: hidden; box-shadow: var(--shadow); border: 1px solid var(--border); transition: 0.2s; }
.subject-card:hover, .archived-card:hover { transform: translateY(-5px); }
.card-header { background: var(--primary-gradient); color: white; padding: 20px; text-align: center; }
.subject-code { font-size: 20px; font-weight: bold; }
.subject-name { font-size: 16px; opacity: 0.9; margin-top: 5px; }
.card-body { padding: 20px; }
.subject-section { color: var(--primary); font-weight: 600; margin-bottom: 15px; padding-bottom: 10px; border-bottom: 1px solid var(--border); }
.student-count { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; color: var(--text-muted); font-size: 14px; }
.student-count span:last-child { color: var(--primary); font-weight: bold; font-size: 18px; }

.btn-attendance, .btn-archive, .btn-delete, .btn-unarchive { width: 100%; padding: 12px; border-radius: var(--radius-sm); border: none; cursor: pointer; font-size: 14px; font-weight: 600; display: flex; align-items: center; justify-content: center; gap: 8px; text-decoration: none; transition: 0.2s; }
.btn-attendance { background: var(--primary); color: white; }
.btn-attendance:hover { background: var(--primary-dark); transform: translateY(-2px); }
.btn-archive { background: #F2C94C; color: #1A1A1A; margin-top: 10px; }
.btn-delete { background: var(--danger); color: white; margin-top: 10px; }
.btn-delete:hover { background: var(--danger-dark); transform: translateY(-2px); }
.btn-unarchive { background: var(--primary); color: white; }
.btn-unarchive:hover { background: var(--primary-dark); transform: translateY(-2px); }
.btn-archive:disabled, .btn-unarchive:disabled, .btn-delete:disabled { opacity: 0.6; cursor: not-allowed; transform: none; }

.archive-section { background: rgba(255,255,255,0.96); border: 1px solid var(--border); border-radius: var(--radius); margin-top: 35px; box-shadow: var(--shadow); overflow: hidden; }
.archive-header { display: flex; justify-content: space-between; align-items: center; padding: 20px 25px; }
.archive-title { color: var(--primary); font-size: 20px; font-weight: 700; display: flex; align-items: center; gap: 10px; }
.archive-title i { color: var(--gold); }
.archive-toggle-btn { background: var(--gold); color: #1A1A1A; border: none; padding: 9px 18px; border-radius: 22px; font-size: 13px; font-weight: 700; cursor: pointer; display: inline-flex; align-items: center; gap: 8px; }
.archive-content { max-height: 0; opacity: 0; overflow: hidden; padding: 0 25px; transition: max-height .4s ease, opacity .3s ease, padding .3s ease; }
.archive-section.open .archive-content { max-height: 5000px; opacity: 1; padding: 0 25px 25px; }
.archived-card { background: #F5F5F5; opacity: .95; }
.archived-card .card-header { background: linear-gradient(135deg, #5f6b61, #3f4942); }
.archived-label { display: inline-flex; align-items: center; gap: 7px; background: #E8D5A3; color: #5E4B19; padding: 6px 12px; border-radius: 20px; font-size: 12px; font-weight: 700; margin-bottom: 15px; }

.no-subjects, .no-archived { text-align: center; padding: 50px; color: var(--text-muted); grid-column: 1/-1; }
.no-archived { background: #F8F8F8; border: 1px dashed #D5D5D5; border-radius: var(--radius-sm); }

.modal, .confirm-modal { display: none; position: fixed; inset: 0; background: rgba(0,0,0,.5); z-index: 1000; justify-content: center; align-items: center; }
.modal-content, .confirm-modal-content { background: white; border-radius: var(--radius); width: 90%; max-width: 500px; padding: 25px; }
.modal-content h2 { color: var(--primary); margin-bottom: 20px; }
.close { float: right; font-size: 28px; font-weight: bold; cursor: pointer; color: #999; }
.form-group { margin-bottom: 15px; }
.form-group label { display: block; margin-bottom: 5px; font-weight: 600; }
.form-group input { width: 100%; padding: 10px; border: 1px solid var(--border); border-radius: var(--radius-sm); font-size: 14px; }
.modal-buttons, .confirm-buttons { display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px; }
.btn-cancel, .btn-submit, .btn-confirm-yes, .btn-confirm-no { padding: 9px 20px; border: none; border-radius: var(--radius-sm); cursor: pointer; font-weight: 600; }
.btn-cancel, .btn-confirm-no { background: #6c757d; color: white; }
.btn-submit { background: var(--gold); color: #1A1A1A; }
.btn-confirm-yes { background: var(--danger); color: white; }
.confirm-modal-content { max-width: 400px; text-align: center; }
.confirm-modal-content h3 { color: var(--danger); margin-bottom: 15px; }

/* ===== TOAST NOTIFICATIONS (replaces alert()) ===== */
#toastContainer { position: fixed; top: 20px; right: 20px; z-index: 2000; display: flex; flex-direction: column; gap: 10px; max-width: 340px; }
.toast { background: white; border-radius: var(--radius-sm); padding: 14px 18px; box-shadow: 0 8px 24px rgba(0,0,0,0.15); display: flex; align-items: flex-start; gap: 10px; font-size: 13px; font-weight: 600; border-left: 4px solid #ccc; animation: toastIn 0.25s ease; }
.toast.success { border-left-color: #2E7D32; color: #1A1A1A; }
.toast.success i { color: #2E7D32; }
.toast.error { border-left-color: var(--danger); color: #1A1A1A; }
.toast.error i { color: var(--danger); }
.toast i { margin-top: 1px; }
.toast .toast-close { margin-left: auto; cursor: pointer; color: #999; font-weight: bold; }
@keyframes toastIn { from { opacity: 0; transform: translateX(30px); } to { opacity: 1; transform: translateX(0); } }
@keyframes toastOut { from { opacity: 1; } to { opacity: 0; transform: translateX(30px); } }

@media (max-width:768px) {
    .sidebar { width: 90px; }
    .main-content { margin-left: 90px; width: calc(100% - 90px); padding: 15px; }
    .summary-stats { grid-template-columns: repeat(2,1fr); }
    .grid-container { grid-template-columns: 1fr; }
}
@media (max-width:480px) {
    .sidebar { width: 70px; }
    .main-content { margin-left: 70px; width: calc(100% - 70px); padding: 10px; }
    .sidebar .logo { width: 50px; margin-bottom: 20px; }
    .sidebar .nav-item img { width: 32px; height: 32px; }
    .sidebar .nav-item span { font-size: 8px; }
    .summary-stats { grid-template-columns: 1fr; }
    #toastContainer { left: 10px; right: 10px; max-width: none; }
}
</style>
</head>
<body>

<div id="toastContainer"></div>

<div class="sidebar">
    <img src="logo1.png" alt="LOGO" class="logo">
    <div class="nav-icons">
        <div class="nav-item"><a href="prof_dashboard.php"><img src="dashboard.png" alt="DASHBOARD"><span>Dashboard</span></a></div>
        <div class="nav-item active"><a href="attendance.php"><img src="attendance.png" alt="ATTENDANCE"><span>Attendance</span></a></div>
        <div class="nav-item"><a href="grades.php"><img src="grades.png" alt="GRADES"><span>Grades</span></a></div>
        <div class="nav-item"><a href="settings.php"><img src="settings.png" alt="SETTINGS"><span>Settings</span></a></div>
    </div>
</div>

<div class="main-content">

<div class="header">
    <h2><i class="fas fa-calendar-check"></i> Attendance Management</h2>
    <a href="prof_dashboard.php" class="back-btn">← Back to Dashboard</a>
</div>

<div class="summary-container">
    <div class="summary-header">
        <h3><i class="fas fa-chart-simple"></i> Daily Student Attendance</h3>
        <div class="date-picker">
            <label style="font-weight:500;color:var(--text-muted);">Date:</label>
            <input type="date" id="summaryDate" value="<?php echo htmlspecialchars($selected_date, ENT_QUOTES, 'UTF-8'); ?>">
            <a href="#" id="viewAttendanceLink" class="view-attendance-link"><i class="fas fa-eye"></i> View Attendance</a>
        </div>
    </div>
    <div class="summary-stats">
        <div class="summary-stat present"><div class="label"><i class="fas fa-check-circle"></i> Present</div><div class="value" id="presentCount"><?php echo $summary['Present']; ?></div></div>
        <div class="summary-stat late"><div class="label"><i class="fas fa-clock"></i> Late</div><div class="value" id="lateCount"><?php echo $summary['Late']; ?></div></div>
        <div class="summary-stat absent"><div class="label"><i class="fas fa-times-circle"></i> Absent</div><div class="value" id="absentCount"><?php echo $summary['Absent']; ?></div></div>
        <div class="summary-stat percentage"><div class="label"><i class="fas fa-percent"></i> Attendance %</div><div class="value" id="attendancePercent"><?php echo $attendance_percentage; ?>%</div></div>
    </div>
</div>

<button type="button" class="add-subject-btn" onclick="openAddSubjectModal()"><i class="fas fa-plus"></i> Add New Subject</button>

<div class="grid-container">
<?php if (count($subjects) > 0): ?>
    <?php foreach ($subjects as $subject): ?>
        <div class="subject-card">
            <div class="card-header">
                <div class="subject-code"><?php echo htmlspecialchars($subject['subject_code'], ENT_QUOTES, 'UTF-8'); ?></div>
                <div class="subject-name"><?php echo htmlspecialchars($subject['subject_name'], ENT_QUOTES, 'UTF-8'); ?></div>
            </div>
            <div class="card-body">
                <div class="subject-section"><i class="fas fa-users"></i> Section: <?php echo htmlspecialchars($subject['section'], ENT_QUOTES, 'UTF-8'); ?></div>
                <div class="student-count"><span><i class="fas fa-user-graduate"></i> Enrolled:</span><span><?php echo (int)$subject['total_enrolled']; ?></span></div>
                <a href="list_of_students.php?subject_code=<?php echo urlencode($subject['subject_code']); ?>&subject_name=<?php echo urlencode($subject['subject_name']); ?>&section=<?php echo urlencode($subject['section']); ?>" class="btn-attendance"><i class="fas fa-calendar-check"></i> Take Attendance</a>
                <button type="button" class="btn-archive archive-btn"
                    data-subject-code="<?php echo htmlspecialchars($subject['subject_code'], ENT_QUOTES, 'UTF-8'); ?>"
                    data-subject-name="<?php echo htmlspecialchars($subject['subject_name'], ENT_QUOTES, 'UTF-8'); ?>"
                    data-section="<?php echo htmlspecialchars($subject['section'], ENT_QUOTES, 'UTF-8'); ?>">
                    <i class="fas fa-box-archive"></i> Archive Subject
                </button>
                <button type="button" class="btn-delete delete-subject-btn"
                    data-subject-code="<?php echo htmlspecialchars($subject['subject_code'], ENT_QUOTES, 'UTF-8'); ?>"
                    data-subject-name="<?php echo htmlspecialchars($subject['subject_name'], ENT_QUOTES, 'UTF-8'); ?>"
                    data-section="<?php echo htmlspecialchars($subject['section'], ENT_QUOTES, 'UTF-8'); ?>">
                    <i class="fas fa-trash-alt"></i> Delete Subject
                </button>
            </div>
        </div>
    <?php endforeach; ?>
<?php else: ?>
    <div class="no-subjects">
        <i class="fas fa-folder-open" style="font-size:48px;margin-bottom:15px;"></i>
        <p>No active subjects found. Click "Add New Subject" to get started.</p>
    </div>
<?php endif; ?>
</div>

<div class="archive-section">
    <div class="archive-header">
        <div class="archive-title"><i class="fas fa-box-archive"></i> Archived Subjects</div>
        <button type="button" class="archive-toggle-btn" id="archiveToggleButton">
            <span><?php echo count($archived_subjects); ?> Archived</span>
            <i class="fas fa-chevron-down"></i>
        </button>
    </div>
    <div class="archive-content" id="archiveContent">
        <?php if (count($archived_subjects) > 0): ?>
            <div class="grid-container">
                <?php foreach ($archived_subjects as $subject): ?>
                    <div class="archived-card">
                        <div class="card-header">
                            <div class="subject-code"><?php echo htmlspecialchars($subject['subject_code'], ENT_QUOTES, 'UTF-8'); ?></div>
                            <div class="subject-name"><?php echo htmlspecialchars($subject['subject_name'], ENT_QUOTES, 'UTF-8'); ?></div>
                        </div>
                        <div class="card-body">
                            <div class="archived-label"><i class="fas fa-box-archive"></i> Archived</div>
                            <div class="subject-section"><i class="fas fa-users"></i> Section: <?php echo htmlspecialchars($subject['section'], ENT_QUOTES, 'UTF-8'); ?></div>
                            <div class="student-count"><span><i class="fas fa-user-graduate"></i> Enrolled:</span><span><?php echo (int)$subject['total_enrolled']; ?></span></div>
                            <button type="button" class="btn-unarchive unarchive-btn"
                                data-subject-code="<?php echo htmlspecialchars($subject['subject_code'], ENT_QUOTES, 'UTF-8'); ?>"
                                data-subject-name="<?php echo htmlspecialchars($subject['subject_name'], ENT_QUOTES, 'UTF-8'); ?>"
                                data-section="<?php echo htmlspecialchars($subject['section'], ENT_QUOTES, 'UTF-8'); ?>">
                                <i class="fas fa-box-open"></i> Unarchive Subject
                            </button>
                            <button type="button" class="btn-delete delete-subject-btn"
                                data-subject-code="<?php echo htmlspecialchars($subject['subject_code'], ENT_QUOTES, 'UTF-8'); ?>"
                                data-subject-name="<?php echo htmlspecialchars($subject['subject_name'], ENT_QUOTES, 'UTF-8'); ?>"
                                data-section="<?php echo htmlspecialchars($subject['section'], ENT_QUOTES, 'UTF-8'); ?>">
                                <i class="fas fa-trash-alt"></i> Delete Subject
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="no-archived"><i class="fas fa-box-open"></i><p>No archived subjects.</p></div>
        <?php endif; ?>
    </div>
</div>

</div>

<!-- ADD SUBJECT MODAL -->
<div id="addSubjectModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="closeAddSubjectModal()">&times;</span>
        <h2><i class="fas fa-plus-circle"></i> Add New Subject</h2>
        <form id="addSubjectForm" method="POST" action="add_subject.php">
            <div class="form-group"><label>Subject Code</label><input type="text" name="subject_code" required placeholder="e.g., 225B075"></div>
            <div class="form-group"><label>Subject Name</label><input type="text" name="subject_name" required placeholder="e.g., Capstone Project 1"></div>
            <div class="form-group"><label>Section</label><input type="text" name="section" required placeholder="e.g., I301"></div>
            <div class="modal-buttons">
                <button type="button" class="btn-cancel" onclick="closeAddSubjectModal()">Cancel</button>
                <button type="submit" class="btn-submit">Add Subject</button>
            </div>
        </form>
    </div>
</div>

<!-- GENERIC CONFIRM MODAL (replaces native confirm()) -->
<div id="confirmModal" class="confirm-modal">
    <div class="confirm-modal-content">
        <h3 id="confirmModalTitle"><i class="fas fa-exclamation-triangle"></i> Please Confirm</h3>
        <p id="confirmModalMessage">Are you sure?</p>
        <p id="confirmModalNote" style="font-size:12px;color:#999;margin-top:10px;"></p>
        <div class="confirm-buttons">
            <button type="button" class="btn-confirm-no" id="confirmModalCancelBtn">Cancel</button>
            <button type="button" class="btn-confirm-yes" id="confirmModalYesBtn">Yes, Proceed</button>
        </div>
    </div>
</div>

<script>
// =====================================================
// TOAST NOTIFICATIONS (replaces alert())
// =====================================================
function showToast(message, type) {
    type = type === 'error' ? 'error' : 'success';
    const container = document.getElementById('toastContainer');
    const toast = document.createElement('div');
    toast.className = 'toast ' + type;
    const icon = type === 'success' ? 'fa-circle-check' : 'fa-circle-exclamation';
    toast.innerHTML = '<i class="fas ' + icon + '"></i><span>' + escapeHtml(message) + '</span><span class="toast-close">&times;</span>';
    container.appendChild(toast);

    const remove = () => {
        toast.style.animation = 'toastOut 0.25s ease forwards';
        setTimeout(() => toast.remove(), 250);
    };
    toast.querySelector('.toast-close').addEventListener('click', remove);
    setTimeout(remove, 4000);
}

function escapeHtml(value) {
    const div = document.createElement('div');
    div.textContent = String(value);
    return div.innerHTML;
}

// =====================================================
// GENERIC CONFIRM MODAL (replaces native confirm())
// =====================================================
function showConfirm(title, message, note) {
    return new Promise((resolve) => {
        const modal = document.getElementById('confirmModal');
        document.getElementById('confirmModalTitle').innerHTML = '<i class="fas fa-exclamation-triangle"></i> ' + escapeHtml(title);
        document.getElementById('confirmModalMessage').innerHTML = message; // pre-escaped by caller
        document.getElementById('confirmModalNote').textContent = note || '';
        modal.style.display = 'flex';

        const yesBtn = document.getElementById('confirmModalYesBtn');
        const noBtn = document.getElementById('confirmModalCancelBtn');

        function cleanup(result) {
            modal.style.display = 'none';
            yesBtn.removeEventListener('click', onYes);
            noBtn.removeEventListener('click', onNo);
            resolve(result);
        }
        function onYes() { cleanup(true); }
        function onNo() { cleanup(false); }

        yesBtn.addEventListener('click', onYes);
        noBtn.addEventListener('click', onNo);
    });
}

// =====================================================
// VIEW ATTENDANCE
// =====================================================
const viewAttendanceLink = document.getElementById('viewAttendanceLink');
if (viewAttendanceLink) {
    viewAttendanceLink.addEventListener('click', function(event) {
        event.preventDefault();
        const dateInput = document.getElementById('summaryDate');
        if (!dateInput || !dateInput.value) {
            showToast('Please select a date first.', 'error');
            return;
        }
        window.location.href = 'attendance.php?date=' + encodeURIComponent(dateInput.value);
    });
}

// =====================================================
// ADD SUBJECT MODAL
// =====================================================
function openAddSubjectModal() {
    const modal = document.getElementById('addSubjectModal');
    if (modal) modal.style.display = 'flex';
}
function closeAddSubjectModal() {
    const modal = document.getElementById('addSubjectModal');
    if (modal) modal.style.display = 'none';
}

// =====================================================
// ARCHIVE TOGGLE
// =====================================================
const archiveToggleButton = document.getElementById('archiveToggleButton');
const archiveSection = document.querySelector('.archive-section');
if (archiveToggleButton && archiveSection) {
    archiveToggleButton.addEventListener('click', function() {
        archiveSection.classList.toggle('open');
        const icon = this.querySelector('i');
        if (icon) {
            icon.classList.toggle('fa-chevron-down');
            icon.classList.toggle('fa-chevron-up');
        }
    });
}

// =====================================================
// ARCHIVE / UNARCHIVE
// =====================================================
async function changeArchiveStatus(subjectCode, subjectName, section, action, button) {
    const actionText = action === 'archive' ? 'archive' : 'restore';
    const message = 'Are you sure you want to ' + actionText + ' <strong>' + escapeHtml(subjectCode) + ' - ' + escapeHtml(subjectName) + '</strong> (Section: ' + escapeHtml(section) + ')?';

    const confirmed = await showConfirm('Confirm ' + (action === 'archive' ? 'Archive' : 'Unarchive'), message);
    if (!confirmed) return;

    if (button) button.disabled = true;

    try {
        const formData = new FormData();
        formData.append('subject_code', subjectCode);
        formData.append('subject_name', subjectName);
        formData.append('section', section);
        formData.append('action', action);

        const response = await fetch('archive_subject.php', {
            method: 'POST',
            body: formData,
            credentials: 'same-origin',
            cache: 'no-store',
            headers: { 'Accept': 'application/json' }
        });

        const text = await response.text();
        let data;
        try {
            data = JSON.parse(text.trim());
        } catch (error) {
            console.error('archive_subject.php raw response:', text);
            throw new Error('The server did not return a valid response. Check that archive_subject.php exists and returns JSON (see console for the raw output).');
        }

        if (data.success === true) {
            showToast(data.message || 'Subject updated successfully.', 'success');
            setTimeout(() => window.location.reload(), 900);
            return;
        }

        showToast(data.message || 'Unable to update subject.', 'error');

    } catch (error) {
        console.error(error);
        showToast(error.message, 'error');
    } finally {
        if (button) button.disabled = false;
    }
}

document.addEventListener('click', function(event) {
    const button = event.target.closest('.archive-btn');
    if (!button) return;
    changeArchiveStatus(button.dataset.subjectCode, button.dataset.subjectName, button.dataset.section, 'archive', button);
});

document.addEventListener('click', function(event) {
    const button = event.target.closest('.unarchive-btn');
    if (!button) return;
    changeArchiveStatus(button.dataset.subjectCode, button.dataset.subjectName, button.dataset.section, 'unarchive', button);
});

// =====================================================
// DELETE SUBJECT
// =====================================================
document.addEventListener('click', async function(event) {
    const button = event.target.closest('.delete-subject-btn');
    if (!button) return;
    event.preventDefault();

    const subjectCode = button.dataset.subjectCode;
    const subjectName = button.dataset.subjectName;
    const section = button.dataset.section;

    const message = 'Are you sure you want to permanently delete <strong>' + escapeHtml(subjectCode) + ' - ' + escapeHtml(subjectName) + '</strong> (Section: ' + escapeHtml(section) + ')?';
    const confirmed = await showConfirm('Confirm Delete', message, 'This will permanently remove the subject and its enrolled student records.');
    if (!confirmed) return;

    button.disabled = true;

    try {
        const formData = new FormData();
        formData.append('subject_code', subjectCode);
        formData.append('subject_name', subjectName);
        formData.append('section', section);

        const response = await fetch('delete_subject.php', {
            method: 'POST',
            body: formData,
            credentials: 'same-origin',
            cache: 'no-store',
            headers: { 'Accept': 'application/json' }
        });

        const text = await response.text();
        if (!response.ok) throw new Error('Server error: HTTP ' + response.status);

        let result;
        try {
            result = JSON.parse(text.trim());
        } catch (error) {
            console.error('delete_subject.php raw response:', text);
            throw new Error('delete_subject.php returned an invalid response (see console).');
        }

        if (result.success === true) {
            showToast(result.message || 'Subject deleted successfully.', 'success');
            setTimeout(() => window.location.reload(), 900);
            return;
        }

        showToast(result.message || 'Unable to delete subject.', 'error');

    } catch (error) {
        console.error(error);
        showToast(error.message, 'error');
    } finally {
        button.disabled = false;
    }
});

// =====================================================
// DATE CHANGE
// =====================================================
const summaryDate = document.getElementById('summaryDate');
if (summaryDate) {
    summaryDate.addEventListener('change', function() {
        if (!this.value) return;
        window.location.href = 'attendance.php?date=' + encodeURIComponent(this.value);
    });
}

// =====================================================
// CLOSE MODALS ON OUTSIDE CLICK / ESC
// =====================================================
window.addEventListener('click', function(event) {
    const addModal = document.getElementById('addSubjectModal');
    const confirmModalEl = document.getElementById('confirmModal');
    if (addModal && event.target === addModal) closeAddSubjectModal();
    if (confirmModalEl && event.target === confirmModalEl) {
        document.getElementById('confirmModalCancelBtn').click();
    }
});

document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        closeAddSubjectModal();
        const confirmModalEl = document.getElementById('confirmModal');
        if (confirmModalEl && confirmModalEl.style.display === 'flex') {
            document.getElementById('confirmModalCancelBtn').click();
        }
    }
});
</script>

</body>
</html>