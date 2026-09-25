<?php
session_start();
require_once 'connect.php';

if (!isset($_SESSION['id_number']) || $_SESSION['role'] !== 'dean') {
    header("Location: login.php");
    exit();
}

$dean_id = $_SESSION['id_number'];
$dean_name = $_SESSION['name'];

// Fetch dean data from users table
$user_sql = "SELECT * FROM users WHERE id_number = ?";
$user_stmt = $conn->prepare($user_sql);
$user_stmt->bind_param("s", $dean_id);
$user_stmt->execute();
$user_result = $user_stmt->get_result();
$user_data = $user_result->fetch_assoc();
$user_stmt->close();

$update_message = '';
$update_error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_profile'])) {
    $new_name = $conn->real_escape_string($_POST['name']);
    $new_email = $conn->real_escape_string($_POST['email']);
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Verify current password
    $verify_sql = "SELECT password FROM users WHERE id_number = ?";
    $verify_stmt = $conn->prepare($verify_sql);
    $verify_stmt->bind_param("s", $dean_id);
    $verify_stmt->execute();
    $verify_result = $verify_stmt->get_result();
    $verify_data = $verify_result->fetch_assoc();
    $verify_stmt->close();
    
    $password_valid = false;
    if (password_verify($current_password, $verify_data['password'])) {
        $password_valid = true;
    } elseif ($current_password === $verify_data['password']) {
        $password_valid = true; // fallback for plain text
    }
    
    if (!$password_valid) {
        $update_error = "Current password is incorrect!";
    } else {
        if (!empty($new_password)) {
            if ($new_password !== $confirm_password) {
                $update_error = "New passwords do not match!";
            } elseif (strlen($new_password) < 6) {
                $update_error = "New password must be at least 6 characters.";
            } else {
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $update_sql = "UPDATE users SET name = ?, email = ?, password = ? WHERE id_number = ?";
                $update_stmt = $conn->prepare($update_sql);
                $update_stmt->bind_param("ssss", $new_name, $new_email, $hashed_password, $dean_id);
                if ($update_stmt->execute()) {
                    $_SESSION['name'] = $new_name;
                    $update_message = "Profile updated successfully!";
                    $user_data['name'] = $new_name;
                    $user_data['email'] = $new_email;
                } else {
                    $update_error = "Failed to update profile!";
                }
                $update_stmt->close();
            }
        } else {
            $update_sql = "UPDATE users SET name = ?, email = ? WHERE id_number = ?";
            $update_stmt = $conn->prepare($update_sql);
            $update_stmt->bind_param("sss", $new_name, $new_email, $dean_id);
            if ($update_stmt->execute()) {
                $_SESSION['name'] = $new_name;
                $update_message = "Profile updated successfully!";
                $user_data['name'] = $new_name;
                $user_data['email'] = $new_email;
            } else {
                $update_error = "Failed to update profile!";
            }
            $update_stmt->close();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dean Settings - CCA TRACS</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            display: flex;
            background-color: #f0f4f2;
        }
        body::before {
            content: "";
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-image: url('cca.webp');
            background-size: cover;
            background-position: center;
            opacity: 0.3;
            z-index: -1;
        }
        
        .sidebar {
            width: 120px;
            background: linear-gradient(180deg, #004d00 0%, #003300 100%);
            height: 100vh;
            position: fixed;
            display: flex;
            flex-direction: column;
            align-items: center;
            padding-top: 30px;
            box-shadow: 5px 0 20px rgba(0,0,0,0.15);
            z-index: 100;
        }
        .sidebar .logo {
            width: 70px;
            margin-bottom: 40px;
            padding: 10px;
            background: rgba(255,255,255,0.1);
            border-radius: 50%;
            transition: all 0.3s;
        }
        .sidebar .logo:hover {
            transform: scale(1.05);
            background: rgba(255,255,255,0.2);
        }
        .sidebar .nav-icons {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 25px;
            flex: 1;
        }
        .sidebar .nav-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 5px;
            cursor: pointer;
            transition: all 0.2s;
            position: relative;
        }
        .sidebar .nav-item a {
            text-decoration: none;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 5px;
        }
        .sidebar .nav-item img {
            width: 45px;
            height: 45px;
            object-fit: contain;
            transition: transform 0.2s;
            filter: brightness(0) invert(1);
            opacity: 0.8;
        }
        .sidebar .nav-item span {
            color: rgba(255,255,255,0.8);
            font-size: 11px;
            font-weight: 500;
            letter-spacing: 0.5px;
        }
        .sidebar .nav-item:hover img {
            transform: translateY(-3px);
            opacity: 1;
        }
        .sidebar .nav-item:hover span {
            color: white;
        }
        .sidebar .nav-item.active img {
            opacity: 1;
            transform: scale(1.1);
        }
        .sidebar .nav-item.active span {
            color: white;
            font-weight: bold;
        }
        .sidebar .nav-item.active::before {
            content: '';
            position: absolute;
            left: -20px;
            top: 10px;
            width: 4px;
            height: 30px;
            background: #ffc107;
            border-radius: 0 4px 4px 0;
        }
        
        .main-content {
            margin-left: 120px;
            padding: 25px;
            width: calc(100% - 120px);
        }
        .header {
            background: linear-gradient(135deg, #004d00, #006d1f);
            padding: 1.4rem 2rem;
            border-radius: 1rem 1rem 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0;
        }
        .header h1 {
            color: white;
            font-size: 1.9rem;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .user-name {
            background-color: rgba(255,255,255,0.2);
            color: white;
            padding: 8px 20px;
            border-radius: 20px;
            font-weight: bold;
        }
        .settings-container {
            background: white;
            border-radius: 0 0 1rem 1rem;
            padding: 25px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        .settings-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 25px;
            margin-top: 20px;
        }
        .settings-card {
            background: #f9fbf9;
            border-radius: 16px;
            padding: 20px;
            border: 1px solid #e2eee9;
        }
        .settings-card h3 {
            color: #004d00;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #004d00;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .profile-info { margin-bottom: 20px; }
        .info-row {
            display: flex;
            padding: 10px 0;
            border-bottom: 1px solid #e2eee9;
        }
        .info-label {
            width: 130px;
            font-weight: 600;
            color: #1a3a2f;
        }
        .info-value {
            flex: 1;
            color: #2b4b3f;
        }
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
            color: #1a3a2f;
        }
        .form-group input {
            width: 100%;
            padding: 10px;
            border: 1px solid #cde0d9;
            border-radius: 8px;
            font-size: 14px;
            font-family: inherit;
        }
        .form-group input:focus {
            outline: none;
            border-color: #004d00;
        }
        .btn {
            background-color: #004d00;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.2s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .btn:hover {
            background-color: #006d1f;
            transform: translateY(-1px);
        }
        .btn-danger {
            background-color: #dc3545;
        }
        .btn-danger:hover {
            background-color: #c82333;
        }
        .btn-secondary {
            background-color: #6c757d;
        }
        .btn-secondary:hover {
            background-color: #5a6268;
        }
        .message-success {
            background: #d4edda;
            color: #155724;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #28a745;
        }
        .message-error {
            background: #f8d7da;
            color: #721c24;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #dc3545;
        }
        @media (max-width: 768px) {
            .sidebar { width: 90px; }
            .main-content { margin-left: 90px; padding: 15px; }
            .sidebar .nav-item img { width: 35px; height: 35px; }
            .sidebar .nav-item span { font-size: 9px; }
            .settings-grid { grid-template-columns: 1fr; }
            .info-row { flex-direction: column; }
            .info-label { width: 100%; margin-bottom: 5px; }
            .header { flex-direction: column; gap: 15px; text-align: center; }
            .header h1 { font-size: 1.5rem; }
        }

        /* ===== CUSTOM LOGOUT CONFIRMATION MODAL ===== */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0; left: 0;
            width: 100%; height: 100%;
            background: rgba(0, 0, 0, 0.55);
            z-index: 1000;
            justify-content: center;
            align-items: center;
            animation: fadeIn 0.2s ease;
        }
        .modal-overlay.active { display: flex; }
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        .modal-box {
            background: white;
            border-radius: 16px;
            width: 100%;
            max-width: 380px;
            box-shadow: 0 15px 45px rgba(0,0,0,0.25);
            overflow: hidden;
            animation: popIn 0.2s ease;
        }
        @keyframes popIn {
            from { transform: scale(0.92); opacity: 0; }
            to { transform: scale(1); opacity: 1; }
        }
        .modal-header {
            background: linear-gradient(135deg, #004d00, #006d1f);
            padding: 18px 22px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .modal-header i {
            color: #ffc107;
            font-size: 1.3rem;
        }
        .modal-header h4 {
            color: white;
            font-size: 1.1rem;
        }
        .modal-body {
            padding: 22px;
            color: #1a3a2f;
            font-size: 14.5px;
            line-height: 1.5;
        }
        .modal-footer {
            padding: 16px 22px;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            background: #f9fbf9;
            border-top: 1px solid #e2eee9;
        }
        .modal-footer .btn {
            padding: 9px 18px;
            font-size: 14px;
        }
    </style>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>
    <div class="sidebar">
        <img src="logo1.png" alt="LOGO" class="logo">
        <div class="nav-icons">
            <div class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'dean_dashboard.php' ? 'active' : ''; ?>">
                <a href="dean_dashboard.php">
                    <img src="dashboard.png" alt="DASHBOARD">
                    <span>DASHBOARD</span>
                </a>
            </div>
            <div class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'dean_attendance.php' ? 'active' : ''; ?>">
                <a href="dean_attendance.php">
                    <img src="attendance.png" alt="ATTENDANCE">
                    <span>ATTENDANCE</span>
                </a>
            </div>
            <div class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'dean_grades.php' ? 'active' : ''; ?>">
                <a href="dean_grades.php">
                    <img src="grades.png" alt="GRADES">
                    <span>GRADES</span>
                </a>
            </div>
            <div class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'dean_settings.php' ? 'active' : ''; ?>">
                <a href="dean_settings.php">
                    <img src="settings.png" alt="SETTINGS">
                    <span>SETTINGS</span>
                </a>
            </div>
        </div>
    </div>

    <div class="main-content">
        <div class="header">
            <h1><i class="fas fa-cog"></i> Dean Settings</h1>
            <div class="user-name"><i class="fas fa-user"></i> <?php echo htmlspecialchars($_SESSION['name'] ?? 'Dean'); ?></div>
        </div>
        <div class="settings-container">
            <div style="margin-bottom: 20px;">
                <a href="dean_dashboard.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </a>
            </div>

            <?php if ($update_message): ?>
                <div class="message-success">
                    <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($update_message); ?>
                </div>
            <?php endif; ?>

            <?php if ($update_error): ?>
                <div class="message-error">
                    <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($update_error); ?>
                </div>
            <?php endif; ?>

            <div class="settings-grid">
                <div class="settings-card">
                    <h3><i class="fas fa-user-circle"></i> Profile Settings</h3>
                    <form method="POST" action="">
                        <div class="form-group">
                            <label><i class="fas fa-id-card"></i> Dean ID</label>
                            <input type="text" value="<?php echo htmlspecialchars($dean_id); ?>" disabled style="background: #e9ecef;">
                        </div>
                        <div class="form-group">
                            <label><i class="fas fa-user"></i> Full Name</label>
                            <input type="text" name="name" value="<?php echo htmlspecialchars($user_data['name'] ?? $dean_name); ?>" required>
                        </div>
                        <div class="form-group">
                            <label><i class="fas fa-envelope"></i> Email Address</label>
                            <input type="email" name="email" value="<?php echo htmlspecialchars($user_data['email'] ?? ''); ?>" placeholder="your.email@example.com">
                        </div>
                        <div class="form-group">
                            <label><i class="fas fa-lock"></i> Current Password</label>
                            <input type="password" name="current_password" placeholder="Enter current password to make changes">
                        </div>
                        <div class="form-group">
                            <label><i class="fas fa-key"></i> New Password (leave blank to keep current)</label>
                            <input type="password" name="new_password" placeholder="Enter new password (min. 6 characters)">
                        </div>
                        <div class="form-group">
                            <label><i class="fas fa-check-circle"></i> Confirm New Password</label>
                            <input type="password" name="confirm_password" placeholder="Confirm new password">
                        </div>
                        <button type="submit" name="update_profile" class="btn">
                            <i class="fas fa-save"></i> Update Profile
                        </button>
                    </form>
                </div>

                <div class="settings-card">
                    <h3><i class="fas fa-info-circle"></i> Account Information</h3>
                    <div class="profile-info">
                        <div class="info-row">
                            <div class="info-label"><i class="fas fa-user-tag"></i> Role:</div>
                            <div class="info-value">
                                <span style="background: #004d00; color: white; padding: 3px 10px; border-radius: 20px; font-size: 12px;">
                                    Dean / Administrator
                                </span>
                            </div>
                        </div>
                        <div class="info-row">
                            <div class="info-label"><i class="fas fa-building"></i> Department:</div>
                            <div class="info-value">Dean's Office</div>
                        </div>
                        <div class="info-row">
                            <div class="info-label"><i class="fas fa-calendar-alt"></i> Member Since:</div>
                            <div class="info-value"><?php echo date('F d, Y'); ?> (System Access)</div>
                        </div>
                    </div>

                    <div style="margin-top: 20px; border-top: 1px solid #e2eee9; padding-top: 20px;">
                        <button type="button" class="btn btn-danger" onclick="openLogoutModal()">
                            <i class="fas fa-sign-out-alt"></i> Logout
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- CUSTOM LOGOUT CONFIRMATION MODAL -->
    <div class="modal-overlay" id="logoutModal">
        <div class="modal-box">
            <div class="modal-header">
                <i class="fas fa-sign-out-alt"></i>
                <h4>Confirm Logout</h4>
            </div>
            <div class="modal-body">
                Are you sure you want to log out? You'll need to log in again to access this page.
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeLogoutModal()">Cancel</button>
                <button type="button" class="btn btn-danger" onclick="confirmLogout()">
                    <i class="fas fa-sign-out-alt"></i> Yes, Logout
                </button>
            </div>
        </div>
    </div>

    <script>
        function openLogoutModal() {
            document.getElementById('logoutModal').classList.add('active');
        }
        function closeLogoutModal() {
            document.getElementById('logoutModal').classList.remove('active');
        }
        function confirmLogout() {
            window.location.href = 'logout.php';
        }
        // Close modal when clicking outside the box
        document.getElementById('logoutModal').addEventListener('click', function(e) {
            if (e.target === this) closeLogoutModal();
        });
        // Close modal using the ESC key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') closeLogoutModal();
        });
    </script>
</body>
</html>