<?php
// admin_courses.php — จัดการรายวิชา (แอดมินเท่านั้น)
session_start();
require_once __DIR__ . '/includes/auth.php';
require_login();
if (!is_admin()) { header("Location: dashboard.php"); exit; }

require_once __DIR__ . '/config/db.php';

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
$current = basename($_SERVER['PHP_SELF']);

// --- รับ action เปิด/ปิดวิชา (POST มาหน้าเดียว) ---
$flash = '';
if ($_SERVER['REQUEST_METHOD']==='POST') {
    $cid    = (int)($_POST['course_id'] ?? 0);
    $action = $_POST['action'] ?? '';
    if ($cid > 0 && in_array($action, ['open','close'], true)) {
        try {
            $st = $pdo->prepare("UPDATE courses SET status=? WHERE course_id=?");
            $st->execute([$action==='open'?'open':'closed', $cid]);
            $flash = $action==='open' ? "เปิดวิชา #{$cid} แล้ว" : "ปิดวิชา #{$cid} แล้ว";
        } catch (Throwable $e) {
            $flash = "อัปเดตไม่สำเร็จ: " . $e->getMessage();
        }
    }
}

// --- ค้นหา/กรอง ---
$q       = trim($_GET['q'] ?? '');
$statusF = strtolower(trim($_GET['status'] ?? '')); // '', open, closed

$sql = "SELECT
          c.course_id, c.title, c.description, c.max_seats, c.status,
          u.name AS teacher_name,
          s.code AS subject_code, s.name AS subject_name
        FROM courses c
        LEFT JOIN users    u ON u.user_id = c.teacher_id
        LEFT JOIN subjects s ON s.id      = c.subject_id
        WHERE 1=1";
$args = [];

if ($q !== '') {
    $sql  .= " AND (c.title LIKE ? OR s.code LIKE ? OR s.name LIKE ?)";
    $kw    = "%$q%";
    $args  = [$kw, $kw, $kw];
}
if (in_array($statusF, ['open','closed'], true)) {
    $sql  .= " AND c.status = ?";
    $args[] = $statusF;
}

$sql .= " ORDER BY c.course_id DESC";
$st = $pdo->prepare($sql);
$st->execute($args);
$rows = $st->fetchAll(PDO::FETCH_ASSOC);

