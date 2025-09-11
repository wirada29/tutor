<?php
// teacher_assign_list.php
session_start();
require_once __DIR__ . '/includes/auth.php';
require_login();
if (!is_teacher() && !is_admin()) { header("Location: dashboard.php"); exit; }
require_once __DIR__ . '/config/db.php';

$tid = current_user_id();
$q   = trim($_GET['q'] ?? '');
$current = basename($_SERVER['PHP_SELF']);

// ดึงงานของครู พร้อมชื่อรายวิชา และจำนวนการส่ง
$sql = "
SELECT a.assignment_id, a.title, a.due_date, a.course_id, c.title AS course_title,
       (SELECT COUNT(*) FROM submissions s WHERE s.assignment_id=a.assignment_id) AS submit_count
FROM assignments a
JOIN courses c ON c.course_id=a.course_id
WHERE c.teacher_id = :tid
" . ($q !== '' ? " AND (a.title LIKE :kw OR c.title LIKE :kw) " : "") . "
ORDER BY a.assignment_id DESC";
$stmt = $pdo->prepare($sql);
$params = [':tid'=>$tid];
if ($q !== '') $params[':kw'] = "%$q%";
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html>
<html lang="th">
<head>
<meta charset="utf-8">
<title>งานที่มอบหมายของฉัน</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
<link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;600&display=swap" rel="stylesheet">
<style>
:root{
  --blue:#3b82f6; --blue-dark:#2563eb;
  --ink:#0f172a; --muted:#64748b; --bg:#f5f7fa;
  --surface:#ffffff;
}
*{box-sizing:border-box}
body{margin:0;font-family:'Sarabun',sans-serif;background:var(--bg);color:var(--ink);display:flex;min-height:100vh}

/* Sidebar (สไตล์เดียวกับหน้าครู) */
.sidebar{
  width:230px; background:linear-gradient(180deg,var(--blue),#2b6de1); color:#fff;
  height:100vh; padding:26px 16px; position:fixed; inset:0 auto 0 0; overflow-y:auto;
  box-shadow:0 6px 20px rgba(0,0,0,.08);
}
.sidebar h2{font-size:22px;font-weight:600;margin:0 0 24px;text-align:center}
.sidebar a{
  display:flex; align-items:center; gap:10px; color:#fff; text-decoration:none;
  margin-bottom:12px; padding:11px 10px; border-radius:10px; transition:transform .15s, background .2s, opacity .2s;
  opacity:.95
}
.sidebar a:hover{background:rgba(255,255,255,.15); transform:translateY(-1px); opacity:1}
.sidebar a.active{background:rgba(255,255,255,.22); box-shadow:inset 0 0 0 1px rgba(255,255,255,.18)}

/* Main */
.main{flex:1; margin-left:230px; padding:28px}
.card{
  background:var(--surface); padding:18px; border-radius:14px;
  box-shadow:0 8px 24px rgba(15,23,42,.06); margin-bottom:14px
}
.row{display:flex;gap:10px;flex-wrap:wrap}
.input{padding:10px;border:1px solid #e5e7eb;border-radius:10px}
.btn{padding:10px 14px;border-radius:10px;border:none;cursor:pointer;font-weight:700}
.btn-primary{background:var(--blue-dark);color:#fff}
.btn-muted{background:#e5e7eb}
.btn-green{background:#22c55e;color:#fff}
table{width:100%;border-collapse:collapse}
th,td{padding:10px;border-bottom:1px solid #e5e7eb;text-align:left}
th{background:#eef2ff}
.badge{display:inline-block;background:#eef2ff;color:#1e3a8a;padding:6px 10px;border-radius:999px;font-weight:600}

@media(max-width:992px){
  .sidebar{position:relative;width:100%;height:auto;inset:auto}
  .main{margin-left:0;padding:20px}
}
</style>
</head>
<body>

<!-- Sidebar ครู -->
<div class="sidebar">
  <h2>📘 งานที่มอบหมาย</h2>

    <a href="teacher_dashboard.php"><i class="bi bi-house"></i> หน้าหลัก</a>
    <a href="content_manage.php"><i class="bi bi-folder2-open"></i> เนื้อหา/เอกสาร</a>
    <?php if (is_teacher() || is_admin()): ?>
      <a href="teacher_assign_list.php"><i class="bi bi-card-checklist"></i> งานที่มอบหมาย</a>
      <a href="teacher_assign_create.php"><i class="bi bi-clipboard-plus"></i> สร้างงานใหม่</a>
    <?php endif; ?>

    <a href="attendance_manage.php"><i class="bi bi-clipboard-check"></i> เช็คชื่อ</a>
    <a href="behavior_manage.php"><i class="bi bi-emoji-smile"></i> ความประพฤติ</a>
    <a href="logout.php"><i class="bi bi-box-arrow-right"></i> ออกจากระบบ</a>
  </div>

<!-- Main -->
<div class="main">
  <div class="card">
    <h2 style="margin:6px 0 12px"><i class="bi bi-card-checklist"></i> งานที่มอบหมาย</h2>
    <?php if (!empty($_SESSION['flash'])): ?>
      <div class="badge"><?= htmlspecialchars($_SESSION['flash']); unset($_SESSION['flash']); ?></div>
    <?php endif; ?>
    <form class="row" method="get">
      <input class="input" name="q" placeholder="ค้นหาชื่อวิชาหรือชื่องาน..." value="<?= htmlspecialchars($q) ?>">
      <button class="btn btn-primary" type="submit"><i class="bi bi-search"></i> ค้นหา</button>
      <?php if ($q !== ''): ?><a class="btn btn-muted" href="?">ล้างคำค้น</a><?php endif; ?>
      <a class="btn btn-green" href="teacher_assign_create.php"><i class="bi bi-plus-circle"></i> สร้างงาน</a>
    </form>
  </div>

  <div class="card">
    <?php if ($rows): ?>
      <table>
        <thead>
          <tr>
            <th>วิชา</th>
            <th>หัวข้องาน</th>
            <th>กำหนดส่ง</th>
            <th>ส่งแล้ว</th>
            <th>จัดการ</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach($rows as $r): ?>
            <tr>
              <td><?= htmlspecialchars($r['course_title']) ?></td>
              <td><?= htmlspecialchars($r['title']) ?></td>
              <td><?= htmlspecialchars($r['due_date'] ?: '—') ?></td>
              <td><?= (int)$r['submit_count'] ?></td>
              <td>
                <a class="btn btn-primary" href="teacher_assign_submissions.php?id=<?= (int)$r['assignment_id'] ?>">
                  <i class="bi bi-inbox"></i> ดูการส่ง
                </a>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php else: ?>
      <p style="color:var(--muted);">ยังไม่มีงานที่มอบหมาย</p>
    <?php endif; ?>
  </div>
</div>

</body>
</html>
