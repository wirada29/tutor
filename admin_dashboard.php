<?php
// admin_dashboard.php
session_start();
require_once __DIR__ . '/includes/auth.php';
require_login();
if (!is_admin()) { header("Location: dashboard.php"); exit; }
require_once __DIR__ . '/config/db.php';

$me = $_SESSION['user'] ?? [];
$name = $me['name'] ?? 'ผู้ดูแลระบบ';
$email = $me['email'] ?? '-';
$role  = 'admin';

/* ========== สรุปตัวเลขหลัก ========== */
function fetchInt(PDO $pdo, string $sql, array $args=[]): int {
  try { $st=$pdo->prepare($sql); $st->execute($args); return (int)$st->fetchColumn(); }
  catch(Throwable $e){ return 0; }
}
$totalUsers      = fetchInt($pdo, "SELECT COUNT(*) FROM users");
$totalStudents   = fetchInt($pdo, "SELECT COUNT(*) FROM users WHERE role='student'");
$totalTeachers   = fetchInt($pdo, "SELECT COUNT(*) FROM users WHERE role='teacher'");
$totalAdmins     = fetchInt($pdo, "SELECT COUNT(*) FROM users WHERE role='admin'");
$totalCourses    = fetchInt($pdo, "SELECT COUNT(*) FROM courses");
$totalOpen       = fetchInt($pdo, "SELECT COUNT(*) FROM courses WHERE status IN ('open','เปิด')");
$totalEnrollAct  = fetchInt($pdo, "SELECT COUNT(*) FROM enrollments WHERE status='active'");

/* ========== ค้นหา/กรองผู้ใช้ ========== */
$q    = trim($_GET['q'] ?? '');
$rfit = strtolower(trim($_GET['role'] ?? '')); // '', student, teacher, admin

$sqlUsers = "SELECT user_id, name, email, role, 
                /* กันกรณีไม่มี created_at */ 
                NULLIF(DATE_FORMAT(created_at, '%Y-%m-%d %H:%i'), '') AS created_fmt
             FROM users WHERE 1=1";
$args = [];
if ($rfit !== '' && in_array($rfit, ['student','teacher','admin'], true)) {
  $sqlUsers .= " AND role = ?";
  $args[] = $rfit;
}
if ($q !== '') {
  $sqlUsers .= " AND (name LIKE ? OR email LIKE ?)";
  $args[] = "%$q%";
  $args[] = "%$q%";
}
$sqlUsers .= " ORDER BY user_id DESC LIMIT 30";
$st = $pdo->prepare($sqlUsers);
$st->execute($args);
$users = $st->fetchAll(PDO::FETCH_ASSOC);

