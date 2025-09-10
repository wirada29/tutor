<?php
// teacher_dashboard.php
session_start();
require_once __DIR__ . '/includes/auth.php';
require_login();
if (!is_teacher() && !is_admin()) { header("Location: dashboard.php"); exit; }
require_once __DIR__ . '/config/db.php';

$tid = current_user_id();

// สรุปจำนวนคอร์สของครู
$st = $pdo->prepare("SELECT COUNT(*) FROM courses WHERE teacher_id=?");
$st->execute([$tid]);
$totalCourses = (int)$st->fetchColumn();

// นักเรียนทั้งหมดในคอร์สของครู (active)
$st = $pdo->prepare("
  SELECT COUNT(DISTINCT e.user_id)
  FROM courses c JOIN enrollments e ON e.course_id=c.course_id AND e.status='active'
  WHERE c.teacher_id=?
");
$st->execute([$tid]);
$totalStudents = (int)$st->fetchColumn();

// งานที่มอบหมายทั้งหมด
$st = $pdo->prepare("SELECT COUNT(*) FROM assignments WHERE teacher_id=?");
$st->execute([$tid]);
$totalAssignments = (int)$st->fetchColumn();

// 5 รายการ “การส่งล่าสุด” (ทุกคอร์สของครู)
$recent = $pdo->prepare("
  SELECT s.submission_id, s.submitted_at, s.status, s.score,
         a.title AS assignment_title, u.name AS student_name, c.title AS course_title
  FROM submissions s
  JOIN assignments a ON a.assignment_id=s.assignment_id
  JOIN courses c ON c.course_id=a.course_id
  JOIN users   u ON u.user_id=s.student_id
  WHERE a.teacher_id=?
  ORDER BY s.submitted_at DESC
  LIMIT 5
");
$recent->execute([$tid]);
$recentRows = $recent->fetchAll(PDO::FETCH_ASSOC);

// คอร์สของครู
$courses = $pdo->prepare("SELECT course_id, title, status, max_seats FROM courses WHERE teacher_id=? ORDER BY title ASC");
$courses->execute([$tid]);
$courses = $courses->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="th"><head>
<meta charset="UTF-8">
<title>แดชบอร์ดครู | สถาบันติวเตอร์</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;600&display=swap" rel="stylesheet">
<style>
:root{--blue:#3b82f6;--blue2:#2563eb;--ink:#0f172a;--muted:#64748b;--bg:#f5f7fa;--surface:#fff;--ok:#16a34a;--warn:#f59e0b}
*{box-sizing:border-box} body{margin:0;font-family:'Sarabun',sans-serif;background:var(--bg);color:var(--ink);display:flex;min-height:100vh}
.sidebar{width:230px;background:linear-gradient(180deg,var(--blue),#2b6de1);color:#fff;padding:26px 16px;position:fixed;inset:0 auto 0 0}
.sidebar h2{margin:0 0 24px;text-align:center;font-size:22px;font-weight:600}
.sidebar a{display:flex;align-items:center;gap:10px;color:#fff;text-decoration:none;margin-bottom:12px;padding:11px;border-radius:10px}
.sidebar a:hover{background:rgba(255,255,255,.12)}
.main{flex:1;margin-left:230px;padding:28px}
.card{background:#fff;padding:20px;border-radius:14px;box-shadow:0 6px 22px rgba(15,23,42,.06);margin-bottom:20px}
.kpis{display:grid;grid-template-columns:repeat(3,1fr);gap:14px}
.kpi{padding:16px;border-radius:12px;background:#fff;box-shadow:0 6px 16px rgba(15,23,42,.06)}
.kpi .h{color:var(--muted);font-size:14px}
.kpi .v{font-size:28px;font-weight:800;margin-top:2px}
.table{width:100%;border-collapse:collapse}
.table th,.table td{padding:10px;border-bottom:1px solid #e5e7eb;text-align:left}
.badge{display:inline-block;background:#eef2ff;color:#1e3a8a;padding:6px 10px;border-radius:999px;font-weight:600}
.actions a{display:inline-flex;align-items:center;gap:6px;background:var(--blue2);color:#fff;padding:8px 10px;border-radius:10px;text-decoration:none;margin-right:6px}
@media(max-width:992px){.kpis{grid-template-columns:1fr}}
</style>
</head>
<body>
  <div class="sidebar">
    <h2>👩‍🏫 ครู</h2>
    <a href="teacher_dashboard.php"><i class="bi bi-speedometer2"></i> แดชบอร์ด</a>
    <a href="content_manage.php"><i class="bi bi-folder2-open"></i> เนื้อหา/เอกสาร</a>
    <a href="attendance_manage.php"><i class="bi bi-clipboard-check"></i> เช็คชื่อ</a>
    <a href="behavior_manage.php"><i class="bi bi-emoji-smile"></i> ความประพฤติ</a>
    <a href="courses.php"><i class="bi bi-journal-bookmark-fill"></i> รายวิชา (มุมมองนักเรียน)</a>
    <a href="logout.php"><i class="bi bi-box-arrow-right"></i> ออกจากระบบ</a>
  </div>

  <div class="main">
    <div class="card">
      <h2 style="margin:0 0 10px">แดชบอร์ดครู</h2>
      <div class="kpis">
        <div class="kpi"><div class="h">วิชาที่สอน</div><div class="v"><?= $totalCourses ?></div></div>
        <div class="kpi"><div class="h">จำนวนนักเรียนรวม</div><div class="v"><?= $totalStudents ?></div></div>
        <div class="kpi"><div class="h">งานที่มอบหมาย</div><div class="v"><?= $totalAssignments ?></div></div>
      </div>
    </div>

    <div class="card">
      <div style="display:flex;justify-content:space-between;align-items:center;gap:10px">
        <h3 style="margin:0">📚 วิชาของฉัน</h3>
        <div class="actions">
          <a href="content_manage.php"><i class="bi bi-plus-square"></i> จัดการเนื้อหา</a>
        </div>
      </div>
      <table class="table" style="margin-top:8px">
        <thead><tr><th>วิชา</th><th>สถานะ</th><th>ที่นั่ง</th><th>จัดการ</th></tr></thead>
        <tbody>
        <?php foreach($courses as $c):
          // นับนักเรียนที่ลง active
          $st = $pdo->prepare("SELECT COUNT(*) FROM enrollments WHERE course_id=? AND status='active'");
          $st->execute([$c['course_id']]);
          $used = (int)$st->fetchColumn();
          $cap  = (int)$c['max_seats'];
        ?>
          <tr>
            <td><?= htmlspecialchars($c['title']) ?></td>
            <td><?= $c['status']==='open' ? '<span class="badge">เปิด</span>' : '<span class="badge" style="background:#fee2e2;color:#991b1b">ปิด</span>' ?></td>
            <td><?= $cap>0 ? "$used / $cap" : 'ไม่จำกัด' ?></td>
            <td class="actions">
              <a href="content_manage.php?course_id=<?= (int)$c['course_id'] ?>"><i class="bi bi-folder2-open"></i> เนื้อหา</a>
              <a href="attendance_manage.php?course_id=<?= (int)$c['course_id'] ?>"><i class="bi bi-clipboard-check"></i> เช็คชื่อ</a>
              <a href="behavior_manage.php?course_id=<?= (int)$c['course_id'] ?>"><i class="bi bi-emoji-smile"></i> พฤติกรรม</a>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <div class="card">
      <h3 style="margin:0 0 8px">📝 การส่งล่าสุด</h3>
      <table class="table">
        <thead><tr><th>งาน</th><th>นักเรียน</th><th>วิชา</th><th>เวลา</th><th>สถานะ</th><th>คะแนน</th></tr></thead>
        <tbody>
          <?php foreach($recentRows as $r): ?>
            <tr>
              <td><?= htmlspecialchars($r['assignment_title']) ?></td>
              <td><?= htmlspecialchars($r['student_name']) ?></td>
              <td><?= htmlspecialchars($r['course_title']) ?></td>
              <td><?= htmlspecialchars($r['submitted_at']) ?></td>
              <td><?= htmlspecialchars($r['status']) ?></td>
              <td><?= $r['score']!==null ? htmlspecialchars($r['score']) : '-' ?></td>
            </tr>
          <?php endforeach; if(!$recentRows): ?>
            <tr><td colspan="6" class="muted">ยังไม่มีการส่ง</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</body></html>
