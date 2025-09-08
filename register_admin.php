<?php
// register_admin.php
session_start();
require_once __DIR__ . '/config/db.php';

// เฉพาะแอดมิน
$me = $_SESSION['user'] ?? null;
if (!$me || strtolower($me['role'] ?? '') !== 'admin') {
  header('Location: login.php');
  exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $name  = trim($_POST['name'] ?? '');
  $email = trim($_POST['email'] ?? '');
  $pass  = trim($_POST['password'] ?? '');

  if ($name && $email && $pass) {
    try {
      // หมายเหตุ: ตอนนี้บันทึกรหัสผ่านแบบตรง ๆ ตามระบบเดิม
      $stmt = $pdo->prepare("INSERT INTO users(name, email, password, role) VALUES(?, ?, ?, 'admin')");
      $stmt->execute([$name, $email, $pass]);

      $ok = "สร้างบัญชีแอดมินสำเร็จแล้ว!";
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
  <title>สร้างบัญชี: แอดมิน</title>
  <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;600&display=swap" rel="stylesheet">
  <style>
    body{margin:0;font-family:'Prompt',sans-serif;background:linear-gradient(135deg,#36d1dc,#5b86e5);
         min-height:100vh;display:flex;justify-content:center;align-items:center}
    .box{background:#fff;padding:34px;border-radius:16px;box-shadow:0 10px 30px rgba(0,0,0,.2);width:480px}
    h2{margin:0 0 16px;color:#5b86e5;text-align:center}
    label{display:block;margin:10px 0 6px}
    input,button{width:100%;padding:12px;border-radius:10px;border:1px solid #cbd5e1;font-size:16px}
    button{margin-top:12px;background:linear-gradient(90deg,#36d1dc,#5b86e5);color:#fff;border:none;font-weight:700;cursor:pointer}
    .error{color:#e11d48;margin-top:10px;text-align:center}
    .ok{color:#16a34a;margin-top:10px;text-align:center}
    .back{margin-top:12px;text-align:center}
    .back a{color:#5b86e5;font-weight:700;text-decoration:none}
  </style>
</head>
<body>
  <div class="box">
    <h2>🛡️ สร้างบัญชี: แอดมิน</h2>
    <form method="POST" autocomplete="off">
      <label>ชื่อ-สกุล</label>
      <input name="name" required>
      <label>อีเมล</label>
      <input type="email" name="email" required>
      <label>รหัสผ่าน</label>
      <input type="password" name="password" required>
      <button type="submit">สร้างบัญชีแอดมิน</button>
    </form>

    <?php if(!empty($error)): ?><p class="error"><?= htmlspecialchars($error) ?></p><?php endif; ?>
    <?php if(!empty($ok)): ?><p class="ok"><?= htmlspecialchars($ok) ?></p><?php endif; ?>

    <p class="back"><a href="dashboard.php">⬅ กลับหน้าแดชบอร์ด</a></p>
  </div>
</body>
</html>
