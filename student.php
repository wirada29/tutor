<?php
session_start();

/* ---- รับ session ได้สองแบบ ---- */
// แบบ 1: เก็บรวมใน $_SESSION['user'] (แนะนำ)
if (isset($_SESSION['user']) && is_array($_SESSION['user'])) {
    $u = $_SESSION['user'];
}
// แบบ 2: เก็บแยกคีย์ (name/email/role/..)
elseif (isset($_SESSION['name']) || isset($_SESSION['email']) || isset($_SESSION['role'])) {
    $u = [
        'name'       => $_SESSION['name']       ?? 'ผู้ใช้',
        'email'      => $_SESSION['email']      ?? '',
        'role'       => $_SESSION['role']       ?? 'student',
        'student_no' => $_SESSION['student_no'] ?? '-',
        'class'      => $_SESSION['class']      ?? '-',
        'major'      => $_SESSION['major']      ?? '-',
    ];
} else {
    header("Location: login.php");
    exit();
}

$name       = $u['name']       ?? 'ผู้ใช้';
$email      = $u['email']      ?? '-';
$role       = strtolower($u['role'] ?? 'student');
$student_no = $u['student_no'] ?? '-';
$class      = $u['class']      ?? '-';
$major      = $u['major']      ?? '-';

$current = basename($_SERVER['PHP_SELF']); // ใช้ไฮไลท์เมนูปัจจุบัน

/* ============================================================
   ⬇⬇⬇ ใส่พวก require db.php และ Query รายวิชา "ตรงนี้"
   ============================================================ */
require_once __DIR__ . '/config/db.php';

// ดึง user_id จาก session
$user_id = (int)($u['user_id'] ?? 0);
if ($user_id <= 0 && $email !== '-') {
    $st = $pdo->prepare("SELECT user_id FROM users WHERE email = ? LIMIT 1");
    $st->execute([$email]);
    if ($row = $st->fetch()) {
        $user_id = (int)$row['user_id'];
    }
}

// Query รายวิชาที่ลงทะเบียน
$sql = "
    SELECT
        c.course_id,
        c.title,
        c.max_seats,
        c.status        AS course_status,
        e.enrollment_id,
        e.status        AS enroll_status,
        e.enrolled_at,
        t.name          AS teacher_name,
        (
            SELECT COUNT(*)
            FROM enrollments e2
            WHERE e2.course_id = c.course_id
              AND e2.status = 'active'
        ) AS seats_used
    FROM enrollments e
    JOIN courses c    ON c.course_id = e.course_id
    LEFT JOIN users t ON t.user_id   = c.teacher_id
    WHERE e.user_id = ?
    ORDER BY e.enrolled_at DESC";
$st = $pdo->prepare($sql);
$st->execute([$user_id]);
$courses = $st->fetchAll(PDO::FETCH_ASSOC);

/* ============================================================
   ⬆⬆⬆ จบส่วน PHP เตรียมตัวแปร แล้วค่อยลงไป HTML ต่อ
   ============================================================ */
?>
<!doctype html>
<html lang="th">
<head> … </head>
<body>
<head>
    <meta charset="UTF-8" />
    <title>หน้าของนักเรียน</title>
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;600&display=swap" rel="stylesheet" />
    <style>
        :root {
            --blue: #3b82f6;
            --blue-dark: #2563eb;
            --ink: #0f172a;
            --muted: #64748b;
            --bg: #f5f7fa;
            --surface: #ffffff;
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
            min-height: 100vh;
        }

        /* === Sidebar (เหมือนหน้า dashboard) === */
        .sidebar {
            width: 230px;
            background: linear-gradient(180deg, var(--blue), var(--blue-dark));
            color: #fff;
            height: 100vh;
            padding: 26px 16px;
            position: fixed;
            inset: 0 auto 0 0;
            /* top:0; left:0; bottom:0 */
            overflow-y: auto;
            box-shadow: 0 6px 20px rgba(0, 0, 0, .08);
            border-right: 1px solid rgba(255, 255, 255, .08);
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
            padding: 11px 10px;
            border-radius: 10px;
            transition: transform .15s, background .2s, opacity .2s;
            opacity: .95;
        }

        .sidebar a:hover {
            background: rgba(255, 255, 255, .15);
            transform: translateY(-1px);
            opacity: 1;
        }

        .sidebar a.active {
            background: rgba(255, 255, 255, .22);
            box-shadow: inset 0 0 0 1px rgba(255, 255, 255, .18);
        }

        /* Main */
        .main {
            flex: 1;
            margin-left: 230px;
            /* ให้ตรงกับความกว้าง sidebar */
            padding: 28px 32px;
        }

        .card {
            background: var(--surface);
            padding: 22px;
            border-radius: 16px;
            box-shadow: 0 6px 24px rgba(15, 23, 42, .06);
            margin-bottom: 22px;
        }

        .pill {
            display: inline-block;
            background: #e2e8f0;
            padding: 6px 12px;
            border-radius: 999px;
            margin: 2px 6px 2px 0;
            font-size: 14px;
        }

        @media (max-width: 992px) {
            .sidebar {
                position: relative;
                width: 100%;
                height: auto;
                inset: auto;
            }

            .main {
                margin-left: 0;
                padding: 20px;
            }
        }

        .muted {
            color: var(--muted);
            font-size: 14px;
        }
    </style>
