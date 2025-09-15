<?php
// behavior_manage.php — ครู/แอดมินใช้บันทึกและประเมินพฤติกรรม
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
$eval_date = $_GET['date'] ?? date('Y-m-d');

// ----- รายวิชาของครู (ไว้เลือกจากดรอปดาวน์) -----
$st = $pdo->prepare("SELECT course_id, title FROM courses WHERE teacher_id=? ORDER BY title");
$st->execute([$tid]);
$courses = $st->fetchAll(PDO::FETCH_ASSOC);
if (!$course_id && $courses) {
  $course_id = (int)$courses[0]['course_id'];
}

// ----- ตรวจสิทธิ์คอร์ส -----
if ($course_id) {
  $chk = $pdo->prepare("SELECT COUNT(*) FROM courses WHERE course_id=? AND teacher_id=?");
  $chk->execute([$course_id, $tid]);
  if (!$chk->fetchColumn() && !is_admin()) {
    die('Forbidden');
  }
}

$msg = "";

// ----- นักเรียนในคอร์ส -----
$students = [];
if ($course_id) {
  $q = "SELECT u.user_id, u.name
        FROM enrollments e
        JOIN users u ON u.user_id = e.user_id
        WHERE e.course_id = ?
          AND (e.status IS NULL OR e.status='active')
        ORDER BY u.name";
  $st = $pdo->prepare($q);
  $st->execute([$course_id]);
  $students = $st->fetchAll(PDO::FETCH_ASSOC);
}

// ----- บันทึก (หลายคนในครั้งเดียว) -----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $course_id) {
  $eval_date = $_POST['date'] ?? $eval_date;

  $ins = $pdo->prepare(
    "INSERT INTO behavior_reports(user_id, course_id, type, note, date)
     VALUES(?,?,?,?,?)"
  );

  $saved = 0;
  foreach ($students as $s) {
    $sid  = (int)$s['user_id'];
    $type = $_POST['type_' . $sid] ?? '';          // positive / negative หรือว่าง
    $note = trim($_POST['note_' . $sid] ?? '');

    if ($type === '' && $note === '') continue;  // ไม่กรอกอะไร ข้าม

    // ถ้าไม่เลือกประเภท แต่มีหมายเหตุ จะนับเป็น neutral ด้วยการเก็บ type เป็น NULL
    $ins->execute([$sid, $course_id, ($type ?: null), ($note ?: null), $eval_date]);
    $saved++;
  }
  $msg = $saved ? "บันทึกแล้ว $saved รายการ" : "ไม่มีรายการที่บันทึก";
}

// ----- สรุปผลต่อคน (นับจำนวน positive / negative) -----
$summary = [];
if ($course_id) {
  $q = "SELECT user_id,
               SUM(CASE WHEN type='positive' THEN 1 ELSE 0 END) AS pos_cnt,
               SUM(CASE WHEN type='negative' THEN 1 ELSE 0 END) AS neg_cnt
        FROM behavior_reports
        WHERE course_id=?
        GROUP BY user_id";
  $st = $pdo->prepare($q);
  $st->execute([$course_id]);
  $summary = $st->fetchAll(PDO::FETCH_UNIQUE | PDO::FETCH_ASSOC);
}

// ----- ประวัติรายวัน (ของวันที่เลือก) -----
$logs = [];
if ($course_id) {
  $q = "SELECT b.report_id, b.user_id, b.type, b.note, b.date,
               u.name AS student_name
        FROM behavior_reports b
        JOIN users u ON u.user_id=b.user_id
        WHERE b.course_id=? AND b.date=?
        ORDER BY b.report_id DESC";
  $st = $pdo->prepare($q);
  $st->execute([$course_id, $eval_date]);
  $logs = $st->fetchAll(PDO::FETCH_ASSOC);
}

function h($s)
{
  return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}
?>
<!doctype html>
<html lang="th">

<head>
  <meta charset="utf-8">
  <title>ความประพฤติ | สถาบันติวเตอร์</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
  <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;600&display=swap" rel="stylesheet">
  <style>
    :root {
      --blue: #3b82f6;
      --blue2: #2563eb;
      --ink: #0f172a;
      --muted: #64748b;
      --bg: #f6f7fb;
      --card: #fff;
      --line: #e5e7eb;
      --ok: #16a34a;
      --err: #ef4444;
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

    /* Sidebar */
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
      border-radius: 10px;
      opacity: .95;
      transition: .15s
    }

    .sidebar a:hover {
      background: rgba(255, 255, 255, .12);
      opacity: 1
    }

    /* Main */
    .main {
      flex: 1;
      margin-left: 230px;
      padding: 28px
    }

    .card {
      background: var(--card);
      border-radius: 14px;
      box-shadow: 0 8px 24px rgba(15, 23, 42, .06);
      padding: 18px;
      margin-bottom: 14px
    }

    .row {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 12px
    }

    label {
      font-size: 14px;
      color: var(--muted)
    }

    input,
    select,
    textarea {
      width: 100%;
      padding: 10px;
      border: 1px solid var(--line);
      border-radius: 10px
    }

    .btn {
      padding: 10px 14px;
      border: none;
      border-radius: 10px;
      cursor: pointer;
      font-weight: 700
    }

    .btn-primary {
      background: var(--blue2);
      color: #fff
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
      font-weight: 700
    }

    .b-pos {
      background: #dcfce7;
      color: #166534
    }

    .b-neg {
      background: #fee2e2;
      color: #991b1b
    }

    .table {
      width: 100%;
      border-collapse: collapse
    }

    .table th,
    .table td {
      padding: 10px;
      border-bottom: 1px solid var(--line);
      text-align: left;
      vertical-align: top
    }

    .table th {
      background: #eef2ff
    }

    .muted {
      color: var(--muted)
    }

    @media(max-width:992px) {
      .row {
        grid-template-columns: 1fr
      }

      .sidebar {
        position: relative;
        width: 100%;
        inset: auto
      }

      .main {
        margin-left: 0
      }
    }
  </style>
