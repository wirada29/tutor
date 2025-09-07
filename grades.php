<?php
session_start();

/* --------- ตรวจสอบการล็อกอิน --------- */
if (!isset($_SESSION['user']) && !isset($_SESSION['name'])) {
  header("Location: login.php");
  exit();
}

/* --------- ดึงข้อมูลผู้ใช้จาก session --------- */
if (isset($_SESSION['user']) && is_array($_SESSION['user'])) {
  $u = $_SESSION['user'];
} else {
  $u = [
    'id'   => $_SESSION['id']   ?? ($_SESSION['user_id'] ?? null),
    'name' => $_SESSION['name'] ?? 'ผู้ใช้',
    'role' => $_SESSION['role'] ?? 'student',
  ];
}

$studentId = $u['id'] ?? ($u['user_id'] ?? null);
$name      = $u['name'] ?? 'ผู้ใช้';

/* --------- พยายามเชื่อม DB --------- */
$pdo = null;
try {
  $pdo = new PDO("mysql:host=localhost;dbname=school_system;charset=utf8mb4", "root", "");
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Throwable $e) {
  $pdo = null; // ถ้าเชื่อมไม่ได้จะใช้ session
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
  try {
    $sql = "SELECT g.course_id, g.total_score, g.grade, c.course_name
            FROM grades g
            LEFT JOIN courses c ON c.course_id = g.course_id
            WHERE g.user_id = ?
            ORDER BY g.grade_id DESC";
    $st = $pdo->prepare($sql);
    $st->execute([$studentId]);
    $rows = $st->fetchAll();
  } catch (Throwable $e) {
    $sql = "SELECT course_id, total_score, grade
            FROM grades
            WHERE user_id = ?
            ORDER BY grade_id DESC";
    $st = $pdo->prepare($sql);
    $st->execute([$studentId]);
    $rows = $st->fetchAll();
  }
} else {
  // mock ถ้าไม่มี DB
  $rows = $_SESSION['grades'] ?? [
    ['course_id' => 1, 'course_name' => 'คณิตศาสตร์', 'total_score' => 87, 'grade' => 'A'],
    ['course_id' => 2, 'course_name' => 'วิทยาศาสตร์', 'total_score' => 72, 'grade' => 'B+'],
  ];
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
    body {
      margin: 0;
      font-family: 'Sarabun', sans-serif;
      background: #f5f7fa;
      color: #1a202c;
      display: flex;
      min-height: 100vh
    }

    .sidebar {
      width: 230px;
      background: #3b82f6;
      color: #fff;
      padding: 24px 16px;
      position: fixed;
      top: 0;
      bottom: 0;
      left: 0
    }

    .sidebar h2 {
      text-align: center;
      margin: 0 0 24px
    }

    .sidebar a {
      display: flex;
      align-items: center;
      gap: 8px;
      padding: 10px;
      color: #fff;
      text-decoration: none;
      border-radius: 8px;
      margin-bottom: 10px
    }

    .sidebar a:hover {
      background: #2563eb
    }

    .main {
      flex: 1;
      margin-left: 230px;
      padding: 28px
    }

    .card {
      background: #fff;
      padding: 20px;
      border-radius: 12px;
      box-shadow: 0 4px 12px rgba(0, 0, 0, .08);
      margin-bottom: 20px
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

    .pill {
      display: inline-block;
      background: #e2e8f0;
      padding: 6px 10px;
      border-radius: 999px;
      margin-left: 6px
    }

    .muted {
      color: #64748b
    }

    @media(max-width:768px) {
      .sidebar {
        position: relative;
        width: 100%;
        height: auto
      }

      .main {
        margin-left: 0
      }
    }
  </style>
</head>

<body>
  <div class="sidebar">
    <h2>📘 นักเรียน</h2>
    <a href="dashboard.php"><i class="bi bi-house-fill"></i> หน้าแรก</a>
    <a href="student.php"><i class="bi bi-person"></i> โปรไฟล์</a>
    <a href="grades.php"><i class="bi bi-bar-chart-line-fill"></i> ผลการเรียน</a>
    <a href="logout.php"><i class="bi bi-box-arrow-right"></i> ออกจากระบบ</a>
  </div>

  <div class="main">
    <div class="card">
      <h2 style="margin:0">ผลการเรียนของ <?= htmlspecialchars($name) ?>
        <?php if ($gpa !== null): ?><span class="pill">GPA: <?= number_format($gpa, 2) ?></span><?php endif; ?>
      </h2>
    </div>

    <div class="card">
      <?php if ($rows): ?>
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
              $course = $r['course_name'] ?? ('วิชา #' . $r['course_id']);
              $score  = $r['total_score'] ?? '-';
              $grade  = $r['grade'] ?? '-';
            ?>
              <tr>
                <td><?= htmlspecialchars($course) ?></td>
                <td><?= htmlspecialchars($score) ?></td>
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