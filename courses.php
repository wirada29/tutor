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

/* ----- เชื่อมฐานข้อมูล ----- */
$pdo = null;
if (is_file(__DIR__ . '/config/db.php')) {
    require __DIR__ . '/config/db.php'; // ควรสร้าง $pdo = new PDO(...)
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

/* ----- รับคำค้นหา ----- */
$q = trim($_GET['q'] ?? '');

/* ---------- Helper DB ---------- */
function fetchCoursesExact(PDO $pdo, string $q = ''): array
{
    // หา teacher name จาก users (ถ้ามี)
    $sql =
        "SELECT c.course_id AS id,
            c.title,
            c.description,
            c.max_seats,
            c.status,
            c.teacher_id,
            u.name AS teacher_name
     FROM courses c
     LEFT JOIN users u ON u.user_id = c.teacher_id
     ";
    $args = [];
    if ($q !== '') {
        $sql .= " WHERE c.title LIKE ?";
        $args = ["%$q%"];
    }
    $sql .= " ORDER BY c.title ASC";
    $st = $pdo->prepare($sql);
    $st->execute($args);
    return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function enrolledMap(PDO $pdo, int $studentId): array
{
    // สมมติ enrollments(student_id, course_id)
    $map = [];
    try {
        $st = $pdo->prepare("SELECT course_id FROM enrollments WHERE student_id = ?");
        $st->execute([$studentId]);
        foreach ($st as $r) {
            $map[(int)$r['course_id']] = true;
        }
    } catch (Throwable $e) { /* เงียบ */
    }
    return $map;
}

function currentSeats(PDO $pdo, int $courseId): int
{
    try {
        $st = $pdo->prepare("SELECT COUNT(*) FROM enrollments WHERE course_id = ?");
        $st->execute([$courseId]);
        return (int)$st->fetchColumn();
    } catch (Throwable $e) {
        return 0;
    }
}

/* ---------- Actions (enroll/drop) ---------- */
$msg = '';
if ($hasDb && $studentId && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $action   = $_POST['action']   ?? '';
    $courseId = (int)($_POST['course_id'] ?? 0);

    if ($courseId > 0) {
        if ($action === 'enroll') {
            try {
                // เช็คที่นั่งว่างก่อน
                $st = $pdo->prepare("SELECT max_seats FROM courses WHERE course_id=?");
                $st->execute([$courseId]);
                $max = (int)($st->fetchColumn() ?: 0);
                $used = currentSeats($pdo, $courseId);
                if ($max > 0 && $used >= $max) {
                    $msg = "เต็มแล้ว ไม่สามารถลงทะเบียนได้";
                } else {
                    $st = $pdo->prepare("INSERT INTO enrollments(student_id, course_id) VALUES(?, ?)");
                    $st->execute([$studentId, $courseId]);
                    $msg = "ลงทะเบียนสำเร็จ";
                }
            } catch (Throwable $e) {
                $msg = "ลงทะเบียนไม่สำเร็จ: " . $e->getMessage();
            }
        } elseif ($action === 'drop') {
            try {
                $st = $pdo->prepare("DELETE FROM enrollments WHERE student_id=? AND course_id=?");
                $st->execute([$studentId, $courseId]);
                $msg = "ถอนรายวิชาเรียบร้อย";
            } catch (Throwable $e) {
                $msg = "ถอนรายวิชาไม่สำเร็จ: " . $e->getMessage();
            }
        }
    }
}

/* ---------- Load data ---------- */
$courses = $hasDb ? fetchCoursesExact($pdo, $q) : [];
$enrolled = ($hasDb && $studentId) ? enrolledMap($pdo, $studentId) : [];

?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <title>รายวิชา</title>
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

        .row {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            align-items: center
        }

        .input {
            padding: 10px 12px;
            border: 1px solid #e5e7eb;
            border-radius: 10px
        }

        .btn {
            padding: 10px 14px;
            border-radius: 10px;
            border: 0;
            cursor: pointer
        }

        .btn-primary {
            background: var(--blue-dark);
            color: #fff
        }

        .btn-muted {
            background: #e5e7eb
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
            text-align: left;
            vertical-align: top
        }

        th {
            background: #eef2ff
        }

        .status {
            font-weight: 600
        }

        .ok {
            color: var(--ok)
        }

        .muted {
            color: var(--muted)
        }

        .actions form {
            display: inline
        }

        .actions button {
            margin: 0 4px 6px 0
        }

        .desc {
            color: #475569;
            font-size: 14px
        }

        .alert {
            padding: 10px;
            border-radius: 10px;
            margin-bottom: 10px
        }

        .alert-ok {
            background: #dcfce7
        }

        .alert-err {
            background: #fee2e2
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
        <h2>📘 รายวิชา</h2>
        <a href="dashboard.php"><i class="bi bi-house-fill"></i> หน้าแรก</a>
        <a href="student.php"><i class="bi bi-person-circle"></i> โปรไฟล์</a>
        <a href="courses.php"><i class="bi bi-journal-bookmark-fill"></i> รายวิชา</a>
        <a href="grades.php"><i class="bi bi-bar-chart-line-fill"></i> ผลการเรียน</a>
        <a href="logout.php"><i class="bi bi-box-arrow-right"></i> ออกจากระบบ</a>
    </div>

    <div class="main">
        <div class="card">
            <h2 style="margin:0">📚 รายวิชา <span class="tag">ตาราง: courses</span></h2>
            <form class="row" method="get" action="courses.php" style="margin-top:10px">
                <input class="input" type="text" name="q" placeholder="ค้นหาชื่อวิชา (title)..." value="<?= htmlspecialchars($q) ?>">
                <button class="btn btn-primary" type="submit"><i class="bi bi-search"></i> ค้นหา</button>
                <?php if ($q !== ''): ?><a class="btn btn-muted" href="courses.php">ล้างคำค้น</a><?php endif; ?>
            </form>
            <?php if ($msg): ?>
                <div class="alert <?= (str_starts_with($msg, 'ลงทะเบียน') || str_starts_with($msg, 'ถอนรายวิชาเรียบร้อย')) ? 'alert-ok' : 'alert-err' ?>">
                    <?= htmlspecialchars($msg) ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="card">
            <?php if ($hasDb && $courses): ?>
                <table>
                    <thead>
                        <tr>
                            <th style="width:240px;">ชื่อวิชา</th>
                            <th>รายละเอียด</th>
                            <th style="width:120px;">อาจารย์</th>
                            <th style="width:110px;">ที่นั่ง</th>
                            <th style="width:110px;">สถานะ</th>
                            <th style="width:210px;">จัดการ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($courses as $c):
                            $id     = (int)$c['id'];
                            $title  = $c['title'] ?? 'ไม่ระบุชื่อ';
                            $desc   = $c['description'] ?? '';
                            $max    = (int)($c['max_seats'] ?? 0);
                            $status = strtolower($c['status'] ?? 'open'); // open/close อะไรก็ว่าไป
                            $used   = currentSeats($pdo, $id);
                            $left   = $max > 0 ? max(0, $max - $used) : '—';
                            $teacher = $c['teacher_name'] ?? ("ครู #" . $c['teacher_id']);
                            $isEnrolled = isset($enrolled[$id]);
                        ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($title) ?></strong></td>
                                <td class="desc"><?= nl2br(htmlspecialchars($desc)) ?></td>
                                <td><?= htmlspecialchars($teacher) ?></td>
                                <td><?= ($max > 0) ? "{$used}/{$max} (เหลือ {$left})" : 'ไม่จำกัด' ?></td>
                                <td class="status">
                                    <?php
                                    if ($status === 'open' || $status === 'เปิด') echo '<span class="ok">เปิด</span>';
                                    elseif ($status === 'close' || $status === 'ปิด') echo '<span class="muted">ปิด</span>';
                                    else echo htmlspecialchars($status);
                                    ?>
                                </td>
                                <td class="actions">
                                    <?php if ($studentId): ?>
                                        <?php if (!$isEnrolled): ?>
                                            <form method="post">
                                                <input type="hidden" name="action" value="enroll">
                                                <input type="hidden" name="course_id" value="<?= $id ?>">
                                                <button class="btn btn-primary" type="submit" <?= ($status !== 'open' && $status !== 'เปิด') ? 'disabled' : '' ?>>
                                                    <i class="bi bi-plus-square"></i> ลงทะเบียน
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <form method="post" onsubmit="return confirm('ยืนยันถอนรายวิชา?')">
                                                <input type="hidden" name="action" value="drop">
                                                <input type="hidden" name="course_id" value="<?= $id ?>">
                                                <button class="btn btn-muted" type="submit">
                                                    <i class="bi bi-dash-square"></i> ถอนรายวิชา
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="muted">ต้องล็อกอินเป็นนักเรียน</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php elseif (!$hasDb): ?>
                <p class="muted">ยังไม่เชื่อมต่อฐานข้อมูล — ตรวจไฟล์ <code>config/db.php</code> หรือ MySQL ของคุณ</p>
            <?php else: ?>
                <p class="muted">ไม่พบรายวิชาในตาราง <code>courses</code></p>
            <?php endif; ?>
        </div>
    </div>
</body>

</html>