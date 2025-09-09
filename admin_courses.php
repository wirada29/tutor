<?php
session_start();
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';
require_login();
if (!is_admin()) { header("Location: dashboard.php"); exit; }

// เพิ่มคอร์ส
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='create') {
  $title = trim($_POST['title'] ?? '');
  $desc  = trim($_POST['description'] ?? '');
  $seats = (int)($_POST['max_seats'] ?? 30);
  $status= ($_POST['status'] ?? 'open') === 'closed' ? 'closed' : 'open';
  $teacher_id = (int)($_POST['teacher_id'] ?? 0) ?: null;

  if ($title && $seats>0) {
    $st = $pdo->prepare("INSERT INTO courses(title,description,max_seats,status,teacher_id) VALUES(?,?,?,?,?)");
    $st->execute([$title,$desc,$seats,$status,$teacher_id]);
  }
  header("Location: admin_courses.php"); exit;
}

// toggle เปิด/ปิด
if (isset($_GET['toggle'])) {
  $cid = (int)$_GET['toggle'];
  $pdo->prepare("UPDATE courses SET status = IF(status='open','closed','open') WHERE course_id=?")->execute([$cid]);
  header("Location: admin_courses.php"); exit;
}

$courses = $pdo->query("SELECT c.*, u.name AS teacher_name
                        FROM courses c
                        LEFT JOIN users u ON u.user_id=c.teacher_id
                        ORDER BY c.course_id DESC")->fetchAll();

$teachers = $pdo->query("SELECT user_id,name FROM users WHERE role='teacher' ORDER BY name")->fetchAll();
?>
<!doctype html>
<html lang="th"><head>
<meta charset="utf-8">
<title>จัดการคอร์ส (แอดมิน)</title>
<style>
body{font-family:Prompt,system-ui,-apple-system,Segoe UI,Roboto; background:#f6f7fb; margin:0; color:#0f172a}
.wrap{max-width:1000px;margin:40px auto;padding:0 16px}
.card{background:#fff;border-radius:14px;box-shadow:0 8px 24px rgba(15,23,42,.06);padding:18px;margin-bottom:14px}
.row{display:grid;grid-template-columns:1fr auto;gap:14px;align-items:center}
.btn{padding:8px 12px;border-radius:10px;border:none;cursor:pointer;font-weight:700}
.btn-primary{background:#3b82f6;color:#fff}
.btn-gray{background:#e5e7eb}
.badge{display:inline-block;background:#eef2ff;color:#1e3a8a;padding:6px 10px;border-radius:999px;font-weight:600}
.muted{color:#64748b}
input,textarea,select{width:100%;padding:10px;border:1px solid #e5e7eb;border-radius:10px}
</style>
</head>
<body>
<div class="wrap">
  <h2>🛠️ จัดการคอร์ส (แอดมิน)</h2>

  <div class="card">
    <h3 style="margin-top:0">เพิ่มคอร์สใหม่</h3>
    <form method="post">
      <input type="hidden" name="action" value="create">
      <div style="display:grid;grid-template-columns:1fr 180px 160px;gap:10px;margin-bottom:10px">
        <input name="title" placeholder="ชื่อคอร์ส" required>
        <input name="max_seats" type="number" min="1" value="30" placeholder="ที่นั่ง">
        <select name="status">
          <option value="open">open</option>
          <option value="closed">closed</option>
        </select>
      </div>
      <div style="display:grid;grid-template-columns:1fr 260px;gap:10px">
        <textarea name="description" rows="3" placeholder="รายละเอียด"></textarea>
        <select name="teacher_id">
          <option value="">— เลือกครูผู้สอน —</option>
          <?php foreach($teachers as $t): ?>
            <option value="<?= (int)$t['user_id'] ?>"><?= htmlspecialchars($t['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div style="margin-top:10px"><button class="btn btn-primary" type="submit">เพิ่มคอร์ส</button></div>
    </form>
  </div>

  <?php foreach($courses as $c): ?>
    <div class="card">
      <div class="row">
        <div>
          <h3 style="margin:6px 0 6px"><?= htmlspecialchars($c['title']) ?></h3>
          <div class="muted"><?= nl2br(htmlspecialchars($c['description'] ?? '')) ?></div>
          <div style="margin-top:8px">
            <span class="badge">สถานะ: <?= htmlspecialchars($c['status']) ?></span>
            <span class="badge">ที่นั่ง: <?= (int)$c['max_seats'] ?></span>
            <span class="badge">ครู: <?= htmlspecialchars($c['teacher_name'] ?? '-') ?></span>
          </div>
        </div>
        <div>
          <a class="btn btn-gray" href="?toggle=<?= (int)$c['course_id'] ?>">
            สลับเป็น <?= $c['status']==='open'?'closed':'open' ?>
          </a>
        </div>
      </div>
    </div>
  <?php endforeach; ?>

</div>
</body></html>
