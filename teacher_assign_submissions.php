<?php
// teacher_assign_submissions.php
session_start();
require_once __DIR__ . '/includes/auth.php';
require_login();
if (!is_teacher() && !is_admin()) { header("Location: dashboard.php"); exit; }
require_once __DIR__ . '/config/db.php';

$tid = current_user_id();
$aid = (int)($_GET['id'] ?? 0);
if (!$aid) { header("Location: teacher_assign_list.php"); exit; }

// ตรวจสิทธิ์: งานนี้ต้องอยู่ในคอร์สของครู
$chk = $pdo->prepare("
  SELECT a.assignment_id, a.title, a.due_date, c.title AS course_title
  FROM assignments a
  JOIN courses c ON c.course_id=a.course_id
  WHERE a.assignment_id=? AND c.teacher_id=?");
$chk->execute([$aid, $tid]);
$assign = $chk->fetch(PDO::FETCH_ASSOC);
if (!$assign) { die("ไม่พบงานนี้ หรือคุณไม่มีสิทธิ์เข้าถึง"); }

// อัปเดตคะแนน (ให้ครูกรอกแล้ว submit)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['score'])) {
    foreach ($_POST['score'] as $sid => $sc) {
        $sid = (int)$sid;
        $sc  = trim($sc);
        if ($sid <= 0) continue;
        // อัปเดตเฉพาะ record ที่อยู่ภายใต้ assignment นี้จริง
        $upd = $pdo->prepare("
            UPDATE submissions s
            JOIN assignments a ON a.assignment_id=s.assignment_id
            JOIN courses c ON c.course_id=a.course_id
            SET s.score = :sc
            WHERE s.submission_id=:sid AND s.assignment_id=:aid AND c.teacher_id=:tid
        ");
        $upd->execute([':sc'=>$sc === '' ? null : $sc, ':sid'=>$sid, ':aid'=>$aid, ':tid'=>$tid]);
    }
    $_SESSION['flash'] = "บันทึกคะแนนเรียบร้อย";
    header("Location: teacher_assign_submissions.php?id=".$aid);
    exit;
}

// โหลดรายการส่งงาน
$sql = "
SELECT s.submission_id, s.user_id, s.answer_text, s.file_path, s.score, s.submitted_at,
       u.name AS student_name, u.email
FROM submissions s
JOIN users u ON u.user_id=s.user_id
WHERE s.assignment_id=?
ORDER BY s.submitted_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute([$aid]);
$subs = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html>
<html lang="th">
<head>
<meta charset="utf-8">
<title>การส่งงาน: <?= htmlspecialchars($assign['title']) ?></title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
<style>
body{font-family:Prompt,system-ui,-apple-system,Segoe UI,Roboto; background:#f6f7fb; margin:0; color:#0f172a}
.wrap{max-width:980px;margin:40px auto;padding:0 16px}
.card{background:#fff;border-radius:14px;box-shadow:0 8px 24px rgba(15,23,42,.06);padding:18px;margin-bottom:14px}
table{width:100%;border-collapse:collapse}
th,td{padding:10px;border-bottom:1px solid #e5e7eb;text-align:left;vertical-align:top}
th{background:#eef2ff}
.input{padding:8px;border:1px solid #e5e7eb;border-radius:8px;width:90px}
.btn{padding:10px 14px;border-radius:10px;border:none;cursor:pointer;font-weight:700}
.btn-primary{background:#2563eb;color:#fff}
.badge{display:inline-block;background:#eef2ff;color:#1e3a8a;padding:6px 10px;border-radius:999px;font-weight:600}
.muted{color:#64748b}
.file a{color:#2563eb;text-decoration:none}
</style>
</head>
<body>
<div class="wrap">
  <div class="card">
    <h2 style="margin:6px 0 4px"><i class="bi bi-inbox"></i> การส่งงาน — <?= htmlspecialchars($assign['title']) ?></h2>
    <div class="muted">รายวิชา: <b><?= htmlspecialchars($assign['course_title']) ?></b> | กำหนดส่ง: <?= htmlspecialchars($assign['due_date'] ?: '—') ?></div>
    <?php if (!empty($_SESSION['flash'])): ?>
      <div class="badge" style="margin-top:8px"><?= htmlspecialchars($_SESSION['flash']); unset($_SESSION['flash']); ?></div>
    <?php endif; ?>
  </div>

  <div class="card">
    <?php if ($subs): ?>
      <form method="post">
        <table>
          <thead>
            <tr>
              <th style="width:220px">นักเรียน</th>
              <th>คำตอบ / ไฟล์</th>
              <th style="width:120px">คะแนน</th>
              <th style="width:180px">ส่งเมื่อ</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach($subs as $s): ?>
              <tr>
                <td>
                  <div><b><?= htmlspecialchars($s['student_name']) ?></b></div>
                  <div class="muted"><?= htmlspecialchars($s['email']) ?></div>
                </td>
                <td>
                  <?php if ($s['answer_text']): ?>
                    <div style="white-space:pre-line; margin-bottom:6px"><?= htmlspecialchars($s['answer_text']) ?></div>
                  <?php endif; ?>
                  <?php if ($s['file_path']): ?>
                    <div class="file"><i class="bi bi-paperclip"></i>
                      <a href="<?= htmlspecialchars($s['file_path']) ?>" target="_blank">เปิดไฟล์แนบ</a>
                    </div>
                  <?php endif; ?>
                </td>
                <td>
                  <input class="input" name="score[<?= (int)$s['submission_id'] ?>]" value="<?= htmlspecialchars($s['score'] ?? '') ?>" placeholder="0–100">
                </td>
                <td><?= htmlspecialchars($s['submitted_at']) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
        <div style="margin-top:12px">
          <button class="btn btn-primary" type="submit"><i class="bi bi-save2"></i> บันทึกคะแนนทั้งหมด</button>
          <a class="btn" href="teacher_assign_list.php" style="background:#e5e7eb;margin-left:6px">ย้อนกลับ</a>
        </div>
      </form>
    <?php else: ?>
      <p class="muted">ยังไม่มีการส่งงาน</p>
    <?php endif; ?>
  </div>
</div>
</body>
</html>
