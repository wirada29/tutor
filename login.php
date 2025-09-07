<?php
session_start();
require_once __DIR__ . '/config/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email']);
    $password = trim($_POST['password']);

    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && $password === $user['password']) {  // ✅ เทียบตรง ๆ
        $_SESSION['user'] = [
            'user_id' => $user['user_id'],
            'name'    => $user['name'],
            'email'   => $user['email'], // ถ้าใน DB ไม่มีฟิลด์ email ก็ใส่ ''
            'role'    => $user['role']
        ];

        // 🔽 เพิ่มค่าที่แดชบอร์ดจะใช้
        // ปรับตัวเลขได้ตามจริง หรือจะไปดึงจากฐานข้อมูลก็ได้ภายหลัง
        $_SESSION['behavior_score'] = 92;   // คะแนนประพฤติ (0-100)
        $_SESSION['total_score']    = 430;  // คะแนนรวมปัจจุบัน
        $_SESSION['total_max']      = 500;  // คะแนนเต็ม

        header("Location: dashboard.php");
        exit();
    } else {
        $error = "❌ อีเมลหรือรหัสผ่านไม่ถูกต้อง";
    }
}
?>


<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <title>เข้าสู่ระบบ</title>
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
            color: #36d1dc;
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
            border-color: #5b86e5;
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
        <h2>🔑 เข้าสู่ระบบ</h2>
        <form method="POST">
            <input type="email" name="email" placeholder="📧 อีเมล" required>
            <input type="password" name="password" placeholder="🔒 รหัสผ่าน" required>
            <button type="submit">เข้าสู่ระบบ</button>
        </form>
        <?php if (isset($error)) echo "<p class='error'>$error</p>"; ?>
        <p class="link">ยังไม่มีบัญชี? <a href="register.php">สมัครสมาชิก</a></p>
    </div>
</body>

</html>