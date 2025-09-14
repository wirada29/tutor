<?php
session_start();
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';
require_login();
if (!is_teacher() && !is_admin()) { header("Location: subjects.php"); exit; }

$code = trim($_POST['code'] ?? '');
$name = trim($_POST['name'] ?? '');
$credit = (int)($_POST['credit'] ?? 3);
$error = '';

if ($_SERVER['REQUEST_METHOD']==='POST') {
  if ($code==='' || $name==='') {
    $error = 'กรอกรหัสและชื่อวิชาให้ครบ';
  } else {
    try {
      $st = $pdo->prepare("INSERT INTO subjects(code,name,credit) VALUES(?,?,?)");
      $st->execute([$code,$name,$credit]);
      $_SESSION['flash'] = 'เพิ่มวิชาเรียบร้อย';
      header("Location: subjects.php"); exit;
    } catch (Throwable $e) {
      $error = 'บันทึกไม่สำเร็จ: '.$e->getMessage();
    }
  }
}
?>
<!doctype html><html lang="th"><head>
<meta charset="utf-8"><title>เพิ่มวิชา</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
<link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;600&display=swap" rel="stylesheet">
<style>
:root{--blue:#3b82f6;--blue2:#2563eb;--ink:#0f172a;--muted:#64748b;--bg:#f5f7fa;--card:#fff;--line:#e5e7eb;--err:#ef4444}
*{box-sizing:border-box} body{margin:0;font-family:'Sarabun',sans-serif;background:var(--bg);color:var(--ink);display:flex;min-height:100vh}
.sidebar{width:230px;background:linear-gradient(180deg,var(--blue),#2b6de1);color:#fff;padding:26px 16px;position:fixed;inset:0 auto 0 0}
.sidebar h2{margin:0 0 24px;text-align:center;font-size:22px;font-weight:600}
.sidebar a{display:flex;align-items:center;gap:10px;color:#fff;text-decoration:none;margin-bottom:12px;padding:11px;border-radius:10px}
.sidebar a:hover{background:rgba(255,255,255,.12)}
.main{flex:1;margin-left:230px;padding:28px}
.card{background:var(--card);padding:18px;border-radius:14px;box-shadow:0 6px 22px rgba(15,23,42,.06);margin-bottom:14px}
label{display:block;font-size:14px;color:#475569;margin:8px 0 6px}
.input,.btn,.select{width:100%;padding:10px 12px;border-radius:10px;border:1px solid var(--line)}
.btn{background:var(--blue2);color:#fff;border:none;font-weight:700;cursor:pointer}
.alert{padding:10px;border-radius:10px;background:#fee2e2;color:var(--err);margin-bottom:10px}
.row{display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px}
</style></head><body>

<div class="sidebar">
  <h2>📘 เมนู</h2>
  <a href="dashboard.php"><i class="bi bi-house-fill"></i> หน้าแรก</a>
  <a href="subjects.php"><i class="bi bi-book"></i> วิชา (Subjects)</a>
  <a href="courses.php"><i class="bi bi-journal-bookmark-fill"></i> รายวิชาเรียน (Courses)</a>
  <a href="logout.php"><i class="bi bi-box-arrow-right"></i> ออกจากระบบ</a>
</div>

<div class="main">
  <div class="card">
    <h2 style="margin:0">➕ เพิ่มวิชา</h2>
    <?php if($error): ?><div class="alert"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <form method="post" autocomplete="off" style="margin-top:10px">
      <div class="row">
        <div>
          <label>รหัสวิชา</label>
          <input class="input" name="code" value="<?= htmlspecialchars($code) ?>" placeholder="เช่น IT-201" required>
        </div>
        <div>
          <label>ชื่อวิชา</label>
          <input class="input" name="name" value="<?= htmlspecialchars($name) ?>" placeholder="เช่น ระบบเครือข่าย 1" required>
        </div>
        <div>
          <label>หน่วยกิต</label>
          <input class="input" type="number" name="credit" min="1" max="6" value="<?= (int)$credit ?>">
        </div>
      </div>
      <div style="margin-top:12px;display:flex;gap:10px">
        <button class="btn" type="submit"><i class="bi bi-check2-circle"></i> บันทึก</button>
        <a class="input" style="text-decoration:none;display:inline-flex;align-items:center;justify-content:center" href="subjects.php">ยกเลิก</a>
      </div>
    </form>
  </div>
</div>
</body></html>
