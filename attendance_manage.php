<?php
// attendance_manage.php — เช็คชื่อ (ครู/แอดมิน)
session_start();
require_once __DIR__ . '/includes/auth.php';
require_login();
if (!is_teacher() && !is_admin()) {
  header("Location: dashboard.php");
  exit;
}
require_once __DIR__ . '/config/db.php';

$tid       = current_user_id();
$course_id = (int)($_GET['course_id'] ?? 0);
$att_date  = $_GET['att_date'] ?? date('Y-m-d');

// รายวิชาที่ครูรับผิดชอบ
$st = $pdo->prepare("SELECT course_id, title FROM courses WHERE teacher_id=? ORDER BY title");
$st->execute([$tid]);
$courses = $st->fetchAll(PDO::FETCH_ASSOC);

// ถ้ายังไม่เลือกคอร์สให้ auto เลือกตัวแรก
if (!$course_id && $courses) $course_id = (int)$courses[0]['course_id'];

// ตรวจสิทธิ์คอร์ส (ยกเว้นแอดมิน)
if ($course_id) {
  $chk = $pdo->prepare("SELECT COUNT(*) FROM courses WHERE course_id=? AND teacher_id=?");
  $chk->execute([$course_id, $tid]);
  if (!$chk->fetchColumn() && !is_admin()) {
    die('Forbidden');
  }
}

$msg = "";

// บันทึกเช็คชื่อ
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $course_id) {
  $att_date = $_POST['att_date'] ?? $att_date;

  // ดึงรายชื่อนักเรียนที่ลงทะเบียนวิชานี้ (active/ไม่มี status)
  $q = "
    SELECT u.user_id
    FROM enrollments e
    JOIN users u ON u.user_id = e.user_id
    WHERE e.course_id=? AND (e.status IS NULL OR e.status='active')
  ";
  $st = $pdo->prepare($q);
  $st->execute([$course_id]);
  $studentIds = $st->fetchAll(PDO::FETCH_COLUMN);

  // บันทึกทีละคน
  $sqlSave = "INSERT INTO attendance(course_id,user_id,att_date,status)
              VALUES(?,?,?,?)
              ON DUPLICATE KEY UPDATE status=VALUES(status)";
  $ps = $pdo->prepare($sqlSave);

  foreach ($studentIds as $sid) {
    $status = $_POST['status_' . $sid] ?? 'present'; // present/absent/late
    $ps->execute([$course_id, $sid, $att_date, $status]);
  }
  $msg = "บันทึกการเช็คชื่อเรียบร้อยแล้ว";
}

// โหลดรายชื่อ + สถานะของวันที่เลือก
$list = [];
if ($course_id) {
  $q = "
    SELECT u.user_id, u.name, COALESCE(a.status,'') AS status
    FROM enrollments e
    JOIN users u ON u.user_id = e.user_id
    LEFT JOIN attendance a
           ON a.course_id = e.course_id
          AND a.user_id  = e.user_id
          AND a.att_date = ?
    WHERE e.course_id=? AND (e.status IS NULL OR e.status='active')
    ORDER BY u.name
  ";
  $st = $pdo->prepare($q);
  $st->execute([$att_date, $course_id]);
  $list = $st->fetchAll(PDO::FETCH_ASSOC);
}

// ใช้สำหรับทำ active ของเมนู
$current = basename($_SERVER['PHP_SELF']);
function h($s)
{
  return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}
?>
<!doctype html>
<html lang="th">

