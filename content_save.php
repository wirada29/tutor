<?php
session_start();
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';
require_login();

$uid   = current_user_id();
$role  = current_user_role();

$courseId = (int)($_POST['course_id'] ?? 0);
$type     = $_POST['type'] ?? 'text';
$title    = trim($_POST['title'] ?? '');
$body     = trim($_POST['body'] ?? '');
$video    = trim($_POST['video_url'] ?? '');
$link     = trim($_POST['link_url'] ?? '');

if ($courseId<=0 || $title==='') { $_SESSION['flash_err']='ข้อมูลไม่ครบ'; header("Location: teacher_contents.php?course_id={$courseId}"); exit; }

// ตรวจสิทธิ์
$st = $pdo->prepare("SELECT teacher_id FROM courses WHERE course_id=?");
$st->execute([$courseId]);
$teacherId = (int)($st->fetchColumn() ?: 0);
if (!is_admin() && $teacherId !== $uid) { $_SESSION['flash_err']='ไม่มีสิทธิ์'; header("Location: teacher_contents.php?course_id={$courseId}"); exit; }

$filePath = null;

// อัปโหลดไฟล์ (type=file)
if ($type==='file' && !empty($_FILES['file']['name'])) {
  $dir = __DIR__ . '/uploads/contents';
  if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
  if (is_uploaded_file($_FILES['file']['tmp_name'])) {
    $ext  = pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION);
    $name = 'ct_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . strtolower($ext);
    $dest = $dir . '/' . $name;
    if (move_uploaded_file($_FILES['file']['tmp_name'], $dest)) {
      $filePath = 'uploads/contents/' . $name; // path สำหรับเรียกดูในเว็บ
    } else {
      $_SESSION['flash_err'] = 'อัปโหลดไฟล์ไม่สำเร็จ';
      header("Location: teacher_contents.php?course_id={$courseId}"); exit;
    }
  }
}

$sql = "INSERT INTO contents(course_id, teacher_id, type, title, body, file_path, video_url, link_url, created_at)
        VALUES(?,?,?,?,?,?,?, ?, NOW())";
$st  = $pdo->prepare($sql);
$ok  = $st->execute([
  $courseId, $teacherId ?: $uid, $type, $title,
  $type==='text' ? $body : null,
  $type==='file' ? $filePath : null,
  $type==='video'? $video : null,
  $type==='link' ? $link  : null
]);

$_SESSION['flash_'.($ok?'ok':'err')] = $ok ? 'บันทึกสำเร็จ' : 'บันทึกไม่สำเร็จ';
header("Location: teacher_contents.php?course_id={$courseId}");
