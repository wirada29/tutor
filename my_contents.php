<?php
session_start();
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';

require_login();
$uid = current_user_id();

// ดึงคอนเทนต์ของวิชาที่ user ลงทะเบียนแล้ว
$st = $pdo->prepare("
  SELECT cc.*
  FROM course_contents cc
  JOIN enrollments e ON e.course_id = cc.course_id
  WHERE e.user_id = ?
    AND (e.status IS NULL OR e.status='active')
  ORDER BY cc.created_at DESC
  LIMIT 0, 25
");
$st->execute([$uid]);
$rows = $st->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html>
<html lang="th">
<head>
  <meta charset="utf-8">
  <title>เนื้อหาวิชาของฉัน</title>
</head>
<body>
  <h2>📂 เนื้อหาวิชาของฉัน</h2>
  <?php if ($rows): ?>
    <ul>
      <?php foreach ($rows as $r): ?>
        <li>
          <?= htmlspecialchars($r['title']) ?> 
          (<?= htmlspecialchars($r['type']) ?>)
          <?php if ($r['file_path']): ?>
            - <a href="<?= htmlspecialchars($r['file_path']) ?>" target="_blank">เปิดไฟล์</a>
          <?php endif; ?>
          <?php if ($r['video_url']): ?>
            - <a href="<?= htmlspecialchars($r['video_url']) ?>" target="_blank">ดูวิดีโอ</a>
          <?php endif; ?>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php else: ?>
    <p>ยังไม่มีเนื้อหาที่คุณลงทะเบียนเรียน</p>
  <?php endif; ?>
</body>
</html>
