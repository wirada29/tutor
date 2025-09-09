<?php
session_start();
require_once __DIR__ . '/config/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $name  = trim($_POST['name'] ?? '');
  $email = trim($_POST['email'] ?? '');
  $pass  = trim($_POST['password'] ?? '');

  if ($name && $email && $pass) {
    try {
      $stmt = $pdo->prepare("INSERT INTO users(name, email, password, role) VALUES(?, ?, ?, 'admin')");
      $stmt->execute([$name, $email, $pass]);
      $_SESSION['flash'] = "✅ สมัครแอดมินสำเร็จ! เข้าสู่ระบบได้เลย";
      header("Location: login.php");
      exit;
    } catch (Throwable $e) {
      $error = "❌ อีเมลนี้ถูกใช้แล้ว หรือบันทึกไม่สำเร็จ";
    }
  } else {
    $error = "⚠️ กรุณากรอกข้อมูลให้ครบถ้วน";
  }
}
?>
<!DOCTYPE html>
<html lang="th">

<head>
  <meta charset="UTF-8">
  <title>สมัครแอดมิน | สถาบันติวเตอร์</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;600&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
  <style>
    :root {
      --c1: #36d1dc;
      --c2: #5b86e5;
      --bg: #f5f7fa;
      --ink: #0f172a;
      --muted: #64748b;
      --card: #ffffff;
      --ok: #16a34a;
      --err: #e11d48;
      --line: #e5e7eb;
    }

    * {
      box-sizing: border-box
    }

    body {
      margin: 0;
      font-family: 'Prompt', sans-serif;
      background: linear-gradient(135deg, var(--c1), var(--c2));
      display: flex;
      align-items: center;
      justify-content: center;
      min-height: 100vh;
      padding: 20px;
      color: var(--ink);
    }

    .wrap {
      width: min(500px, 100%);
      background: var(--card);
      border-radius: 20px;
      box-shadow: 0 20px 50px rgba(0, 0, 0, .18);
      overflow: hidden;
    }

    .hero {
      padding: 22px;
      background: linear-gradient(90deg, #4cb5da, #5b86e5);
      color: #fff;
    }

    .hero h2 {
      margin: 0;
      font-size: 22px;
      font-weight: 700;
      display: flex;
      align-items: center;
      gap: 10px
    }

    .hero p {
      margin: 6px 0 0;
      font-size: 14px;
      opacity: .95
    }

    .card {
      padding: 26px;
    }

    label {
      display: block;
      font-size: 14px;
      color: var(--muted);
      margin: 10px 0 6px
    }

    .group {
      position: relative;
    }

    .group i {
      position: absolute;
      left: 12px;
      top: 50%;
      transform: translateY(-50%);
      color: #94a3b8;
      font-size: 18px;
    }

    input,
    button {
      width: 100%;
      padding: 12px 12px 12px 38px;
      font-size: 16px;
      border-radius: 12px;
      border: 1px solid var(--line);
      transition: border-color .2s, box-shadow .2s;
    }

    input:focus {
      outline: none;
      border-color: #5b86e5;
      box-shadow: 0 0 0 3px rgba(91, 134, 229, .2);
    }

    .pw-toggle {
      position: absolute;
      right: 10px;
      top: 50%;
      transform: translateY(-50%);
      border: none;
      background: transparent;
      cursor: pointer;
      font-size: 18px;
      color: #64748b;
    }

    .btn {
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
      margin-top: 18px;
      padding: 12px;
      font-weight: 700;
      background: linear-gradient(90deg, var(--c1), var(--c2));
      color: #fff;
      border: none;
      border-radius: 12px;
      cursor: pointer;
      box-shadow: 0 6px 16px rgba(91, 134, 229, .25);
    }

    .btn:hover {
      filter: brightness(1.05);
    }

    .hint {
      color: var(--muted);
      font-size: 12px;
      margin: 6px 2px 0
    }

    .msg {
      text-align: center;
      margin: 12px 0;
      font-size: 14px
    }

    .msg.err {
      color: var(--err);
    }

    .msg.ok {
      color: var(--ok);
    }

    .link {
      text-align: center;
      margin-top: 16px;
      font-size: 14px
    }

    .link a {
      color: #5b86e5;
      font-weight: 700;
      text-decoration: none
    }
  </style>
</head>

<body>
  <div class="wrap">
    <div class="hero">
      <h2><i class="bi bi-shield-lock-fill"></i> ลงทะเบียน (แอดมิน)</h2>
      <p>สร้างบัญชีผู้ดูแลระบบเพื่อจัดการผู้ใช้และการตั้งค่า</p>
    </div>

    <div class="card">
      <?php if (!empty($error)): ?>
        <p class="msg err"><?= htmlspecialchars($error) ?></p>
      <?php endif; ?>
      <?php if (!empty($_SESSION['flash'])): ?>
        <p class="msg ok"><?= htmlspecialchars($_SESSION['flash']);
                          unset($_SESSION['flash']); ?></p>
      <?php endif; ?>

      <form method="POST" autocomplete="off">
        <label>ชื่อ-สกุล</label>
        <div class="group">
          <i class="bi bi-person-fill"></i>
          <input name="name" placeholder="เช่น สมชาย แอดมิน" required>
        </div>

        <label>อีเมล</label>
        <div class="group">
          <i class="bi bi-envelope-fill"></i>
          <input type="email" name="email" placeholder="admin@example.com" required>
        </div>

        <label>รหัสผ่าน</label>
        <div>
          <div class="group">
            <i class="bi bi-shield-lock-fill"></i>
            <input type="password" name="password" placeholder="••••••••" required>
            <div class="hint">แนะนำให้ตั้งรหัสผ่านที่คาดเดายาก (ผสมตัวเลข/ตัวอักษร)</div>
          </div>
        </div>


        <button class="btn" type="submit"><i class="bi bi-check2-circle"></i> เข้าสู่ระบบ</button>
      </form>

      <p class="link">มีบัญชีอยู่แล้ว? <a href="login.php">เข้าสู่ระบบ</a></p>
    </div>
  </div>

  <script>
    function togglePw() {
      const pw = document.getElementById('pw');
      const icon = document.getElementById('pwIcon');
      if (pw.type === 'password') {
        pw.type = 'text';
        icon.className = 'bi bi-eye';
      } else {
        pw.type = 'password';
        icon.className = 'bi bi-eye-slash';
      }
    }
  </script>
</body>

</html>