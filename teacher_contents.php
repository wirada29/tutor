<?php
session_start();
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';
require_login();

$uid  = current_user_id();
$role = current_user_role();

// course_id ที่ครูต้องการจัดการ
$courseId = (int)($_GET['course_id'] ?? 0);
if ($courseId <= 0) { header('Location: courses.php'); exit; }

// ตรวจสิทธิ์: ครูต้องเป็นผู้สอนของคอร์สนี้ หรือเป็นแอดมิน
$st = $pdo->prepare("SELECT c.*, u.name AS teacher_name FROM courses c LEFT JOIN users u ON u.user_id=c.teacher_id WHERE c.course_id=?");
$st->execute([$courseId]);
$course = $st->fetch(PDO::FETCH_ASSOC);

if (!$course) { die('Course not found'); }
if (!is_admin() && (!$course['teacher_id'] || (int)$course['teacher_id'] !== $uid)) {
  die('คุณไม่มีสิทธิ์จัดการคอร์สนี้');
}

// โหลดเนื้อหาทั้งหมด
$st = $pdo->prepare("SELECT * FROM contents WHERE course_id=? ORDER BY created_at DESC");
$st->execute([$courseId]);
$contents = $st->fetchAll(PDO::FETCH_ASSOC);

// flash message
$ok  = $_SESSION['flash_ok']  ?? '';
$err = $_SESSION['flash_err'] ?? '';
unset($_SESSION['flash_ok'], $_SESSION['flash_err']);
?>
<!doctype html>
<html lang="th">
<head>
<meta charset="utf-8">
<title>จัดการเนื้อหา | <?= htmlspecialchars($course['title']) ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
<style>
:root{--blue:#3b82f6;--ink:#0f172a;--muted:#64748b;--bg:#f5f7fa;--ok:#16a34a;--err:#ef4444;--line:#e5e7eb}
*{box-sizing:border-box} body{margin:0;font-family:Prompt,system-ui,Segoe UI,Roboto;background:var(--bg);color:var(--ink)}
.wrap{max-width:1024px;margin:32px auto;padding:0 16px}
.h{display:flex;align-items:center;gap:10px;margin-bottom:12px}
.card{background:#fff;border-radius:16px;box-shadow:0 8px 24px rgba(15,23,42,.06);padding:18px;margin-bottom:16px}
.row{display:grid;grid-template-columns:1fr auto;gap:12px;align-items:start}
.badge{background:#eef2ff;color:#1e3a8a;border-radius:999px;padding:6px 10px;font-weight:700;font-size:13px}
.alert{padding:10px 12px;border-radius:10px;margin-bottom:10px}
.ok{background:#dcfce7} .err{background:#fee2e2}
table{width:100%;border-collapse:collapse} th,td{padding:10px;border-bottom:1px solid var(--line);vertical-align:top}
th{background:#eef2ff}
.input,select,textarea{width:100%;padding:10px 12px;border:1px solid var(--line);border-radius:10px;font:inherit}
.btn{padding:10px 14px;border-radius:10px;border:0;cursor:pointer;font-weight:700}
.btn-pri{background:var(--blue);color:#fff}
.btn-del{background:var(--err);color:#fff}
.small{color:var(--muted);font-size:12px}
.type-pill{display:inline-block;border-radius:999px;padding:4px 8px;font-size:12px;font-weight:700;background:#e2e8f0}
</style>
</head>
<body>
<div class="wrap">
  <div class="h">
    <h2 style="margin:0">📚 จัดการเนื้อหา: <?= htmlspecialchars($course['title']) ?></h2>
    <span class="badge"><i class="bi bi-person-badge-fill"></i> ครู: <?= htmlspecialchars($course['teacher_name'] ?? '—') ?></span>
  </div>

  <?php if ($ok): ?><div class="alert ok"><i class="bi bi-check-circle"></i> <?= htmlspecialchars($ok) ?></div><?php endif; ?>
  <?php if ($err): ?><div class="alert err"><i class="bi bi-exclamation-triangle"></i> <?= htmlspecialchars($err) ?></div><?php endif; ?>

  <div class="card">
    <h3 style="margin-top:0">➕ เพิ่มเนื้อหาใหม่</h3>
    <form method="post" action="content_save.php" enctype="multipart/form-data">
      <input type="hidden" name="course_id" value="<?= (int)$courseId ?>">
      <div style="display:grid;grid-template-columns:1fr 200px;gap:12px">
        <div>
          <label>ชื่อเรื่อง</label>
          <input class="input" name="title" required>
        </div>
        <div>
          <label>ชนิดเนื้อหา</label>
          <select class="input" name="type" id="typeSelect">
            <option value="text">บทความ/โน้ต (text)</option>
            <option value="file">ไฟล์เอกสาร (file)</option>
            <option value="video">วิดีโอ (video)</option>
            <option value="link">ลิงก์ภายนอก (link)</option>
          </select>
        </div>
      </div>

      <div id="field_text" style="margin-top:10px">
        <label>เนื้อหา (ตัวหนังสือ)</label>
        <textarea class="input" name="body" rows="6" placeholder="พิมพ์บทความ/สรุปบทเรียนที่นี่"></textarea>
      </div>

      <div id="field_file" style="display:none; margin-top:10px">
        <label>แนบไฟล์ (pdf/docx/pptx/jpg/png สูงสุด ~20MB)</label>
        <input class="input" type="file" name="file">
      </div>

      <div id="field_video" style="display:none; margin-top:10px">
        <label>ลิงก์วิดีโอ (เช่น https://www.youtube.com/watch?v=...)</label>
        <input class="input" name="video_url" placeholder="ใส่ URL วิดีโอ">
      </div>

      <div id="field_link" style="display:none; margin-top:10px">
        <label>ลิงก์เอกสารภายนอก (Google Drive/OneDrive ฯลฯ)</label>
        <input class="input" name="link_url" placeholder="ใส่ URL">
      </div>

      <button class="btn btn-pri" type="submit"><i class="bi bi-upload"></i> บันทึกเนื้อหา</button>
      <div class="small" style="margin-top:6px">ไฟล์จะถูกเก็บที่โฟลเดอร์ <code>uploads/contents</code></div>
    </form>
  </div>

  <div class="card">
    <h3 style="margin-top:0">📄 เนื้อหาทั้งหมด</h3>
    <?php if ($contents): ?>
      <table>
        <thead>
          <tr>
            <th style="width:220px">หัวข้อ</th>
            <th>รายละเอียด</th>
            <th style="width:160px">อัปเดตล่าสุด</th>
            <th style="width:120px"></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach($contents as $ct): ?>
            <tr>
              <td>
                <div><strong><?= htmlspecialchars($ct['title']) ?></strong></div>
                <div class="small"><span class="type-pill"><?= htmlspecialchars($ct['type']) ?></span></div>
              </td>
              <td class="small">
                <?php if ($ct['type']==='text'): ?>
                  <?= nl2br(htmlspecialchars(mb_strimwidth($ct['body'] ?? '', 0, 250, '...'))) ?>
                <?php endif; ?>
                <?php if ($ct['type']==='file' && $ct['file_path']): ?>
                  <a href="<?= htmlspecialchars($ct['file_path']) ?>" target="_blank"><i class="bi bi-paperclip"></i> ดาวน์โหลดไฟล์</a>
                <?php endif; ?>
                <?php if ($ct['type']==='video' && $ct['video_url']): ?>
                  <a href="<?= htmlspecialchars($ct['video_url']) ?>" target="_blank"><i class="bi bi-play-circle"></i> เปิดวิดีโอ</a>
                <?php endif; ?>
                <?php if ($ct['type']==='link' && $ct['link_url']): ?>
                  <a href="<?= htmlspecialchars($ct['link_url']) ?>" target="_blank"><i class="bi bi-link-45deg"></i> เปิดลิงก์</a>
                <?php endif; ?>
              </td>
              <td class="small"><?= htmlspecialchars($ct['updated_at'] ?? $ct['created_at']) ?></td>
              <td>
                <form method="post" action="content_delete.php" onsubmit="return confirm('ลบเนื้อหานี้?')">
                  <input type="hidden" name="course_id" value="<?= (int)$courseId ?>">
                  <input type="hidden" name="content_id" value="<?= (int)$ct['content_id'] ?>">
                  <button class="btn btn-del" type="submit"><i class="bi bi-trash"></i> ลบ</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php else: ?>
      <p class="small" style="color:var(--muted)">ยังไม่มีเนื้อหาในรายวิชานี้</p>
    <?php endif; ?>
  </div>
</div>

<script>
const sel = document.getElementById('typeSelect');
const F = {
  text:  document.getElementById('field_text'),
  file:  document.getElementById('field_file'),
  video: document.getElementById('field_video'),
  link:  document.getElementById('field_link'),
};
function toggleFields(){
  const t = sel.value;
  for (const k in F){ F[k].style.display = (k===t)?'block':'none'; }
}
sel.addEventListener('change', toggleFields);
toggleFields();
</script>
</body>
</html>
