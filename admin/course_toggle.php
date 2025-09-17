<?php
require_once __DIR__.'/../includes/auth.php';
require_admin();
require_once __DIR__.'/../config/db.php';

$cid = (int)($_POST['course_id'] ?? 0);
$act = strtolower($_POST['action'] ?? '');

if (!$cid || !in_array($act, ['open','close'], true)) {
  http_response_code(400); exit('bad request');
}

$new = $act==='open' ? 'open' : 'closed';
$st = $pdo->prepare("UPDATE courses SET status=? WHERE course_id=?");
$st->execute([$new, $cid]);

header('Location: courses_manage.php'); // กลับไปหน้าเดิม
