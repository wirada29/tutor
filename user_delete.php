<?php
// user_delete.php — เฉพาะแอดมิน
session_start();
require_once __DIR__ . '/includes/auth.php';
require_login();
if (!is_admin()) { header("Location: dashboard.php"); exit; }
require_once __DIR__ . '/config/db.php';

$id   = (int)($_POST['id'] ?? 0);
$self = (int)(current_user_id() ?? 0);

if ($id <= 0) { $_SESSION['flash'] = "คำขอไม่ถูกต้อง"; header("Location: admin_users.php"); exit; }

// ดึงข้อมูลผู้ที่จะลบ
$st = $pdo->prepare("SELECT user_id, role FROM users WHERE user_id=?");
$st->execute([$id]);
$u = $st->fetch(PDO::FETCH_ASSOC);
if (!$u) { $_SESSION['flash'] = "ไม่พบบัญชีผู้ใช้"; header("Location: admin_users.php"); exit; }

// กันลบตัวเอง
if ($id === $self) {
  $_SESSION['flash'] = "ไม่สามารถลบบัญชีของตัวเองได้";
  header("Location: user_view.php?id=".$id);
  exit;
}

// กันลบแอดมินคนสุดท้าย
if (strtolower($u['role'] ?? '') === 'admin') {
  $cnt = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE LOWER(role)='admin'")->fetchColumn();
  if ($cnt <= 1) {
    $_SESSION['flash'] = "ไม่สามารถลบแอดมินคนสุดท้ายของระบบได้";
    header("Location: user_view.php?id=".$id);
    exit;
  }
}

try {
  $del = $pdo->prepare("DELETE FROM users WHERE user_id=?");
  $del->execute([$id]);
  $_SESSION['flash'] = "ลบผู้ใช้เรียบร้อย";
  header("Location: admin_users.php");
  exit;
} catch (Throwable $e) {
  $_SESSION['flash'] = "ลบไม่สำเร็จ: ".$e->getMessage();
  header("Location: user_view.php?id=".$id);
  exit;
}
