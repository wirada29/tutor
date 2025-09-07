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
    // ไม่มี session จริง ๆ → กลับไป login
    header("Location: login.php");
    exit();
}

$name       = $u['name']       ?? 'ผู้ใช้';
$email      = $u['email']      ?? '-';
$role       = $u['role']       ?? 'student';
$student_no = $u['student_no'] ?? '-';
$class      = $u['class']      ?? '-';
$major      = $u['major']      ?? '-';
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <title>หน้าของนักเรียน</title>
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
            height: 100vh;
            padding: 25px 15px;
            position: fixed;
            top: 0;
            left: 0;
        }

        .sidebar h2 {
            font-size: 22px;
            font-weight: 600;
            margin-bottom: 30px;
            text-align: center;
        }

        .sidebar a {
            display: flex;
            align-items: center;
            gap: 10px;
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

        .pill {
            display: inline-block;
            background: #e2e8f0;
            padding: 6px 12px;
            border-radius: 999px;
            margin: 2px;
            font-size: 14px
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
        <a href="dashboard.php"><i class="bi bi-house-fill"></i>หน้าแรก</a>
        <a href="student.php"><i class="bi bi-person-circle"></i> โปรไฟล์นักเรียน</a>
        <a href="grades.php"><i class="bi bi-bar-chart-line-fill"></i> ผลการเรียน</a>
        <a href="logout.php"><i class="bi bi-box-arrow-right"></i> ออกจากระบบ</a>
    </div>

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
            <p>— ยังไม่เชื่อมฐานข้อมูล —</p>
        </div>
    </div>
</body>

</html>