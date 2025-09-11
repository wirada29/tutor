<?php
// attendance_manage.php
session_start();
require_once __DIR__.'/includes/auth.php';
require_login(); if(!is_teacher() && !is_admin()){ header("Location: dashboard.php"); exit; }
require_once __DIR__.'/config/db.php';

$tid = current_user_id();
$course_id = (int)($_GET['course_id'] ?? 0);
$date = $_GET['date'] ?? date('Y-m-d');

// รายวิชาของครู
$courses = $pdo->prepare("SELECT course_id,title FROM courses WHERE teacher_id=? ORDER BY title");
$courses->execute([$tid]);
$courses = $courses->fetchAll(PDO::FETCH_ASSOC);

// ตรวจสิทธิ์คอร์ส
if($course_id){
  $chk = $pdo->prepare("SELECT COUNT(*) FROM courses WHERE course_id=? AND teacher_id=?");
  $chk->execute([$course_id,$tid]);
  if(!$chk->fetchColumn() && !is_admin()){ die('Forbidden'); }
}

$msg = '';
// บันทึก
if($_SERVER['REQUEST_METHOD']==='POST' && $course_id){
  $date = $_POST['attended_on'] ?? $date;
  // รายชื่อก่อน
  $st = $pdo->prepare("SELECT u.user_id FROM enrollments e JOIN users u ON u.user_id=e.user_id WHERE e.course_id=? AND e.status='active'");
  $st->execute([$course_id]);
  $students = $st->fetchAll(PDO::FETCH_COLUMN);

  foreach($students as $sid){
    $status = $_POST['status_'.$sid] ?? 'present';
    $note   = $_POST['note_'.$sid]   ?? null;
    $sql = "INSERT INTO attendance(course_id,student_id,attended_on,status,note)
            VALUES(?,?,?,?,?)
            ON DUPLICATE KEY UPDATE status=VALUES(status), note=VALUES(note)";
    $pdo->prepare($sql)->execute([$course_id,$sid,$date,$status,$note]);
  }
  $msg = 'บันทึกเรียบร้อย';
}

// โหลดรายชื่อ+สถานะวันที่เลือก
$list = [];
if($course_id){
  $sql = "SELECT u.user_id,u.name,a.status,a.note
          FROM enrollments e
          JOIN users u ON u.user_id=e.user_id
          LEFT JOIN attendance a ON a.course_id=e.course_id AND a.student_id=e.user_id AND a.attended_on=?
          WHERE e.course_id=? AND e.status='active'
          ORDER BY u.name";
  $st = $pdo->prepare($sql);
  $st->execute([$date,$course_id]);
  $list = $st->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="th"><head>
<meta charset="UTF-8"><title>เช็คชื่อ</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;600&display=swap" rel="stylesheet">
<style>
:root{--blue:#3b82f6;--ink:#0f172a;--muted:#64748b;--bg:#f5f7fa}
*{box-sizing:border-box} body{margin:0;font-family:'Sarabun',sans-serif;background:var(--bg);color:var(--ink);display:flex}
.sidebar{width:230px;background:linear-gradient(180deg,var(--blue),#2b6de1);color:#fff;padding:26px 16px;position:fixed;inset:0 auto 0 0}
.sidebar h2{margin:0 0 24px;text-align:center;font-size:22px;font-weight:600}
.sidebar a{display:flex;align-items:center;gap:10px;color:#fff;text-decoration:none;margin-bottom:12px;padding:11px;border-radius:10px}
.sidebar a:hover{background:rgba(255,255,255,.12)}
.main{flex:1;margin-left:230px;padding:28px}
.card{background:#fff;padding:20px;border-radius:14px;box-shadow:0 6px 22px rgba(15,23,42,.06);margin-bottom:20px}
.table{width:100%;border-collapse:collapse}
.table th,.table td{padding:10px;border-bottom:1px solid #e5e7eb;text-align:left}
.select{padding:8px;border:1px solid #e5e7eb;border-radius:8px}
.badge{display:inline-block;background:#eef2ff;color:#1e3a8a;padding:6px 10px;border-radius:999px;font-weight:600}
</style>
</head>
<body>
  <div class="sidebar">
    <h2>✅ เช็คชื่อ</h2>
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
  </div>

  <div class="main">
    <div class="card">
      <h2 style="margin:0 0 12px">เช็คชื่อ</h2>
      <form method="get" style="display:flex;gap:10px;flex-wrap:wrap">
        <div>
          <label>วิชา</label>
          <select class="select" name="course_id" onchange="this.form.submit()">
            <?php foreach($courses as $c): ?>
              <option value="<?= (int)$c['course_id'] ?>" <?= $course_id==$c['course_id']?'selected':'' ?>>
                <?= htmlspecialchars($c['title']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label>วันที่</label>
          <input class="select" type="date" name="date" value="<?= htmlspecialchars($date) ?>" onchange="this.form.submit()">
        </div>
      </form>
      <?php if($msg): ?><p class="badge" style="margin-top:10px"><?= htmlspecialchars($msg) ?></p><?php endif; ?>
    </div>

    <?php if($course_id): ?>
    <div class="card">
      <form method="post">
        <input type="hidden" name="attended_on" value="<?= htmlspecialchars($date) ?>">
        <table class="table">
          <thead><tr><th>นักเรียน</th><th>สถานะ</th><th>หมายเหตุ</th></tr></thead>
          <tbody>
          <?php foreach($list as $row): $sid=(int)$row['user_id']; $st=$row['status']??'present'; ?>
            <tr>
              <td><?= htmlspecialchars($row['name']) ?></td>
              <td>
                <select name="status_<?= $sid ?>" class="select">
                  <option value="present" <?= $st==='present'?'selected':'' ?>>มา</option>
                  <option value="late"    <?= $st==='late'   ?'selected':'' ?>>สาย</option>
                  <option value="absent"  <?= $st==='absent' ?'selected':'' ?>>ขาด</option>
                </select>
              </td>
              <td><input class="select" style="width:100%" name="note_<?= $sid ?>" value="<?= htmlspecialchars($row['note']??'') ?>"></td>
            </tr>
          <?php endforeach; if(!$list): ?>
            <tr><td colspan="3" class="muted">ไม่มีนักเรียนในคอร์สนี้</td></tr>
          <?php endif; ?>
          </tbody>
        </table>
        <?php if($list): ?><button class="badge" style="border:0;cursor:pointer;margin-top:10px">บันทึกทั้งหมด</button><?php endif; ?>
      </form>
    </div>
    <?php endif; ?>
  </div>
</body></html>
