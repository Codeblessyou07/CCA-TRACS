<?php
// =====================================================
// generate_id.php
// Generates the next temporary ID number for a role.
//   Student             -> IS-0001, IS-0002, ...
//   Professor           -> PF-0001, ...
//   Dean                -> DN-0001, ...
//   Program Coordinator -> PC-0001, ...
//   Registrar           -> RG-0001, ...
// =====================================================

// Change these to match your database.
const ID_TABLE  = 'users';      // table where accounts are saved
const ID_COLUMN = 'id_number';  // column that stores the ID used for login

/**
 * Returns the ID prefix for a role, or null if the role is unknown.
 * Accepts "Program Coordinator", "program_coordinator", "program-coordinator", etc.
 */
function getIdPrefix(string $role): ?string
{
    $map = [
        'student'             => 'IS',
        'professor'           => 'PF',
        'prof'                => 'PF',
        'instructor'          => 'PF',
        'dean'                => 'DN',
        'program_coordinator' => 'PC',
        'program_coor'        => 'PC',
        'registrar'           => 'RG',
    ];

    $key = strtolower(trim(preg_replace('/[\s\-]+/', '_', $role)));
    return $map[$key] ?? null;
}

/**
 * Builds the next ID for the given role, e.g. IS-0001 -> IS-0002.
 * Returns null if the role is not recognized or the query fails.
 */
function generateTempId(mysqli $conn, string $role): ?string
{
    $prefix = getIdPrefix($role);
    if ($prefix === null) {
        return null;
    }

    $startPos = strlen($prefix) + 2; // skip "IS-" (prefix + dash), SQL is 1-based
    $sql = "SELECT MAX(CAST(SUBSTRING(" . ID_COLUMN . ", $startPos) AS UNSIGNED)) AS max_num
            FROM " . ID_TABLE . "
            WHERE " . ID_COLUMN . " LIKE ?";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return null;
    }

    $like = $prefix . '-%';
    $stmt->bind_param("s", $like);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $next = ((int)($row['max_num'] ?? 0)) + 1;
    return sprintf('%s-%04d', $prefix, $next);
}