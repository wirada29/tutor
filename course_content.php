<?php
session_start();
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';
require_login();

$uid = current_user_id();
$cid = (int)($_GET['course_id'] ?? 0);
if ($cid<=0){ header('Location: courses.php'); exit; }

// ตรวจว่านักเรียนลงทะเบียนคอร์สนี้แล้ว (active)
$st = $pdo->prepare("SELECT COUNT(*) FROM enrollments WHERE user_id=? AND course_id=? AND status='active'");
$st->execute([$uid, $cid]);
if ((int)$st->fetchColumn() === 0 && !is_admin() && !is_teacher()){
  die('ต้องลงทะเบียนวิชานี้ก่อนจึงจะเข้าถึงเนื้อหาได้');
}

$st = $pdo->prepare("SELECT c.*, u.name AS teacher_name FROM courses c LEFT JOIN users u ON u.user_id=c.teacher_id WHERE c.course_id=?");
$st->execute([$cid]);
$course = $st->fetch(PDO::FETCH_ASSOC);

$st = $pdo->prepare("SELECT * FROM contents WHERE course_id=? ORDER BY created_at DESC");
$st->execute([$cid]);
$items = $st->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html>
<html lang="th">
<head>
<meta charset="utf-8">
<title>เนื้อหารายวิชา | <?= htmlspecialchars($course['title'] ?? 'Course') ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
<style>
:root{--blue:#3b82f6;--ink:#0f172a;--muted:#64748b;--bg:#f5f7fa;--line:#e5e7eb}
*{box-sizing:border-box} body{margin:0;font-family:Prompt,system-ui,Segoe UI,Roboto;background:var(--bg);color:var(--ink)}
.wrap{max-width:980px;margin:32px auto;padding:0 16px}
.header{display:flex;align-items:center;gap:10px;margin-bottom:12px}
.badge{background:#eef2ff;color:#1e3a8a;border-radius:999px;padding:6px 10px;font-weight:700;font-size:13px}
.card{background:#fff;border-radius:16px;box-shadow:0 8px 24px rgba(15,23,42,.06);padding:18px;margin-bottom:14px}
.title{margin:0 0 6px;font-size:18px}
.small{color:var(--muted);font-size:13px}
.file{display:inline-flex;align-items:center;gap:8px;padding:10px 12px;border:1px solid var(--line);border-radius:12px}
iframe{max-width:100%;border:0;border-radius:12px}
</style>
</head>
<body>
<div class="wrap">
  <div class="header">
    <h2 style="margin:0">📘 <?= htmlspecialchars($course['title'] ?? 'Course') ?></h2>
    <span class="badge"><i class="bi bi-person-badge-fill"></i> ครู: <?= htmlspecialchars($course['teacher_name'] ?? '—') ?></span>
  </div>

  <?php if ($items): foreach($items as $ct): ?>
    <div class="card">
      <h3 class="title"><?= htmlspecialchars($ct['title']) ?> <span class="small">— <?= htmlspecialchars($ct['type']) ?></span></h3>

      <?php if ($ct['type']==='text'): ?>
        <div class="small"><?= nl2br(htmlspecialchars($ct['body'] ?? '')) ?></div>

      <?php elseif ($ct['type']==='file' && $ct['file_path']): ?>
        <a class="file" href="<?= htmlspecialchars($ct['file_path']) ?>" target="_blank">
          <i class="bi bi-paperclip"></i> ดาวน์โหลดไฟล์แนบ
        </a>

      <?php elseif ($ct['type']==='video' && $ct['video_url']): 
        // แปลง YouTube link เป็น embed แบบง่าย ๆ
        $url = $ct['video_url'];
        if (preg_match('~youtu\.be/([A-Za-z0-9_-]+)~',$url,$m) || preg_match('~v=([A-Za-z0-9_-]+)~',$url,$m)) {
          $embed = 'https://www.youtube.com/embed/'.$m[1];
        } else {
          $embed = $url;
        }
      ?>
        <?php if (str_contains($embed, 'youtube.com/embed')): ?>
          <div style="position:relative;padding-top:56.25%">
            <iframe src="<?= htmlspecialchars($embed) ?>" allowfullscreen style="position:absolute;inset:0;width:100%;height:100%"></iframe>
          </div>
        <?php else: ?>
          <a class="file" href="<?= htmlspecialchars($ct['video_url']) ?>" target="_blank"><i class="bi bi-play-circle"></i> เปิดวิดีโอ</a>
        <?php endif; ?>

      <?php elseif ($ct['type']==='link' && $ct['link_url']): ?>
        <a class="file" href="<?= htmlspecialchars($ct['link_url']) ?>" target="_blank"><i class="bi bi-link-45deg"></i> เปิดลิงก์</a>
      <?php endif; ?>

      <div class="small" style="margin-top:8px;color:#64748b">เผยแพร่เมื่อ: <?= htmlspecialchars($ct['created_at']) ?></div>
    </div>
  <?php endforeach; else: ?>
    <div class="card small" style="color:#64748b">ยังไม่มีเนื้อหาในรายวิชานี้</div>
  <?php endif; ?>
</div>
</body>
</html>
