<?php
// content_manage.php (แก้ปรับปรุง)
session_start();
require_once __DIR__ . '/includes/auth.php';
require_login();
if (!is_teacher() && !is_admin()) {
  header("Location: dashboard.php");
  exit;
}
require_once __DIR__ . '/config/db.php';

$uid       = current_user_id();
$isAdmin   = is_admin();
$course_id = (int)($_GET['course_id'] ?? 0);

/* ---------- โหลดรายวิชาสำหรับดรอปดาวน์ ---------- */
if ($isAdmin) {
  // แอดมินเห็นทุกคอร์ส
  $st = $pdo->query("SELECT course_id, title FROM courses ORDER BY title");
  $myCourses = $st->fetchAll(PDO::FETCH_ASSOC);
} else {
  // ครูเห็นเฉพาะคอร์สของตัวเอง
  $st = $pdo->prepare("SELECT course_id, title FROM courses WHERE teacher_id = ? ORDER BY title");
  $st->execute([$uid]);
  $myCourses = $st->fetchAll(PDO::FETCH_ASSOC);
}

/* ตั้งค่า course_id เริ่มต้นเป็นตัวแรกถ้ายังไม่ได้เลือก */
if (!$course_id && $myCourses) {
  $course_id = (int)$myCourses[0]['course_id'];
}

/* ---------- ตรวจสิทธิ์คอร์สนี้ ---------- */
if ($course_id) {
  if ($isAdmin) {
    $chk = $pdo->prepare("SELECT COUNT(*) FROM courses WHERE course_id=?");
    $chk->execute([$course_id]);
  } else {
    $chk = $pdo->prepare("SELECT COUNT(*) FROM courses WHERE course_id=? AND teacher_id=?");
    $chk->execute([$course_id, $uid]);
  }
  if (!(int)$chk->fetchColumn()) {
    // ไม่มีสิทธิ์หรือไม่มีคอร์สนี้
    $course_id = 0;
  }
}

$msg = '';

/* ---------- เพิ่มเนื้อหา ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $course_id) {
  $type  = $_POST['type']  ?? 'document';
  $title = trim($_POST['title'] ?? '');
  $video = trim($_POST['video_url'] ?? '');
  $note  = trim($_POST['note'] ?? '');
  $file_path = null;

  if ($title === '') {
    $msg = 'กรุณาใส่ชื่อเนื้อหา';
  } else {
    // อัปโหลดไฟล์ (ถ้ามี)
    if (!empty($_FILES['file']['name']) && is_uploaded_file($_FILES['file']['tmp_name'])) {
      $dir = __DIR__ . '/uploads/contents/';
      if (!is_dir($dir)) mkdir($dir, 0777, true);
      $safeName = preg_replace('/[^a-zA-Z0-9_.-]/', '_', $_FILES['file']['name']);
      $fn = time() . '_' . $safeName;
      if (move_uploaded_file($_FILES['file']['tmp_name'], $dir . $fn)) {
        $file_path = 'uploads/contents/' . $fn;
      }
    }

    $sql = "INSERT INTO course_contents(course_id,type,title,file_path,video_url,note)
            VALUES(?,?,?,?,?,?)";
    $pdo->prepare($sql)->execute([$course_id, $type, $title, $file_path, $video, $note]);
    $msg = 'เพิ่มเนื้อหาสำเร็จ';
  }
}

/* ---------- ลบเนื้อหา ---------- */
if (isset($_GET['del']) && $course_id) {
  $del = (int)$_GET['del'];
  $pdo->prepare("DELETE FROM course_contents WHERE content_id=? AND course_id=?")->execute([$del, $course_id]);
  $msg = 'ลบแล้ว';
}

