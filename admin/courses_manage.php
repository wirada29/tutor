<?php
// courses_manage.php
session_start();
require_once __DIR__.'/../includes/auth.php';
require_admin();
require_once __DIR__.'/../config/db.php';

// list courses + seats
$sql = "
  SELECT
    c.course_id, c.title, c.status,
    c.enroll_open, c.enroll_close, c.max_seats,
    (SELECT COUNT(*) FROM enrollments e
      WHERE e.course_id=c.course_id AND e.status='active') AS seats_used
  FROM courses c
  ORDER BY c.title";
$rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <title>จัดการรายวิชา</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
  <style>
    body { font-family: sans-serif; background:#f5f7fa; padding:20px }
    .card { background:#fff; padding:16px; border-radius:12px; margin-bottom:12px; box-shadow:0 2px 6px rgba(0,0,0,.05) }
    .pill { padding:3px 8px; border-radius:999px; background:#eef2ff; color:#1e3a8a; font-size:13px }
    .btn { padding:8px 14px; border:none; border-radius:8px; cursor:pointer; font-weight:600 }
    .btn-primary { background:#2563eb; color:#fff }
    .btn-danger  { background:#ef4444; color:#fff }
  </style>
</head>
<body>

<h2>📘 จัดการรายวิชา</h2>

<?php foreach($rows as $r): 
  $left = ($r['max_seats']? max(0, (int)$r['max_seats'] - (int)$r['seats_used']) : null);
?>
  <div class="card">
    <div><b><?=htmlspecialchars($r['title'])?></b></div>
    <div>
      สถานะ: <span class="pill"><?=htmlspecialchars($r['status'])?></span>
      • ที่นั่ง: <?= $r['max_seats'] ? "{$left} / {$r['max_seats']}" : 'ไม่จำกัด' ?>
      • รับ: <?= $r['enroll_open'] ?: '—' ?> ถึง <?= $r['enroll_close'] ?: '—' ?>
    </div>
    <form method="post" action="course_toggle.php" style="margin-top:8px;">
      <input type="hidden" name="course_id" value="<?= (int)$r['course_id'] ?>">
      <?php if (strtolower($r['status'])==='open'): ?>
        <input type="hidden" name="action" value="close">
        <button class="btn btn-danger"><i class="bi bi-toggle-off"></i> ปิดการลงทะเบียน</button>
      <?php else: ?>
        <input type="hidden" name="action" value="open">
        <button class="btn btn-primary"><i class="bi bi-toggle-on"></i> เปิดการลงทะเบียน</button>
      <?php endif; ?>
    </form>
  </div>
<?php endforeach; ?>

</body>
</html>
