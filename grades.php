<?php
session_start();
require_once 'connect.php';

if (!isset($_SESSION['id_number'])) {
    header("Location: login.php");
    exit();
}

// ===== HANDLE CLEAR ALL GRADES =====
if (isset($_POST['clear_all_grades'])) {
    $conn->query("TRUNCATE TABLE grades");
    $_SESSION['upload_message'] = "All grade records have been cleared.";
    header("Location: grades.php");
    exit();
}

// ===== HANDLE AJAX REQUEST FOR STUDENT DETAILS =====
if (isset($_GET['action']) && $_GET['action'] === 'get_details' && isset($_GET['student_id']) && isset($_GET['subject'])) {
    header('Content-Type: application/json');
    $student_id = $_GET['student_id'];
    $subject = $_GET['subject'];

    $stmt = $conn->prepare("SELECT assignment, participation, quizzes, activity FROM grades WHERE student_id = ? AND subject = ?");
    $stmt->bind_param("ss", $student_id, $subject);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result && $result->num_rows > 0) {
        $data = $result->fetch_assoc();
        echo json_encode(['success' => true, 'data' => $data]);
    } else {
        echo json_encode(['success' => true, 'data' => ['assignment' => 'N/A', 'participation' => 'N/A', 'quizzes' => 'N/A', 'activity' => 'N/A']]);
    }
    $stmt->close();
    exit();
}

/**
 * Shared helper: process an uploaded CSV for either 'midterm' or 'final' grades.
 * Expected CSV columns (in this exact order, matches the spreadsheet template),
 * header row is skipped automatically:
 *   0 - Student ID
 *   1 - Name
 *   2 - Section
 *   3 - Subject       (informational only; the Subject typed in the upload form is what's actually used)
 *   4 - Assignment
 *   5 - Participation
 *   6 - Quizzes
 *   7 - Activity
 *   8 - Midterms / Final (the score column — header label changes but position is always index 8)
 *
 * If a Student ID in the CSV does not yet exist in the `students` table, a new
 * student record is AUTO-CREATED using the Name and Section columns from that row.
 * Average = (midterm + final) / 2, computed and saved automatically after every upload.
 * Remarks = "Passed" if average is between 1.00 and 3.00 (inclusive), otherwise "Failed"
 * (standard Philippine college numeric scale, where a LOWER number is a better grade).
 */
function process_grades_csv($conn, $file, $subject, $type) {
    $upload_success = 0;
    $upload_errors = 0;
    $students_created = 0;

    if (($handle = fopen($file, "r")) === FALSE) {
        return ['success' => 0, 'errors' => 0, 'created' => 0, 'read_error' => true];
    }

    fgetcsv($handle); // skip header row

    // Prepared statements reused for every row
    $findStudentById = $conn->prepare("SELECT student_id FROM students WHERE student_id = ?");
    $insertStudent   = $conn->prepare("INSERT INTO students (student_id, name, section) VALUES (?, ?, ?)");
    $findGrade       = $conn->prepare("SELECT id, midterm, final FROM grades WHERE student_id = ? AND subject = ?");
    $updateGrade     = $conn->prepare("UPDATE grades SET midterm = ?, final = ?, assignment = ?, participation = ?, quizzes = ?, activity = ?, average = ?, remarks = ? WHERE id = ?");
    $insertGrade     = $conn->prepare("INSERT INTO grades (student_id, subject, midterm, final, assignment, participation, quizzes, activity, average, remarks) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

    while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
        if (count($data) < 9) {
            $upload_errors++;
            continue;
        }

        $student_id    = trim($data[0]);
        $name          = trim($data[1]);
        $section       = trim($data[2]);
        // $data[3] = Subject — informational only, not used for the update
        $assignment    = trim($data[4]);
        $participation = trim($data[5]);
        $quizzes       = trim($data[6]);
        $activity      = trim($data[7]);
        $score         = trim($data[8]);

        if ($student_id === '' || !is_numeric($score)) {
            $upload_errors++;
            continue;
        }

        // Optional fields: keep numeric values as-is, anything else becomes 'N/A'
        foreach (['assignment', 'participation', 'quizzes', 'activity'] as $field) {
            if (!is_numeric($$field)) {
                $$field = 'N/A';
            }
        }

        $findStudentById->bind_param("s", $student_id);
        $findStudentById->execute();
        $res = $findStudentById->get_result();

        if (!$res || $res->num_rows === 0) {
            // Auto-create the student using this row's Name/Section
            $insertStudent->bind_param("sss", $student_id, $name, $section);
            $insertStudent->execute();
            $students_created++;
        }

        $findGrade->bind_param("ss", $student_id, $subject);
        $findGrade->execute();
        $existing = $findGrade->get_result();

        if ($existing && $existing->num_rows > 0) {
            $row = $existing->fetch_assoc();
            $midterm = ($type === 'midterm') ? $score : ($row['midterm'] ?? 0);
            $final   = ($type === 'final')   ? $score : ($row['final'] ?? 0);
            $average = ($midterm + $final) / 2;
            $remarks = ($average > 0 && $average <= 3.00) ? 'Passed' : 'Failed';

            $updateGrade->bind_param("ddssssdsi", $midterm, $final, $assignment, $participation, $quizzes, $activity, $average, $remarks, $row['id']);
            $updateGrade->execute();
        } else {
            $midterm = ($type === 'midterm') ? $score : 0;
            $final   = ($type === 'final')   ? $score : 0;
            $average = ($midterm + $final) / 2;
            $remarks = ($average > 0 && $average <= 3.00) ? 'Passed' : 'Failed';

            $insertGrade->bind_param("ssddssssds", $student_id, $subject, $midterm, $final, $assignment, $participation, $quizzes, $activity, $average, $remarks);
            $insertGrade->execute();
        }

        $upload_success++;
    }

    fclose($handle);
    $findStudentById->close();
    $insertStudent->close();
    $findGrade->close();
    $updateGrade->close();
    $insertGrade->close();

    return ['success' => $upload_success, 'errors' => $upload_errors, 'created' => $students_created, 'read_error' => false];
}

