<?php
session_start();
require_once 'connect.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $id_number = $_POST['ID_number'];
    $password = $_POST['Password'];

    $stmt = $conn->prepare("SELECT * FROM users WHERE id_number = ?");
    $stmt->bind_param("s", $id_number);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $data = $result->fetch_assoc();

        // Password verification (supports both hashed and plain text)
        $valid = false;
        if (password_verify($password, $data['password'])) {
            $valid = true;
        } elseif ($password === $data['password']) {
            $valid = true; // fallback for plain text
        }

        if ($valid) {
            $_SESSION['id_number'] = $data['id_number'];
            $_SESSION['name'] = $data['name'];
            $_SESSION['role'] = $data['role'];

            $role = strtolower(trim($data['role']));
            switch ($role) {
                case 'president':
                    header('Location: president_dashboard.php');
                    break;
                case 'student':
                    header('Location: student_dashboard.php');
                    break;
                case 'vp':
                    header('Location: VP_dashboard.php');
                    break;
                case 'dean':
                    header('Location: dean_dashboard.php');
                    break;
                case 'program coordinator':
                    header('Location: coordinator_dashboard.php');
                    break;
                case 'registrar':
                    header('Location: registrar_dashboard.php');
                    break;
                case 'professor':
                case 'instructor':
                    header('Location: prof_dashboard.php');
                    break;
                default:
                    $first = substr($id_number, 0, 1);
                    if ($first == "P" && substr($id_number, 1, 1) == "R") header('Location: president_dashboard.php');
                    elseif ($first == "S") header('Location: student_dashboard.php');
                    elseif ($first == "V") header('Location: VP_dashboard.php');
                    elseif ($first == "D") header('Location: dean_dashboard.php');
                    elseif ($first == "P") header('Location: prof_dashboard.php');
                    else header('Location: prof_dashboard.php');
                    break;
            }
            exit();
        } else {
            $error = "Invalid ID or Password!";
        }
    } else {
        $error = "Invalid ID or Password!";
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Login - City College of Angeles</title>
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
            width: 360px;
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

        input {
            width: 100%;
            padding: 12px;
            border: 1.5px solid #ddd;
            border-radius: 8px;
            box-sizing: border-box;
            font-size: 14px;
        }

        input:focus {
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

        .error-msg {
            background: #dc3545;
            color: white;
            padding: 10px;
            border-radius: 8px;
            text-align: center;
            margin-bottom: 20px;
        }

        /* ===== PASSWORD REQUIREMENTS CHECKLIST ===== */
        .password-checklist {
            margin-top: 10px;
            padding: 10px 12px;
            background: #f4f8f6;
            border: 1px solid #e0e8e4;
            border-radius: 8px;
            display: none;
        }

        .password-checklist.show {
            display: block;
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
            <h2>LOGIN</h2>
            <?php if (isset($error)): ?>
                <div class="error-msg"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <form method="POST" action="">
                <label>ID Number</label>
                <input type="text" name="ID_number" placeholder="Enter ID" required>

                <label>Password</label>
                <input type="password" name="Password" id="passwordInput" placeholder="Enter password" required autocomplete="off">

                <div class="password-checklist" id="passwordChecklist">
                    <div class="check-item" id="check-length"><i class="far fa-circle"></i> At least 8 characters</div>
                    <div class="check-item" id="check-upper"><i class="far fa-circle"></i> One uppercase letter</div>
                    <div class="check-item" id="check-number"><i class="far fa-circle"></i> One number</div>
                    <div class="check-item" id="check-symbol"><i class="far fa-circle"></i> One symbol (e.g. ! @ # $ %)</div>
                </div>

                <button type="submit">LOGIN →</button>
            </form>
            <div style="text-align:center;margin-top:18px;font-size:13px;color:#555;">
                Don't have an account? <a href="signup.php" style="color:#004d00;font-weight:600;text-decoration:none;">Sign up</a>
            </div>
        </div>
    </div>

    <script>
        const passwordInput = document.getElementById('passwordInput');
        const checklist = document.getElementById('passwordChecklist');

        const checks = {
            length: { el: document.getElementById('check-length'), test: v => v.length >= 8 },
            upper:  { el: document.getElementById('check-upper'),  test: v => /[A-Z]/.test(v) },
            number: { el: document.getElementById('check-number'), test: v => /[0-9]/.test(v) },
            symbol: { el: document.getElementById('check-symbol'), test: v => /[^A-Za-z0-9]/.test(v) }
        };

        function updateChecklist() {
            const value = passwordInput.value;
            Object.values(checks).forEach(({ el, test }) => {
                el.classList.toggle('met', test(value));
            });
        }

        passwordInput.addEventListener('focus', function() {
            checklist.classList.add('show');
        });

        passwordInput.addEventListener('input', updateChecklist);

        // Keep the checklist visible if the field still has text when it loses focus
        passwordInput.addEventListener('blur', function() {
            if (!passwordInput.value) {
                checklist.classList.remove('show');
            }
        });
    </script>
</body>
</html>