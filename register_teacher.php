<?php
session_start();
require_once __DIR__ . '/config/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $name  = trim($_POST['name'] ?? '');
  $email = trim($_POST['email'] ?? '');
  $pass  = trim($_POST['password'] ?? '');

  if ($name && $email && $pass) {
    try {
      $stmt = $pdo->prepare("INSERT INTO users(name, email, password, role) VALUES(?, ?, ?, 'teacher')");
      $stmt->execute([$name, $email, $pass]);
      $_SESSION['flash'] = "ลงทะเบียนครูสำเร็จ! เข้าสู่ระบบได้เลย";
      header("Location: login.php");
      exit;
    } catch (Throwable $e) {
      $error = "อีเมลนี้อาจถูกใช้แล้ว หรือบันทึกไม่สำเร็จ";
    }
  } else {
    $error = "กรอกข้อมูลให้ครบถ้วน";
  }
}
?>
<!DOCTYPE html>
<html lang="th">

<head>
  <meta charset="UTF-8">
  <title>สมัครครู | สถาบันติวเตอร์</title>
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
      color: var(--ink);
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      background: linear-gradient(135deg, var(--c1), var(--c2));
      padding: 24px;
    }

    .wrap {
      width: min(520px, 100%);
      background: linear-gradient(180deg, rgba(255, 255, 255, .9), rgba(255, 255, 255, .95));
      border-radius: 20px;
      box-shadow: 0 20px 50px rgba(0, 0, 0, .18);
      overflow: hidden;
      backdrop-filter: blur(4px);
    }

    .hero {
      padding: 20px 24px;
      background: linear-gradient(90deg, #4cb5da, #5b86e5);
      color: #fff;
      position: relative;
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
      opacity: .95;
      font-size: 14px
    }

    .hero .ribbon {
      position: absolute;
      right: -30px;
      top: -30px;
      width: 140px;
      height: 140px;
      border-radius: 50%;
      background: radial-gradient(closest-side, rgba(255, 255, 255, .45), transparent 70%);
      pointer-events: none;
    }

    .card {
      padding: 24px;
      background: var(--card);
    }

    label {
      display: block;
      font-size: 14px;
      color: var(--muted);
      margin: 8px 0 6px
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
      background: #fff;
      transition: box-shadow .2s, border-color .2s, transform .02s;
    }

    input:focus {
      outline: none;
      border-color: #8ab0ff;
      box-shadow: 0 0 0 4px rgba(91, 134, 229, .18);
    }

    .pw-toggle {
      position: absolute;
      right: 10px;
      top: 50%;
      transform: translateY(-50%);
      border: none;
      background: transparent;
      cursor: pointer;
      color: #64748b;
      font-size: 18px;
      padding: 6px;
    }

    .row {
      display: grid;
      grid-template-columns: 1fr;
      gap: 14px
    }

    .hint {
      color: var(--muted);
      font-size: 12px;
      margin: 6px 2px 0
    }

    .msg {
      text-align: center;
      margin: 8px 0 0;
      font-size: 14px
    }

    .msg.ok {
      color: var(--ok)
    }

    .msg.err {
      color: var(--err)
    }

    .actions {
      margin-top: 16px;
      display: flex;
      gap: 10px;
      align-items: center
    }

    .btn {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 10px;
      padding: 12px 14px;
      border-radius: 12px;
      border: none;
      cursor: pointer;
      font-weight: 700;
      background: linear-gradient(90deg, var(--c1), var(--c2));
      color: #fff;
      flex: 1;
      box-shadow: 0 8px 20px rgba(91, 134, 229, .28);
    }

    .btn:hover {
      filter: brightness(1.02)
    }

    .btn:active {
      transform: translateY(1px)
    }

    .link {
      text-align: center;
      margin-top: 10px;
      font-size: 14px;
    }

    .link a {
      color: #3266e3;
      text-decoration: none;
      font-weight: 700
    }

    .divider {
      height: 1px;
      background: var(--line);
      margin: 18px 0 10px;
      opacity: .7
    }

    @media (max-width:480px) {
      .hero h2 {
        font-size: 20px
      }

      .card {
        padding: 20px
      }
    }
  </style>
</head>

<body>
  <div class="wrap">
    <div class="hero">
      <div class="ribbon"></div>
      <h2><i class="bi bi-mortarboard-fill"></i> สมัครสมาชิก (ครู)</h2>
      <p>สร้างบัญชีครูเพื่อจัดการรายวิชา งานมอบหมาย และผลการเรียน</p>
    </div>

    <div class="card">
      <?php if (!empty($error)): ?>
        <p class="msg err"><i class="bi bi-exclamation-triangle-fill"></i> <?= htmlspecialchars($error) ?></p>
      <?php endif; ?>

      <form method="POST" autocomplete="off">
        <div class="row">
          <div>
            <label>ชื่อ-สกุล</label>
            <div class="group">
              <i class="bi bi-person-fill"></i>
              <input name="name" placeholder="เช่น ครูดารณี วิชาญ" required>
            </div>
          </div>

          <div>
            <label>อีเมล</label>
            <div class="group">
              <i class="bi bi-envelope-fill"></i>
              <input type="email" name="email" placeholder="teacher@example.com" required>
            </div>
            <div class="hint">อีเมลนี้จะใช้เป็นชื่อผู้ใช้ในการเข้าสู่ระบบ</div>
          </div>

          <div>
            <div>
              <label>รหัสผ่าน</label>
              <div class="group">
                <i class="bi bi-shield-lock-fill"></i>
                <input type="password" name="password" placeholder=" ••••••••" required>
              </div>
            </div>

            <div class="hint">แนะนำให้ตั้งรหัสผ่านที่คาดเดายาก (ผสมตัวเลข/ตัวอักษร)</div>
          </div>
        </div>

        <div class="actions">
          <button class="btn" type="submit"><i class="bi bi-person-plus-fill"></i> ลงทะเบียน</button>
        </div>
      </form>

      <div class="divider"></div>
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