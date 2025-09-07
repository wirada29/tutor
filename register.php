<?php
session_start();
require_once __DIR__ . '/config/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name     = trim($_POST['name']);
    $email    = trim($_POST['email']);
    $password = trim($_POST['password']);
    $confirm  = trim($_POST['confirm']);

    if ($password !== $confirm) {
        $error = "❌ รหัสผ่านไม่ตรงกัน";
    } else {
        // ตรวจสอบอีเมลซ้ำ
        $stmt = $pdo->prepare("SELECT 1 FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $error = "⚠️ อีเมลนี้ถูกใช้ไปแล้ว";
        } else {
            // บันทึกผู้ใช้ใหม่ (เก็บรหัสผ่านตรง ๆ)
            $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, 'student')");
            if ($stmt->execute([$name, $email, $password])) {
                header("Location: login.php");
                exit();
            } else {
                $error = "เกิดข้อผิดพลาดในการสมัครสมาชิก";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <title>สมัครสมาชิก</title>
    <style>
        body {
            margin: 0;
            font-family: 'Prompt', sans-serif;
            background: linear-gradient(135deg, #36d1dc, #5b86e5);
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
        }

        .box {
            background: #fff;
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.2);
            width: 380px;
            text-align: center;
        }

        h2 {
            color: #5b86e5;
            margin-bottom: 20px;
        }

        input,
        button {
            width: 100%;
            padding: 12px;
            margin: 10px 0;
            border-radius: 8px;
            border: 1px solid #ccc;
            font-size: 16px;
        }

        input:focus {
            border-color: #36d1dc;
            outline: none;
        }

        button {
            background: linear-gradient(to right, #36d1dc, #5b86e5);
            color: white;
            border: none;
            cursor: pointer;
            font-weight: bold;
        }

        button:hover {
            opacity: 0.9;
        }

        .error {
            color: red;
            margin-top: 10px;
        }

        .link {
            margin-top: 15px;
            font-size: 14px;
        }

        .link a {
            color: #5b86e5;
            font-weight: bold;
            text-decoration: none;
        }
    </style>
</head>

<body>
    <div class="box">
        <h2>📝 สมัครสมาชิก</h2>
        <form method="POST">
            <input type="text" name="name" placeholder="👤 ชื่อ-นามสกุล" required>
            <input type="email" name="email" placeholder="📧 อีเมล" required>
            <input type="password" name="password" placeholder="🔒 รหัสผ่าน" required>
            <input type="password" name="confirm" placeholder="🔑 ยืนยันรหัสผ่าน" required>
            <button type="submit">สมัครสมาชิก</button>
        </form>
        <?php if (isset($error)) echo "<p class='error'>$error</p>"; ?>
        <p class="link">มีบัญชีแล้ว? <a href="login.php">เข้าสู่ระบบ</a></p>
    </div>
</body>

</html>