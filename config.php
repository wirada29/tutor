<?php

declare(strict_types=1);
session_start();

/* ----- เอาง่าย ๆ: ใช้ค่าใน SESSION ตรง ๆ ----- */
/* ให้หน้า login.php ของคุณ เซ็ต 3 ตัวนี้หลังเข้าสำเร็จ:
   $_SESSION['name']  = $row['name'];
   $_SESSION['email'] = $row['email'];
   $_SESSION['role']  = $row['role'];   // 'admin' หรือ 'student'
*/
$name  = $_SESSION['name']  ?? 'ผู้ใช้';
$email = $_SESSION['email'] ?? '-';
$role  = strtolower($_SESSION['role'] ?? 'student');

/* ----- ถ้ามี config.php ก็ require เพื่อใช้ $pdo แสดงตารางผู้ใช้ (แอดมิน) ----- */
$pdo = null;
$configPath = __DIR__ . '/config.php';
if (is_file($configPath)) {
    require_once $configPath; // ควรประกาศ $pdo ข้างใน
}
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8" />
    <title>แดชบอร์ด | สถาบันติวเตอร์</title>
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;600&display=swap" rel="stylesheet">

    <style>
        body {
            margin: 0;
            font-family: 'Sarabun', sans-serif;
            background: #f5f7fa;
            display: flex;
            color: #1a202c;
        }

        .sidebar {
            width: 230px;
            background: #3b82f6;
            color: #fff;
            height: 100vh;
            padding: 25px 15px;
            box-sizing: border-box;
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
            color: #fff;
            text-decoration: none;
            margin-bottom: 15px;
            font-size: 16px;
            padding: 12px 10px;
            border-radius: 8px;
            transition: background .3s, padding-left .3s;
        }

        .sidebar a:hover {
            background: #2563eb;
            padding-left: 15px;
        }

        .main {
            flex: 1;
            margin-left: 230px;
            padding: 30px 40px;
        }

        h2,
        h3 {
            color: #1e3a8a;
            margin-bottom: 20px;
        }

        .card {
            background: #fff;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, .08);
            margin-bottom: 25px;
            transition: transform .2s;
        }

        .card:hover {
            transform: translateY(-2px);
        }

        .info {
            font-size: 16px;
            margin-bottom: 10px;
        }

        .actions a {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-top: 15px;
            padding: 10px 18px;
            background: #2563eb;
            color: #fff;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 500;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
            border-radius: 10px;
            overflow: hidden;
        }

        th,
        td {
            padding: 12px;
            border-bottom: 1px solid #e2e8f0;
            text-align: left;
        }

        th {
            background: #2563eb;
            color: #fff;
        }

        tr:hover {
            background: #e0f2fe;
        }

        .grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 25px;
        }

        .chart-container canvas {
            width: 100% !important;
            height: 320px !important;
        }

        @media (max-width: 992px) {
            .grid {
                grid-template-columns: 1fr;
            }

            .main {
                margin-left: 0;
                padding: 20px;
            }

            .sidebar {
                width: 100%;
                height: auto;
                position: relative;
            }
        }
    </style>
</head>

<body>
    <div class="sidebar">
        <h2>📘 สถาบันติวเตอร์</h2>
        <a href="dashboard.php"><i class="bi bi-house-fill"></i> หน้าแรก</a>
        <a href="#"><i class="bi bi-journal-bookmark-fill"></i> รายวิชา</a>
        <a href="#"><i class="bi bi-bell-fill"></i> แจ้งเตือน</a>
        <a href="#"><i class="bi bi-bar-chart-line-fill"></i> ผลการเรียน</a>
        <?php if ($role === 'admin'): ?>
            <a href="users.php"><i class="bi bi-people-fill"></i> ผู้ใช้ทั้งหมด</a>
        <?php endif; ?>
        <a href="logout.php"><i class="bi bi-box-arrow-right"></i> ออกจากระบบ</a>
    </div>

    <div class="main">
        <div class="card">
            <h2>สวัสดีคุณ <?= htmlspecialchars($name) ?></h2>
            <div class="info"><i class="bi bi-envelope-fill"></i> อีเมล: <?= htmlspecialchars($email) ?></div>
            <div class="info"><i class="bi bi-person-badge-fill"></i> บทบาท: <?= htmlspecialchars(ucfirst($role)) ?></div>
            <div class="actions">
                <a href="change_password.php"><i class="bi bi-key-fill"></i> เปลี่ยนรหัสผ่าน</a>
            </div>
        </div>

        <div class="grid">
            <div class="card chart-container">
                <h3><i class="bi bi-bar-chart-fill"></i> สถิติระบบ</h3>
                <canvas id="statsChart"></canvas>
            </div>

            <?php if ($role === 'admin'): ?>
                <div class="card">
                    <h3><i class="bi bi-people-fill"></i> จัดการผู้ใช้</h3>
                    <?php if ($pdo instanceof PDO): ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>ชื่อ</th>
                                    <th>อีเมล</th>
                                    <th>บทบาท</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                try {
                                    $stmt = $pdo->query("SELECT name, email, role FROM users ORDER BY id DESC");
                                    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)):
                                ?>
                                        <tr>
                                            <td><?= htmlspecialchars($row['name']) ?></td>
                                            <td><?= htmlspecialchars($row['email']) ?></td>
                                            <td><?= htmlspecialchars($row['role']) ?></td>
                                        </tr>
                                <?php
                                    endwhile;
                                } catch (Throwable $e) {
                                    echo '<tr><td colspan="3">ไม่สามารถโหลดรายชื่อผู้ใช้ได้</td></tr>';
                                }
                                ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p style="margin:0;">(ยังไม่พบการเชื่อมต่อฐานข้อมูล — สร้างไฟล์ <code>config.php</code> และกำหนดตัวแปร <code>$pdo</code> เพื่อให้แสดงตารางผู้ใช้)</p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        const ctx = document.getElementById('statsChart').getContext('2d');
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: ['นักเรียน', 'ครู', 'รายวิชา', 'คะแนนเฉลี่ย'],
                datasets: [{
                    label: 'ข้อมูลระบบ',
                    data: [120, 12, 8, 85]
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        display: false
                    }
                }
            }
        });
    </script>
</body>

</html>