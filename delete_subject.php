<?php
// =====================================================
// delete_subject.php
// TOTALIS HUMANAE
// PERMANENT SUBJECT DELETE
// =====================================================

session_start();

require_once 'connect.php';

header('Content-Type: application/json; charset=UTF-8');


// =====================================================
// CHECK LOGIN
// =====================================================

if (!isset($_SESSION['id_number'])) {

    echo json_encode([
        'success' => false,
        'message' => 'You are not logged in.'
    ]);

    exit();
}


// =====================================================
// ONLY POST REQUEST
// =====================================================

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method.'
    ]);

    exit();
}


// =====================================================
// GET DATA
// =====================================================

$subject_code =
    isset($_POST['subject_code'])
        ? trim($_POST['subject_code'])
        : '';

$subject_name =
    isset($_POST['subject_name'])
        ? trim($_POST['subject_name'])
        : '';

$section =
    isset($_POST['section'])
        ? trim($_POST['section'])
        : '';


// =====================================================
// VALIDATE
// =====================================================

if (
    $subject_code === '' ||
    $section === ''
) {

    echo json_encode([
        'success' => false,
        'message' => 'Subject code and section are required.'
    ]);

    exit();
}


// =====================================================
// START TRANSACTION
// =====================================================

$conn->begin_transaction();


try {

    // =================================================
    // 1. DELETE STUDENT ATTENDANCE
    // =================================================

    $attendance_sql = "
        DELETE FROM attendance
        WHERE subject_code = ?
        AND section = ?
    ";

    $attendance_stmt =
        $conn->prepare($attendance_sql);


    if (!$attendance_stmt) {

        throw new Exception(
            'Unable to prepare attendance deletion.'
        );
    }


    $attendance_stmt->bind_param(
        "ss",
        $subject_code,
        $section
    );


    if (!$attendance_stmt->execute()) {

        throw new Exception(
            'Unable to delete attendance records.'
        );
    }


    $attendance_stmt->close();


    // =================================================
    // 2. DELETE STUDENT ENROLLMENTS
    // =================================================

    $students_sql = "
        DELETE FROM subject_students
        WHERE subject_code = ?
        AND section = ?
    ";

    $students_stmt =
        $conn->prepare($students_sql);


    if (!$students_stmt) {

        throw new Exception(
            'Unable to prepare student record deletion.'
        );
    }


    $students_stmt->bind_param(
        "ss",
        $subject_code,
        $section
    );


    if (!$students_stmt->execute()) {

        throw new Exception(
            'Unable to delete enrolled student records.'
        );
    }


    $students_stmt->close();


    // =================================================
    // 3. DELETE SUBJECT
    // =================================================

    $subject_sql = "
        DELETE FROM subjects
        WHERE subject_code = ?
        AND section = ?
    ";

    $subject_stmt =
        $conn->prepare($subject_sql);


    if (!$subject_stmt) {

        throw new Exception(
            'Unable to prepare subject deletion.'
        );
    }


    $subject_stmt->bind_param(
        "ss",
        $subject_code,
        $section
    );


    if (!$subject_stmt->execute()) {

        throw new Exception(
            'Unable to delete subject.'
        );
    }


    $deleted_rows =
        $subject_stmt->affected_rows;


    $subject_stmt->close();


    // =================================================
    // CHECK IF SUBJECT WAS ACTUALLY DELETED
    // =================================================

    if ($deleted_rows <= 0) {

        throw new Exception(
            'Subject was not found or was already deleted.'
        );
    }


    // =================================================
    // COMMIT
    // =================================================

    $conn->commit();


    echo json_encode([
        'success' => true,
        'message' =>
            'Subject "' .
            $subject_code .
            ' - ' .
            $subject_name .
            '" was permanently deleted.'
    ]);

    exit();


} catch (Exception $e) {

    // =================================================
    // ROLLBACK
    // =================================================

    $conn->rollback();


    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);

    exit();
}
?>