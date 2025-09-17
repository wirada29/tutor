<?php
// admin_users.php — เฉพาะแอดมินเท่านั้น
session_start();
require_once __DIR__ . '/includes/auth.php';
require_login();
if (!is_admin()) { header("Location: dashboard.php"); exit; }

require_once __DIR__ . '/config/db.php';

// รับค่าค้นหา/กรองบทบาท/เพจ
$q     = trim($_GET['q'] ?? '');
$roleF = strtolower(trim($_GET['role'] ?? '')); // '', student, teacher, admin
$page  = max(1, (int)($_GET['page'] ?? 1));
$per   = 12;
$off   = ($page - 1) * $per;

// นับทั้งหมด
$sqlCount = "SELECT COUNT(*) FROM users WHERE 1";
$argsC    = [];
if ($q !== '') {
    $sqlCount .= " AND (name LIKE ? OR email LIKE ?)";
    $argsC[] = "%$q%";
    $argsC[] = "%$q%";
}
if (in_array($roleF, ['student','teacher','admin'], true)) {
    $sqlCount .= " AND LOWER(role)=?";
    $argsC[] = $roleF;
}
$st = $pdo->prepare($sqlCount);
$st->execute($argsC);
$total = (int)$st->fetchColumn();

// รายการผู้ใช้ (จัดหน้า) — ตัด created_at, updated_at ที่ไม่มีในตารางออก
$sql = "SELECT user_id, name, email, role
        FROM users
        WHERE 1";
$args = [];
if ($q !== '') {
    $sql .= " AND (name LIKE ? OR email LIKE ?)";
    $args[] = "%$q%";
    $args[] = "%$q%";
}
if (in_array($roleF, ['student','teacher','admin'], true)) {
    $sql .= " AND LOWER(role)=?";
    $args[] = $roleF;
}
$sql .= " ORDER BY user_id DESC LIMIT $per OFFSET $off";
$st = $pdo->prepare($sql);
$st->execute($args);
$rows = $st->fetchAll(PDO::FETCH_ASSOC);

