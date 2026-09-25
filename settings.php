<?php
session_start();
require_once 'connect.php';

if (!isset($_SESSION['id_number'])) {
    header("Location: login.php");
    exit();
}

$id_number = $_SESSION['id_number'];
$user_name = $_SESSION['name'] ?? '';

$user_sql = "SELECT * FROM users WHERE id_number = ?";
$user_stmt = $conn->prepare($user_sql);
$user_stmt->bind_param("s", $id_number);
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

    $verify_sql = "SELECT password FROM users WHERE id_number = ?";
    $verify_stmt = $conn->prepare($verify_sql);
    $verify_stmt->bind_param("s", $id_number);
    $verify_stmt->execute();
    $verify_result = $verify_stmt->get_result();
    $verify_data = $verify_result->fetch_assoc();
    $verify_stmt->close();

    if ($verify_data['password'] !== $current_password) {
        $update_error = "Current password is incorrect!";
    } else {
        if (!empty($new_password)) {
            if ($new_password !== $confirm_password) {
                $update_error = "New passwords do not match!";
            } else {
                $update_sql = "UPDATE users SET name = ?, email = ?, password = ? WHERE id_number = ?";
                $update_stmt = $conn->prepare($update_sql);
                $update_stmt->bind_param("ssss", $new_name, $new_email, $new_password, $id_number);
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
            $update_stmt->bind_param("sss", $new_name, $new_email, $id_number);
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

if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: login.php");
    exit();
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Settings - CCA TRACS</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        /* ===== ROOT VARIABLES - BERDE ===== */
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
            --font: 'Segoe UI', Arial, sans-serif;
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
        /* SIDEBAR - BERDE */
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
        .header h1 { color: white; font-size: 1.8rem; display: flex; align-items: center; gap: 12px; }
        .header h1 i { color: var(--gold); }
        .user-name {
            background: rgba(255,255,255,0.15);
            color: white;
            padding: 8px 22px;
            border-radius: 30px;
            font-weight: 600;
            border: 1px solid rgba(255,255,255,0.1);
        }
        .settings-container {
            background: var(--card-bg);
            border-radius: 0 0 var(--radius) var(--radius);
            padding: 25px;
            box-shadow: var(--shadow);
        }
        .settings-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 25px;
            margin-top: 20px;
        }
        .settings-card {
            background: var(--bg);
            border-radius: var(--radius-sm);
            padding: 20px;
            border: 1px solid var(--border);
        }
        .settings-card h3 {
            color: var(--primary);
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid var(--primary);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .info-row {
            display: flex;
            padding: 10px 0;
            border-bottom: 1px solid var(--border);
        }
        .info-label { width: 130px; font-weight: 600; color: var(--text-muted); }
        .info-value { flex: 1; color: var(--text); }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: 600; color: var(--text-muted); }
        .form-group input { width: 100%; padding: 10px; border: 1px solid var(--border); border-radius: var(--radius-sm); font-size: 14px; }
        .form-group input:focus { outline: none; border-color: var(--primary); }
        .btn {
            background: var(--primary);
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: var(--radius-sm);
            cursor: pointer;
            font-weight: 600;
            transition: 0.2s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .btn:hover { background: var(--primary-dark); transform: translateY(-2px); }
        .btn-secondary { background: #6c757d; }
        .btn-secondary:hover { background: #5a6268; }
        .btn-danger { background: var(--danger); }
        .btn-danger:hover { background: #8B1A2B; }
        .message-success {
            background: #d4edda;
            color: #155724;
            padding: 12px;
            border-radius: var(--radius-sm);
            margin-bottom: 20px;
            border-left: 4px solid #28a745;
        }
        .message-error {
            background: #f8d7da;
            color: #721c24;
            padding: 12px;
            border-radius: var(--radius-sm);
            margin-bottom: 20px;
            border-left: 4px solid var(--danger);
        }
        @media (max-width: 768px) {
            .sidebar { width: 90px; }
            .main-content { margin-left: 90px; padding: 15px; }
            .settings-grid { grid-template-columns: 1fr; }
            .info-row { flex-direction: column; }
            .info-label { width: 100%; margin-bottom: 5px; }
            .header { flex-direction: column; text-align: center; }
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
            background: var(--card-bg);
            border-radius: var(--radius);
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
            background: var(--primary-gradient);
            padding: 18px 22px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .modal-header i {
            color: var(--gold);
            font-size: 1.3rem;
        }
        .modal-header h4 {
            color: white;
            font-size: 1.1rem;
        }
        .modal-body {
            padding: 22px;
            color: var(--text);
            font-size: 14.5px;
            line-height: 1.5;
        }
        .modal-footer {
            padding: 16px 22px;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            background: var(--bg);
            border-top: 1px solid var(--border);
        }
        .modal-footer .btn {
            padding: 9px 18px;
            font-size: 14px;
        }
    </style>
</head>
<body>

    <!-- SIDEBAR -->
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
            <div class="nav-item">
                <a href="grades.php">
                    <img src="grades.png" alt="GRADES">
                    <span>Grades</span>
                </a>
            </div>
            <div class="nav-item active">
                <a href="settings.php">
                    <img src="settings.png" alt="SETTINGS">
                    <span>Settings</span>
                </a>
            </div>
        </div>
    </div>

    <!-- MAIN CONTENT -->
    <div class="main-content">
        <div class="header">
            <h1><i class="fas fa-cog"></i> Settings</h1>
            <div class="user-name"><i class="fas fa-user"></i> <?php echo htmlspecialchars($_SESSION['name'] ?? 'Professor'); ?></div>
        </div>
        <div class="settings-container">
            <div style="margin-bottom:20px;">
                <a href="prof_dashboard.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
            </div>

            <?php if ($update_message): ?>
                <div class="message-success"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($update_message); ?></div>
            <?php endif; ?>
            <?php if ($update_error): ?>
                <div class="message-error"><i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($update_error); ?></div>
            <?php endif; ?>

            <div class="settings-grid">
                <div class="settings-card">
                    <h3><i class="fas fa-user-circle"></i> Profile Settings</h3>
                    <form method="POST">
                        <div class="form-group">
                            <label><i class="fas fa-id-card"></i> ID Number</label>
                            <input type="text" value="<?php echo htmlspecialchars($id_number); ?>" disabled style="background:#e9ecef;">
                        </div>
                        <div class="form-group">
                            <label><i class="fas fa-user"></i> Full Name</label>
                            <input type="text" name="name" value="<?php echo htmlspecialchars($user_data['name'] ?? $user_name); ?>" required>
                        </div>
                        <div class="form-group">
                            <label><i class="fas fa-envelope"></i> Email</label>
                            <input type="email" name="email" value="<?php echo htmlspecialchars($user_data['email'] ?? ''); ?>" placeholder="your.email@example.com">
                        </div>
                        <div class="form-group">
                            <label><i class="fas fa-lock"></i> Current Password</label>
                            <input type="password" name="current_password" placeholder="Enter current password to make changes">
                        </div>
                        <div class="form-group">
                            <label><i class="fas fa-key"></i> New Password</label>
                            <input type="password" name="new_password" placeholder="Leave blank to keep current">
                        </div>
                        <div class="form-group">
                            <label><i class="fas fa-check-circle"></i> Confirm New Password</label>
                            <input type="password" name="confirm_password" placeholder="Confirm new password">
                        </div>
                        <button type="submit" name="update_profile" class="btn"><i class="fas fa-save"></i> Update Profile</button>
                    </form>
                </div>

                <div class="settings-card">
                    <h3><i class="fas fa-info-circle"></i> Account Info</h3>
                    <div class="info-row">
                        <div class="info-label"><i class="fas fa-user-tag"></i> Role:</div>
                        <div class="info-value"><span style="background:var(--primary);color:white;padding:3px 12px;border-radius:20px;font-size:12px;">Professor</span></div>
                    </div>
                    <div class="info-row">
                        <div class="info-label"><i class="fas fa-calendar-alt"></i> Member Since:</div>
                        <div class="info-value"><?php echo date('F d, Y'); ?></div>
                    </div>
                    <div style="margin-top:20px;border-top:1px solid var(--border);padding-top:20px;">
                        <button type="button" class="btn btn-danger" onclick="openLogoutModal()"><i class="fas fa-sign-out-alt"></i> Logout</button>
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
            window.location.href = '?logout=1';
        }
        // Close modal kapag nag-click sa labas ng box
        document.getElementById('logoutModal').addEventListener('click', function(e) {
            if (e.target === this) closeLogoutModal();
        });
        // Close modal gamit ang ESC key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') closeLogoutModal();
        });
    </script>
</body>
</html>