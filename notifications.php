<?php
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit;
}

$u = $_SESSION['user'];
$studentId = (int)($u['user_id'] ?? ($u['id'] ?? 0));
$name      = $u['name'] ?? 'ผู้ใช้';
$role      = strtolower($u['role'] ?? 'student');

/* ---------- ต่อฐานข้อมูล ---------- */
$pdo = null;
if (is_file(__DIR__ . '/config/db.php')) {
    require __DIR__ . '/config/db.php';
}
if (!($pdo instanceof PDO)) {
    try {
        $pdo = new PDO("mysql:host=localhost;dbname=school_system;charset=utf8mb4", "root", "");
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    } catch (Throwable $e) {
        $pdo = null;
    }
}
$hasDb = $pdo instanceof PDO;

/* ---------- ฟังก์ชันช่วย ---------- */
function getSubmissionsMap(PDO $pdo, int $studentId): array
{
    // คืน map โดยพยายามใช้ assignment_id ก่อน ถ้าไม่มีให้ fallback เป็น course_id
    $map = ['by_assign' => [], 'by_course' => []];
    try {
        $st = $pdo->prepare("SELECT assignment_id, MAX(submitted_at) AS submitted_at
                         FROM submissions WHERE user_id=? AND assignment_id IS NOT NULL
                         GROUP BY assignment_id");
        $st->execute([$studentId]);
        foreach ($st as $r) {
            if ($r['assignment_id']) $map['by_assign'][(int)$r['assignment_id']] = $r['submitted_at'];
        }
    } catch (Throwable $e) {
    }
    try {
        $st = $pdo->prepare("SELECT course_id, MAX(submitted_at) AS submitted_at
                         FROM submissions WHERE user_id=? AND course_id IS NOT NULL
                         GROUP BY course_id");
        $st->execute([$studentId]);
        foreach ($st as $r) {
            if ($r['course_id']) $map['by_course'][(int)$r['course_id']] = $r['submitted_at'];
        }
    } catch (Throwable $e) {
    }
    return $map;
}

function fetchAssignmentsForStudent(PDO $pdo, int $studentId): array
{
    // ดึงงานของวิชาที่นักเรียนลงทะเบียน + ชื่อวิชา + ชื่ออาจารย์
    $sql = "SELECT
            a.assignment_id AS id,
            a.course_id,
            COALESCE(a.title, a.name, 'งานไม่มีชื่อ')      AS title,
            COALESCE(a.description, '')                    AS description,
            COALESCE(a.due_date, a.deadline, a.due)        AS due_date,
            COALESCE(a.teacher_id, a.assigned_by)          AS teacher_id,
            c.title  AS course_title,
            u.name   AS teacher_name
          FROM assignments a
          INNER JOIN enrollments e ON e.course_id = a.course_id
          LEFT JOIN courses c ON c.course_id = a.course_id
          LEFT JOIN users   u ON u.user_id   = COALESCE(a.teacher_id, a.assigned_by)
          WHERE e.student_id = ?
          ORDER BY COALESCE(a.due_date,a.deadline,a.due) ASC, a.assignment_id DESC";
    $st = $pdo->prepare($sql);
    $st->execute([$studentId]);
    return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

/* ---------- เตรียมข้อมูล ---------- */
$rows = [];
$subs = ['by_assign' => [], 'by_course' => []];
if ($hasDb && $studentId) {
    try {
        $rows = fetchAssignmentsForStudent($pdo, $studentId);
    } catch (Throwable $e) {
        $rows = [];
    }
    try {
        $subs = getSubmissionsMap($pdo, $studentId);
    } catch (Throwable $e) {
    }
}

$today = new DateTimeImmutable('today');
$soon  = $today->modify('+7 days');

$assigned   = []; // งานที่มอบหมายทั้งหมด
$dueSoon    = []; // ภายใน 7 วัน (ยังไม่ส่ง)
$overdue    = []; // เลยกำหนด/ยังไม่ส่ง

foreach ($rows as $r) {
    $id         = (int)($r['id'] ?? 0);
    $courseId   = (int)($r['course_id'] ?? 0);
    $title      = $r['title'] ?? 'งานไม่มีชื่อ';
    $courseName = $r['course_title'] ?? ('วิชา #' . $courseId);
    $teacher    = $r['teacher_name'] ?? ('ครู #' . ($r['teacher_id'] ?? ''));
    $dueRaw     = $r['due_date'] ?? null;

    // วันที่
    $dueDt = null;
    if ($dueRaw) {
        try {
            $dueDt = new DateTimeImmutable($dueRaw);
        } catch (Throwable $e) {
        }
    }

    // ส่งแล้วหรือยัง
    $submittedAt = $subs['by_assign'][$id] ?? ($subs['by_course'][$courseId] ?? null);
    $isSubmitted = $submittedAt !== null;

    $item = [
        'id' => $id,
        'title' => $title,
        'course' => $courseName,
        'teacher' => $teacher,
        'due' => $dueDt ? $dueDt->format('Y-m-d H:i') : '—',
        'isSubmitted' => $isSubmitted,
        'submittedAt' => $submittedAt
    ];

    $assigned[] = $item;

    if (!$isSubmitted && $dueDt) {
        if ($dueDt < $today)        $overdue[] = $item;
        elseif ($dueDt <= $soon)    $dueSoon[] = $item;
    }
}
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <title>การแจ้งเตือน</title>
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
            --ok: #16a34a;
            --warn: #eab308;
            --danger: #ef4444;
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

        .section-title {
            display: flex;
            align-items: center;
            gap: 8px;
            margin: 0 0 10px
        }

        .tag {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: #eef2ff;
            color: #1e3a8a;
            border-radius: 999px;
            padding: 6px 10px;
            font-size: 13px;
            font-weight: 600
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

        .muted {
            color: var(--muted)
        }

        .ok {
            color: var(--ok);
            font-weight: 600
        }

        .warn {
            color: var(--warn);
            font-weight: 600
        }

        .danger {
            color: var(--danger);
            font-weight: 600
        }

        .empty {
            color: var(--muted);
            padding: 12px 0
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
        <h2>📘 แจ้งเตือน</h2>
        <a href="dashboard.php"><i class="bi bi-house-fill"></i> หน้าแรก</a>
        <a href="student.php"><i class="bi bi-person-circle"></i> นักเรียน</a>
        <a href="courses.php"><i class="bi bi-journal-bookmark-fill"></i> รายวิชา</a>
        <a href="my_enrollments.php"><i class="bi bi-journal-bookmark-fill"></i> ลงทะเบียนเรียน</a>
        <a href="grades.php"><i class="bi bi-bar-chart-line-fill"></i> ผลการเรียน</a>
        <a href="notifications.php"><i class="bi bi-bell-fill"></i> แจ้งเตือน</a>
        <a href="logout.php"><i class="bi bi-box-arrow-right"></i> ออกจากระบบ</a>
    </div>

    <div class="main">
        <div class="card">
            <h2 class="section-title"><i class="bi bi-bell-fill"></i> การแจ้งเตือน</h2>
            <div class="tag">ผู้ใช้: <?= htmlspecialchars($name) ?></div>
        </div>

        <!-- งานค้าง -->
        <div class="card">
            <h3 class="section-title"><i class="bi bi-exclamation-triangle-fill"></i> งานค้าง (เลยกำหนด / ยังไม่ส่ง)</h3>
            <?php if ($overdue): ?>
                <table>
                    <thead>
                        <tr>
                            <th>ชื่องาน</th>
                            <th>รายวิชา</th>
                            <th>อาจารย์</th>
                            <th>กำหนดส่ง</th>
                            <th>สถานะ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($overdue as $r): ?>
                            <tr>
                                <td><?= htmlspecialchars($r['title']) ?></td>
                                <td><?= htmlspecialchars($r['course']) ?></td>
                                <td><?= htmlspecialchars($r['teacher']) ?></td>
                                <td><?= htmlspecialchars($r['due']) ?></td>
                                <td class="danger">ค้างส่ง</td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="empty">ไม่มีงานค้าง 🎉</div>
            <?php endif; ?>
        </div>

        <!-- งานที่กำหนดส่งภายใน 7 วัน -->
        <div class="card">
            <h3 class="section-title"><i class="bi bi-hourglass-split"></i> งานที่กำหนดส่ง (ภายใน 7 วัน)</h3>
            <?php if ($dueSoon): ?>
                <table>
                    <thead>
                        <tr>
                            <th>ชื่องาน</th>
                            <th>รายวิชา</th>
                            <th>อาจารย์</th>
                            <th>กำหนดส่ง</th>
                            <th>สถานะ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($dueSoon as $r): ?>
                            <tr>
                                <td><?= htmlspecialchars($r['title']) ?></td>
                                <td><?= htmlspecialchars($r['course']) ?></td>
                                <td><?= htmlspecialchars($r['teacher']) ?></td>
                                <td><?= htmlspecialchars($r['due']) ?></td>
                                <td class="warn">ใกล้กำหนด</td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="empty">ไม่มีงานที่กำหนดส่งภายในสัปดาห์นี้</div>
            <?php endif; ?>
        </div>

        <!-- งานที่อาจารย์มอบหมายทั้งหมด -->
        <div class="card">
            <h3 class="section-title"><i class="bi bi-card-checklist"></i> งานที่อาจารย์มอบหมาย</h3>
            <?php if ($assigned): ?>
                <table>
                    <thead>
                        <tr>
                            <th>ชื่องาน</th>
                            <th>รายวิชา</th>
                            <th>อาจารย์</th>
                            <th>กำหนดส่ง</th>
                            <th>ส่งแล้ว</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($assigned as $r): ?>
                            <tr>
                                <td><?= htmlspecialchars($r['title']) ?></td>
                                <td><?= htmlspecialchars($r['course']) ?></td>
                                <td><?= htmlspecialchars($r['teacher']) ?></td>
                                <td><?= htmlspecialchars($r['due']) ?></td>
                                <td><?= $r['isSubmitted'] ? '<span class="ok">ส่งแล้ว</span>' : '<span class="muted">ยังไม่ส่ง</span>' ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="empty">ยังไม่มีงานที่ได้รับมอบหมาย</div>
            <?php endif; ?>
        </div>
    </div>
</body>

</html>