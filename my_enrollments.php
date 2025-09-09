<?php
session_start();
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';
require_login();
$uid = current_user_id();

$sql = "SELECT e.*, c.title, c.status AS course_status
        FROM enrollments e
        JOIN courses c ON c.course_id = e.course_id
        WHERE e.user_id = ?
        ORDER BY e.enrolled_at DESC";
$st = $pdo->prepare($sql);
$st->execute([$uid]);
$rows = $st->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html>
<html lang="th">

<head>
    <meta charset="utf-8">
    <title>การลงทะเบียนของฉัน | สถาบันติวเตอร์</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root {
            --blue: #3b82f6;
            --blue2: #2563eb;
            --ink: #0f172a;
            --muted: #64748b;
            --bg: #f6f7fb;
            --card: #fff;
            --ok: #16a34a;
            --warn: #eab308;
            --err: #ef4444;
            --line: #e5e7eb;
        }

        * {
            box-sizing: border-box
        }

        body {
            margin: 0;
            font-family: Prompt, system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif;
            background: var(--bg);
            color: var(--ink);
        }

        .wrap {
            max-width: 960px;
            margin: 40px auto;
            padding: 0 16px
        }

        .header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin: 0 0 14px;
        }

        .header h2 {
            margin: 0;
            font-size: 26px
        }

        .header .chip {
            background: #eef2ff;
            color: #1e3a8a;
            border-radius: 999px;
            padding: 6px 10px;
            font-weight: 700;
            font-size: 13px
        }

        .card {
            background: var(--card);
            border-radius: 16px;
            padding: 18px;
            margin-bottom: 14px;
            box-shadow: 0 8px 24px rgba(15, 23, 42, .06);
        }

        .row {
            display: grid;
            grid-template-columns: 1fr auto;
            gap: 12px;
            align-items: start;
        }

        .title {
            display: flex;
            align-items: center;
            gap: 10px;
            margin: 4px 0 6px;
            font-size: 18px;
            font-weight: 700;
        }

        .meta {
            color: var(--muted);
            font-size: 14px
        }

        .badges {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin: 8px 0
        }

        .badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 10px;
            border-radius: 999px;
            font-weight: 700;
            font-size: 13px;
        }

        .b-ok {
            background: #dcfce7;
            color: #166534
        }

        .b-warn {
            background: #fef9c3;
            color: #854d0e
        }

        .b-err {
            background: #fee2e2;
            color: #991b1b
        }

        .b-info {
            background: #eef2ff;
            color: #1e3a8a
        }

        .actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            justify-self: end
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 14px;
            border-radius: 12px;
            border: none;
            cursor: pointer;
            font-weight: 700;
        }

        .btn-muted {
            background: #e5e7eb;
            color: #111827
        }

        .btn-danger {
            background: var(--err);
            color: #fff;
            box-shadow: 0 8px 18px rgba(239, 68, 68, .2)
        }

        .btn-danger:hover {
            filter: brightness(1.03)
        }

        .empty {
            text-align: center;
            padding: 36px 16px;
            color: var(--muted)
        }

        .empty i {
            font-size: 44px;
            color: #c7d2fe
        }

        .empty a {
            color: var(--blue2);
            text-decoration: none;
            font-weight: 700
        }

        @media (max-width:720px) {
            .row {
                grid-template-columns: 1fr;
            }

            .actions {
                justify-self: stretch
            }

            .btn,
            .btn-danger,
            .btn-muted {
                width: 100%;
                justify-content: center
            }
        }
    </style>
</head>

<body>
    <div class="wrap">
        <div class="header">
            <h2>🧾 การลงทะเบียนของฉัน</h2>
            <span class="chip"><i class="bi bi-journal-bookmark-fill"></i> <?= number_format(count($rows)) ?> รายการ</span>
        </div>

        <?php if ($rows): ?>
            <?php foreach ($rows as $r):
                $course   = $r['title'] ?? ('วิชา #' . (int)$r['course_id']);
                $myStatus = strtolower($r['status'] ?? '');
                $courseSt = strtolower($r['course_status'] ?? '');
                $when     = $r['enrolled_at'] ?? '';

                // ป้ายสถานะของฉัน
                $badgeMyClass = $myStatus === 'active' ? 'b-ok' : ($myStatus === 'cancelled' ? 'b-err' : 'b-info');
                $badgeMyText  = $myStatus === 'active' ? 'กำลังเรียน (active)' : ($myStatus === 'cancelled' ? 'ยกเลิกแล้ว' : ($r['status'] ?? '-'));

                // ป้ายสถานะคอร์ส
                $badgeCourseClass = in_array($courseSt, ['open', 'เปิด']) ? 'b-ok' : 'b-warn';
                $badgeCourseText  = in_array($courseSt, ['open', 'เปิด']) ? 'เปิด' : ($r['course_status'] ?? '-');
            ?>
                <div class="card">
                    <div class="row">
                        <div>
                            <div class="title"><i class="bi bi-book-half" style="color:var(--blue)"></i> <?= htmlspecialchars($course) ?></div>
                            <div class="badges">
                                <span class="badge <?= $badgeMyClass ?>"><i class="bi bi-person-badge-fill"></i> สถานะของฉัน: <?= htmlspecialchars($badgeMyText) ?></span>
                                <span class="badge <?= $badgeCourseClass ?>"><i class="bi bi-broadcast-pin"></i> สถานะคอร์ส: <?= htmlspecialchars($badgeCourseText) ?></span>
                                <?php if ($when): ?>
                                    <span class="badge b-info"><i class="bi bi-clock-history"></i> ลงทะเบียนเมื่อ: <?= htmlspecialchars($when) ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="meta">รหัสวิชา: <?= (int)$r['course_id'] ?> • เลขที่การลงทะเบียน: <?= (int)$r['enrollment_id'] ?></div>
                        </div>

                        <div class="actions">
                            <?php if ($myStatus === 'active'): ?>
                                <form method="post" action="enroll.php" onsubmit="return confirm('ต้องการยกเลิกรายวิชานี้ใช่หรือไม่?')">
                                    <input type="hidden" name="course_id" value="<?= (int)$r['course_id'] ?>">
                                    <input type="hidden" name="action" value="withdraw">
                                    <button type="submit" class="btn btn-danger"><i class="bi bi-x-circle"></i> ยกเลิกวิชา</button>
                                </form>
                            <?php else: ?>
                                <button class="btn btn-muted" type="button" disabled><i class="bi bi-dash-circle"></i> ไม่อยู่ในสถานะเรียน</button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="card empty">
                <i class="bi bi-clipboard-x"></i>
                <h3 style="margin:10px 0 6px">ยังไม่มีประวัติการลงทะเบียน</h3>
                <p>เริ่มต้นเลือกวิชาที่สนใจได้ที่หน้า <a href="courses.php">รายวิชา</a></p>
            </div>
        <?php endif; ?>
    </div>
</body>

</html>