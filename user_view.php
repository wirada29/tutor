<?php
// user_view.php — เฉพาะแอดมิน
session_start();
require_once __DIR__ . '/includes/auth.php';
require_login();
if (!is_admin()) { header("Location: dashboard.php"); exit; }
require_once __DIR__ . '/config/db.php';

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { header("Location: admin_users.php"); exit; }

// เช็คว่ามีคอลัมน์ created_at / updated_at ไหม
$stCols = $pdo->prepare("
  SELECT COLUMN_NAME
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'users'
    AND COLUMN_NAME IN ('created_at','updated_at')
");
$stCols->execute();
$cols = $stCols->fetchAll(PDO::FETCH_COLUMN);
$hasCreated = in_array('created_at', $cols, true);
$hasUpdated = in_array('updated_at', $cols, true);

// ประกอบ SELECT ตามคอลัมน์ที่มีจริง
$select = "user_id, name, email, role";
if ($hasCreated) $select .= ", created_at";
if ($hasUpdated) $select .= ", updated_at";

$st = $pdo->prepare("SELECT $select FROM users WHERE user_id=?");
$st->execute([$id]);
$u = $st->fetch(PDO::FETCH_ASSOC);
if (!$u) { $_SESSION['flash'] = "ไม่พบบัญชีผู้ใช้"; header("Location: admin_users.php"); exit; }

$current = basename($_SERVER['PHP_SELF']);
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
?>
<!doctype html>
<html lang="th">
<head>
<meta charset="utf-8">
<title>ดูผู้ใช้ #<?= (int)$u['user_id'] ?> | แอดมิน</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;600&display=swap" rel="stylesheet">
<style>
:root{--blue:#3b82f6;--blue-dark:#2563eb;--ink:#0f172a;--muted:#64748b;--bg:#f5f7fa;--card:#fff;--line:#e5e7eb;--err:#ef4444}
*{box-sizing:border-box}
body{margin:0;font-family:'Sarabun',system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif;background:var(--bg);color:var(--ink);display:flex;min-height:100vh}
.sidebar{width:230px;background:linear-gradient(180deg,var(--blue),#2b6de1);color:#fff;height:100vh;padding:26px 16px;position:fixed;inset:0 auto 0 0}
.sidebar h2{font-size:22px;font-weight:600;margin:0 0 24px;text-align:center}
.sidebar a{display:flex;align-items:center;gap:10px;color:#fff;text-decoration:none;margin-bottom:12px;padding:11px 10px;border-radius:10px}
.sidebar a:hover{background:rgba(255,255,255,.15)}
.sidebar a.active{background:rgba(255,255,255,.22)}
.main{flex:1;margin-left:230px;padding:28px}
.card{background:var(--card);border-radius:16px;padding:18px;margin-bottom:14px;box-shadow:0 8px 24px rgba(15,23,42,.06)}
.row{display:flex;gap:10px;flex-wrap:wrap;align-items:center}
.label{color:var(--muted);font-size:13px}
.value{font-weight:700}
.badge{display:inline-flex;align-items:center;gap:6px;padding:6px 10px;border-radius:999px;font-weight:700;font-size:12px}
.b-student{background:#e0f2fe;color:#075985}
.b-teacher{background:#dcfce7;color:#166534}
.b-admin{background:#fee2e2;color:#991b1b}
.btn{padding:10px 14px;border-radius:10px;border:0;cursor:pointer;font-weight:700}
.btn-primary{background:var(--blue-dark);color:#fff}
.btn-muted{background:#e5e7eb}
.btn-danger{background:var(--err);color:#fff}
.meta{color:var(--muted);font-size:13px}
@media (max-width: 768px){
  .main{margin-left:0;padding:20px}
  .sidebar{position:relative;width:100%;height:auto;inset:auto}
}
</style>
</head>
<body>
  <div class="sidebar">
    <h2>🛡️ แอดมิน</h2>
    <a href="admin_dashboard.php" class="<?= $current==='admin_dashboard.php'?'active':'' ?>"><i class="bi bi-speedometer2"></i> หน้าแรกแอดมิน</a>
    <a href="admin_users.php" class="active"><i class="bi bi-people-fill"></i> ผู้เข้าใช้ทั้งหมด</a>
    <a href="courses.php"><i class="bi bi-journal-bookmark-fill"></i> รายวิชา</a>
    <a href="teacher_assign_list.php"><i class="bi bi-card-checklist"></i> งานครู</a>
    <a href="logout.php"><i class="bi bi-box-arrow-right"></i> ออกจากระบบ</a>
  </div>

  <div class="main">
    <div class="card">
      <h2 style="margin:0 0 8px"><i class="bi bi-person-badge"></i> ผู้ใช้ #<?= (int)$u['user_id'] ?></h2>
      <?php if ($hasCreated || $hasUpdated): ?>
        <div class="meta">
          <?php if ($hasCreated): ?>สร้าง: <?= h($u['created_at'] ?? '—') ?><?php endif; ?>
          <?php if ($hasCreated && $hasUpdated): ?> • <?php endif; ?>
          <?php if ($hasUpdated): ?>อัปเดต: <?= h($u['updated_at'] ?? '—') ?><?php endif; ?>
        </div>
      <?php endif; ?>
    </div>

    <div class="card">
      <div class="row" style="align-items:flex-start">
        <div style="flex:1;min-width:260px">
          <div class="label">ชื่อ</div>
          <div class="value" style="margin-bottom:10px"><?= h($u['name']) ?></div>

          <div class="label">อีเมล</div>
          <div class="value" style="margin-bottom:10px"><?= h($u['email']) ?></div>

          <div class="label">บทบาท</div>
          <div style="margin-top:6px">
            <?php
              $rl = strtolower($u['role']);
              $b = $rl==='admin'?'b-admin':($rl==='teacher'?'b-teacher':'b-student');
            ?>
            <span class="badge <?= $b ?>"><i class="bi bi-person-badge-fill"></i> <?= strtoupper($rl) ?></span>
          </div>
        </div>

        <div style="min-width:240px;display:flex;flex-direction:column;gap:8px">
          <a class="btn btn-primary" href="user_edit.php?id=<?= (int)$u['user_id'] ?>"><i class="bi bi-pencil-square"></i> แก้ไขข้อมูล</a>
          <form method="post" action="user_delete.php" onsubmit="return confirm('ยืนยันลบผู้ใช้นี้? การกระทำนี้ย้อนกลับไม่ได้')">
            <input type="hidden" name="id" value="<?= (int)$u['user_id'] ?>">
            <button class="btn btn-danger" type="submit"><i class="bi bi-trash"></i> ลบผู้ใช้</button>
          </form>
          <a class="btn btn-muted" href="admin_users.php"><i class="bi bi-arrow-left"></i> กลับรายการ</a>
        </div>
      </div>
    </div>
  </div>
</body>
</html>
