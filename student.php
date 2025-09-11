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
?>
<!DOCTYPE html>
<html lang="th">

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
        <a href="grades.php"><i class="bi bi-bar-chart-line-fill"></i> ผลการเรียน</a>
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
            <p>รหัสนักศึกษา: <span class="pill"><?= htmlspecialchars($student_no) ?></span></p>
            <p>อีเมล: <span class="pill"><?= htmlspecialchars($email) ?></span></p>
            <p>ชั้น/ห้อง: <span class="pill"><?= htmlspecialchars($class) ?></span></p>
            <p>สาขา: <span class="pill"><?= htmlspecialchars($major) ?></span></p>
            <p>บทบาท: <span class="pill"><?= htmlspecialchars(ucfirst($role)) ?></span></p>
        </div>

        <div class="card">
            <h3>📚 รายวิชาที่ลงทะเบียน</h3>
            <p class="muted">— ยังไม่เชื่อมฐานข้อมูล —</p>
        </div>
    </div>

</body>

</html>