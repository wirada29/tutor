<?php
// teacher_assign_create.php
session_start();
require_once __DIR__ . '/includes/auth.php';
require_login();
if (!is_teacher() && !is_admin()) { header("Location: dashboard.php"); exit; }
require_once __DIR__ . '/config/db.php';

$tid = current_user_id();
$current = basename($_SERVER['PHP_SELF']);

// โหลดรายวิชาของครู (ไว้ให้เลือกตอนสร้างงาน)
$stmt = $pdo->prepare("SELECT course_id, title FROM courses WHERE teacher_id = ? ORDER BY title ASC");
$stmt->execute([$tid]);
$courses = $stmt->fetchAll(PDO::FETCH_ASSOC);

$error = $ok = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $course_id   = (int)($_POST['course_id'] ?? 0);
    $title       = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $due_date    = trim($_POST['due_date'] ?? ''); // YYYY-MM-DD

    // ตรวจว่า course_id เป็นของครูคนนี้จริง
    $own = $pdo->prepare("SELECT COUNT(*) FROM courses WHERE course_id=? AND teacher_id=?");
    $own->execute([$course_id, $tid]);
    if (!$own->fetchColumn()) {
        $error = "ไม่พบรายวิชานี้ในความดูแลของคุณ";
    } elseif ($title === '') {
        $error = "กรุณากรอกหัวข้องาน";
    } else {
        try {
            $ins = $pdo->prepare("INSERT INTO assignments(course_id, title, description, due_date)
                                  VALUES(?, ?, ?, ?)");
            $ins->execute([$course_id, $title, $description, $due_date ?: null]);
            $_SESSION['flash'] = "เพิ่มงานใหม่เรียบร้อย";
            header("Location: teacher_assign_list.php");
            exit;
        } catch (Throwable $e) {
            $error = "บันทึกไม่สำเร็จ: " . $e->getMessage();
        }
    }
}
?>
<!doctype html>
<html lang="th">
<head>
<meta charset="utf-8">
<title>สร้างงานใหม่ | แดชบอร์ดครู</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;600&display=swap" rel="stylesheet">
<style>
:root{
  --blue:#3b82f6; --blue-dark:#2563eb;
  --ink:#0f172a; --muted:#64748b; --bg:#f5f7fa; --surface:#ffffff;
  --ok:#16a34a; --err:#e11d48; --line:#e5e7eb;
}
*{box-sizing:border-box}
body{margin:0;font-family:'Sarabun',sans-serif;background:var(--bg);color:var(--ink);display:flex;min-height:100vh}

/* Sidebar (เหมือนหน้าอื่น ๆ ของครู) */
.sidebar{
  width:230px;background:linear-gradient(180deg,var(--blue),#2b6de1);color:#fff;
  height:100vh;padding:26px 16px;position:fixed;inset:0 auto 0 0;overflow-y:auto;
  box-shadow:0 6px 20px rgba(0,0,0,.08)
}
.sidebar h2{font-size:22px;font-weight:600;margin:0 0 24px;text-align:center}
.sidebar a{
  display:flex;align-items:center;gap:10px;color:#fff;text-decoration:none;
  margin-bottom:12px;padding:11px 10px;border-radius:10px;
  transition:transform .15s, background .2s, opacity .2s; opacity:.95
}
.sidebar a:hover{background:rgba(255,255,255,.15);transform:translateY(-1px);opacity:1}
.sidebar a.active{background:rgba(255,255,255,.22);box-shadow:inset 0 0 0 1px rgba(255,255,255,.18)}

/* Main */
.main{flex:1;margin-left:230px;padding:28px}
.card{
  background:var(--surface);border-radius:14px;box-shadow:0 8px 24px rgba(15,23,42,.06);
  padding:18px;margin-bottom:14px
}

/* Form */
.row{display:grid;grid-template-columns:1fr 1fr;gap:12px}
label{font-size:14px;color:#475569}
input,select,textarea{
  width:100%;padding:12px;border:1px solid var(--line);border-radius:12px;background:#fff;font-size:16px;
  transition:border-color .2s, box-shadow .2s
}
input:focus,select:focus,textarea:focus{
  outline:none;border-color:#8ab0ff;box-shadow:0 0 0 4px rgba(91,134,229,.18)
}
textarea{min-height:120px}
.btn{padding:10px 14px;border-radius:10px;border:none;cursor:pointer;font-weight:700}
.btn-primary{background:var(--blue-dark);color:#fff}
.btn-muted{background:#e5e7eb}
.badge{display:inline-block;background:#dcfce7;color:#166534;padding:6px 10px;border-radius:999px;font-weight:600}
.alert{padding:10px;border-radius:10px;margin:10px 0}
.alert-err{background:#fee2e2;color:#991b1b}

@media(max-width:992px){
  .sidebar{position:relative;width:100%;height:auto;inset:auto}
  .main{margin-left:0;padding:20px}
  .row{grid-template-columns:1fr}
}
</style>
</head>
<body>

<!-- Sidebar -->
<div class="sidebar">
  <h2>📘 สร้างงานใหม่</h2>
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

<!-- Main -->
<div class="main">
  <div class="card">
    <h2 style="margin:6px 0 14px"><i class="bi bi-clipboard-plus"></i> สร้างงานใหม่</h2>

    <?php if (!empty($_SESSION['flash'])): ?>
      <div class="badge"><?= htmlspecialchars($_SESSION['flash']); unset($_SESSION['flash']); ?></div>
    <?php endif; ?>
    <?php if ($error): ?><div class="alert alert-err"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <form method="post">
      <div class="row">
        <div>
          <label>รายวิชา</label>
          <select name="course_id" required>
            <option value="">— เลือกรายวิชา —</option>
            <?php foreach($courses as $c): ?>
              <option value="<?= (int)$c['course_id'] ?>"><?= htmlspecialchars($c['title']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label>วันกำหนดส่ง</label>
          <input type="date" name="due_date">
        </div>
      </div>

      <label style="margin-top:10px">หัวข้องาน</label>
      <input name="title" placeholder="เช่น แบบฝึกหัดบทที่ 3" required>

      <label style="margin-top:10px">รายละเอียด</label>
      <textarea name="description" placeholder="อธิบายโจทย์/แนบลิงก์คู่มือ ฯลฯ"></textarea>

      <div style="margin-top:12px;display:flex;gap:10px;flex-wrap:wrap">
        <button class="btn btn-primary" type="submit"><i class="bi bi-check2-circle"></i> บันทึกงาน</button>
      </div>
    </form>
  </div>
</div>

</body>
</html>