// นับจำนวนรวมเพื่อโชว์ badge
$cntAll = count($rows);
?>
<!doctype html>
<html lang="th">
<head>
<meta charset="utf-8">
<title>จัดการรายวิชา | แอดมิน</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;600&display=swap" rel="stylesheet">
<style>
:root{--blue:#3b82f6;--blue2:#2563eb;--ink:#0f172a;--muted:#64748b;--bg:#f5f7fa;--card:#fff;--line:#e5e7eb;--ok:#16a34a;--err:#ef4444}
*{box-sizing:border-box}
body{margin:0;font-family:'Sarabun',system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif;background:var(--bg);color:var(--ink);display:flex;min-height:100vh}
.sidebar{width:230px;background:linear-gradient(180deg,var(--blue),#2b6de1);color:#fff;height:100vh;padding:26px 16px;position:fixed;inset:0 auto 0 0;overflow-y:auto;box-shadow:0 6px 20px rgba(0,0,0,.08)}
.sidebar h2{font-size:22px;font-weight:600;margin:0 0 24px;text-align:center}
.sidebar a{display:flex;align-items:center;gap:10px;color:#fff;text-decoration:none;margin-bottom:12px;padding:11px 10px;border-radius:10px;opacity:.95;transition:transform .15s,background .2s,opacity .2s}
.sidebar a:hover{background:rgba(255,255,255,.15);transform:translateY(-1px);opacity:1}
.sidebar a.active{background:rgba(255,255,255,.22)}
.main{flex:1;margin-left:230px;padding:28px}
.header{display:flex;align-items:center;gap:12px;margin:0 0 14px}
.header h2{margin:0;font-size:26px}
.chip{background:#eef2ff;color:#1e3a8a;border-radius:999px;padding:6px 10px;font-weight:700;font-size:13px}
.card{background:var(--card);border-radius:16px;padding:18px;margin-bottom:14px;box-shadow:0 8px 24px rgba(15,23,42,.06)}
.row{display:flex;gap:10px;flex-wrap:wrap;align-items:center}
.input{padding:10px 12px;border:1px solid var(--line);border-radius:10px}
.select{padding:10px 12px;border:1px solid var(--line);border-radius:10px;background:#fff}
.btn{padding:10px 14px;border-radius:10px;border:0;cursor:pointer;font-weight:700;display:inline-flex;align-items:center;gap:8px}
.btn-primary{background:var(--blue2);color:#fff}
.btn-muted{background:#e5e7eb}
.btn-ok{background:var(--ok);color:#fff}
.btn-err{background:var(--err);color:#fff}
.badge{display:inline-flex;align-items:center;gap:6px;padding:6px 10px;border-radius:999px;font-weight:700;font-size:12px}
.b-open{background:#dcfce7;color:#166534}
.b-closed{background:#fee2e2;color:#991b1b}
table{width:100%;border-collapse:collapse}
th,td{padding:10px;border-bottom:1px solid var(--line);text-align:left;vertical-align:top}
th{background:#eef2ff}
.meta{color:var(--muted);font-size:12px}
.alert{padding:10px;border-radius:10px;margin:10px 0 0}
.alert-ok{background:#dcfce7}
.alert-err{background:#fee2e2}
@media(max-width:992px){.sidebar{position:relative;width:100%;height:auto;inset:auto}.main{margin-left:0;padding:20px}}
</style>
</head>
<body>
  <!-- Sidebar -->
  <div class="sidebar">
    <h2>🛡️ แอดมิน</h2>
    <a href="admin_dashboard.php"><i class="bi bi-speedometer2"></i> หน้าแรกแอดมิน</a>
    <a href="admin_users.php"><i class="bi bi-people-fill"></i> ผู้เข้าใช้ทั้งหมด</a>
    <a href="admin_courses.php" class="active"><i class="bi bi-journal-bookmark-fill"></i> จัดการรายวิชา</a>
    <a href="teacher_assign_list.php"><i class="bi bi-card-checklist"></i> งานครู</a>
    <a href="logout.php"><i class="bi bi-box-arrow-right"></i> ออกจากระบบ</a>
  </div>

  <!-- Main -->
  <div class="main">
    <div class="header">
      <h2><i class="bi bi-journal-bookmark-fill"></i> จัดการรายวิชา</h2>
      <span class="chip"><?= number_format($cntAll) ?> รายการ</span>
    </div>

    <div class="card">
      <form class="row" method="get" action="admin_courses.php">
        <input class="input" type="text" name="q" placeholder="ค้นหา: ชื่อคอร์ส / รหัสวิชา / ชื่อวิชาใน subjects" value="<?= h($q) ?>">
        <select class="select" name="status">
          <option value="">ทุกสถานะ</option>
          <option value="open"   <?= $statusF==='open'   ? 'selected':'' ?>>เปิด</option>
          <option value="closed" <?= $statusF==='closed' ? 'selected':'' ?>>ปิด</option>
        </select>
        <button class="btn btn-primary" type="submit"><i class="bi bi-search"></i> ค้นหา</button>
        <?php if ($q !== '' || $statusF !== ''): ?>
          <a class="btn btn-muted" href="admin_courses.php"><i class="bi bi-eraser"></i> ล้างตัวกรอง</a>
        <?php endif; ?>
      </form>
      <?php if ($flash): ?>
        <div class="alert <?= str_starts_with($flash,'อัปเดตไม่สำเร็จ') ? 'alert-err' : 'alert-ok' ?>"><?= h($flash) ?></div>
      <?php endif; ?>
    </div>

    <div class="card">
      <?php if ($rows): ?>
      <table>
        <thead>
          <tr>
            <th style="width:160px">วิชา (Subject)</th>
            <th style="width:220px">ชื่อคอร์ส</th>
            <th>รายละเอียด</th>
            <th style="width:120px">อาจารย์</th>
            <th style="width:110px">ที่นั่ง</th>
            <th style="width:100px">สถานะ</th>
            <th style="width:170px">จัดการ</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $r):
            $cid   = (int)$r['course_id'];
            $sub   = trim(($r['subject_code'] ?? '').' - '.($r['subject_name'] ?? ''));
            if ($sub === '-' || $sub === ' - ') $sub = '—';
            $max   = (int)($r['max_seats'] ?? 0);
            // นับคนลงทะเบียน (พยายามนับเฉพาะ active ถ้ามี)
            try {
              $stX = $pdo->prepare("SELECT COUNT(*) FROM enrollments WHERE course_id=? AND status='active'");
              $stX->execute([$cid]);
              $used = (int)$stX->fetchColumn();
            } catch (Throwable $e) {
              $stX = $pdo->prepare("SELECT COUNT(*) FROM enrollments WHERE course_id=?");
              $stX->execute([$cid]);
              $used = (int)$stX->fetchColumn();
            }
            $left  = $max>0 ? max(0, $max-$used) : '—';
            $isOpen = strtolower($r['status'])==='open';
          ?>
          <tr>
            <td><strong><?= h($sub) ?></strong></td>
            <td><strong><?= h($r['title']) ?></strong></td>
            <td class="meta"><?= nl2br(h($r['description'] ?? '')) ?></td>
            <td><?= h($r['teacher_name'] ?? '—') ?></td>
            <td><?= $max>0 ? "{$used}/{$max} (เหลือ {$left})" : 'ไม่จำกัด' ?></td>
            <td>
              <span class="badge <?= $isOpen?'b-open':'b-closed' ?>">
                <i class="bi <?= $isOpen?'bi-unlock-fill':'bi-lock-fill' ?>"></i>
                <?= $isOpen ? 'เปิด' : 'ปิด' ?>
              </span>
            </td>
            <td>
              <form method="post" style="display:inline">
                <input type="hidden" name="course_id" value="<?= $cid ?>">
                <?php if ($isOpen): ?>
                  <button class="btn btn-err" name="action" value="close" onclick="return confirm('ยืนยันปิดคอร์สนี้?')">
                    <i class="bi bi-lock-fill"></i> ปิดวิชา
                  </button>
                <?php else: ?>
                  <button class="btn btn-ok" name="action" value="open">
                    <i class="bi bi-unlock-fill"></i> เปิดวิชา
                  </button>
                <?php endif; ?>
              </form>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <?php else: ?>
        <p class="meta">ไม่พบรายวิชาตามเงื่อนไข</p>
      <?php endif; ?>
    </div>
  </div>
</body>
</html>
