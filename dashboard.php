<?php
session_start();

// ถ้ายังไม่ได้ล็อกอิน → เด้งกลับ
if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit();
}

require_once __DIR__ . '/config/db.php'; // ต้องมี $pdo (PDO) ต่อ DB แล้ว

// ดึงข้อมูลจาก session
$user = $_SESSION['user'];
$name  = $user['name']  ?? 'ผู้ใช้';
$email = $user['email'] ?? '-';
$role  = strtolower($user['role'] ?? 'student');
$studentId = $user['user_id'] ?? ($user['id'] ?? null);

/* ---------- ฟังก์ชันดึงคะแนนจากฐานข้อมูล ---------- */

/** คะแนนความประพฤติ (0–100)
 * พยายามหาจาก behavior_reports ก่อน:
 *  - SUM(points) หรือ SUM(score) หรือ behavior_score
 * ถ้าไม่เจอ ให้ลองนับเหตุการณ์บวก/ลบแบบดี/ไม่ดี (good/bad) แล้วถ่วงเป็นเปอร์เซ็นต์
 */
function fetchBehaviorScore(PDO $pdo, int $userId): int
{
    // 1) คะแนนรวมจากคอลัมน์ point/score
    $try = [
        "SELECT COALESCE(SUM(points),0) AS v FROM behavior_reports WHERE user_id = ?",
        "SELECT COALESCE(SUM(score),0)  AS v FROM behavior_reports WHERE user_id = ?",
        "SELECT COALESCE(MAX(behavior_score),0) AS v FROM behavior_reports WHERE user_id = ?",
    ];
    foreach ($try as $sql) {
        try {
            $st = $pdo->prepare($sql);
            $st->execute([$userId]);
            $v = (float)$st->fetchColumn();
            if ($v !== null) {
                // กันไม่เกิน 100
                return (int)max(0, min(100, round($v)));
            }
        } catch (Throwable $e) { /* ลองตัวถัดไป */
        }
    }

    // 2) แบบ good/bad event → คิดเปอร์เซ็นต์อย่างคร่าว ๆ
    try {
        $st = $pdo->prepare("SELECT
                 SUM(CASE WHEN type IN ('good','positive','reward') THEN 1 ELSE 0 END) AS good_cnt,
                 SUM(CASE WHEN type IN ('bad','negative','penalty') THEN 1 ELSE 0 END) AS bad_cnt
               FROM behavior_reports WHERE user_id = ?");
        $st->execute([$userId]);
        $r = $st->fetch(PDO::FETCH_ASSOC) ?: ['good_cnt' => 0, 'bad_cnt' => 0];
        $good = (int)($r['good_cnt'] ?? 0);
        $bad  = (int)($r['bad_cnt']  ?? 0);
        $total = $good + $bad;
        if ($total > 0) {
            $pct = ($good / $total) * 100;
            return (int)max(0, min(100, round($pct)));
        }
    } catch (Throwable $e) {
    }

    // 3) ไม่พบข้อมูล → 0
    return 0;
}

/** คะแนนรวมทั้งหมด
 * พยายามหาจากตาราง grades ก่อน (SUM(total_score))
 * ถ้าไม่เจอ ลอง submissions.score หรือ assignments.score
 * คืน [totalScore, totalMax] เพื่อไปทำเปอร์เซ็นต์ที่หน้า
 */
function fetchTotalScore(PDO $pdo, int $userId): array
{
    // 1) จาก grades.total_score
    try {
        $st = $pdo->prepare("SELECT COALESCE(SUM(total_score),0) AS total FROM grades WHERE user_id = ?");
        $st->execute([$userId]);
        $total = (float)$st->fetchColumn();
        if ($total > 0) {
            // เดา "เต็มรวม" จากจำนวนรายวิชา * 100 ถ้าไม่รู้จริง ๆ
            $st2 = $pdo->prepare("SELECT COUNT(*) FROM grades WHERE user_id = ?");
            $st2->execute([$userId]);
            $cnt = (int)$st2->fetchColumn();
            $max = max(1, $cnt) * 100; // สมมติวิชาละ 100
            return [(int)round($total), (int)$max];
        }
    } catch (Throwable $e) {
    }

    // 2) จาก submissions.score (ถ้ามีคอลัมน์ score/points)
    $try = [
        "SELECT COALESCE(SUM(score),0)  AS total FROM submissions WHERE user_id = ?",
        "SELECT COALESCE(SUM(points),0) AS total FROM submissions WHERE user_id = ?",
    ];
    foreach ($try as $sql) {
        try {
            $st = $pdo->prepare($sql);
            $st->execute([$userId]);
            $total = (float)$st->fetchColumn();
            if ($total > 0) {
                // เดาเต็มรวมจากจำนวนชิ้นงาน * 100
                $st2 = $pdo->prepare("SELECT COUNT(*) FROM submissions WHERE user_id = ?");
                $st2->execute([$userId]);
                $cnt = (int)$st2->fetchColumn();
                $max = max(1, $cnt) * 100;
                return [(int)round($total), (int)$max];
            }
        } catch (Throwable $e) {
        }
    }

    // 3) จาก assignments.score (ถ้ามี)
    try {
        $st = $pdo->prepare("SELECT COALESCE(SUM(score),0) FROM assignments WHERE user_id = ?");
        $st->execute([$userId]);
        $total = (float)$st->fetchColumn();
        if ($total > 0) {
            $st2 = $pdo->prepare("SELECT COUNT(*) FROM assignments WHERE user_id = ?");
            $st2->execute([$userId]);
            $cnt = (int)$st2->fetchColumn();
            $max = max(1, $cnt) * 100;
            return [(int)round($total), (int)$max];
        }
    } catch (Throwable $e) {
    }

    // 4) ไม่พบข้อมูล → 0/100 (กันหาร 0)
    return [0, 100];
}

/* ---------- คำนวณจริงจากฐานข้อมูล ---------- */
$behaviorScore = 0;
$totalScore = 0;
$totalMax = 100;

if ($studentId && isset($pdo) && $pdo instanceof PDO) {
    $behaviorScore = fetchBehaviorScore($pdo, (int)$studentId);
    [$totalScore, $totalMax] = fetchTotalScore($pdo, (int)$studentId);
}

/* ---------- ค่าที่ใช้วาดกรอบ ---------- */
$behaviorPercent = max(0, min(100, $behaviorScore));
$totalPercent    = max(0, min(100, ($totalScore / max(1, $totalMax)) * 100));
?>


<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <title>แดชบอร์ด | สถาบันติวเตอร์</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --blue: #3b82f6;
            --blue-2: #60a5fa;
            --blue-dark: #2563eb;
            --ink: #0f172a;
            --muted: #64748b;
            --bg: #f5f7fa;
            --red: #ef4444;
            --red-2: #fb7185;
            --yellow: #f59e0b;
            --yellow-2: #fbbf24;
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
            display: flex
        }

        /* Sidebar */
        .sidebar {
            width: 230px;
            background: linear-gradient(180deg, var(--blue), #2b6de1);
            color: #fff;
            height: 100vh;
            padding: 26px 16px;
            position: fixed;
            inset: 0 auto 0 0
        }

        .sidebar h2 {
            font-size: 22px;
            font-weight: 600;
            margin: 0 0 24px;
            text-align: center
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
            transition: transform .15s, background .2s
        }

        .sidebar a:hover {
            background: rgba(255, 255, 255, .12);
            transform: translateY(-1px)
        }

        /* Main */
        .main {
            flex: 1;
            margin-left: 230px;
            padding: 30px 40px
        }

        .card {
            background: var(--surface);
            padding: 22px;
            border-radius: 16px;
            box-shadow: 0 6px 24px rgba(15, 23, 42, .06);
            margin-bottom: 24px;
            position: relative;
            overflow: hidden
        }

        .card h2,
        .card h3 {
            margin: 0 0 10px
        }

        .sub {
            color: var(--muted)
        }

        /* Layout */
        .grid {
            display: grid;
            grid-template-columns: 1.3fr 1fr;
            gap: 24px;
            align-items: start
        }

        .chart-container canvas {
            width: 100% !important;
            height: 330px !important
        }

        /* Right side stack */
        .right {
            display: flex;
            flex-direction: column;
            gap: 24px
        }

        /* Fancy bordered cards (red/yellow) */
        .kpi {
            position: relative
        }

        .kpi::before {
            content: "";
            position: absolute;
            inset: -2px;
            border-radius: 18px;
            z-index: -1;
            filter: blur(0);
            background: linear-gradient(135deg, var(--c1), var(--c2));
        }

        .kpi .heading {
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 600;
            margin-bottom: 6px
        }

        .kpi .value {
            font-size: 28px;
            font-weight: 800;
            letter-spacing: .2px
        }

        .kpi .note {
            color: var(--muted);
            font-size: 14px
        }

        /* Progress bar */
        .bar {
            height: 12px;
            background: #eef2f7;
            border-radius: 999px;
            overflow: hidden;
            margin-top: 8px
        }

        .bar>span {
            display: block;
            height: 100%;
            width: 0;
            animation: grow 1s ease forwards
        }

        @keyframes grow {
            to {
                width: var(--w)
            }
        }

        .kpi-red {
            --c1: var(--red);
            --c2: var(--red-2)
        }

        .kpi-yellow {
            --c1: var(--yellow);
            --c2: var(--yellow-2)
        }

        .chip {
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

        /* Little floaty decoration */
        .glow {
            position: absolute;
            right: -40px;
            top: -40px;
            width: 140px;
            height: 140px;
            border-radius: 50%;
            background: radial-gradient(closest-side, rgba(255, 255, 255, .45), transparent 70%)
        }

        @media (max-width: 992px) {
            .grid {
                grid-template-columns: 1fr
            }

            .main {
                margin-left: 0;
                padding: 20px
            }

            .sidebar {
                width: 100%;
                height: auto;
                position: relative
            }
        }
    </style>
</head>

<body>
    <div class="sidebar">
        <h2>📘 สถาบันติวเตอร์</h2>
        <a href="dashboard.php"><i class="bi bi-house-fill"></i> หน้าแรก</a>
        <a href="student.php"><i class="bi bi-person-circle"></i> นักเรียน</a>
        <a href="courses.php"><i class="bi bi-journal-bookmark-fill"></i> รายวิชา</a>
        <a href="grades.php"><i class="bi bi-bar-chart-line-fill"></i> ผลการเรียน</a>
        <a href="notifications.php"><i class="bi bi-bell-fill"></i> แจ้งเตือน</a>
        <?php if ($role === 'admin'): ?>
            <a href="#"><i class="bi bi-people-fill"></i> ผู้ใช้ทั้งหมด</a>
        <?php endif; ?>
        <a href="logout.php"><i class="bi bi-box-arrow-right"></i> ออกจากระบบ</a>
    </div>

    <div class="main">
        <div class="card">
            <div class="glow"></div>
            <h2>สวัสดีคุณ <?= htmlspecialchars($name) ?></h2>
            <div class="sub"><i class="bi bi-envelope-fill"></i> อีเมล: <?= htmlspecialchars($email) ?></div>
            <div class="sub"><i class="bi bi-person-badge-fill"></i> บทบาท: <?= ucfirst($role) ?></div>
            <div style="margin-top:10px">
                <span class="chip"><i class="bi bi-trophy-fill"></i> คะแนนรวม <?= number_format($totalScore) ?> / <?= number_format($totalMax) ?></span>
                <span class="chip"><i class="bi bi-emoji-smile-fill"></i> ประพฤติ <?= $behaviorScore ?> / 100</span>
            </div>
        </div>

        <div class="grid">
            <!-- ซ้าย: กราฟสถิติระบบ -->
            <div class="card chart-container">
                <h3><i class="bi bi-bar-chart-fill"></i> สถิติระบบ</h3>
                <canvas id="statsChart"></canvas>
            </div>

            <!-- ขวา: การ์ดคะแนน 2 กล่อง -->
            <div class="right">
                <!-- กรอบแดง: คะแนนประพฤติ -->
                <div class="card kpi kpi-red" title="คะแนนด้านวินัย/ความรับผิดชอบ">
                    <div class="heading"><i class="bi bi-heart-pulse-fill"></i> คะแนนประพฤติ</div>
                    <div class="value"><?= $behaviorScore ?> / 100</div>
                    <div class="bar" aria-label="behavior bar"><span style="--w:<?= round($behaviorPercent, 2) ?>%;background:linear-gradient(90deg,var(--red),var(--red-2))"></span></div>
                    <div class="note">คิดเป็น <?= number_format($behaviorPercent, 1) ?>%</div>
                </div>

                <!-- กรอบเหลือง: คะแนนรวม -->
                <div class="card kpi kpi-yellow" title="ผลรวมคะแนนรายวิชาทั้งหมด">
                    <div class="heading"><i class="bi bi-star-fill"></i> คะแนนรวม</div>
                    <div class="value"><?= number_format($totalScore) ?> / <?= number_format($totalMax) ?></div>
                    <div class="bar" aria-label="total bar"><span style="--w:<?= round($totalPercent, 2) ?>%;background:linear-gradient(90deg,var(--yellow),var(--yellow-2))"></span></div>
                    <div class="note">คิดเป็น <?= number_format($totalPercent, 1) ?>%</div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // กราฟหลัก
        const ctx = document.getElementById('statsChart').getContext('2d');
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: ['นักเรียน', 'ครู', 'รายวิชา', 'คะแนนเฉลี่ย'],
                datasets: [{
                    data: [120, 12, 8, 85],
                    backgroundColor: ['#93c5fd', '#60a5fa', '#3b82f6', '#2563eb'],
                    borderRadius: 8
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        grid: {
                            color: 'rgba(2,6,23,.06)'
                        },
                        ticks: {
                            stepSize: 20
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        }
                    }
                }
            }
        });
    </script>
</body>

</html>