</head>

<body>

    <!-- Sidebar -->
    <!-- Sidebar -->
    <div class="sidebar">
        <h2>📘 นักเรียน</h2>
        <a href="dashboard.php"><i class="bi bi-house-fill"></i> หน้าแรก</a>
        <a href="student.php"><i class="bi bi-person-circle"></i> นักเรียน</a>
        <a href="courses.php"><i class="bi bi-journal-bookmark-fill"></i> รายวิชา</a>
        <a href="my_enrollments.php"><i class="bi bi-journal-bookmark-fill"></i> ลงทะเบียนเรียน</a>
        <a href="notifications.php"><i class="bi bi-bell-fill"></i> แจ้งเตือน</a>

        <?php if ($role === 'admin'): ?>
            <a href="#"><i class="bi bi-people-fill"></i> ผู้ใช้ทั้งหมด</a>
            <a href="register_teacher.php"><i class="bi bi-person-plus"></i> สร้างบัญชีครู</a>
            <a href="register_admin.php"><i class="bi bi-shield-plus"></i> สร้างบัญชีแอดมิน</a>
        <?php endif; ?>

        <a href="logout.php"><i class="bi bi-box-arrow-right"></i> ออกจากระบบ</a>
    </div>


    <!-- Main -->
    <div class="main">
        <div class="card">
            <h2>👋 สวัสดีคุณ <?= htmlspecialchars($name) ?></h2>
            <p>อีเมล: <span class="pill"><?= htmlspecialchars($email) ?></span></p>
            <p>บทบาท: <span class="pill"><?= htmlspecialchars(ucfirst($role)) ?></span></p>
        </div>

        <div class="card">
            <h3>📚 รายวิชาที่ลงทะเบียน</h3>
            <?php if (empty($courses)): ?>
                <p class="muted">ยังไม่มีข้อมูลรายวิชาที่ลงทะเบียน</p>
            <?php else: ?>
                <div style="overflow:auto;">
                    <table style="width:100%; border-collapse:collapse;">
                        <thead>
                            <tr style="text-align:left; background:#f1f5f9;">
                                <th style="padding:10px; border-bottom:1px solid #e2e8f0;">ชื่อวิชา</th>
                                <th style="padding:10px; border-bottom:1px solid #e2e8f0;">ผู้สอน</th>
                                <th style="padding:10px; border-bottom:1px solid #e2e8f0;">สถานะวิชา</th>
                                <th style="padding:10px; border-bottom:1px solid #e2e8f0;">ที่นั่งคงเหลือ</th>
                                <th style="padding:10px; border-bottom:1px solid #e2e8f0;">สถานะลงทะเบียน</th>
                                <th style="padding:10px; border-bottom:1px solid #e2e8f0;">ลงทะเบียนเมื่อ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($courses as $c):
                                $used = (int)($c['seats_used'] ?? 0);
                                $max  = (int)($c['max_seats'] ?? 0);
                                $left = ($max > 0) ? max(0, $max - $used) : null; // null = ไม่กำหนดจำนวนที่นั่ง
                            ?>
                                <tr>
                                    <td style="padding:10px; border-bottom:1px solid #f1f5f9;">
                                        <?= htmlspecialchars($c['title'] ?? '-') ?>
                                    </td>
                                    <td style="padding:10px; border-bottom:1px solid #f1f5f9;">
                                        <?= htmlspecialchars($c['teacher_name'] ?? '-') ?>
                                    </td>
                                    <td style="padding:10px; border-bottom:1px solid #f1f5f9;">
                                        <span class="pill"><?= htmlspecialchars($c['course_status'] ?? '-') ?></span>
                                    </td>
                                    <td style="padding:10px; border-bottom:1px solid #f1f5f9;">
                                        <?php if ($max > 0): ?>
                                            <span class="pill"><?= $left ?> / <?= $max ?></span>
                                        <?php else: ?>
                                            <span class="pill">ไม่จำกัด</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="padding:10px; border-bottom:1px solid #f1f5f9;">
                                        <?= htmlspecialchars($c['enroll_status'] ?? '-') ?>
                                    </td>
                                    <td style="padding:10px; border-bottom:1px solid #f1f5f9;" class="muted">
                                        <?= htmlspecialchars($c['enrolled_at'] ?? '-') ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

    </div>

</body>

</html>