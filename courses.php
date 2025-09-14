<?php
// courses.php — รายวิชา + ลงทะเบียน/ถอน (JOIN subjects)
session_start();

require_once __DIR__ . '/config/db.php';      // ต้องมี $pdo
require_once __DIR__ . '/includes/auth.php';  // ต้องมี require_login(), current_user_id()

require_login();
$uid = current_user_id();

// ---------- ค่าค้นหา ----------
$q = trim($_GET['q'] ?? '');

// ---------- Helpers ----------
function count_used_seats(PDO $pdo, int $courseId): int
{
    // พยายามนับเฉพาะ status='active' ถ้ามีคอลัมน์นี้
    try {
        $st = $pdo->prepare("SELECT COUNT(*) FROM enrollments WHERE course_id=? AND status='active'");
        $st->execute([$courseId]);
        return (int)$st->fetchColumn();
    } catch (Throwable $e) {
        $st = $pdo->prepare("SELECT COUNT(*) FROM enrollments WHERE course_id=?");
        $st->execute([$courseId]);
        return (int)$st->fetchColumn();
    }
}

function enrolled_map(PDO $pdo, int $userId): array
{
    $map = [];
    try {
        $st = $pdo->prepare("SELECT course_id FROM enrollments WHERE user_id=? AND (status IS NULL OR status='active')");
        $st->execute([$userId]);
        foreach ($st as $r) $map[(int)$r['course_id']] = true;
    } catch (Throwable $e) {
    }
    return $map;
}

// ---------- Actions ----------
$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action   = $_POST['action']    ?? '';
    $courseId = (int)($_POST['course_id'] ?? 0);

    if ($courseId > 0) {
        if ($action === 'enroll') {
            try {
                $st = $pdo->prepare("SELECT max_seats, status FROM courses WHERE course_id=?");
                $st->execute([$courseId]);
                $course = $st->fetch(PDO::FETCH_ASSOC);

                if (!$course) {
                    $msg = "ไม่พบรายวิชา";
                } elseif (strtolower($course['status'] ?? 'open') !== 'open') {
                    $msg = "รายวิชานี้ปิดรับลงทะเบียน";
                } else {
                    $max  = (int)($course['max_seats'] ?? 0);
                    $used = count_used_seats($pdo, $courseId);
                    if ($max > 0 && $used >= $max) {
                        $msg = "เต็มแล้ว ไม่สามารถลงทะเบียนได้";
                    } else {
                        $st = $pdo->prepare("INSERT INTO enrollments(user_id, course_id, status, enrolled_at) VALUES(?, ?, 'active', NOW())");
                        $st->execute([$uid, $courseId]);
                        $msg = "ลงทะเบียนสำเร็จ";
                    }
                }
            } catch (Throwable $e) {
                $msg = "ลงทะเบียนไม่สำเร็จ: " . $e->getMessage();
            }
        } elseif ($action === 'drop') {
            try {
                $st = $pdo->prepare("DELETE FROM enrollments WHERE user_id=? AND course_id=?");
                $st->execute([$uid, $courseId]);
                $msg = "ถอนรายวิชาเรียบร้อย";
            } catch (Throwable $e) {
                $msg = "ถอนรายวิชาไม่สำเร็จ: " . $e->getMessage();
            }
        }
    }
}

$sql = "SELECT
          c.course_id, c.title, c.description, c.max_seats, c.status,
          u.name AS teacher_name,
          s.code AS subject_code, s.name AS subject_name
        FROM courses c
        LEFT JOIN users    u ON u.user_id = c.teacher_id
        LEFT JOIN subjects s ON s.id      = c.subject_id
        WHERE 1=1";  // << ใช้ตรงนี้

$args = [];
if ($q !== '') {
    $sql .= " AND (c.title LIKE ? OR s.code LIKE ? OR s.name LIKE ?)";
    $kw = "%$q%";
    $args = [$kw, $kw, $kw];
}
$sql .= " ORDER BY c.course_id DESC";

$st = $pdo->prepare($sql);
$st->execute($args);
$courses = $st->fetchAll(PDO::FETCH_ASSOC);



// วิชาที่นักเรียนลงทะเบียนอยู่
$enrolled = enrolled_map($pdo, $uid);
?>
<!doctype html>
<html lang="th">

