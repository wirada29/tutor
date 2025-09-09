<?php
session_start();
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';
require_login();

$uid      = current_user_id();
$contentId= (int)($_POST['content_id'] ?? 0);
$courseId = (int)($_POST['course_id'] ?? 0);

if ($contentId<=0 || $courseId<=0){ header("Location: teacher_contents.php?course_id={$courseId}"); exit; }

// ดึง content + ตรวจสิทธิ์
$st = $pdo->prepare("SELECT c.*, cc.teacher_id AS course_teacher FROM contents c JOIN courses cc ON cc.course_id=c.course_id WHERE c.content_id=?");
$st->execute([$contentId]);
$ct = $st->fetch(PDO::FETCH_ASSOC);
if (!$ct){ $_SESSION['flash_err']='ไม่พบเนื้อหา'; header("Location: teacher_contents.php?course_id={$courseId}"); exit; }

if (!is_admin() && (int)$ct['course_teacher'] !== $uid) {
  $_SESSION['flash_err'] = 'ไม่มีสิทธิ์';
  header("Location: teacher_contents.php?course_id={$courseId}"); exit;
}

// ลบไฟล์จริงถ้ามี
if (!empty($ct['file_path'])) {
  $path = __DIR__ . '/' . $ct['file_path'];
  if (is_file($path)) @unlink($path);
}

$st = $pdo->prepare("DELETE FROM contents WHERE content_id=?");
$ok = $st->execute([$contentId]);

$_SESSION['flash_'.($ok?'ok':'err')] = $ok ? 'ลบสำเร็จ' : 'ลบไม่สำเร็จ';
header("Location: teacher_contents.php?course_id={$courseId}");
