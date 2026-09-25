<?php
session_start();
require_once 'connect.php';
require_once 'generate_id.php';
require_once 'mailer.php';

/** @var mysqli $conn */

$error = '';
$success = '';
$name = '';
$email = '';
$role = '';

// Only school emails can sign up. Change this to test with another domain.
$allowed_email_domain = 'cca.edu.ph';

// Roles allowed to self-register
$role_labels = [
    'student'              => 'Student',
    'professor'            => 'Professor / Instructor',
    'dean'                 => 'Dean',
    'program coordinator'  => 'Program Coordinator',
    'registrar'            => 'Registrar',
];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = trim($_POST['Name'] ?? '');
    $email = strtolower(trim($_POST['Email'] ?? ''));
    $role = strtolower(trim($_POST['Role'] ?? ''));
    $password = $_POST['Password'] ?? '';
    $confirm_password = $_POST['ConfirmPassword'] ?? '';

    if ($name === '' || $email === '' || $role === '' || $password === '') {
        $error = "Please fill out all fields.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } elseif (substr($email, -strlen('@' . $allowed_email_domain)) !== '@' . $allowed_email_domain) {
        $error = "Please use your school email (@" . $allowed_email_domain . "). Personal emails are not allowed.";
    } elseif (!array_key_exists($role, $role_labels)) {
        $error = "Please select a valid role.";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match.";
    } elseif (
        strlen($password) < 8 ||
        !preg_match('/[A-Z]/', $password) ||
        !preg_match('/[0-9]/', $password) ||
        !preg_match('/[^A-Za-z0-9]/', $password)
    ) {
        $error = "Password must be at least 8 characters and include an uppercase letter, a number, and a symbol.";
    } else {
        // One account per email
        $check_stmt = $conn->prepare("SELECT 1 FROM users WHERE email = ? LIMIT 1");
        $check_stmt->bind_param("s", $email);
        $check_stmt->execute();
        $check_stmt->store_result();
        $email_taken = $check_stmt->num_rows > 0;
        $check_stmt->close();

        if ($email_taken) {
            $error = "An account with this email already exists.";
        } else {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $inserted = false;
            $id_number = null;

            // Retry if two people were given the same ID at the same moment
            for ($attempt = 0; $attempt < 5 && !$inserted; $attempt++) {
                $id_number = generateTempId($conn, $role);
                if ($id_number === null) {
                    break;
                }

                $insert_stmt = $conn->prepare("INSERT INTO users (id_number, name, email, password, role) VALUES (?, ?, ?, ?, ?)");
                $insert_stmt->bind_param("sssss", $id_number, $name, $email, $hashed_password, $role);

                try {
                    $ok = $insert_stmt->execute();
                    $errno = $insert_stmt->errno;
                    $errmsg = $insert_stmt->error;
                } catch (mysqli_sql_exception $e) {
                    $ok = false;
                    $errno = $e->getCode();
                    $errmsg = $e->getMessage();
                }
                $insert_stmt->close();

                if ($ok) {
                    $inserted = true;
                } elseif ((int)$errno === 1062) {
                    if (stripos($errmsg, 'email') !== false) {
                        $error = "An account with this email already exists.";
                        break;
                    }
                    // otherwise: ID collision, loop again for the next ID
                } else {
                    error_log('Signup insert error: ' . $errmsg);
                    break;
                }
            }

            if ($inserted) {
                if (sendTempIdEmail($email, $name, $id_number)) {
                    $success = "Account created! Your ID Number was sent to $email. Check your inbox (and spam folder), then log in.";
                } else {
                    // Email failed: remove the account so nobody is left with an account but no ID
                    $del_stmt = $conn->prepare("DELETE FROM users WHERE id_number = ?");
                    $del_stmt->bind_param("s", $id_number);
                    $del_stmt->execute();
                    $del_stmt->close();
                    $error = "We could not send the email. Please check your email address and try again.";
                    if (MAIL_DEBUG && !empty($GLOBALS['mail_last_error'])) {
                        $error .= " [Debug: " . $GLOBALS['mail_last_error'] . "]";
                    }
                }
            } elseif ($error === '') {
                $error = "Something went wrong while creating your account. Please try again.";
            }
        }
    }

    if ($success) {
        $name = $email = $role = '';
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Sign Up - City College of Angeles</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 0;
            font-family: 'Segoe UI', Arial, sans-serif;
            display: flex;
            position: relative;
            min-height: 100vh;
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
            opacity: 0.4;
            z-index: -1;
        }

        .sidebar {
            background-color: #004d00;
            width: 300px;
            height: 100vh;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            position: fixed;
            left: 0;
            top: 0;
            box-shadow: 4px 0 20px rgba(0, 0, 0, 0.15);
        }

        .sidebar img {
            width: 180px;
            height: auto;
            margin-bottom: 20px;
        }

        .sidebar h2 {
            color: white;
            text-align: center;
            margin: 0 0 10px 0;
            font-size: 24px;
        }

        .sidebar p {
            color: #FFE6A3;
            font-size: 16px;
            font-style: italic;
        }

        .form-side {
            margin-left: 300px;
            width: calc(100% - 300px);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }

        .form-box {
            background: white;
            padding: 40px;
            border-radius: 16px;
            width: 380px;
            box-shadow: 0 20px 35px rgba(0, 0, 0, 0.2);
        }

        h2 {
            text-align: center;
            margin-bottom: 30px;
            color: #1a3a2f;
        }

        label {
            display: block;
            margin: 15px 0 5px 0;
            font-weight: 600;
            color: #1e4d42;
        }

        input, select {
            width: 100%;
            padding: 12px;
            border: 1.5px solid #ddd;
            border-radius: 8px;
            box-sizing: border-box;
            font-size: 14px;
            font-family: inherit;
            background: white;
        }

        input:focus, select:focus {
            border-color: #004d00;
            outline: none;
        }

        button {
            width: 100%;
            padding: 12px;
            background-color: #004d00;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: bold;
            margin-top: 25px;
            cursor: pointer;
            transition: background 0.2s;
        }

        button:hover {
            background-color: #006d1f;
        }

        button:disabled {
            background-color: #999;
            cursor: not-allowed;
        }

        .error-msg {
            background: #dc3545;
            color: white;
            padding: 10px;
            border-radius: 8px;
            text-align: center;
            margin-bottom: 20px;
        }

        .success-msg {
            background: #28a745;
            color: white;
            padding: 10px;
            border-radius: 8px;
            text-align: center;
            margin-bottom: 20px;
        }

        .info-note {
            background: #f4f8f6;
            border: 1px solid #e0e8e4;
            color: #1e4d42;
            font-size: 12.5px;
            padding: 10px 12px;
            border-radius: 8px;
            margin-bottom: 5px;
        }

        .login-link {
            text-align: center;
            margin-top: 18px;
            font-size: 13px;
            color: #555;
        }

        .login-link a {
            color: #004d00;
            font-weight: 600;
            text-decoration: none;
        }

        .login-link a:hover {
            text-decoration: underline;
        }

        /* ===== PASSWORD REQUIREMENTS CHECKLIST ===== */
        .password-checklist {
            margin-top: 10px;
            padding: 10px 12px;
            background: #f4f8f6;
            border: 1px solid #e0e8e4;
            border-radius: 8px;
        }

        .password-checklist .check-item {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 12.5px;
            color: #8a8a8a;
            padding: 3px 0;
            transition: color 0.15s;
        }

        .password-checklist .check-item i {
            width: 14px;
            text-align: center;
            font-size: 11px;
        }

        .password-checklist .check-item.met {
            color: #1e7d32;
            font-weight: 600;
        }

        .password-checklist .check-item.met i.fa-circle::before {
            content: "\f058"; /* fa-circle-check */
        }

        .match-msg {
            font-size: 12.5px;
            margin-top: 6px;
            font-weight: 600;
        }

        .match-msg.match {
            color: #1e7d32;
        }

        .match-msg.no-match {
            color: #dc3545;
        }

        @media (max-width: 768px) {
            .sidebar {
                width: 260px;
            }
            .form-side {
                margin-left: 260px;
            }
        }

        @media (max-width: 600px) {
            body {
                flex-direction: column;
            }
            .sidebar {
                width: 100%;
                height: auto;
                position: relative;
                flex-direction: row;
                flex-wrap: wrap;
                padding: 15px;
                gap: 10px;
            }
            .sidebar img {
                width: 60px;
            }
            .sidebar h2 {
                font-size: 16px;
            }
            .form-side {
                margin-left: 0;
                width: 100%;
            }
        }
    </style>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body>
    <div class="sidebar">
        <img src="logo1.png" alt="LOGO">
        <h2>CITY COLLEGE OF ANGELES</h2>
        <p>Totalis Humanae</p>
    </div>
    <div class="form-side">
        <div class="form-box">
            <h2>CREATE ACCOUNT</h2>

            <?php if ($error): ?>
                <div class="error-msg"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="success-msg"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>

            <div class="info-note">
                <i class="fas fa-envelope"></i> Use your school email (@<?php echo htmlspecialchars($allowed_email_domain); ?>). Your ID Number will be sent there after you sign up, and you will use it to log in.
            </div>

            <form method="POST" action="" id="signupForm">
                <label>Full Name</label>
                <input type="text" name="Name" placeholder="Enter full name" required value="<?php echo htmlspecialchars($name); ?>">

                <label>School Email</label>
                <input type="email" name="Email" placeholder="yourname@<?php echo htmlspecialchars($allowed_email_domain); ?>" required
                       pattern=".+@<?php echo str_replace('.', '\\.', htmlspecialchars($allowed_email_domain)); ?>"
                       title="Please use your school email (@<?php echo htmlspecialchars($allowed_email_domain); ?>)"
                       value="<?php echo htmlspecialchars($email); ?>">

                <label>Role</label>
                <select name="Role" required>
                    <option value="">-- Select role --</option>
                    <?php foreach ($role_labels as $value => $label): ?>
                        <option value="<?php echo htmlspecialchars($value); ?>" <?php echo ($role === $value) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($label); ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <label>Password</label>
                <input type="password" name="Password" id="passwordInput" placeholder="Enter password" required autocomplete="off">

                <div class="password-checklist" id="passwordChecklist">
                    <div class="check-item" id="check-length"><i class="far fa-circle"></i> At least 8 characters</div>
                    <div class="check-item" id="check-upper"><i class="far fa-circle"></i> One uppercase letter</div>
                    <div class="check-item" id="check-number"><i class="far fa-circle"></i> One number</div>
                    <div class="check-item" id="check-symbol"><i class="far fa-circle"></i> One symbol (e.g. ! @ # $ %)</div>
                </div>

                <label>Confirm Password</label>
                <input type="password" name="ConfirmPassword" id="confirmPasswordInput" placeholder="Re-enter password" required autocomplete="off">
                <div class="match-msg" id="matchMsg"></div>

                <button type="submit" id="submitBtn">CREATE ACCOUNT →</button>
            </form>

            <div class="login-link">
                Already have an account? <a href="login.php">Log in</a>
            </div>
        </div>
    </div>

    <script>
        const passwordInput = document.getElementById('passwordInput');
        const confirmInput = document.getElementById('confirmPasswordInput');
        const matchMsg = document.getElementById('matchMsg');
        const submitBtn = document.getElementById('submitBtn');
        const form = document.getElementById('signupForm');

        const checks = {
            length: { el: document.getElementById('check-length'), test: v => v.length >= 8 },
            upper:  { el: document.getElementById('check-upper'),  test: v => /[A-Z]/.test(v) },
            number: { el: document.getElementById('check-number'), test: v => /[0-9]/.test(v) },
            symbol: { el: document.getElementById('check-symbol'), test: v => /[^A-Za-z0-9]/.test(v) }
        };

        function allRequirementsMet(value) {
            return Object.values(checks).every(({ test }) => test(value));
        }

        function updateChecklist() {
            const value = passwordInput.value;
            Object.values(checks).forEach(({ el, test }) => {
                el.classList.toggle('met', test(value));
            });
            updateMatchMessage();
        }

        function updateMatchMessage() {
            if (confirmInput.value === '') {
                matchMsg.textContent = '';
                matchMsg.className = 'match-msg';
                return;
            }
            const matches = passwordInput.value === confirmInput.value;
            matchMsg.textContent = matches ? 'Passwords match.' : 'Passwords do not match.';
            matchMsg.className = 'match-msg ' + (matches ? 'match' : 'no-match');
        }

        passwordInput.addEventListener('input', updateChecklist);
        confirmInput.addEventListener('input', updateMatchMessage);

        // Enforce requirements before allowing submit (this IS a signup page, so it's safe to block)
        form.addEventListener('submit', function(event) {
            const value = passwordInput.value;
            if (!allRequirementsMet(value)) {
                event.preventDefault();
                document.getElementById('passwordChecklist').scrollIntoView({ behavior: 'smooth', block: 'center' });
                return;
            }
            if (passwordInput.value !== confirmInput.value) {
                event.preventDefault();
                updateMatchMessage();
                return;
            }
            // Sending the email takes a few seconds, so prevent double clicks
            submitBtn.disabled = true;
            submitBtn.textContent = 'CREATING ACCOUNT...';
        });
    </script>
</body>
</html>