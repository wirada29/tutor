<?php
// teacher_assign_create.php
session_start();
require_once __DIR__ . '/includes/auth.php';
require_login();
if (!is_teacher() && !is_admin()) { header("Location: dashboard.php"); exit; }
require_once __DIR__ . '/config/db.php';

$tid = current_user_id();

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
<title>สร้างงานใหม่</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
<style>
body{font-family:Prompt,system-ui,-apple-system,Segoe UI,Roboto; background:#f6f7fb; margin:0; color:#0f172a}
.wrap{max-width:860px;margin:40px auto;padding:0 16px}
.card{background:#fff;border-radius:14px;box-shadow:0 8px 24px rgba(15,23,42,.06);padding:18px;margin-bottom:14px}
.row{display:grid;grid-template-columns:1fr 1fr;gap:12px}
label{font-size:14px;color:#475569}
input,select,textarea{width:100%;padding:10px;border:1px solid #e5e7eb;border-radius:10px}
textarea{min-height:120px}
.btn{padding:10px 14px;border-radius:10px;border:none;cursor:pointer;font-weight:700}
.btn-primary{background:#2563eb;color:#fff}
.badge{display:inline-block;background:#dcfce7;color:#166534;padding:6px 10px;border-radius:999px;font-weight:600}
.alert{padding:10px;border-radius:10px;margin:10px 0}
.alert-err{background:#fee2e2;color:#991b1b}
</style>
</head>
<body>
<div class="wrap">
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

      <div style="margin-top:12px">
        <button class="btn btn-primary" type="submit"><i class="bi bi-check2-circle"></i> บันทึกงาน</button>
        <a class="btn" href="teacher_assign_list.php" style="background:#e5e7eb">ย้อนกลับ</a>
      </div>
    </form>
  </div>
</div>
</body>
</html>
