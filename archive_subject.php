<?php
// =====================================================
// archive_subject.php
// Toggles a subject's is_archived flag (archive / unarchive).
// Called via fetch() from attendance.php — always returns JSON.
// =====================================================

session_start();
header('Content-Type: application/json');
require_once 'connect.php';

/** @var mysqli $conn */

if (!isset($_SESSION['id_number'])) {
    echo json_encode(['success' => false, 'message' => 'You must be logged in to do this.']);
    exit();
}

$subject_code = isset($_POST['subject_code']) ? trim($_POST['subject_code']) : '';
$section      = isset($_POST['section']) ? trim($_POST['section']) : '';
$action       = isset($_POST['action']) ? trim($_POST['action']) : '';

if ($subject_code === '' || $section === '' || !in_array($action, ['archive', 'unarchive'], true)) {
    echo json_encode(['success' => false, 'message' => 'Missing or invalid subject information.']);
    exit();
}

$is_archived = ($action === 'archive') ? 1 : 0;

$stmt = $conn->prepare("UPDATE subjects SET is_archived = ? WHERE subject_code = ? AND section = ?");
if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . mysqli_error($conn)]);
    exit();
}

$stmt->bind_param("iss", $is_archived, $subject_code, $section);

if ($stmt->execute()) {
    if ($stmt->affected_rows > 0) {
        $msg = ($action === 'archive')
            ? "\"$subject_code\" ($section) has been archived."
            : "\"$subject_code\" ($section) has been restored.";
        echo json_encode(['success' => true, 'message' => $msg]);
    } else {
        // Query ran fine but matched no row, OR the value was already set — check which.
        $check = $conn->prepare("SELECT is_archived FROM subjects WHERE subject_code = ? AND section = ?");
        $check->bind_param("ss", $subject_code, $section);
        $check->execute();
        $res = $check->get_result();
        if ($res && $res->num_rows > 0) {
            // Row exists, value was already what we wanted to set it to.
            $msg = ($action === 'archive') ? 'Subject is already archived.' : 'Subject is already active.';
            echo json_encode(['success' => true, 'message' => $msg]);
        } else {
            echo json_encode(['success' => false, 'message' => 'No matching subject was found for that code and section.']);
        }
        $check->close();
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to update subject: ' . $stmt->error]);
}

$stmt->close();
$conn->close();