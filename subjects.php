<?php
session_start();
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';
require_login();

$role = strtolower($_SESSION['user']['role'] ?? 'student');
$q = trim($_GET['q'] ?? '');

$sql = "SELECT id, code, name, credit FROM subjects";
$args = [];
if ($q !== '') {
  $sql .= " WHERE code LIKE ? OR name LIKE ?";
  $args = ["%$q%", "%$q%"];
}
$sql .= " ORDER BY code ASC";
$st = $pdo->prepare($sql);
$st->execute($args);
$rows = $st->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html><html lang="th"><head>
<meta charset="utf-8"><title>รายวิชา (Subjects)</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
<link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;600&display=swap" rel="stylesheet">
<style>
:root{--blue:#3b82f6;--blue2:#2563eb;--ink:#0f172a;--muted:#64748b;--bg:#f5f7fa;--card:#fff;--line:#e5e7eb}
*{box-sizing:border-box} body{margin:0;font-family:'Sarabun',sans-serif;background:var(--bg);color:var(--ink);display:flex;min-height:100vh}
.sidebar{width:230px;background:linear-gradient(180deg,var(--blue),#2b6de1);color:#fff;padding:26px 16px;position:fixed;inset:0 auto 0 0}
.sidebar h2{margin:0 0 24px;text-align:center;font-size:22px;font-weight:600}
.sidebar a{display:flex;align-items:center;gap:10px;color:#fff;text-decoration:none;margin-bottom:12px;padding:11px;border-radius:10px}
.sidebar a:hover{background:rgba(255,255,255,.12)}
.main{flex:1;margin-left:230px;padding:28px}
.card{background:var(--card);padding:18px;border-radius:14px;box-shadow:0 6px 22px rgba(15,23,42,.06);margin-bottom:14px}
.row{display:flex;gap:10px;flex-wrap:wrap;align-items:center}
.input, .btn, .select{padding:10px 12px;border-radius:10px;border:1px solid var(--line)}
.btn{background:var(--blue2);color:#fff;border:none;font-weight:700;cursor:pointer}
.btn-muted{background:#e5e7eb;color:#111}
table{width:100%;border-collapse:collapse} th,td{padding:10px;border-bottom:1px solid var(--line);text-align:left}
th{background:#eef2ff}
</style></head><body>

<div class="sidebar">
  <h2>📘 เมนู</h2>
  <a href="dashboard.php"><i class="bi bi-house-fill"></i> หน้าแรก</a>
  <a href="courses.php"><i class="bi bi-journal-bookmark-fill"></i> รายวิชาเรียน (Courses)</a>
  <a href="subjects.php"><i class="bi bi-book"></i> วิชา (Subjects)</a>
  <a href="my_enrollments.php"><i class="bi bi-list-check"></i> การลงทะเบียนของฉัน</a>
  <?php if ($role==='teacher' || $role==='admin'): ?>
    <a href="subject_add.php"><i class="bi bi-plus-circle"></i> เพิ่มวิชา</a>
  <?php endif; ?>
  <a href="logout.php"><i class="bi bi-box-arrow-right"></i> ออกจากระบบ</a>
</div>

<div class="main">
  <div class="card">
    <h2 style="margin:0">📚 วิชาทั้งหมด (Subjects)</h2>
    <form class="row" method="get" style="margin-top:10px">
      <input class="input" name="q" placeholder="ค้นหา (รหัส/ชื่อวิชา)..." value="<?= htmlspecialchars($q) ?>">
      <button class="btn" type="submit"><i class="bi bi-search"></i> ค้นหา</button>
      <?php if ($q!==''): ?><a class="btn-muted" href="subjects.php" style="text-decoration:none"><i class="bi bi-eraser"></i> ล้าง</a><?php endif; ?>
      <?php if ($role==='teacher' || $role==='admin'): ?>
        <a class="btn" href="subject_add.php" style="text-decoration:none;background:#22c55e"><i class="bi bi-plus-circle"></i> เพิ่มวิชา</a>
      <?php endif; ?>
    </form>
  </div>

  <div class="card">
    <?php if ($rows): ?>
      <table>
        <thead><tr><th style="width:120px">รหัสวิชา</th><th>ชื่อวิชา</th><th style="width:120px">หน่วยกิต</th></tr></thead>
        <tbody>
          <?php foreach($rows as $s): ?>
            <tr>
              <td><?= htmlspecialchars($s['code']) ?></td>
              <td><?= htmlspecialchars($s['name']) ?></td>
              <td><?= (int)$s['credit'] ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php else: ?>
      <p style="color:var(--muted)">ยังไม่มีข้อมูลวิชา</p>
    <?php endif; ?>
  </div>
</div>
</body></html>
