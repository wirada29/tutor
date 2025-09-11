<?php
// behavior_manage.php
session_start();
require_once __DIR__.'/includes/auth.php';
require_login(); if(!is_teacher() && !is_admin()){ header("Location: dashboard.php"); exit; }
require_once __DIR__.'/config/db.php';

$tid = current_user_id();
$course_id = (int)($_GET['course_id'] ?? 0);

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
// บันทึกรายการใหม่
if($_SERVER['REQUEST_METHOD']==='POST' && $course_id){
  $sid    = (int)$_POST['student_id'];
  $type   = $_POST['type'] ?? 'neutral';
  $points = (int)($_POST['points'] ?? 0);
  $remark = trim($_POST['remark'] ?? '');
  $sql = "INSERT INTO behavior_reports(user_id,course_id,type,points,remark,reported_by) VALUES(?,?,?,?,?,?)";
  $pdo->prepare($sql)->execute([$sid,$course_id,$type,$points,$remark,$tid]);
  $msg = 'บันทึกแล้ว';
}

// รายชื่อนักเรียนในคอร์ส
$students = [];
if($course_id){
  $st = $pdo->prepare("SELECT u.user_id,u.name FROM enrollments e JOIN users u ON u.user_id=e.user_id
                       WHERE e.course_id=? AND e.status='active' ORDER BY u.name");
  $st->execute([$course_id]); $students = $st->fetchAll(PDO::FETCH_ASSOC);
}

// สรุปคะแนนต่อคน
$sum = [];
if($course_id){
  $st = $pdo->prepare("SELECT user_id, COALESCE(SUM(points),0) total_pts FROM behavior_reports
                       WHERE course_id=? GROUP BY user_id");
  $st->execute([$course_id]); $sum = $st->fetchAll(PDO::FETCH_KEY_PAIR);
}

// ประวัติล่าสุด
$logs = [];
if($course_id){
  $st = $pdo->prepare("SELECT b.*, u.name AS student_name FROM behavior_reports b
                       JOIN users u ON u.user_id=b.user_id
                       WHERE b.course_id=? ORDER BY b.created_at DESC LIMIT 20");
  $st->execute([$course_id]); $logs = $st->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="th"><head>
<meta charset="UTF-8"><title>ความประพฤติ</title>
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
.row{display:grid;grid-template-columns:1fr 1fr;gap:16px}
label{display:block;color:var(--muted);font-size:14px;margin:8px 0 6px}
input,select,textarea{width:100%;padding:10px;border:1px solid #e5e7eb;border-radius:10px}
.table{width:100%;border-collapse:collapse}
.table th,.table td{padding:10px;border-bottom:1px solid #e5e7eb;text-align:left}
.badge{display:inline-block;background:#eef2ff;color:#1e3a8a;padding:6px 10px;border-radius:999px;font-weight:600}
@media(max-width:992px){.row{grid-template-columns:1fr}}
</style>
</head>
<body>
  <div class="sidebar">
    <h2>🙂 พฤติกรรม</h2>
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
      <h2 style="margin:0 0 10px">บันทึก/สรุปความประพฤติ</h2>
      <form method="get" style="display:flex;gap:10px;align-items:center">
        <label style="margin:0">เลือกวิชา</label>
        <select name="course_id" onchange="this.form.submit()">
          <?php foreach($courses as $c): ?>
            <option value="<?= (int)$c['course_id'] ?>" <?= $course_id==$c['course_id']?'selected':'' ?>>
              <?= htmlspecialchars($c['title']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </form>
      <?php if($msg): ?><p class="badge" style="margin-top:10px"><?= htmlspecialchars($msg) ?></p><?php endif; ?>
    </div>

    <?php if($course_id): ?>
    <div class="card">
      <h3 style="margin:0 0 10px">เพิ่มบันทึก</h3>
      <form method="post" class="row">
        <div>
          <label>นักเรียน</label>
          <select name="student_id" required>
            <?php foreach($students as $s): ?>
              <option value="<?= (int)$s['user_id'] ?>"><?= htmlspecialchars($s['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label>ประเภท</label>
          <select name="type">
            <option value="good">ดี</option>
            <option value="reward">ให้รางวัล</option>
            <option value="neutral">ปกติ</option>
            <option value="bad">ไม่ดี</option>
            <option value="penalty">หักคะแนน</option>
          </select>
        </div>
        <div>
          <label>คะแนน (+/-)</label>
          <input type="number" name="points" value="0">
        </div>
        <div>
          <label>หมายเหตุ</label>
          <input name="remark" placeholder="ระบุเหตุผล/รายละเอียด">
        </div>
        <div style="grid-column:1/-1">
          <button class="badge" style="border:0;cursor:pointer">บันทึก</button>
        </div>
      </form>
    </div>

    <div class="card">
      <h3 style="margin:0 0 10px">สรุปคะแนนรวมต่อคน</h3>
      <table class="table">
        <thead><tr><th>นักเรียน</th><th>คะแนนรวม</th></tr></thead>
        <tbody>
          <?php foreach($students as $s):
            $total = (int)($sum[$s['user_id']] ?? 0);
          ?>
            <tr><td><?= htmlspecialchars($s['name']) ?></td><td><b><?= $total ?></b></td></tr>
          <?php endforeach; if(!$students): ?>
            <tr><td colspan="2" class="muted">ไม่มีนักเรียนในคอร์สนี้</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <div class="card">
      <h3 style="margin:0 0 10px">ประวัติล่าสุด</h3>
      <table class="table">
        <thead><tr><th>เวลา</th><th>นักเรียน</th><th>ประเภท</th><th>คะแนน</th><th>หมายเหตุ</th></tr></thead>
        <tbody>
          <?php foreach($logs as $l): ?>
            <tr>
              <td><?= htmlspecialchars($l['created_at']) ?></td>
              <td><?= htmlspecialchars($l['student_name']) ?></td>
              <td><?= htmlspecialchars($l['type']) ?></td>
              <td><?= (int)$l['points'] ?></td>
              <td><?= htmlspecialchars($l['remark'] ?? '') ?></td>
            </tr>
          <?php endforeach; if(!$logs): ?>
            <tr><td colspan="5" class="muted">ยังไม่มีข้อมูล</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>
</body></html>