<head>
  <meta charset="utf-8">
  <title>เช็คชื่อ | แดชบอร์ดครู</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;600&display=swap" rel="stylesheet">
  <style>
    :root {
      --blue: #3b82f6;
      --blue2: #2563eb;
      --ink: #0f172a;
      --muted: #64748b;
      --bg: #f5f7fa;
      --card: #fff;
      --line: #e5e7eb;
      --ok: #16a34a;
      --warn: #eab308;
      --err: #ef4444
    }

    * {
      box-sizing: border-box
    }

    body {
      margin: 0;
      font-family: 'Sarabun', system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif;
      background: var(--bg);
      color: var(--ink);
      display: flex;
      min-height: 100vh
    }

    .sidebar {
      width: 230px;
      background: linear-gradient(180deg, var(--blue), #2b6de1);
      color: #fff;
      height: 100vh;
      padding: 26px 16px;
      position: fixed;
      inset: 0 auto 0 0;
      overflow: auto
    }

    .sidebar h2 {
      font-size: 22px;
      font-weight: 600;
      margin: 0 0 24px;
      text-align: center
    }

    .sidebar a {
      display: flex;
      align-items: center;
      gap: 10px;
      color: #fff;
      text-decoration: none;
      margin-bottom: 12px;
      padding: 11px 10px;
      border-radius: 10px;
      opacity: .95
    }

    .sidebar a:hover {
      background: rgba(255, 255, 255, .12);
      opacity: 1
    }

    .sidebar a.active {
      background: rgba(255, 255, 255, .22)
    }

    .main {
      flex: 1;
      margin-left: 230px;
      padding: 28px
    }

    .card {
      background: var(--card);
      border-radius: 16px;
      padding: 18px;
      margin-bottom: 14px;
      box-shadow: 0 8px 24px rgba(15, 23, 42, .06)
    }

    .row {
      display: flex;
      gap: 10px;
      flex-wrap: wrap;
      align-items: center
    }

    .input,
    .select {
      padding: 10px 12px;
      border: 1px solid var(--line);
      border-radius: 10px;
      background: #fff
    }

    .btn {
      padding: 10px 14px;
      border-radius: 10px;
      border: 0;
      cursor: pointer;
      font-weight: 700
    }

    .btn-primary {
      background: var(--blue2);
      color: #fff;
      box-shadow: 0 6px 16px rgba(37, 99, 235, .18)
    }

    .btn-muted {
      background: #e5e7eb
    }

    .badge {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      padding: 6px 10px;
      border-radius: 999px;
      font-weight: 700;
      font-size: 12px
    }

    .b-ok {
      background: #dcfce7;
      color: #166534
    }

    .alert {
      padding: 10px;
      border-radius: 10px;
      margin: 10px 0 0
    }

    .alert-ok {
      background: #dcfce7
    }

    .table {
      width: 100%;
      border-collapse: collapse
    }

    .table th,
    .table td {
      padding: 10px;
      border-bottom: 1px solid var(--line);
      text-align: left
    }

    .table th {
      background: #eef2ff
    }

    .rbtn {
      display: flex;
      gap: 14px;
      align-items: center
    }

    .rbtn label {
      display: inline-flex;
      gap: 6px;
      align-items: center;
      cursor: pointer
    }

    @media (max-width:992px) {
      .sidebar {
        position: relative;
        width: 100%;
        height: auto
      }

      .main {
        margin-left: 0;
        padding: 20px
      }
    }
  </style>
</head>

<body>

  <!-- Sidebar -->
  <div class="sidebar">
    <h2>👩‍🏫 แดชบอร์ดครู</h2>
    <a href="teacher_dashboard.php"><i class="bi bi-house"></i> หน้าหลักครู</a>
    <a href="content_manage.php"><i class="bi bi-folder2-open"></i> เนื้อหา/เอกสาร</a>
    <a href="teacher_assign_list.php"><i class="bi bi-card-checklist"></i> งานที่มอบหมาย</a>
    <a href="teacher_assign_create.php"><i class="bi bi-clipboard-plus"></i> สร้างงานใหม่</a>
    <a class="active" href="attendance_manage.php"><i class="bi bi-clipboard-check"></i> เช็คชื่อ</a>
    <a href="behavior_manage.php"><i class="bi bi-emoji-smile"></i> ความประพฤติ</a>
    <a href="logout.php"><i class="bi bi-box-arrow-right"></i> ออกจากระบบ</a>
  </div>

  <!-- Main -->
  <div class="main">

    <div class="card">
      <h2 style="margin:0 0 10px"><i class="bi bi-clipboard-check"></i> เช็คชื่อ</h2>

      <form method="get" class="row" action="attendance_manage.php">
        <select class="select" name="course_id" onchange="this.form.submit()">
          <?php foreach ($courses as $c): ?>
            <option value="<?= (int)$c['course_id'] ?>" <?= $course_id == $c['course_id'] ? 'selected' : '' ?>>
              <?= h($c['title']) ?>
            </option>
          <?php endforeach; ?>
        </select>

        <input class="input" type="date" name="att_date" value="<?= h($att_date) ?>" onchange="this.form.submit()">
        <?php if ($msg): ?><span class="badge b-ok"><?= h($msg) ?></span><?php endif; ?>
      </form>
    </div>

    <?php if ($course_id): ?>
      <div class="card">
        <form method="post">
          <input type="hidden" name="att_date" value="<?= h($att_date) ?>">
          <table class="table">
            <thead>
              <tr>
                <th style="width:70px">รหัส</th>
                <th>ชื่อนักเรียน</th>
                <th style="width:380px">สถานะเข้าชั้น</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($list as $r):
                $sid = (int)$r['user_id'];
                $status = $r['status'] ?: 'present';
              ?>
                <tr>
                  <td>#<?= $sid ?></td>
                  <td><?= h($r['name']) ?></td>
                  <td>
                    <div class="rbtn">
                      <label><input type="radio" name="status_<?= $sid ?>" value="present" <?= $status === 'present' ? 'checked' : '' ?>> มาเรียน</label>
                      <label><input type="radio" name="status_<?= $sid ?>" value="late" <?= $status === 'late' ? 'checked' : '' ?>> มาสาย</label>
                      <label><input type="radio" name="status_<?= $sid ?>" value="absent" <?= $status === 'absent' ? 'checked' : '' ?>> ขาด</label>
                    </div>
                  </td>
                </tr>
              <?php endforeach;
              if (!$list): ?>
                <tr>
                  <td colspan="3" style="color:#64748b">ยังไม่มีนักเรียนในรายวิชานี้</td>
                </tr>
              <?php endif; ?>
            </tbody>
          </table>

          <div style="margin-top:10px;display:flex;gap:8px">
            <button class="btn btn-primary" type="submit"><i class="bi bi-check2-circle"></i> บันทึกการเช็คชื่อ</button>
            <a class="btn btn-muted" href="attendance_manage.php?course_id=<?= (int)$course_id ?>&att_date=<?= h($att_date) ?>">
              <i class="bi bi-arrow-clockwise"></i> โหลดใหม่
            </a>
          </div>
        </form>
      </div>
    <?php endif; ?>

  </div>
</body>

</html>