/* ========== รายการล่าสุดเล็ก ๆ ========== */
$recentTeachers = $pdo->query("SELECT user_id,name,email FROM users WHERE role='teacher' ORDER BY user_id DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
$recentStudents = $pdo->query("SELECT user_id,name,email FROM users WHERE role='student' ORDER BY user_id DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<title>แดชบอร์ดผู้ดูแล | สถาบันติวเตอร์</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;600&display=swap" rel="stylesheet">
<style>
:root{
  --blue:#3b82f6; --blue-dark:#2563eb; --ink:#0f172a; --muted:#64748b;
  --bg:#f5f7fa; --surface:#ffffff; --ok:#16a34a; --warn:#eab308; --err:#ef4444;
}
*{box-sizing:border-box}
body{margin:0;font-family:'Sarabun',sans-serif;background:var(--bg);color:var(--ink);display:flex;min-height:100vh}

/* Sidebar (เหมือนหน้าอื่น ๆ) */
.sidebar{
  width:230px; background:linear-gradient(180deg,var(--blue),var(--blue-dark)); color:#fff;
  padding:26px 16px; position:fixed; inset:0 auto 0 0; overflow-y:auto;
}
.sidebar h2{margin:0 0 24px; font-size:22px; font-weight:600; text-align:center}
.sidebar a{
  display:flex; align-items:center; gap:10px; color:#fff; text-decoration:none;
  margin-bottom:12px; padding:11px; border-radius:10px; transition:background .2s, transform .15s;
}
.sidebar a:hover{background:rgba(255,255,255,.15); transform:translateY(-1px)}
.sidebar a.active{background:rgba(255,255,255,.22); box-shadow:inset 0 0 0 1px rgba(255,255,255,.18)}

/* Main */
.main{flex:1; margin-left:230px; padding:28px 32px}
.card{
  background:var(--surface); padding:22px; border-radius:16px;
  box-shadow:0 6px 24px rgba(15,23,42,.06); margin-bottom:22px;
}
.grid-3{display:grid; grid-template-columns:repeat(3,1fr); gap:18px}
.grid-2{display:grid; grid-template-columns:repeat(2,1fr); gap:18px}
.kpi{display:flex; align-items:center; gap:14px}
.kpi .num{font-size:28px; font-weight:800}
.kpi .tag{color:var(--muted); font-size:14px}

.badge{display:inline-flex; align-items:center; gap:6px; padding:6px 10px; border-radius:999px;
       font-weight:700; font-size:13px; background:#eef2ff; color:#1e3a8a}
.table{width:100%; border-collapse:collapse}
.table th,.table td{padding:10px; border-bottom:1px solid #e5e7eb; text-align:left}
.table th{background:#eef2ff}
.controls{display:flex; gap:10px; flex-wrap:wrap}
.input{padding:10px 12px; border:1px solid #e5e7eb; border-radius:10px}
.btn{padding:10px 14px; border-radius:10px; border:none; cursor:pointer; font-weight:700}
.btn-blue{background:var(--blue-dark); color:#fff}
.btn-muted{background:#e5e7eb}
.muted{color:var(--muted)}
.list{display:grid; gap:10px}
.item{display:flex; justify-content:space-between; align-items:center; padding:10px 12px; border:1px solid #e5e7eb; border-radius:12px}
.item .name{font-weight:700}
@media (max-width:992px){ .grid-3{grid-template-columns:1fr} .grid-2{grid-template-columns:1fr} .main{margin-left:0; padding:20px} .sidebar{position:relative; width:100%; inset:auto} }
</style>
</head>
<body>

<!-- Sidebar -->
<div class="sidebar">
  <h2>🛡️ แอดมิน</h2>

  <!-- ส่วนของแอดมิน -->
  <div style="height:1px;background:rgba(255,255,255,.25);margin:10px 0"></div>
  <a href="admin_dashboard.php" class="active"><i class="bi bi-speedometer2"></i> หน้าผู้ดูแล</a>
  <a href="admin_users.php"><i class="bi bi-people-fill"></i> ผู้ใช้ทั้งหมด</a>
  <a href="register_teacher.php"><i class="bi bi-person-plus"></i> สร้างบัญชีครู</a>
  <a href="register_admin.php"><i class="bi bi-shield-plus"></i> สร้างบัญชีแอดมิน</a>

  <div style="height:1px;background:rgba(255,255,255,.25);margin:10px 0"></div>
  <a href="logout.php"><i class="bi bi-box-arrow-right"></i> ออกจากระบบ</a>
</div>

<!-- Main -->
<div class="main">
  <!-- ทักทาย -->
  <div class="card">
    <h2 style="margin:0 0 6px">สวัสดีคุณ <?= htmlspecialchars($name) ?> <span class="badge"><i class="bi bi-shield-lock"></i> Admin</span></h2>
    <div class="muted"><i class="bi bi-envelope-fill"></i> <?= htmlspecialchars($email) ?></div>
  </div>

  <!-- KPIs -->
  <div class="grid-3">
    <div class="card kpi">
      <i class="bi bi-people-fill" style="font-size:28px;color:#1e3a8a"></i>
      <div>
        <div class="num"><?= number_format($totalUsers) ?></div>
        <div class="tag">ผู้ใช้ทั้งหมด (นักเรียน <?= number_format($totalStudents) ?> • ครู <?= number_format($totalTeachers) ?> • แอดมิน <?= number_format($totalAdmins) ?>)</div>
      </div>
    </div>
    <div class="card kpi">
      <i class="bi bi-journal-bookmark-fill" style="font-size:28px;color:#0ea5e9"></i>
      <div>
        <div class="num"><?= number_format($totalCourses) ?></div>
        <div class="tag">รายวิชา (เปิดอยู่ <?= number_format($totalOpen) ?>)</div>
      </div>
    </div>
    <div class="card kpi">
      <i class="bi bi-clipboard-check" style="font-size:28px;color:#16a34a"></i>
      <div>
        <div class="num"><?= number_format($totalEnrollAct) ?></div>
        <div class="tag">การลงทะเบียน (กำลังเรียน)</div>
      </div>
    </div>
  </div>

  <!-- กล่องซ้าย: ค้นหา/ดูผู้ใช้ | ขวา: รายการล่าสุด -->
  <div class="grid-2">
    <!-- Users search/list -->
    <div class="card">
      <h3 style="margin:0 0 10px"><i class="bi bi-people"></i> ผู้ใช้ล่าสุด/ค้นหา</h3>
      <form class="controls" method="get" action="admin_dashboard.php" style="margin-bottom:10px">
        <input class="input" type="text" name="q" placeholder="ค้นหาชื่อ/อีเมล..." value="<?= htmlspecialchars($q) ?>">
        <select class="input" name="role">
          <option value="">บทบาททั้งหมด</option>
          <option value="student" <?= $rfit==='student'?'selected':'' ?>>นักเรียน</option>
          <option value="teacher" <?= $rfit==='teacher'?'selected':'' ?>>ครู</option>
          <option value="admin"   <?= $rfit==='admin'  ?'selected':'' ?>>แอดมิน</option>
        </select>
        <button class="btn btn-blue" type="submit"><i class="bi bi-search"></i> ค้นหา</button>
        <?php if ($q!=='' || $rfit!==''): ?>
          <a class="btn btn-muted" href="admin_dashboard.php">ล้าง</a>
        <?php endif; ?>
      </form>

      <?php if ($users): ?>
        <table class="table">
          <thead>
            <tr>
              <th style="width:46%">ชื่อ</th>
              <th style="width:28%">อีเมล</th>
              <th style="width:12%">บทบาท</th>
              <th style="width:14%">สร้างเมื่อ</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach($users as $u): ?>
              <tr>
                <td><?= htmlspecialchars($u['name'] ?: '—') ?></td>
                <td><?= htmlspecialchars($u['email'] ?: '—') ?></td>
                <td><?= htmlspecialchars($u['role']) ?></td>
                <td><?= htmlspecialchars($u['created_fmt'] ?: '—') ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
        <div class="muted" style="margin-top:6px">แสดงสูงสุด 30 รายการล่าสุด</div>
      <?php else: ?>
        <p class="muted">ไม่พบผู้ใช้ตามเงื่อนไข</p>
      <?php endif; ?>
    </div>

    <!-- Recent teachers/students -->
    <div class="card">
      <h3 style="margin:0 0 10px"><i class="bi bi-speedometer2"></i> สรุปอย่างเร็ว</h3>

      <div class="list" style="margin-bottom:16px">
        <div class="item" style="background:#f1f5ff">
          <span class="name"><i class="bi bi-mortarboard-fill" style="color:#1d4ed8"></i> ครูล่าสุด</span>
          <a class="btn btn-blue" href="admin_users.php?role=teacher"><i class="bi bi-eye"></i> ดูทั้งหมด</a>
        </div>
        <?php if ($recentTeachers): foreach($recentTeachers as $t): ?>
          <div class="item">
            <span><b><?= htmlspecialchars($t['name']) ?></b> <span class="muted">• <?= htmlspecialchars($t['email']) ?></span></span>
            <span class="badge">teacher</span>
          </div>
        <?php endforeach; else: ?>
          <div class="muted">— ไม่มีข้อมูล —</div>
        <?php endif; ?>
      </div>

      <div class="list">
        <div class="item" style="background:#f1fff2">
          <span class="name"><i class="bi bi-person-video3" style="color:#16a34a"></i> นักเรียนล่าสุด</span>
          <a class="btn btn-blue" href="admin_users.php?role=student"><i class="bi bi-eye"></i> ดูทั้งหมด</a>
        </div>
        <?php if ($recentStudents): foreach($recentStudents as $s): ?>
          <div class="item">
            <span><b><?= htmlspecialchars($s['name']) ?></b> <span class="muted">• <?= htmlspecialchars($s['email']) ?></span></span>
            <span class="badge">student</span>
          </div>
        <?php endforeach; else: ?>
          <div class="muted">— ไม่มีข้อมูล —</div>
        <?php endif; ?>
      </div>
    </div>
  </div>

</div>
</body>
</html>