/* ---------- โหลดรายการเนื้อหา ---------- */
$contents = [];
if ($course_id) {
  $st = $pdo->prepare("SELECT * FROM course_contents WHERE course_id=? ORDER BY created_at DESC");
  $st->execute([$course_id]);
  $contents = $st->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="th">

<head>
  <meta charset="UTF-8">
  <title>จัดการเนื้อหา</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;600&display=swap" rel="stylesheet">
  <style>
    :root {
      --blue: #3b82f6;
      --blue2: #2563eb;
      --ink: #0f172a;
      --muted: #64748b;
      --bg: #f5f7fa;
      --surface: #fff
    }

    * {
      box-sizing: border-box
    }

    body {
      margin: 0;
      font-family: 'Sarabun', sans-serif;
      background: var(--bg);
      color: var(--ink);
      display: flex
    }

    .sidebar {
      width: 230px;
      background: linear-gradient(180deg, var(--blue), #2b6de1);
      color: #fff;
      padding: 26px 16px;
      position: fixed;
      inset: 0 auto 0 0
    }

    .sidebar h2 {
      margin: 0 0 24px;
      text-align: center;
      font-size: 22px;
      font-weight: 600
    }

    .sidebar a {
      display: flex;
      align-items: center;
      gap: 10px;
      color: #fff;
      text-decoration: none;
      margin-bottom: 12px;
      padding: 11px;
      border-radius: 10px
    }

    .sidebar a:hover {
      background: rgba(255, 255, 255, .12)
    }

    .main {
      flex: 1;
      margin-left: 230px;
      padding: 28px
    }

    .card {
      background: #fff;
      padding: 20px;
      border-radius: 14px;
      box-shadow: 0 6px 22px rgba(15, 23, 42, .06);
      margin-bottom: 20px
    }

    .row {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 16px
    }

    label {
      display: block;
      color: var(--muted);
      font-size: 14px;
      margin: 8px 0 6px
    }

    input,
    select,
    textarea {
      width: 100%;
      padding: 10px;
      border: 1px solid #e5e7eb;
      border-radius: 10px
    }

    .table {
      width: 100%;
      border-collapse: collapse
    }

    .table th,
    .table td {
      padding: 10px;
      border-bottom: 1px solid #e5e7eb;
      text-align: left
    }

    .badge {
      display: inline-block;
      background: #eef2ff;
      color: #1e3a8a;
      padding: 6px 10px;
      border-radius: 999px;
      font-weight: 600
    }

    .btn {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      background: var(--blue2);
      color: #fff;
      padding: 10px 12px;
      border-radius: 10px;
      text-decoration: none;
      border: 0;
      cursor: pointer
    }

    .btn-del {
      background: #ef4444
    }

    @media(max-width:992px) {
      .row {
        grid-template-columns: 1fr
      }
    }
  </style>
</head>

<body>
  <div class="sidebar">
    <h2>📁 เนื้อหาคอร์ส</h2>
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

  <div class="main">
    <div class="card">
      <h2 style="margin:0 0 10px">จัดการเนื้อหา</h2>
      <form method="get" style="display:flex;gap:10px;align-items:center">
        <label style="margin:0">เลือกวิชา</label>
        <select name="course_id" onchange="this.form.submit()" <?= empty($myCourses) ? 'disabled' : '' ?>>
          <?php if (!$myCourses): ?>
            <option value="">— ยังไม่มีรายวิชาที่คุณดูแล —</option>
          <?php else: ?>
            <?php foreach ($myCourses as $c): ?>
              <option value="<?= (int)$c['course_id'] ?>" <?= ($course_id == (int)$c['course_id']) ? 'selected' : '' ?>>
                <?= htmlspecialchars($c['title']) ?>
              </option>
            <?php endforeach; ?>
          <?php endif; ?>
        </select>
      </form>
      <?php if ($msg): ?><p class="badge" style="margin-top:10px"><?= htmlspecialchars($msg) ?></p><?php endif; ?>
    </div>

    <?php if ($course_id): ?>
      <div class="card">
        <h3 style="margin:0 0 10px">เพิ่มเนื้อหาใหม่</h3>
        <form method="post" enctype="multipart/form-data" class="row">
          <div>
            <label>ประเภท</label>
            <select name="type">
              <option value="document">เอกสาร/ไฟล์</option>
              <option value="video">วิดีโอ (ลิงก์)</option>
              <option value="link">ลิงก์</option>
              <option value="slide">สไลด์</option>
              <option value="other">อื่น ๆ</option>
            </select>
          </div>
          <div>
            <label>ชื่อเนื้อหา</label>
            <input name="title" required placeholder="เช่น บทที่ 1 โครงสร้างอะตอม">
          </div>
          <div>
            <label>อัปโหลดไฟล์ (ถ้ามี)</label>
            <input type="file" name="file" accept=".pdf,.doc,.docx,.ppt,.pptx,.zip,.rar,.mp4,.jpg,.png">
          </div>
          <div>
            <label>ลิงก์วิดีโอ/ลิงก์ (ถ้ามี)</label>
            <input name="video_url" placeholder="เช่น https://youtube.com/... หรือ URL เนื้อหา">
          </div>
          <div style="grid-column:1/-1">
            <label>หมายเหตุ</label>
            <textarea name="note" rows="2" placeholder="คำอธิบายเพิ่มเติม"></textarea>
          </div>
          <div style="grid-column:1/-1">
            <button class="btn" type="submit"><i class="bi bi-plus-circle"></i> เพิ่มเนื้อหา</button>
          </div>
        </form>
      </div>

      <div class="card">
        <h3 style="margin:0 0 10px">รายการเนื้อหา</h3>
        <table class="table">
          <thead>
            <tr>
              <th>ชื่อ</th>
              <th>ประเภท</th>
              <th>ไฟล์/ลิงก์</th>
              <th>เวลา</th>
              <th>จัดการ</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($contents as $ct): ?>
              <tr>
                <td><strong><?= htmlspecialchars($ct['title']) ?></strong><br>
                  <span class="muted"><?= htmlspecialchars($ct['note'] ?? '') ?></span>
                </td>
                <td><span class="badge"><?= htmlspecialchars($ct['type']) ?></span></td>
                <td>
                  <?php if ($ct['file_path']): ?>
                    <a href="<?= htmlspecialchars($ct['file_path']) ?>" target="_blank"><i class="bi bi-file-earmark-arrow-down"></i> ไฟล์</a>
                  <?php endif; ?>
                  <?php if ($ct['video_url']): ?>
                    <?= $ct['file_path'] ? ' | ' : '' ?>
                    <a href="<?= htmlspecialchars($ct['video_url']) ?>" target="_blank"><i class="bi bi-link-45deg"></i> ลิงก์</a>
                  <?php endif; ?>
                  <?php if (!$ct['file_path'] && !$ct['video_url']): ?>
                    <span class="muted">—</span>
                  <?php endif; ?>
                </td>
                <td><?= htmlspecialchars($ct['created_at']) ?></td>
                <td>
                  <a class="btn btn-del" href="?course_id=<?= (int)$course_id ?>&del=<?= (int)$ct['content_id'] ?>"
                    onclick="return confirm('ลบเนื้อหานี้?')"><i class="bi bi-trash"></i> ลบ</a>
                </td>
              </tr>
            <?php endforeach;
            if (!$contents): ?>
              <tr>
                <td colspan="5" class="muted">ยังไม่มีเนื้อหา</td>
              </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</body>

</html>