// ===== HANDLE CSV UPLOADS =====
if (isset($_POST['upload_midterm_csv']) && isset($_FILES['csv_file']) && isset($_POST['csv_subject'])) {
    $subject = $_POST['csv_subject'];
    $result = process_grades_csv($conn, $_FILES['csv_file']['tmp_name'], $subject, 'midterm');
    $_SESSION['upload_message'] = $result['read_error']
        ? "Error reading CSV file."
        : "Midterm grades uploaded successfully! ({$result['success']} records, {$result['created']} new students created, {$result['errors']} errors)";
    header("Location: grades.php");
    exit();
}

if (isset($_POST['upload_final_csv']) && isset($_FILES['csv_file']) && isset($_POST['csv_subject'])) {
    $subject = $_POST['csv_subject'];
    $result = process_grades_csv($conn, $_FILES['csv_file']['tmp_name'], $subject, 'final');
    $_SESSION['upload_message'] = $result['read_error']
        ? "Error reading CSV file."
        : "Final grades uploaded successfully! ({$result['success']} records, {$result['created']} new students created, {$result['errors']} errors)";
    header("Location: grades.php");
    exit();
}

// Get sections for filter
$sections_result = $conn->query("SELECT DISTINCT section FROM students ORDER BY section ASC");
$sections = [];
if ($sections_result && $sections_result->num_rows > 0) {
    while ($row = $sections_result->fetch_assoc()) {
        $sections[] = $row['section'];
    }
}
$filter_section = isset($_GET['section']) ? $_GET['section'] : 'all';