// สำหรับไฮไลท์เมนู
$current = basename($_SERVER['PHP_SELF']);
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
?>
<!doctype html>
<html lang="th">
<head>
<meta charset="utf-8">
<title>ผู้เข้าใช้ทั้งหมด | แอดมินเท่านั้น</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;600&display=swap" rel="stylesheet">
<style>
:root{--blue:#3b82f6;--blue-dark:#2563eb;--ink:#0f172a;--muted:#64748b;--bg:#f5f7fa;--card:#fff;--line:#e5e7eb}
*{box-sizing:border-box}
body{margin:0;font-family:'Sarabun',system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif;background:var(--bg);color:var(--ink);display:flex;min-height:100vh}
.sidebar{width:230px;background:linear-gradient(180deg,var(--blue),#2b6de1);color:#fff;height:100vh;padding:26px 16px;position:fixed;inset:0 auto 0 0;overflow-y:auto;box-shadow:0 6px 20px rgba(0,0,0,.08)}
.sidebar h2{font-size:22px;font-weight:600;margin:0 0 24px;text-align:center}
.sidebar a{display:flex;align-items:center;gap:10px;color:#fff;text-decoration:none;margin-bottom:12px;padding:11px 10px;border-radius:10px;transition:transform .15s,background .2s,opacity .2s;opacity:.95}
.sidebar a:hover{background:rgba(255,255,255,.15);transform:translateY(-1px);opacity:1}
.sidebar a.active{background:rgba(255,255,255,.22);box-shadow:inset 0 0 0 1px rgba(255,255,255,.18)}
.main{flex:1;margin-left:230px;padding:28px}
.header{display:flex;align-items:center;gap:12px;margin:0 0 14px}
.header h2{margin:0;font-size:26px}
.chip{background:#eef2ff;color:#1e3a8a;border-radius:999px;padding:6px 10px;font-weight:700;font-size:13px}
.card{background:var(--card);border-radius:16px;padding:18px;margin-bottom:14px;box-shadow:0 8px 24px rgba(15,23,42,.06)}
.row{display:flex;gap:10px;flex-wrap:wrap;align-items:center}
.input,.select{padding:10px 12px;border:1px solid var(--line);border-radius:10px}
.select{background:#fff}
.btn{padding:10px 14px;border-radius:10px;border:0;cursor:pointer;font-weight:700}
.btn-primary{background:var(--blue-dark);color:#fff}
.btn-muted{background:#e5e7eb}
table{width:100%;border-collapse:collapse}
th,td{padding:10px;border-bottom:1px solid var(--line);text-align:left;vertical-align:top}
th{background:#eef2ff}
.badge{display:inline-flex;align-items:center;gap:6px;padding:6px 10px;border-radius:999px;font-weight:700;font-size:12px}
.b-student{background:#e0f2fe;color:#075985}
.b-teacher{background:#dcfce7;color:#166534}
.b-admin{background:#fee2e2;color:#991b1b}
.meta{color:var(--muted);font-size:12px}
.pager{display:flex;gap:8px;flex-wrap:wrap;justify-content:flex-end;margin-top:10px}
.pager a,.pager span{display:inline-flex;align-items:center;justify-content:center;min-width:36px;padding:8px 12px;border-radius:10px;border:1px solid var(--line);text-decoration:none;color:#0f172a;font-weight:700}
.pager .active{background:var(--blue-dark);color:#fff;border-color:var(--blue-dark)}
@media (max-width:992px){.sidebar{position:relative;width:100%;height:auto;inset:auto}.main{margin-left:0;padding:20px}}
</style>
</head>
<body>

<!-- Sidebar (สำหรับแอดมิน) -->
<div class="sidebar">
  <h2>🛡️ แอดมิน</h2>

  <!-- ส่วนของแอดมิน -->
  <div style="height:1px;background:rgba(255,255,255,.25);margin:10px 0"></div>
   <a href="admin_dashboard.php" class="active"><i class="bi bi-speedometer2"></i> หน้าผู้ดูแล</a>
    <a href="admin_users.php"><i class="bi bi-people-fill"></i> ผู้ใช้ทั้งหมด</a>
      <a href="admin/courses_manage.php"><i class="bi bi-toggle-on"></i> จัดการรายวิชา</a>
    <a href="register_teacher.php"><i class="bi bi-person-plus"></i> สร้างบัญชีครู</a>
    <a href="register_admin.php"><i class="bi bi-shield-plus"></i> สร้างบัญชีแอดมิน</a>
  
  <div style="height:1px;background:rgba(255,255,255,.25);margin:10px 0"></div>
  <a href="logout.php"><i class="bi bi-box-arrow-right"></i> ออกจากระบบ</a>
</div>

<!-- Main -->
<div class="main">
  <div class="header">
    <h2><i class="bi bi-people-fill"></i> ผู้เข้าใช้ทั้งหมด</h2>
    <span class="chip"><?= number_format($total) ?> บัญชี</span>
  </div>

  <div class="card">
    <form class="row" method="get" action="admin_users.php">
      <input class="input" type="text" name="q" placeholder="ค้นหาชื่อหรืออีเมล..." value="<?= h($q) ?>">
      <select class="select" name="role">
        <option value="">ทุกบทบาท</option>
        <option value="student" <?= $roleF==='student'?'selected':'' ?>>Student</option>
        <option value="teacher" <?= $roleF==='teacher'?'selected':'' ?>>Teacher</option>
        <option value="admin"   <?= $roleF==='admin'  ?'selected':'' ?>>Admin</option>
      </select>
      <button class="btn btn-primary" type="submit"><i class="bi bi-search"></i> ค้นหา</button>
      <?php if ($q!=='' || $roleF!==''): ?>
        <a class="btn btn-muted" href="admin_users.php"><i class="bi bi-eraser"></i> ล้างตัวกรอง</a>
      <?php endif; ?>
    </form>
  </div>

  <div class="card">
    <?php if ($rows): ?>
      <table>
        <thead>
          <tr>
            <th style="width:72px">รหัส</th>
            <th>ชื่อ</th>
            <th style="width:26%">อีเมล</th>
            <th style="width:120px">บทบาท</th>
            <th style="width:22%">ข้อมูลระบบ</th>
            <th style="width:110px">จัดการ</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($rows as $u):
          $rid = (int)$u['user_id'];
          $nm  = $u['name'] ?? '';
          $em  = $u['email'] ?? '';
          $rl  = strtolower($u['role'] ?? 'student');
          // ไม่มีคอลัมน์ created_at/updated_at ใน DB → แสดงเป็น  —
          $created = '';
          $updated = '';
          $badge = $rl==='admin' ? 'b-admin' : ($rl==='teacher' ? 'b-teacher' : 'b-student');
        ?>
          <tr>
            <td>#<?= $rid ?></td>
            <td><?= h($nm) ?></td>
            <td><?= h($em) ?></td>
            <td><span class="badge <?= $badge ?>"><i class="bi bi-person-badge-fill"></i> <?= strtoupper($rl) ?></span></td>
            <td class="meta">
              สร้าง: <?= h($created ?: '—') ?><br>
              อัปเดต: <?= h($updated ?: '—') ?>
            </td>
            <td>
              <a class="btn-muted" style="text-decoration:none;padding:6px 10px;border-radius:8px" href="user_view.php?id=<?= $rid ?>">
                <i class="bi bi-eye"></i> ดู
              </a>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>

      <?php if ($pages = max(1, (int)ceil($total / $per))): ?>
        <?php if ($pages > 1): ?>
          <div class="pager">
            <?php for ($i=1; $i<=$pages; $i++):
              $qs = http_build_query(array_filter(['q'=>$q,'role'=>$roleF,'page'=>$i], fn($v)=>$v!==''));
            ?>
              <?php if ($i===$page): ?>
                <span class="active"><?= $i ?></span>
              <?php else: ?>
                <a href="admin_users.php?<?= $qs ?>"><?= $i ?></a>
              <?php endif; ?>
            <?php endfor; ?>
          </div>
        <?php endif; ?>
      <?php endif; ?>

    <?php else: ?>
      <p style="color:var(--muted)">ไม่พบบัญชีผู้ใช้ตามเงื่อนไขที่ค้นหา</p>
    <?php endif; ?>
  </div>
</div>
</body>
</html>
