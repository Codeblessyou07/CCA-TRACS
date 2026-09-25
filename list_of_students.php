<?php
session_start();
require_once 'connect.php';

if (!isset($_SESSION['id_number'])) {
    header("Location: login.php");
    exit();
}

$subject_code = isset($_GET['subject_code']) ? trim($_GET['subject_code']) : '';
$subject_name = isset($_GET['subject_name']) ? trim($_GET['subject_name']) : '';
$section      = isset($_GET['section']) ? trim($_GET['section']) : '';

if (empty($subject_code) || empty($section)) {
    die("Invalid subject or section");
}

// Kunin ang tamang section mula sa subjects table (para iwas mismatch)
$correct_section = $section;
$subj_stmt = $conn->prepare("SELECT section FROM subjects WHERE subject_code = ? LIMIT 1");
$subj_stmt->bind_param("s", $subject_code);
$subj_stmt->execute();
$subj_result = $subj_stmt->get_result();
if ($subj_result && $subj_result->num_rows > 0) {
    $db_section = $subj_result->fetch_assoc()['section'];
    if (!empty($db_section)) {
        $correct_section = $db_section;
    }
}
$subj_stmt->close();

$today = date('Y-m-d');

$redirect_url = "list_of_students.php?subject_code=" . urlencode($subject_code) . "&subject_name=" . urlencode($subject_name) . "&section=" . urlencode($correct_section);

function setFlash($msg, $type = 'success') {
    $_SESSION['flash_message'] = $msg;
    $_SESSION['flash_type'] = $type;
}

// ========== CSV UPLOAD ==========
if (isset($_POST['upload'])) {
    if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] == 0) {
        $file = $_FILES['csv_file']['tmp_name'];
        $handle = fopen($file, "r");
        $inserted = 0;
        $errors = 0;

        if (isset($_POST['replace_all']) && $_POST['replace_all'] == '1') {
            $del_stmt = $conn->prepare("DELETE FROM subject_students WHERE subject_code = ? AND section = ?");
            $del_stmt->bind_param("ss", $subject_code, $correct_section);
            $del_stmt->execute();
            $del_stmt->close();
        }

        $insertStudent = $conn->prepare("INSERT IGNORE INTO students (student_id, name, section) VALUES (?, ?, ?)");
        $insertSubjectStudent = $conn->prepare("INSERT IGNORE INTO subject_students (subject_code, subject_name, section, student_id, student_name) VALUES (?, ?, ?, ?, ?)");

        while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
            if (count($data) >= 2) {
                $student_id = trim($data[0]);
                $name = trim($data[1]);

                if ($student_id === '') {
                    $errors++;
                    continue;
                }

                $insertStudent->bind_param("sss", $student_id, $name, $correct_section);
                $insertStudent->execute();

                $insertSubjectStudent->bind_param("sssss", $subject_code, $subject_name, $correct_section, $student_id, $name);
                if ($insertSubjectStudent->execute()) {
                    $inserted++;
                } else {
                    $errors++;
                }
            }
        }
        fclose($handle);
        $insertStudent->close();
        $insertSubjectStudent->close();

        if ($errors === 0) {
            setFlash("Upload complete! $inserted students added to subject.", 'success');
        } else {
            setFlash("Upload completed with some issues: $inserted added, $errors skipped.", 'error');
        }
    } else {
        setFlash("Please select a valid CSV file.", 'error');
    }
    header("Location: $redirect_url");
    exit();
}

// ========== CLEAR STUDENTS ==========
if (isset($_GET['clear_students'])) {
    $stmt = $conn->prepare("DELETE FROM subject_students WHERE subject_code = ? AND section = ?");
    $stmt->bind_param("ss", $subject_code, $correct_section);
    $stmt->execute();
    $stmt->close();
    setFlash("All students removed from this subject.", 'success');
    header("Location: $redirect_url");
    exit();
}

