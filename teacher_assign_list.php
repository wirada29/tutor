<?php
// teacher_assign_list.php
session_start();
require_once __DIR__ . '/includes/auth.php';
require_login();
if (!is_teacher() && !is_admin()) { header("Location: dashboard.php"); exit; }
require_once __DIR__ . '/config/db.php';

$tid = current_user_id();
$q = trim($_GET['q'] ?? '');

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
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
<style>
body{font-family:Prompt,system-ui,-apple-system,Segoe UI,Roboto; background:#f6f7fb; margin:0; color:#0f172a}
.wrap{max-width:980px;margin:40px auto;padding:0 16px}
.card{background:#fff;border-radius:14px;box-shadow:0 8px 24px rgba(15,23,42,.06);padding:18px;margin-bottom:14px}
.row{display:flex;gap:10px;flex-wrap:wrap}
.input{padding:10px;border:1px solid #e5e7eb;border-radius:10px}
.btn{padding:10px 14px;border-radius:10px;border:none;cursor:pointer;font-weight:700}
.btn-primary{background:#2563eb;color:#fff}
.btn-muted{background:#e5e7eb}
table{width:100%;border-collapse:collapse}
th,td{padding:10px;border-bottom:1px solid #e5e7eb;text-align:left}
th{background:#eef2ff}
.badge{display:inline-block;background:#eef2ff;color:#1e3a8a;padding:6px 10px;border-radius:999px;font-weight:600}
</style>
</head>
<body>
<div class="wrap">
  <div class="card">
    <h2 style="margin:6px 0 12px"><i class="bi bi-card-checklist"></i> งานที่มอบหมาย</h2>
    <?php if (!empty($_SESSION['flash'])): ?>
      <div class="badge"><?= htmlspecialchars($_SESSION['flash']); unset($_SESSION['flash']); ?></div>
    <?php endif; ?>
    <form class="row" method="get">
      <input class="input" name="q" placeholder="ค้นหาชื่อวิชาหรือชื่องาน..." value="<?= htmlspecialchars($q) ?>">
      <button class="btn btn-primary" type="submit"><i class="bi bi-search"></i> ค้นหา</button>
      <?php if ($q !== ''): ?><a class="btn btn-muted" href="?">ล้างคำค้น</a><?php endif; ?>
      <a class="btn" href="teacher_assign_create.php" style="background:#22c55e;color:#fff"><i class="bi bi-plus-circle"></i> สร้างงาน</a>
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
      <p style="color:#64748b;">ยังไม่มีงานที่มอบหมาย</p>
    <?php endif; ?>
  </div>
</div>
</body>
</html>
