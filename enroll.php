<?php
// enroll.php — จัดการลงทะเบียน/ถอนรายวิชา พร้อม flash message และการตรวจเงื่อนไขครบ
session_start();

require_once __DIR__ . '/includes/auth.php';
require_login();

$uid = current_user_id(); // ควรคืน user_id ของผู้ใช้ปัจจุบัน

// --- ต่อฐานข้อมูล ---
require_once __DIR__ . '/config/db.php';
if (!isset($pdo) || !($pdo instanceof PDO)) {
  // fallback (ถ้าใน config/db.php ไม่ได้สร้าง $pdo)
  $pdo = new PDO("mysql:host=localhost;dbname=school_system;charset=utf8mb4", "root", "");
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
}

// --- helper: redirect พร้อม flash ---
function back_with(?string $ok = null, ?string $err = null, string $to = 'courses.php'): void {
  if ($ok)  $_SESSION['flash_ok']  = $ok;
  if ($err) $_SESSION['flash_err'] = $err;
  header("Location: {$to}");
  exit;
}

// --- validate method ---
if ($_SERVER['REQUEST_METHOD'] !== 'POST') back_with(err: "วิธีการเรียกไม่ถูกต้อง");

// --- รับข้อมูล ---
$courseId = (int)($_POST['course_id'] ?? 0);
$action   = $_POST['action'] ?? ''; // 'enroll' | 'withdraw'
if (!$uid || !$courseId || !in_array($action, ['enroll','withdraw'], true)) {
  back_with(err: "ข้อมูลคำขอไม่ครบถ้วน");
}

try {
  $pdo->beginTransaction();

  // 1) อ่านข้อมูลคอร์ส + ล็อกแถวเพื่อกันแข่งที่นั่ง
  $st = $pdo->prepare("
    SELECT course_id, title, status, max_seats,
           /* คอลัมน์เวลาอาจไม่มี ก็จะเป็น NULL ได้ */
           NULLIF(enroll_open,  enroll_open)  AS _dummy1, enroll_open,
           NULLIF(enroll_close, enroll_close) AS _dummy2, enroll_close
    FROM courses
    WHERE course_id = ?
    FOR UPDATE
  ");
  $st->execute([$courseId]);
  $course = $st->fetch(PDO::FETCH_ASSOC);

  if (!$course) throw new RuntimeException("ไม่พบรายวิชา");

  $title     = $course['title'] ?? ("วิชา #{$courseId}");
  $status    = strtolower(trim((string)($course['status'] ?? 'open'))); // open/closed/เปิด
  $maxSeats  = (int)($course['max_seats'] ?? 0); // 0 = ไม่จำกัด
  $openAt    = $course['enroll_open']  ?? null;  // DATETIME หรือ null
  $closeAt   = $course['enroll_close'] ?? null;  // DATETIME หรือ null
  $now       = date('Y-m-d H:i:s');

  if ($action === 'enroll') {
    // 2) เช็กสถานะคอร์ส
    if (!in_array($status, ['open','เปิด'], true)) {
      throw new RuntimeException("รายวิชานี้ปิดรับลงทะเบียน");
    }

    // 3) เช็กกรอบเวลา (ถ้ามี)
    if (!empty($openAt)  && $now < $openAt)  throw new RuntimeException("ยังไม่ถึงเวลาเปิดรับลงทะเบียน");
    if (!empty($closeAt) && $now > $closeAt) throw new RuntimeException("เลยกำหนดเวลารับลงทะเบียนแล้ว");

    // 4) เช็กที่นั่งคงเหลือ
    $usedSeats = 0;
    try {
      $stUsed = $pdo->prepare("SELECT COUNT(*) FROM enrollments WHERE course_id=? AND status='active'");
      $stUsed->execute([$courseId]);
      $usedSeats = (int)$stUsed->fetchColumn();
    } catch (Throwable $e) {
      // fallback ถ้าไม่มีคอลัมน์ status
      $stUsed = $pdo->prepare("SELECT COUNT(*) FROM enrollments WHERE course_id=?");
      $stUsed->execute([$courseId]);
      $usedSeats = (int)$stUsed->fetchColumn();
    }
    if ($maxSeats > 0 && $usedSeats >= $maxSeats) {
      throw new RuntimeException("รายวิชาเต็มแล้ว");
    }

    // 5) กันซ้ำ (รองรับทั้ง user_id / student_id)
    $stChk = $pdo->prepare("
      SELECT enrollment_id, status
      FROM enrollments
      WHERE (user_id = ? OR student_id = ?)
        AND course_id = ?
      LIMIT 1
    ");
    $stChk->execute([$uid, $uid, $courseId]);
    $old = $stChk->fetch(PDO::FETCH_ASSOC);

    if ($old) {
      // เคยมี: ถ้าไม่ active ให้ activate กลับ
      if (strtolower($old['status'] ?? '') !== 'active') {
        $stUpd = $pdo->prepare("
          UPDATE enrollments
          SET status='active', enrolled_at=NOW()
          WHERE enrollment_id=?
        ");
        $stUpd->execute([$old['enrollment_id']]);
      }
    } else {
      // แทรกใหม่ (ถ้าตารางใช้ student_id ให้เปลี่ยนชื่อคอลัมน์เองได้)
      $stIns = $pdo->prepare("
        INSERT INTO enrollments(user_id, course_id, status, enrolled_at)
        VALUES(?, ?, 'active', NOW())
      ");
      $stIns->execute([$uid, $courseId]);
    }

    $pdo->commit();
    back_with(ok: "ลงทะเบียน \"{$title}\" สำเร็จ");

  } else { // withdraw
    // 6) ถอน: ใช้ update เป็น cancelled (กันลบประวัติ)
    $stDrop = $pdo->prepare("
      UPDATE enrollments
      SET status='cancelled'
      WHERE (user_id = ? OR student_id = ?)
        AND course_id = ?
        AND status = 'active'
    ");
    $stDrop->execute([$uid, $uid, $courseId]);
    $pdo->commit();

    if ($stDrop->rowCount() > 0) {
      back_with(ok: "ถอนรายวิชา \"{$title}\" เรียบร้อย");
    } else {
      back_with(ok: "ไม่มีรายการ active ของวิชานี้ (อาจถอนแล้ว)");
    }
  }

} catch (Throwable $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();
  back_with(err: "ทำรายการไม่สำเร็จ: " . $e->getMessage());
}