// ========== SAVE ATTENDANCE ==========
if (isset($_POST['save_attendance'])) {
    $count_stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM subject_students WHERE subject_code = ? AND section = ?");
    $count_stmt->bind_param("ss", $subject_code, $correct_section);
    $count_stmt->execute();
    $enrolled_count = $count_stmt->get_result()->fetch_assoc()['cnt'] ?? 0;
    $count_stmt->close();

    if ($enrolled_count == 0) {
        setFlash("No students are enrolled in this subject. Please upload a CSV first.", 'error');
        header("Location: $redirect_url");
        exit();
    }

    $saved = 0;
    $errors = 0;

    if (isset($_POST['attendance']) && is_array($_POST['attendance'])) {
        $att_stmt = $conn->prepare("
            INSERT INTO attendance (student_id, attendance_date, status, subject_code, subject_name, section)
            VALUES (?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE status = VALUES(status)
        ");

        foreach ($_POST['attendance'] as $student_id => $status) {
            $statusText = ($status == 'present') ? 'Present' : (($status == 'late') ? 'Late' : 'Absent');
            $att_stmt->bind_param("ssssss", $student_id, $today, $statusText, $subject_code, $subject_name, $correct_section);
            if ($att_stmt->execute()) {
                $saved++;
            } else {
                $errors++;
            }
        }
        $att_stmt->close();
    }

    if ($errors === 0) {
        setFlash("Attendance saved! $saved records for $today.", 'success');
        header("Location: attendance.php");
    } else {
        setFlash("Attendance saved with some issues: $saved saved, $errors failed.", 'error');
        header("Location: $redirect_url");
    }
    exit();
}

// ========== FLASH MESSAGE (shown as a toast, not a popup) ==========
$flash_message = $_SESSION['flash_message'] ?? '';
$flash_type = $_SESSION['flash_type'] ?? 'success';
unset($_SESSION['flash_message'], $_SESSION['flash_type']);

// ========== KUNIN ANG LISTAHAN NG MGA ESTUDYANTE ==========
$list_stmt = $conn->prepare("SELECT * FROM subject_students WHERE subject_code = ? AND section = ? ORDER BY student_name");
$list_stmt->bind_param("ss", $subject_code, $correct_section);
$list_stmt->execute();
$students = $list_stmt->get_result();

// Kunin ang attendance ngayong araw para sa subject na ito
$attToday = [];
$att_today_stmt = $conn->prepare("SELECT student_id, status FROM attendance WHERE attendance_date = ? AND subject_code = ? AND section = ?");
$att_today_stmt->bind_param("sss", $today, $subject_code, $correct_section);
$att_today_stmt->execute();
$att_today_result = $att_today_stmt->get_result();
if ($att_today_result) {
    while ($row = $att_today_result->fetch_assoc()) {
        $attToday[$row['student_id']] = $row['status'];
    }
}
$att_today_stmt->close();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Take Attendance - <?php echo htmlspecialchars($subject_name); ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Arial, sans-serif; background: #f4f4f4; padding: 30px 20px; }
        .attendance-container { max-width: 1200px; margin: 0 auto; background: white; border-radius: 24px; box-shadow: 0 10px 30px rgba(0,0,0,0.1); overflow: hidden; }
        .header-section { background: linear-gradient(135deg, #004d00, #006d1f); padding: 1.5rem 2rem; border-bottom: 3px solid #f5c542; }
        .header-section h1 { color: white; font-size: 1.8rem; display: flex; align-items: center; gap: 12px; }
        .subject-info { background: rgba(255,255,255,0.1); padding: 8px 15px; border-radius: 30px; margin-top: 10px; display: inline-block; color: #f5c542; }
        .action-bar { display: flex; justify-content: space-between; align-items: center; padding: 1rem 2rem; background: #f9fbf9; border-bottom: 1px solid #e2eee9; flex-wrap: wrap; gap: 15px; }
        .info-wrapper { background: #eef3f0; padding: 0.5rem 1.2rem; border-radius: 40px; display: inline-flex; align-items: center; gap: 10px; }
        .btn-group { display: flex; gap: 12px; }
        .btn { padding: 0.5rem 1.2rem; border-radius: 40px; font-weight: 600; font-size: 0.85rem; border: none; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; transition: 0.2s; }
        .btn-warning { background: #ffc107; color: #333; }
        .btn-warning:hover { background: #e0a800; }
        .btn-secondary { background: #6c757d; color: white; }
        .btn-secondary:hover { background: #5a6268; }
        .btn-danger { background: #dc3545; color: white; }
        .btn-danger:hover { background: #c82333; }
        .table-wrapper { overflow-x: auto; padding: 1.5rem 2rem; }
        .attendance-table { width: 100%; border-collapse: collapse; }
        .attendance-table th { text-align: left; padding: 1rem; background: #eef3f0; color: #004d00; font-weight: 700; }
        .attendance-table td { padding: 1rem; border-bottom: 1px solid #e9efec; color: #2b4b3f; }
        .attendance-select { padding: 8px 12px; border-radius: 8px; border: 1px solid #cde0d9; background: white; cursor: pointer; }
        .save-section { padding: 1rem 2rem 2rem; text-align: right; background: white; border-top: 1px solid #e2eee9; }
        .save-btn { background: #004d00; color: white; padding: 0.8rem 2rem; border-radius: 48px; font-weight: 700; border: none; cursor: pointer; display: inline-flex; align-items: center; gap: 8px; }
        .save-btn:hover { background: #006d1f; }
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); align-items: center; justify-content: center; z-index: 2000; }
        .modal-content { background: white; max-width: 500px; width: 90%; border-radius: 24px; padding: 1.8rem; box-shadow: 0 20px 40px rgba(0,0,0,0.2); }
        .modal-content h3 { color: #004d00; margin-bottom: 1rem; display: flex; align-items: center; gap: 10px; }
        .modal-content input[type="file"] { width: 100%; padding: 12px; border: 1px solid #cde0d9; border-radius: 12px; margin: 1rem 0; background: #f9fbf9; }
        .csv-format { background: #eef3f0; padding: 12px; border-radius: 12px; font-size: 12px; margin: 1rem 0; border-left: 4px solid #004d00; }
        .replace-checkbox { margin: 15px 0; display: flex; align-items: center; gap: 8px; }
        .modal-buttons { display: flex; gap: 12px; justify-content: flex-end; margin-top: 20px; }
        .modal-buttons button { padding: 8px 20px; border-radius: 40px; border: none; cursor: pointer; font-weight: 600; }
        .modal-buttons button:first-child { background: #6c757d; color: white; }
        .modal-buttons button:last-child { background: #28a745; color: white; }
        @media (max-width: 700px) { .action-bar { flex-direction: column; align-items: stretch; } .btn-group { justify-content: flex-end; } }

        /* ===== TOAST NOTIFICATIONS (replaces alert()) ===== */
        #toastContainer { position: fixed; top: 20px; right: 20px; z-index: 3000; display: flex; flex-direction: column; gap: 10px; max-width: 340px; }
        .toast { background: white; border-radius: 12px; padding: 14px 18px; box-shadow: 0 8px 24px rgba(0,0,0,0.15); display: flex; align-items: flex-start; gap: 10px; font-size: 13px; font-weight: 600; border-left: 4px solid #ccc; animation: toastIn 0.25s ease; }
        .toast.success { border-left-color: #28a745; color: #1A1A1A; }
        .toast.success i { color: #28a745; }
        .toast.error { border-left-color: #dc3545; color: #1A1A1A; }
        .toast.error i { color: #dc3545; }
        .toast .toast-close { margin-left: auto; cursor: pointer; color: #999; font-weight: bold; }
        @keyframes toastIn { from { opacity: 0; transform: translateX(30px); } to { opacity: 1; transform: translateX(0); } }
        @keyframes toastOut { from { opacity: 1; } to { opacity: 0; transform: translateX(30px); } }

        /* ===== GENERIC CONFIRM MODAL (replaces confirm()) ===== */
        .confirm-modal { display: none; position: fixed; inset: 0; background: rgba(0,0,0,.5); z-index: 3000; justify-content: center; align-items: center; }
        .confirm-modal-content { background: white; border-radius: 20px; width: 90%; max-width: 400px; padding: 25px; text-align: center; }
        .confirm-modal-content h3 { color: #dc3545; margin-bottom: 15px; display: flex; align-items: center; justify-content: center; gap: 8px; }
        .confirm-buttons { display: flex; gap: 10px; justify-content: center; margin-top: 20px; }
        .btn-confirm-yes, .btn-confirm-no { padding: 9px 20px; border: none; border-radius: 10px; cursor: pointer; font-weight: 600; }
        .btn-confirm-no { background: #6c757d; color: white; }
        .btn-confirm-yes { background: #dc3545; color: white; }
    </style>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>

<div id="toastContainer"></div>

<div class="attendance-container">
    <div class="header-section">
        <h1><i class="fas fa-calendar-check"></i> Take Attendance</h1>
        <div class="subject-info">
            <i class="fas fa-book"></i> <?php echo htmlspecialchars($subject_code); ?> - <?php echo htmlspecialchars($subject_name); ?>
            | <i class="fas fa-users"></i> Section: <?php echo htmlspecialchars($correct_section); ?>
        </div>
    </div>

    <div class="action-bar">
        <div class="info-wrapper">
            <i class="fas fa-calendar-alt"></i> <?php echo date('F d, Y'); ?>
            <i class="fas fa-user-graduate" style="margin-left: 10px;"></i> Enrolled: <?php echo $students->num_rows; ?>
        </div>
        <div class="btn-group">
            <a href="?subject_code=<?php echo urlencode($subject_code); ?>&subject_name=<?php echo urlencode($subject_name); ?>&section=<?php echo urlencode($correct_section); ?>&clear_students=1" class="btn btn-danger" id="clearAllLink">
                <i class="fas fa-trash-alt"></i> Clear All
            </a>
            <button class="btn btn-warning" onclick="showUploadModal()">
                <i class="fas fa-upload"></i> Upload Students
            </button>
            <a href="attendance.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back
            </a>
        </div>
    </div>

    <div class="table-wrapper">
        <form method="POST" id="attendanceForm">
            <table class="attendance-table">
                <thead>
                    <tr>
                        <th>Student ID</th>
                        <th>Student Name</th>
                        <th>Section</th>
                        <th>Attendance</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($students && $students->num_rows > 0): ?>
                        <?php while ($row = $students->fetch_assoc()):
                            $current = $attToday[$row['student_id']] ?? '';
                        ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($row['student_id']); ?></strong></td>
                            <td><?php echo htmlspecialchars($row['student_name']); ?></td>
                            <td><?php echo htmlspecialchars($correct_section); ?></td>
                            <td>
                                <select name="attendance[<?php echo htmlspecialchars($row['student_id']); ?>]" class="attendance-select">
                                    <option value="present" <?php echo $current == 'Present' ? 'selected' : ''; ?>>✅ Present</option>
                                    <option value="late" <?php echo $current == 'Late' ? 'selected' : ''; ?>>⏰ Late</option>
                                    <option value="absent" <?php echo $current == 'Absent' ? 'selected' : ''; ?>>❌ Absent</option>
                                </select>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="4" style="text-align:center; padding: 2rem;">No students found. Click "Upload Students" to add students.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </form>
    </div>

    <div class="save-section">
        <button type="submit" form="attendanceForm" name="save_attendance" class="save-btn">
            <i class="fas fa-save"></i> Save Attendance
        </button>
    </div>
</div>

<div id="uploadModal" class="modal">
    <div class="modal-content">
        <h3><i class="fas fa-file-csv"></i> Upload Students</h3>
        <form method="POST" enctype="multipart/form-data">
            <input type="file" name="csv_file" accept=".csv" required>
            <div class="replace-checkbox">
                <input type="checkbox" name="replace_all" value="1" id="replace_all">
                <label for="replace_all"><i class="fas fa-exclamation-triangle"></i> Replace all existing students</label>
            </div>
            <div class="csv-format">
                <strong><i class="fas fa-info-circle"></i> CSV Format:</strong><br>
                Format: <code>student_id, name</code><br>
                Example: <code>23-0003, Juan Dela Cruz</code>
            </div>
            <div class="modal-buttons">
                <button type="button" onclick="hideUploadModal()">Cancel</button>
                <button type="submit" name="upload">Upload</button>
            </div>
        </form>
    </div>
</div>

<!-- GENERIC CONFIRM MODAL -->
<div id="confirmModal" class="confirm-modal">
    <div class="confirm-modal-content">
        <h3><i class="fas fa-exclamation-triangle"></i> <span id="confirmModalTitle">Please Confirm</span></h3>
        <p id="confirmModalMessage">Are you sure?</p>
        <div class="confirm-buttons">
            <button type="button" class="btn-confirm-no" id="confirmModalCancelBtn">Cancel</button>
            <button type="button" class="btn-confirm-yes" id="confirmModalYesBtn">Yes, Proceed</button>
        </div>
    </div>
</div>

<script>
    function showUploadModal() { document.getElementById('uploadModal').style.display = 'flex'; }
    function hideUploadModal() { document.getElementById('uploadModal').style.display = 'none'; }
    window.onclick = function(e) {
        if (e.target === document.getElementById('uploadModal')) hideUploadModal();
    };

    // ===== TOAST NOTIFICATIONS (replaces alert()) =====
    function showToast(message, type) {
        type = type === 'error' ? 'error' : 'success';
        const container = document.getElementById('toastContainer');
        const toast = document.createElement('div');
        toast.className = 'toast ' + type;
        const icon = type === 'success' ? 'fa-circle-check' : 'fa-circle-exclamation';
        toast.innerHTML = '<i class="fas ' + icon + '"></i><span></span><span class="toast-close">&times;</span>';
        toast.querySelector('span').textContent = message;
        container.appendChild(toast);

        const remove = () => {
            toast.style.animation = 'toastOut 0.25s ease forwards';
            setTimeout(() => toast.remove(), 250);
        };
        toast.querySelector('.toast-close').addEventListener('click', remove);
        setTimeout(remove, 4500);
    }

    // ===== GENERIC CONFIRM MODAL (replaces confirm()) =====
    function showConfirm(title, message) {
        return new Promise((resolve) => {
            const modal = document.getElementById('confirmModal');
            document.getElementById('confirmModalTitle').textContent = title;
            document.getElementById('confirmModalMessage').textContent = message;
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

    // ===== CLEAR ALL: confirm before navigating =====
    const clearAllLink = document.getElementById('clearAllLink');
    if (clearAllLink) {
        clearAllLink.addEventListener('click', async function(event) {
            event.preventDefault();
            const confirmed = await showConfirm('Confirm Clear All', 'Remove all students from this subject?');
            if (confirmed) {
                window.location.href = clearAllLink.href;
            }
        });
    }

    // ===== Show flash message (from PHP) as a toast on page load =====
    <?php if (!empty($flash_message)): ?>
        showToast(<?php echo json_encode($flash_message); ?>, <?php echo json_encode($flash_type); ?>);
    <?php endif; ?>
</script>
</body>
</html>