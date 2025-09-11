<?php
// user_edit.php — เฉพาะแอดมิน
session_start();
require_once __DIR__ . '/includes/auth.php';
require_login();
if (!is_admin()) { header("Location: dashboard.php"); exit; }
require_once __DIR__ . '/config/db.php';

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
if ($id <= 0) { header("Location: admin_users.php"); exit; }

$st = $pdo->prepare("SELECT user_id, name, email, role FROM users WHERE user_id=?");
$st->execute([$id]);
$u = $st->fetch(PDO::FETCH_ASSOC);
if (!$u) { $_SESSION['flash'] = "ไม่พบบัญชีผู้ใช้"; header("Location: admin_users.php"); exit; }

$error = $ok = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $name  = trim($_POST['name'] ?? '');
  $email = trim($_POST['email'] ?? '');
  $role  = strtolower(trim($_POST['role'] ?? 'student'));
  $pass  = trim($_POST['password'] ?? '');

  if ($name==='' || $email==='') {
    $error = "กรุณากรอกชื่อและอีเมล";
  } elseif (!in_array($role, ['student','teacher','admin'], true)) {
    $error = "บทบาทไม่ถูกต้อง";
  } else {
    try {
      if ($pass !== '') {
        // หมายเหตุ: ระบบเดิมเทียบรหัสผ่านแบบตรง ๆ หากจะปลอดภัยให้เปลี่ยนเป็น password_hash/password_verify
        $sql = "UPDATE users SET name=?, email=?, role=?, password=?, updated_at=NOW() WHERE user_id=?";
        $args = [$name, $email, $role, $pass, $id];
      } else {
        $sql = "UPDATE users SET name=?, email=?, role=?, updated_at=NOW() WHERE user_id=?";
        $args = [$name, $email, $role, $id];
      }
      $up = $pdo->prepare($sql);
      $up->execute($args);
      $_SESSION['flash'] = "บันทึกการแก้ไขเรียบร้อย";
      header("Location: user_view.php?id=".$id);
      exit;
    } catch (Throwable $e) {
      $error = "บันทึกไม่สำเร็จ: ".$e->getMessage();
    }
  }
}

$current = basename($_SERVER['PHP_SELF']);
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
?>
<!doctype html>
<html lang="th">
<head>
<meta charset="utf-8">
<title>แก้ไขผู้ใช้ #<?= (int)$u['user_id'] ?> | แอดมิน</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;600&display=swap" rel="stylesheet">
<style>
:root{--blue:#3b82f6;--blue-dark:#2563eb;--ink:#0f172a;--muted:#64748b;--bg:#f5f7fa;--card:#fff;--line:#e5e7eb;--ok:#16a34a;--err:#ef4444}
*{box-sizing:border-box}
body{margin:0;font-family:'Sarabun',system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif;background:var(--bg);color:var(--ink);display:flex;min-height:100vh}
.sidebar{width:230px;background:linear-gradient(180deg,var(--blue),#2b6de1);color:#fff;height:100vh;padding:26px 16px;position:fixed;inset:0 auto 0 0}
.sidebar h2{font-size:22px;font-weight:600;margin:0 0 24px;text-align:center}
.sidebar a{display:flex;align-items:center;gap:10px;color:#fff;text-decoration:none;margin-bottom:12px;padding:11px 10px;border-radius:10px}
.sidebar a:hover{background:rgba(255,255,255,.15)}
.sidebar a.active{background:rgba(255,255,255,.22)}
.main{flex:1;margin-left:230px;padding:28px}
.card{background:var(--card);border-radius:16px;padding:18px;margin-bottom:14px;box-shadow:0 8px 24px rgba(15,23,42,.06)}
.row{display:grid;grid-template-columns:1fr 1fr;gap:12px}
label{font-size:14px;color:#475569}
.input, .select{width:100%;padding:10px;border:1px solid var(--line);border-radius:10px}
.hint{color:var(--muted);font-size:12px;margin-top:4px}
.btn{padding:10px 14px;border-radius:10px;border:0;cursor:pointer;font-weight:700}
.btn-primary{background:var(--blue-dark);color:#fff}
.btn-muted{background:#e5e7eb}
.alert{padding:10px;border-radius:10px;margin:10px 0}
.alert-err{background:#fee2e2;color:#991b1b}
.alert-ok{background:#dcfce7;color:#166534}
</style>
</head>
<body>
  <div class="sidebar">
    <h2>🛡️ แอดมิน</h2>
    <a href="admin_dashboard.php" class="<?= $current==='admin_dashboard.php'?'active':'' ?>"><i class="bi bi-speedometer2"></i> หน้าแรกแอดมิน</a>
    <a href="admin_users.php" class="<?= $current==='admin_users.php'?'active':'' ?>"><i class="bi bi-people-fill"></i> ผู้เข้าใช้ทั้งหมด</a>
    <a href="courses.php"><i class="bi bi-journal-bookmark-fill"></i> รายวิชา</a>
    <a href="teacher_assign_list.php"><i class="bi bi-card-checklist"></i> งานครู</a>
    <a href="logout.php"><i class="bi bi-box-arrow-right"></i> ออกจากระบบ</a>
  </div>

  <div class="main">
    <div class="card">
      <h2 style="margin:0 0 12px"><i class="bi bi-pencil-square"></i> แก้ไขผู้ใช้ #<?= (int)$u['user_id'] ?></h2>
      <?php if ($error): ?><div class="alert alert-err"><?= h($error) ?></div><?php endif; ?>
      <?php if (!empty($_SESSION['flash'])): ?><div class="alert alert-ok"><?= h($_SESSION['flash']); unset($_SESSION['flash']); ?></div><?php endif; ?>

      <form method="post">
        <input type="hidden" name="id" value="<?= (int)$u['user_id'] ?>">

        <div class="row">
          <div>
            <label>ชื่อ</label>
            <input class="input" name="name" value="<?= h($u['name']) ?>" required>
          </div>
          <div>
            <label>อีเมล</label>
            <input class="input" type="email" name="email" value="<?= h($u['email']) ?>" required>
          </div>
        </div>

        <div class="row" style="margin-top:10px">
          <div>
            <label>บทบาท</label>
            <select class="select" name="role" required>
              <option value="student" <?= strtolower($u['role'])==='student'?'selected':'' ?>>Student</option>
              <option value="teacher" <?= strtolower($u['role'])==='teacher'?'selected':'' ?>>Teacher</option>
              <option value="admin"   <?= strtolower($u['role'])==='admin'  ?'selected':'' ?>>Admin</option>
            </select>
          </div>
          <div>
            <label>ตั้งรหัสผ่านใหม่ (ถ้าต้องการ)</label>
            <input class="input" type="text" name="password" placeholder="เว้นว่างถ้าไม่เปลี่ยน">
            <div class="hint">ระบบปัจจุบันเก็บรหัสผ่านแบบไม่เข้ารหัส — แนะนำปรับใช้ password_hash ภายหลัง</div>
          </div>
        </div>

        <div style="margin-top:14px;display:flex;gap:8px;flex-wrap:wrap">
          <button class="btn btn-primary" type="submit"><i class="bi bi-check2-circle"></i> บันทึก</button>
          <a class="btn btn-muted" href="user_view.php?id=<?= (int)$u['user_id'] ?>"><i class="bi bi-arrow-left"></i> ย้อนกลับ</a>
        </div>
      </form>
    </div>
  </div>
</body>
</html>