<head>
    <meta charset="utf-8">
    <title>รายวิชา - ลงทะเบียน</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --blue: #3b82f6;
            --blue-2: #2563eb;
            --ink: #0f172a;
            --muted: #64748b;
            --bg: #f5f7fa;
            --ok: #16a34a;
            --warn: #eab308;
            --err: #e11d48;
            --surface: #ffffff;
        }

        * {
            box-sizing: border-box
        }

        body {
            font-family: 'Sarabun', sans-serif;
            margin: 0;
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
            background: var(--surface);
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
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-weight: 700
        }

        .btn-primary {
            background: var(--blue-2);
            color: #fff;
            box-shadow: 0 8px 20px rgba(37, 99, 235, .18)
        }

        .btn-muted {
            background: #e5e7eb
        }

        .btn-danger {
            background: #ef4444;
            color: #fff
        }

        .badge {
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

        .alert {
            padding: 10px;
            border-radius: 10px;
            margin: 10px 0 0
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
        <a href="student.php"><i class="bi bi-person-circle"></i> นักเรียน</a>
        <a href="courses.php"><i class="bi bi-journal-bookmark-fill"></i> รายวิชา</a>
        <a href="my_enrollments.php"><i class="bi bi-journal-bookmark-fill"></i> ลงทะเบียนเรียน</a>
        <a href="grades.php"><i class="bi bi-bar-chart-line-fill"></i> ผลการเรียน</a>
        <a href="notifications.php"><i class="bi bi-bell-fill"></i> แจ้งเตือน</a>
        <a href="logout.php"><i class="bi bi-box-arrow-right"></i> ออกจากระบบ</a>
    </div>

    <div class="main">
        <div class="card">
            <h2 style="margin:0">📚 รายวิชาที่เปิดให้ลงทะเบียน</h2>
            <form class="row" method="get" action="courses.php" style="margin-top:10px">
                <input class="input" type="text" name="q" placeholder="ค้นหา: ชื่อคอร์ส / รหัสวิชา / ชื่อวิชา (subjects)" value="<?= htmlspecialchars($q) ?>">
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
            <?php if ($courses): ?>
                <table>
                    <thead>
                        <tr>
                            <th style="width:160px;">วิชา (Subject)</th>
                            <th style="width:240px;">ชื่อวิชา</th>
                            <th>รายละเอียด</th>
                            <th style="width:140px;">อาจารย์</th>
                            <th style="width:120px;">ที่นั่ง</th>
                            <th style="width:110px;">สถานะ</th>
                            <th style="width:210px;">จัดการ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($courses as $c):
                            $cid      = (int)$c['course_id'];
                            $title    = $c['title'] ?? 'ไม่ระบุชื่อ';
                            $desc     = $c['description'] ?? '';
                            $max      = (int)($c['max_seats'] ?? 0);
                            $status   = strtolower($c['status'] ?? 'open');
                            $teacher  = $c['teacher_name'] ?? '—';
                            $scode    = $c['subject_code'] ?? '';
                            $sname    = $c['subject_name'] ?? '';
                            $subject  = trim($scode . ($scode && $sname ? ' - ' : '') . $sname);
                            if ($subject === '') $subject = '—';
                            $used     = count_used_seats($pdo, $cid);
                            $left     = ($max > 0) ? max(0, $max - $used) : '—';
                            $enr      = !empty($enrolled[$cid]);
                        ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($subject) ?></strong></td>
                                <td><strong><?= htmlspecialchars($title) ?></strong></td>
                                <td style="color:#475569;font-size:14px"><?= nl2br(htmlspecialchars($desc)) ?></td>
                                <td><?= htmlspecialchars($teacher) ?></td>
                                <td>
                                    <?php if ($max > 0): ?>
                                        <span class="badge"><i class="bi bi-people-fill"></i> <?= $used ?>/<?= $max ?> (เหลือ <?= $left ?>)</span>
                                    <?php else: ?>
                                        <span class="badge">ไม่จำกัด</span>
                                    <?php endif; ?>
                                </td>
                                <td class="status">
                                    <?php
                                    if ($status === 'open' || $status === 'เปิด') echo '<span class="ok">เปิด</span>';
                                    elseif ($status === 'close' || $status === 'ปิด') echo '<span class="muted">ปิด</span>';
                                    else echo htmlspecialchars($status);
                                    ?>
                                </td>
                                <td>
                                    <?php if (!$enr): ?>
                                        <form method="post" style="display:inline">
                                            <input type="hidden" name="action" value="enroll">
                                            <input type="hidden" name="course_id" value="<?= $cid ?>">
                                            <button class="btn btn-primary" type="submit" <?= ($status !== 'open' && $status !== 'เปิด') ? 'disabled' : '' ?>>
                                                <i class="bi bi-check2-circle"></i> ลงทะเบียน
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <form method="post" style="display:inline" onsubmit="return confirm('ยืนยันถอนรายวิชา?')">
                                            <input type="hidden" name="action" value="drop">
                                            <input type="hidden" name="course_id" value="<?= $cid ?>">
                                            <button class="btn btn-muted" type="submit">
                                                <i class="bi bi-dash-square"></i> ถอนรายวิชา
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p class="muted">ไม่พบรายวิชาที่เปิดให้ลงทะเบียน</p>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>