<?php
session_start();

/* ========== DEV DEBUG (เปลี่ยนเป็น false ในโปรดักชัน) ========== */
$DEV = false;

/* --------- ตรวจสอบการล็อกอิน --------- */
if (!isset($_SESSION['user']) && !isset($_SESSION['name'])) {
  header("Location: login.php");
  exit();
}

/* --------- ดึงข้อมูลผู้ใช้จาก session --------- */
if (isset($_SESSION['user']) && is_array($_SESSION['user'])) {
  $u = $_SESSION['user']; // ควรมี user_id, name, role
} else {
  $u = [
    'user_id' => $_SESSION['user_id'] ?? ($_SESSION['id'] ?? null),
    'name'    => $_SESSION['name']     ?? 'ผู้ใช้',
    'role'    => $_SESSION['role']     ?? 'student',
  ];
}

$studentId = $u['user_id'] ?? ($u['id'] ?? null);
$name      = $u['name']    ?? 'ผู้ใช้';
$role      = strtolower($u['role'] ?? 'student');

/* --------- เชื่อม DB --------- */
$pdo = null;
$dbError = null;
try {
  $pdo = new PDO("mysql:host=localhost;dbname=school_system;charset=utf8mb4", "root", "");
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (Throwable $e) {
  $dbError = $e->getMessage();
  $pdo = null; // ใช้ mock ข้างล่างแทน
}

/* --------- helper: สร้างรายการวิชาจาก DB โดยทนคอลัมน์ต่างชื่อ --------- */
function loadGradesResilient(?PDO $pdo, ?int $studentId, bool $DEV = false): array
{
  if (!$pdo || !$studentId) return [];

  // ชื่อคอลัมน์ที่เป็นไปได้
  $userCols   = ['g.user_id', 'g.student_id', 'g.users_id', 'g.uid'];
  $courseCols = ['g.course_id', 'g.subject_id'];
  $gradeCols  = ['g.grade', 'g.letter_grade', 'g.grade_letter'];
  $scoreCols  = ['g.total_score', 'g.score', 'g.final_score', 'g.total'];
  $orderCols  = ['g.grade_id', 'g.id', 'g.updated_at', 'g.created_at'];

  // ตารางวิชา (เผื่อใช้ชื่อไม่เหมือนกัน)
  $courseTables = [
    ['table' => 'courses',  'id' => 'course_id',  'name' => 'course_name'],
    ['table' => 'subjects', 'id' => 'id',         'name' => 'name'],
  ];

  // ลอง userCol × courseCol × gradeCol × scoreCol × orderCol × courseTable
  foreach ($userCols as $UC) {
    foreach ($courseCols as $CC) {
      foreach ($gradeCols as $GC) {
        foreach ($scoreCols as $SC) {
          foreach ($orderCols as $OC) {
            foreach ($courseTables as $ct) {
              $t = $ct['table'];
              $cid = $ct['id'];
              $cname = $ct['name'];

              $sql = "
                SELECT {$CC} AS course_id,
                       {$SC} AS total_score,
                       {$GC} AS grade,
                       c.{$cname} AS course_name
                FROM grades g
                LEFT JOIN {$t} c ON c.{$cid} = {$CC}
                WHERE {$UC} = ?
                ORDER BY {$OC} DESC
              ";
              try {
                $st = $pdo->prepare($sql);
                $st->execute([$studentId]);
                $rows = $st->fetchAll();
                if ($rows && is_array($rows)) return $rows;
              } catch (Throwable $e) {
                if ($DEV) error_log("SQL fail: " . $e->getMessage() . " | " . $sql);
                // ลองชุดถัดไป
              }
            }
          }
        }
      }
    }
  }

  // แผนสำรอง: ดึงแบบไม่ join วิชา
  $fallbacks = [
    "SELECT course_id AS course_id, total_score AS total_score, grade AS grade FROM grades WHERE user_id = ? ORDER BY id DESC",
    "SELECT subject_id AS course_id, score AS total_score, letter_grade AS grade FROM grades WHERE student_id = ? ORDER BY id DESC",
  ];
  foreach ($fallbacks as $sql) {
    try {
      $st = $pdo->prepare($sql);
      $st->execute([$studentId]);
      $rows = $st->fetchAll();
      if ($rows) return $rows;
    } catch (Throwable $e) {
      if ($DEV) error_log("Fallback SQL fail: " . $e->getMessage() . " | " . $sql);
    }
  }
  return [];
}

/* --------- ฟังก์ชันแปลงเกรด --------- */
function gp_from_letter(?string $g): ?float
{
  if (!$g) return null;
  $g = strtoupper(trim($g));
  return match ($g) {
    'A' => 4.0,
    'B+' => 3.5,
    'B' => 3.0,
    'C+' => 2.5,
    'C' => 2.0,
    'D+' => 1.5,
    'D' => 1.0,
    'F' => 0.0,
    default => null
  };
}
function gp_from_score(float $s): float
{
  if ($s >= 80) return 4.0;
  if ($s >= 75) return 3.5;
  if ($s >= 70) return 3.0;
  if ($s >= 65) return 2.5;
  if ($s >= 60) return 2.0;
  if ($s >= 55) return 1.5;
  if ($s >= 50) return 1.0;
  return 0.0;
}

/* --------- โหลดผลการเรียน --------- */
$rows = [];
if ($pdo && $studentId) {
  $rows = loadGradesResilient($pdo, (int)$studentId, $DEV);
}
if (!$rows) {
  // mock เมื่อไม่มี DB/ไม่มีข้อมูล
  $rows = $_SESSION['grades'] ?? [
    ['course_id' => 1, 'course_name' => 'คณิตศาสตร์',  'total_score' => 87, 'grade' => 'A'],
    ['course_id' => 2, 'course_name' => 'วิทยาศาสตร์', 'total_score' => 72, 'grade' => 'B+'],
  ];
  $noDbNote = $dbError ? 'เชื่อมต่อฐานข้อมูลไม่ได้: ' . $dbError : 'ยังไม่มีข้อมูลจากฐานข้อมูล (แสดงตัวอย่างชั่วคราว)';
}

/* --------- คำนวณ GPA --------- */
$total = 0.0;
$n = 0;
foreach ($rows as $r) {
  $gp = gp_from_letter($r['grade'] ?? null);
  if ($gp === null && isset($r['total_score'])) $gp = gp_from_score((float)$r['total_score']);
  if ($gp !== null) {
    $total += $gp;
    $n++;
  }
}
$gpa = $n ? round($total / $n, 2) : null;

$current = basename($_SERVER['PHP_SELF']); // active menu
?>
<!DOCTYPE html>
<html lang="th">

<head>
  <meta charset="UTF-8">
  <title>ผลการเรียน</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;600&display=swap" rel="stylesheet">
  <style>
    :root {
      --blue: #3b82f6;
      --blue-dark: #2563eb;
      --ink: #0f172a;
      --muted: #64748b;
      --bg: #f5f7fa;
      --surface: #ffffff
    }

    * {
      box-sizing: border-box
    }

    body {
      margin: 0;
      font-family: 'Sarabun', sans-serif;
      background: var(--bg);
      color: var(--ink);
      display: flex;
      min-height: 100vh
    }

    /* Sidebar เหมือน dashboard */
    .sidebar {
      width: 230px;
      background: linear-gradient(180deg, var(--blue), var(--blue-dark));
      color: #fff;
      height: 100vh;
      padding: 26px 16px;
      position: fixed;
      inset: 0 auto 0 0;
      overflow-y: auto;
      box-shadow: 0 6px 20px rgba(0, 0, 0, .08);
      border-right: 1px solid rgba(255, 255, 255, .08)
    }

    .sidebar h2 {
      font-size: 22px;
      font-weight: 600;
      margin: 0 0 24px;
      text-align: center;
      display: flex;
      align-items: center;
      gap: 10px;
      color: #fff
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
      transition: transform .15s, background .2s, opacity .2s;
      opacity: .95
    }

    .sidebar a:hover {
      background: rgba(255, 255, 255, .15);
      transform: translateY(-1px);
      opacity: 1
    }

    .sidebar a.active {
      background: rgba(255, 255, 255, .22);
      box-shadow: inset 0 0 0 1px rgba(255, 255, 255, .18)
    }

    .main {
      flex: 1;
      margin-left: 230px;
      padding: 28px 32px
    }

    .card {
      background: var(--surface);
      padding: 22px;
      border-radius: 16px;
      box-shadow: 0 6px 24px rgba(15, 23, 42, .06);
      margin-bottom: 22px
    }

    .pill {
      display: inline-block;
      background: #e2e8f0;
      padding: 6px 12px;
      border-radius: 999px;
      margin: 2px 6px 2px 0;
      font-size: 14px
    }

    .muted {
      color: var(--muted);
      font-size: 14px
    }

    table {
      width: 100%;
      border-collapse: collapse
    }

    th,
    td {
      padding: 10px;
      border-bottom: 1px solid #e5e7eb;
      text-align: left
    }

    th {
      background: #eef2ff
    }

    @media(max-width:992px) {
      .sidebar {
        position: relative;
        width: 100%;
        height: auto;
        inset: auto
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
    <h2>📘 สถาบันติวเตอร์</h2>
    <a href="dashboard.php" class="<?= $current === 'dashboard.php' ? 'active' : '' ?>"><i class="bi bi-house-fill"></i> หน้าแรก</a>
    <a href="student.php" class="<?= $current === 'student.php' ? 'active' : '' ?>"><i class="bi bi-person-circle"></i> นักเรียน</a>
    <a href="courses.php" class="<?= $current === 'courses.php' ? 'active' : '' ?>"><i class="bi bi-journal-bookmark-fill"></i> รายวิชา</a>
    <a href="grades.php" class="<?= $current === 'grades.php' ? 'active' : '' ?>"><i class="bi bi-bar-chart-line-fill"></i> ผลการเรียน</a>
    <a href="notifications.php" class="<?= $current === 'notifications.php' ? 'active' : '' ?>"><i class="bi bi-bell-fill"></i> แจ้งเตือน</a>
    <?php if ($role === 'admin'): ?>
      <a href="users.php" class="<?= $current === 'users.php' ? 'active' : '' ?>"><i class="bi bi-people-fill"></i> ผู้ใช้ทั้งหมด</a>
    <?php endif; ?>
    <a href="logout.php"><i class="bi bi-box-arrow-right"></i> ออกจากระบบ</a>
  </div>

  <!-- Main -->
  <div class="main">
    <div class="card">
      <h2 style="margin:0">ผลการเรียนของ <?= htmlspecialchars($name) ?>
        <?php if ($gpa !== null): ?><span class="pill">GPA: <?= number_format($gpa, 2) ?></span><?php endif; ?>
      </h2>
      <?php if (isset($noDbNote)): ?><p class="muted" style="margin:8px 0 0"><?= htmlspecialchars($noDbNote) ?></p><?php endif; ?>
      <?php if ($DEV && $dbError): ?><p class="muted">[DEBUG] DB Error: <?= htmlspecialchars($dbError) ?></p><?php endif; ?>
    </div>

    <div class="card">
      <?php if ($rows && is_array($rows)): ?>
        <table>
          <thead>
            <tr>
              <th>รายวิชา</th>
              <th>คะแนนรวม</th>
              <th>เกรด</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($rows as $r):
              $course = $r['course_name'] ?? (isset($r['course_id']) ? 'วิชา #' . $r['course_id'] : 'วิชา');
              $score  = isset($r['total_score']) ? $r['total_score'] : (isset($r['score']) ? $r['score'] : '-');
              $grade  = $r['grade'] ?? ($r['letter_grade'] ?? '-');
            ?>
              <tr>
                <td><?= htmlspecialchars($course) ?></td>
                <td><?= htmlspecialchars(is_numeric($score) ? number_format((float)$score, 2) : $score) ?></td>
                <td><?= htmlspecialchars($grade) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php else: ?>
        <p class="muted">ยังไม่มีข้อมูลผลการเรียน</p>
      <?php endif; ?>
    </div>
  </div>
</body>

</html>