</head>

<body>

  <!-- Sidebar -->
  <div class="sidebar">
    <h2>🙂 ความประพฤติ</h2>
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
      <h2 style="margin:6px 0 12px"><i class="bi bi-emoji-smile"></i> บันทึก/ประเมินความประพฤติ</h2>
      <form method="get" class="row" style="align-items:end">
        <div>
          <label>เลือกวิชา</label>
          <select name="course_id" onchange="this.form.submit()">
            <?php foreach ($courses as $c): ?>
              <option value="<?= (int)$c['course_id'] ?>" <?= $course_id == $c['course_id'] ? 'selected' : '' ?>>
                <?= h($c['title']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label>วันที่ประเมิน</label>
          <input type="date" name="date" value="<?= h($eval_date) ?>" onchange="this.form.submit()">
        </div>
      </form>
      <?php if ($msg): ?><div class="badge b-pos" style="margin-top:10px"><?= h($msg) ?></div><?php endif; ?>
    </div>

    <?php if ($course_id): ?>
      <!-- ฟอร์มบันทึก -->
      <div class="card">
        <h3 style="margin:0 0 10px">แบบฟอร์มบันทึก (<?= h($eval_date) ?>)</h3>
        <form method="post">
          <input type="hidden" name="date" value="<?= h($eval_date) ?>">
          <table class="table">
            <thead>
              <tr>
                <th style="width:26%">นักเรียน</th>
                <th style="width:28%">ประเภท</th>
                <th>หมายเหตุ</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($students as $s): $sid = (int)$s['user_id']; ?>
                <tr>
                  <td><strong><?= h($s['name']) ?></strong></td>
                  <td>
                    <label style="margin-right:12px">
                      <input type="radio" name="type_<?= $sid ?>" value="positive"> <span class="badge b-pos">positive</span>
                    </label>
                    <label>
                      <input type="radio" name="type_<?= $sid ?>" value="negative"> <span class="badge b-neg">negative</span>
                    </label>
                  </td>
                  <td><input name="note_<?= $sid ?>" placeholder="ระบุเหตุผล/รายละเอียด (ไม่บังคับ)"></td>
                </tr>
              <?php endforeach;
              if (!$students): ?>
                <tr>
                  <td colspan="3" class="muted">ยังไม่มีนักเรียนในคอร์สนี้</td>
                </tr>
              <?php endif; ?>
            </tbody>
          </table>
          <div style="margin-top:10px">
            <button class="btn btn-primary" type="submit"><i class="bi bi-check2-circle"></i> บันทึก</button>
          </div>
        </form>
      </div>

      <!-- สรุปผลรวมต่อคน -->
      <div class="card">
        <h3 style="margin:0 0 10px">สรุปผลรวมต่อคน (ทั้งหมดในคอร์ส)</h3>
        <table class="table">
          <thead>
            <tr>
              <th>นักเรียน</th>
              <th>Positive</th>
              <th>Negative</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($students as $s):
              $sid = (int)$s['user_id'];
              $pos = (int)($summary[$sid]['pos_cnt'] ?? 0);
              $neg = (int)($summary[$sid]['neg_cnt'] ?? 0);
            ?>
              <tr>
                <td><?= h($s['name']) ?></td>
                <td><span class="badge b-pos"><?= $pos ?></span></td>
                <td><span class="badge b-neg"><?= $neg ?></span></td>
              </tr>
            <?php endforeach;
            if (!$students): ?>
              <tr>
                <td colspan="3" class="muted">ไม่มีข้อมูล</td>
              </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>

      <!-- ประวัติของวันที่เลือก -->
      <div class="card">
        <h3 style="margin:0 0 10px">ประวัติ (<?= h($eval_date) ?>)</h3>
        <table class="table">
          <thead>
            <tr>
              <th>นักเรียน</th>
              <th>ประเภท</th>
              <th>หมายเหตุ</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($logs as $l): ?>
              <tr>
                <td><?= h($l['student_name']) ?></td>
                <td>
                  <?php if ($l['type'] === 'positive'): ?>
                    <span class="badge b-pos">positive</span>
                  <?php elseif ($l['type'] === 'negative'): ?>
                    <span class="badge b-neg">negative</span>
                  <?php else: ?>
                    <span class="muted">—</span>
                  <?php endif; ?>
                </td>
                <td><?= h($l['note'] ?? '') ?></td>
              </tr>
            <?php endforeach;
            if (!$logs): ?>
              <tr>
                <td colspan="3" class="muted">ยังไม่มีข้อมูลในวันที่เลือก</td>
              </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</body>

</html>