// Fetch grades with filter
if ($filter_section != 'all') {
    $sql = "SELECT g.*, s.name as student_name_from_db, s.section as student_section
            FROM grades g
            LEFT JOIN students s ON g.student_id = s.student_id
            WHERE s.section = ?
            ORDER BY g.id DESC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $filter_section);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $sql = "SELECT g.*, s.name as student_name_from_db, s.section as student_section
            FROM grades g
            LEFT JOIN students s ON g.student_id = s.student_id
            ORDER BY g.id DESC";
    $result = $conn->query($sql);
}
$grades = [];
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $row['student_name'] = $row['student_name_from_db'] ?: $row['student_name'];
        $row['section'] = $row['student_section'] ?: $row['section'];
        $grades[] = $row;
    }
}
$upload_message = isset($_SESSION['upload_message']) ? $_SESSION['upload_message'] : '';
unset($_SESSION['upload_message']);
?>
<!DOCTYPE html>
<html>
<head>
    <title>Grades Management - TOTALIS HUMANAE</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,400;14..32,500;14..32,600;14..32,700&display=swap" rel="stylesheet">
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
        body { font-family: var(--font); display: flex; background: var(--bg); min-height: 100vh; }
        body::before {
            content: "";
            position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
            background-image: url('cca.webp');
            background-size: cover; background-position: center;
            opacity: 0.15; z-index: -1;
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
        .sidebar .logo:hover { transform: scale(1.05); background: rgba(255,255,255,0.2); border-color: var(--gold); }
        .sidebar .nav-icons { display: flex; flex-direction: column; align-items: center; gap: 25px; flex: 1; width: 100%; }
        .sidebar .nav-item { display: flex; flex-direction: column; align-items: center; gap: 5px; transition: 0.2s; position: relative; width: 100%; padding: 8px 0; }
        .sidebar .nav-item a { text-decoration: none; display: flex; flex-direction: column; align-items: center; gap: 5px; color: rgba(255,255,255,0.7); }
        .sidebar .nav-item img { width: 42px; height: 42px; object-fit: contain; transition: 0.2s; filter: brightness(0) invert(1); opacity: 0.7; }
        .sidebar .nav-item span { color: rgba(255,255,255,0.7); font-size: 10px; font-weight: 600; letter-spacing: 0.5px; text-transform: uppercase; }
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
        .header h2 { color: white; font-size: 1.8rem; display: flex; align-items: center; gap: 12px; }
        .header h2 i { color: var(--gold); }
        .back-btn { background: rgba(255,255,255,0.15); color: white; padding: 8px 22px; border-radius: 30px; text-decoration: none; font-weight: 600; transition: 0.2s; border: 1px solid rgba(255,255,255,0.1); }
        .back-btn:hover { background: rgba(255,255,255,0.25); }

        .grades-container {
            background: var(--card-bg);
            border-radius: 0 0 var(--radius) var(--radius);
            padding: 25px;
            box-shadow: var(--shadow);
        }

        .action-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 20px;
        }
        .filter-section {
            display: flex;
            align-items: center;
            gap: 10px;
            background: var(--bg);
            padding: 5px 15px;
            border-radius: 40px;
        }
        .filter-section select {
            padding: 8px 15px;
            border: 1px solid var(--border);
            border-radius: 30px;
            font-family: inherit;
            background: white;
        }
        .search-box {
            padding: 8px 15px;
            border: 1px solid var(--border);
            border-radius: 30px;
            width: 250px;
            font-size: 14px;
        }
        .btn-group { display: flex; gap: 10px; flex-wrap: wrap; }
        .btn {
            background: var(--primary);
            color: white;
            padding: 8px 18px;
            border: none;
            border-radius: 30px;
            cursor: pointer;
            font-weight: 600;
            transition: 0.2s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 14px;
        }
        .btn:hover { background: var(--primary-dark); transform: translateY(-2px); }
        .btn-warning { background: var(--gold); color: #1A1A1A; }
        .btn-warning:hover { background: #B8943A; }
        .btn-info { background: #17a2b8; }
        .btn-info:hover { background: #138496; }
        .btn-danger { background: var(--danger); }
        .btn-danger:hover { background: #8B1A2B; }
        .upload-message {
            background: #d4edda;
            color: #155724;
            padding: 12px;
            border-radius: var(--radius-sm);
            margin-bottom: 20px;
            border-left: 4px solid #28a745;
        }

        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        table thead th {
            padding: 12px 15px;
            text-align: left;
            font-weight: 700;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--primary);
            border-bottom: 2px solid var(--primary);
            background: #f0f5f0;
        }
        table tbody td {
            padding: 12px 15px;
            border-bottom: 1px solid var(--border);
            color: var(--text);
            vertical-align: middle;
        }
        table tbody tr:last-child td { border-bottom: none; }
        table tbody tr:hover td { background: #FAFAFA; }

        .grade-btn {
            border: none;
            padding: 4px 14px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 13px;
            cursor: pointer;
            transition: 0.2s;
            min-width: 55px;
            background: var(--primary);
            color: white;
        }
        .grade-btn:hover {
            transform: scale(1.05);
            box-shadow: 0 2px 8px rgba(0, 77, 0, 0.3);
        }
        .grade-btn.gold { background: var(--gold); color: #1A1A1A; }
        .grade-btn.gold:hover { background: #B8943A; }
        .grade-btn.danger { background: var(--danger); color: white; }
        .grade-btn.danger:hover { background: #8B1A2B; }

        .average-text { color: #000000; font-weight: 600; font-size: 14px; }

        .status-badge { display: inline-block; padding: 4px 14px; border-radius: 50px; font-size: 12px; font-weight: 600; }
        .status-badge.passed { background: #d4edda; color: #155724; }
        .status-badge.failed { background: #f8d7da; color: #721c24; }

        .no-data-msg { text-align: center; padding: 40px 20px; color: var(--text-muted); font-size: 1.1rem; }
        .no-data-msg .icon { font-size: 3rem; display: block; margin-bottom: 15px; color: #ccc; }
        .no-data-msg .sub { font-size: 0.9rem; color: #bdc3c7; margin-top: 8px; }

        .modal {
            display: none;
            position: fixed;
            top: 0; left: 0;
            width: 100%; height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }
        .modal-content {
            background: white;
            border-radius: var(--radius);
            width: 90%;
            max-width: 550px;
            padding: 25px;
            animation: slideDown 0.3s ease;
            max-height: 80vh;
            overflow-y: auto;
        }
        @keyframes slideDown { from { opacity: 0; transform: translateY(-30px); } to { opacity: 1; transform: translateY(0); } }
        .modal-content h2 { color: var(--primary); margin-bottom: 10px; }
        .modal-content .subtitle { color: var(--text-muted); margin-bottom: 20px; }
        .modal-close { float: right; font-size: 28px; font-weight: bold; cursor: pointer; color: #999; }
        .modal-close:hover { color: var(--primary); }
        .modal-details { margin-top: 15px; }
        .modal-details .detail-row { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid var(--border); }
        .modal-details .detail-label { font-weight: 600; color: var(--text-muted); }
        .modal-actions { margin-top: 20px; display: flex; justify-content: center; }
        .modal-actions .btn { padding: 8px 25px; border-radius: 30px; }

        .csv-format {
            background: var(--bg);
            padding: 12px 15px;
            border-radius: var(--radius-sm);
            margin-top: 10px;
            border-left: 3px solid var(--gold);
            font-size: 13px;
            color: var(--text-muted);
        }
        .csv-format strong { color: var(--text); }
        .csv-format code { background: #e9ecef; padding: 2px 8px; border-radius: 4px; font-size: 12px; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: 600; color: var(--text); }
        .form-group input {
            width: 100%;
            padding: 10px 14px;
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            font-size: 14px;
            transition: 0.2s;
        }
        .form-group input:focus {
            border-color: var(--primary);
            outline: none;
            box-shadow: 0 0 0 3px rgba(0, 77, 0, 0.1);
        }
        .modal-buttons { display: flex; gap: 12px; justify-content: center; margin-top: 20px; }
        .btn-cancel {
            background: #6c757d; color: white; padding: 10px 28px; border: none;
            border-radius: 30px; cursor: pointer; font-weight: 600; font-size: 14px;
            transition: 0.2s; min-width: 100px;
        }
        .btn-cancel:hover { background: #5a6268; transform: translateY(-2px); }
        .btn-submit {
            background: var(--gold); color: #1A1A1A; padding: 10px 28px; border: none;
            border-radius: 30px; cursor: pointer; font-weight: 700; font-size: 14px;
            transition: 0.2s; min-width: 120px;
        }
        .btn-submit:hover { background: #B8943A; transform: translateY(-2px); box-shadow: 0 4px 15px rgba(201, 168, 76, 0.4); }
        .btn-submit.danger { background: var(--danger); color: white; }
        .btn-submit.danger:hover { background: #8B1A2B; box-shadow: 0 4px 15px rgba(178, 34, 52, 0.4); }
        .close { float: right; font-size: 28px; font-weight: bold; cursor: pointer; color: #999; transition: 0.2s; }
        .close:hover { color: var(--primary); }

        @media (max-width: 768px) {
            .sidebar { width: 90px; }
            .main-content { margin-left: 90px; padding: 15px; }
            .action-bar { flex-direction: column; align-items: stretch; }
            .search-box { width: 100%; }
            .modal-buttons { flex-direction: column; align-items: center; }
            .btn-cancel, .btn-submit { width: 100%; min-width: unset; }
        }
    </style>
</head>
<body>

    <div class="sidebar">
        <img src="logo1.png" alt="LOGO" class="logo">
        <div class="nav-icons">
            <div class="nav-item">
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
            <div class="nav-item active">
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

    <div class="main-content">
        <div class="header">
            <h2><i class="fas fa-chart-line"></i> Grades Management</h2>
            <a href="prof_dashboard.php" class="back-btn">← Back</a>
        </div>
        <div class="grades-container">
            <?php if ($upload_message): ?>
                <div class="upload-message"><i class="fas fa-info-circle"></i> <?php echo htmlspecialchars($upload_message); ?></div>
            <?php endif; ?>

            <div class="action-bar">
                <div class="filter-section">
                    <label><i class="fas fa-filter"></i> Section:</label>
                    <form method="GET" id="filterForm" style="display:inline-flex;gap:10px;">
                        <select name="section" onchange="this.form.submit()">
                            <option value="all" <?php echo $filter_section == 'all' ? 'selected' : ''; ?>>All Sections</option>
                            <?php foreach ($sections as $sec): ?>
                                <option value="<?php echo htmlspecialchars($sec); ?>" <?php echo $filter_section == $sec ? 'selected' : ''; ?>><?php echo htmlspecialchars($sec); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </form>
                </div>
                <input type="text" id="searchInput" placeholder="Search by name, ID, or subject..." class="search-box">
                <div class="btn-group">
                    <button class="btn btn-warning" onclick="openCSVModal('midterm')">
                        <i class="fas fa-file-csv"></i> Upload Midterm
                    </button>
                    <button class="btn btn-info" onclick="openCSVModal('final')">
                        <i class="fas fa-file-csv"></i> Upload Final
                    </button>
                    <button class="btn" onclick="exportToCSV()">
                        <i class="fas fa-download"></i> Export CSV
                    </button>
                    <form method="POST" id="clearForm" style="display:inline;">
                        <input type="hidden" name="clear_all_grades" value="1">
                        <button type="button" class="btn btn-danger" onclick="openClearModal()">
                            <i class="fas fa-trash-alt"></i> Clear All
                        </button>
                    </form>
                </div>
            </div>

            <div style="overflow-x:auto;">
                <table>
                    <thead>
                        <tr>
                            <th>STUDENT ID</th>
                            <th>STUDENT NAME</th>
                            <th>SECTION</th>
                            <th>SUBJECT</th>
                            <th>MIDTERM</th>
                            <th>FINAL</th>
                            <th>AVERAGE</th>
                            <th>REMARKS</th>
                        </tr>
                    </thead>
                    <tbody id="gradesTableBody">
                        <?php if (count($grades) > 0): ?>
                            <?php foreach ($grades as $grade):
                                $midterm = $grade['midterm'];
                                $final = $grade['final'];
                                $average = $grade['average'];
                                $remarks = $grade['remarks'];
                                $midterm_class = ($midterm > 0 && $midterm <= 3.00) ? 'grade-btn' : 'grade-btn danger';
                                $final_class = ($final > 0 && $final <= 3.00) ? 'grade-btn' : 'grade-btn danger';
                                $midterm_display = $midterm ?: '-';
                                $final_display = $final ?: '-';
                                $average_display = $average > 0 ? number_format($average, 2) : '-';
                                $remarks_class = ($remarks == 'Passed') ? 'status-badge passed' : 'status-badge failed';
                            ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($grade['student_id']); ?></td>
                                    <td><?php echo htmlspecialchars($grade['student_name']); ?></td>
                                    <td><?php echo htmlspecialchars($grade['section'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($grade['subject']); ?></td>
                                    <td>
                                        <button class="<?php echo $midterm_class; ?>"
                                                onclick="showStudentDetails('<?php echo htmlspecialchars($grade['student_id']); ?>', '<?php echo htmlspecialchars($grade['subject']); ?>', '<?php echo htmlspecialchars($grade['student_name']); ?>')">
                                            <?php echo $midterm_display; ?>
                                        </button>
                                    </td>
                                    <td>
                                        <button class="<?php echo $final_class; ?>"
                                                onclick="showStudentDetails('<?php echo htmlspecialchars($grade['student_id']); ?>', '<?php echo htmlspecialchars($grade['subject']); ?>', '<?php echo htmlspecialchars($grade['student_name']); ?>')">
                                            <?php echo $final_display; ?>
                                        </button>
                                    </td>
                                    <td class="average-text"><?php echo $average_display; ?></td>
                                    <td>
                                        <span class="<?php echo $remarks_class; ?>">
                                            <?php echo htmlspecialchars($remarks); ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8">
                                    <div class="no-data-msg">
                                        <span class="icon">📊</span>
                                        No grades found.<br>
                                        <span class="sub">Use CSV upload.</span>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div id="detailsModal" class="modal">
        <div class="modal-content">
            <span class="modal-close" onclick="closeDetailsModal()">&times;</span>
            <h2 id="modalStudentName">Student Name</h2>
            <div class="subtitle" id="modalSubject">Subject: <span></span></div>
            <div class="modal-details">
                <div class="detail-row">
                    <span class="detail-label">Assignment</span>
                    <span id="modalAssignment">-</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Participation</span>
                    <span id="modalParticipation">-</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Quizzes</span>
                    <span id="modalQuizzes">-</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Activity</span>
                    <span id="modalActivity">-</span>
                </div>
            </div>
            <div class="modal-actions">
                <button class="btn" onclick="closeDetailsModal()">Close</button>
            </div>
        </div>
    </div>

    <div id="csvModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeCSVModal()">&times;</span>
            <h2 id="csvModalTitle"><i class="fas fa-file-csv"></i> Upload CSV</h2>
            <form id="csvForm" method="POST" enctype="multipart/form-data">
                <div class="form-group">
                    <label>Subject <span style="color:red;">*</span></label>
                    <input type="text" name="csv_subject" id="csv_subject" required placeholder="e.g., Mathematics 101">
                </div>
                <div class="form-group">
                    <label>CSV File <span style="color:red;">*</span></label>
                    <input type="file" name="csv_file" accept=".csv" required>
                </div>
                <div class="csv-format" id="csvFormatInfo">
                    <strong><i class="fas fa-info-circle"></i> CSV Format:</strong><br>
                    <span id="csvFormatText">Format: <code>Student ID, Name, Section, Subject, Assignment, Participation, Quizzes, Activity, Midterms</code></span>
                </div>
                <div class="modal-buttons">
                    <button type="button" class="btn-cancel" onclick="closeCSVModal()">Cancel</button>
                    <button type="submit" class="btn-submit" id="csvSubmitBtn">Upload</button>
                </div>
            </form>
        </div>
    </div>

    <!-- CLEAR ALL CONFIRMATION (in-page, replaces the browser popup) -->
    <div id="clearModal" class="modal">
        <div class="modal-content" style="max-width:420px;text-align:center;">
            <h2 style="color:var(--danger);"><i class="fas fa-exclamation-triangle"></i> Clear All Grades?</h2>
            <p class="subtitle">This will delete ALL grade records. This action cannot be undone.</p>
            <div class="modal-buttons">
                <button type="button" class="btn-cancel" onclick="closeClearModal()">Cancel</button>
                <button type="button" class="btn-submit danger" onclick="document.getElementById('clearForm').submit()">Yes, Clear All</button>
            </div>
        </div>
    </div>

    <script>
        function showStudentDetails(studentId, subject, studentName) {
            document.getElementById('modalStudentName').textContent = studentName;
            document.querySelector('#modalSubject span').textContent = subject;

            document.getElementById('modalAssignment').textContent = 'Loading...';
            document.getElementById('modalParticipation').textContent = 'Loading...';
            document.getElementById('modalQuizzes').textContent = 'Loading...';
            document.getElementById('modalActivity').textContent = 'Loading...';

            fetch('grades.php?action=get_details&student_id=' + encodeURIComponent(studentId) + '&subject=' + encodeURIComponent(subject))
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('modalAssignment').textContent = data.data.assignment || 'N/A';
                        document.getElementById('modalParticipation').textContent = data.data.participation || 'N/A';
                        document.getElementById('modalQuizzes').textContent = data.data.quizzes || 'N/A';
                        document.getElementById('modalActivity').textContent = data.data.activity || 'N/A';
                    } else {
                        document.getElementById('modalAssignment').textContent = 'Error';
                        document.getElementById('modalParticipation').textContent = 'Error';
                        document.getElementById('modalQuizzes').textContent = 'Error';
                        document.getElementById('modalActivity').textContent = 'Error';
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    document.getElementById('modalAssignment').textContent = 'Error loading';
                    document.getElementById('modalParticipation').textContent = 'Error loading';
                    document.getElementById('modalQuizzes').textContent = 'Error loading';
                    document.getElementById('modalActivity').textContent = 'Error loading';
                });

            document.getElementById('detailsModal').style.display = 'flex';
        }

        function closeDetailsModal() {
            document.getElementById('detailsModal').style.display = 'none';
        }

        function openCSVModal(type) {
            document.getElementById('csv_subject').value = '';

            let existingMid = document.querySelector('#csvForm input[name="upload_midterm_csv"]');
            let existingFinal = document.querySelector('#csvForm input[name="upload_final_csv"]');
            if (existingMid) existingMid.remove();
            if (existingFinal) existingFinal.remove();

            let input = document.createElement('input');
            input.type = 'hidden';
            input.name = type === 'midterm' ? 'upload_midterm_csv' : 'upload_final_csv';
            input.value = '1';
            document.getElementById('csvForm').appendChild(input);

            let modalTitle = document.getElementById('csvModalTitle');
            let submitBtn = document.getElementById('csvSubmitBtn');
            let formatText = document.getElementById('csvFormatText');

            if (type === 'midterm') {
                modalTitle.innerHTML = '<i class="fas fa-file-csv"></i> Upload Midterm Grades with Details';
                submitBtn.innerHTML = '<i class="fas fa-upload"></i> Upload Midterm';
                formatText.innerHTML = 'Format: <code>Student ID, Name, Section, Subject, Assignment, Participation, Quizzes, Activity, Midterms</code><br><small>First row will be skipped as header.</small>';
            } else {
                modalTitle.innerHTML = '<i class="fas fa-file-csv"></i> Upload Final Grades with Details';
                submitBtn.innerHTML = '<i class="fas fa-upload"></i> Upload Final';
                formatText.innerHTML = 'Format: <code>Student ID, Name, Section, Subject, Assignment, Participation, Quizzes, Activity, Final</code><br><small>First row will be skipped as header.</small>';
            }

            let fileInput = document.querySelector('#csvForm input[type="file"]');
            if (fileInput) fileInput.value = '';

            document.getElementById('csvModal').style.display = 'flex';
        }

        function closeCSVModal() {
            document.getElementById('csvModal').style.display = 'none';
            document.getElementById('csvForm').reset();
        }

        function openClearModal() {
            document.getElementById('clearModal').style.display = 'flex';
        }

        function closeClearModal() {
            document.getElementById('clearModal').style.display = 'none';
        }

        document.getElementById('searchInput').addEventListener('keyup', function() {
            const q = this.value.toLowerCase();
            document.querySelectorAll('#gradesTableBody tr').forEach(row => {
                if (row.cells && row.cells.length > 0) {
                    row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none';
                }
            });
        });

        function exportToCSV() {
            let rows = document.querySelectorAll('#gradesTableBody tr');
            let csv = [['Student ID','Student Name','Section','Subject','Midterm','Final','Average','Remarks']];
            rows.forEach(row => {
                if (row.cells && row.cells.length > 0) {
                    csv.push([
                        row.cells[0]?.textContent || '',
                        row.cells[1]?.textContent || '',
                        row.cells[2]?.textContent || '',
                        row.cells[3]?.textContent || '',
                        row.cells[4]?.textContent === '-' ? '' : row.cells[4]?.textContent || '',
                        row.cells[5]?.textContent === '-' ? '' : row.cells[5]?.textContent || '',
                        row.cells[6]?.textContent === '-' ? '' : row.cells[6]?.textContent || '',
                        row.cells[7]?.textContent || ''
                    ]);
                }
            });
            let csvContent = csv.map(row => row.join(',')).join('\n');
            let blob = new Blob(["\uFEFF" + csvContent], { type: 'text/csv;charset=utf-8;' });
            let url = URL.createObjectURL(blob);
            let a = document.createElement('a');
            a.href = url;
            a.download = 'grades_export_' + new Date().toISOString().slice(0,10) + '.csv';
            a.click();
            URL.revokeObjectURL(url);
        }

        window.onclick = function(e) {
            if (e.target === document.getElementById('csvModal')) closeCSVModal();
            if (e.target === document.getElementById('detailsModal')) closeDetailsModal();
            if (e.target === document.getElementById('clearModal')) closeClearModal();
        }
    </script>

</body>
</html>