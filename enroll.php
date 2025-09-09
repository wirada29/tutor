<?php
// enroll.php — Action ลงทะเบียน/ถอนรายวิชา พร้อม flash message สวย ๆ
session_start();

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';

require_login();
$uid = current_user_id();

// --- Helper: redirect พร้อม flash ---
function back_with(string $ok = null, string $err = null): void {
    if ($ok)  $_SESSION['flash_ok']  = $ok;
    if ($err) $_SESSION['flash_err'] = $err;
    header("Location: courses.php");
    exit;
}

// --- รับข้อมูลจากฟอร์ม ---
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    back_with(err: "วิธีการเรียกไม่ถูกต้อง");
}

$courseId = (int)($_POST['course_id'] ?? 0);
$action   = $_POST['action'] ?? '';                       // 'enroll' | 'withdraw'

if (!$courseId || !in_array($action, ['enroll','withdraw'], true)) {
    back_with(err: "ข้อมูลคำขอไม่ครบถ้วน");
}

try {
    // เริ่มธุรกรรม
    $pdo->beginTransaction();

    // ล็อกข้อมูลรายวิชาไว้ก่อน (กันที่นั่งซ้อน)
    $st = $pdo->prepare("SELECT course_id, title, status, max_seats FROM courses WHERE course_id = ? FOR UPDATE");
    $st->execute([$courseId]);
    $course = $st->fetch(PDO::FETCH_ASSOC);

    if (!$course) {
        throw new RuntimeException("ไม่พบรายวิชา");
    }

    $title   = $course['title'] ?? "วิชา #{$courseId}";
    $status  = strtolower(trim((string)($course['status'] ?? 'open')));
    $maxSeats = (int)($course['max_seats'] ?? 0); // 0 = ไม่จำกัด

    if ($action === 'enroll') {
        // ตรวจว่าสถานะคอร์สเปิดอยู่ไหม (รองรับคำว่า 'เปิด')
        if (!in_array($status, ['open','เปิด'], true)) {
            throw new RuntimeException("รายวิชานี้ปิดรับลงทะเบียน");
        }

        // จำนวนที่นั่งที่ถูกใช้ (พยายามใช้เฉพาะ active)
        try {
            $stUsed = $pdo->prepare("SELECT COUNT(*) FROM enrollments WHERE course_id = ? AND status = 'active'");
            $stUsed->execute([$courseId]);
            $usedSeats = (int)$stUsed->fetchColumn();
        } catch (Throwable $e) {
            // fallback ถ้าไม่มีคอลัมน์ status
            $stUsed = $pdo->prepare("SELECT COUNT(*) FROM enrollments WHERE course_id = ?");
            $stUsed->execute([$courseId]);
            $usedSeats = (int)$stUsed->fetchColumn();
        }

        if ($maxSeats > 0 && $usedSeats >= $maxSeats) {
            throw new RuntimeException("รายวิชาเต็มแล้ว");
        }

        // ดูว่ามีประวัติลงไว้แล้วหรือไม่
        $stChk = $pdo->prepare("SELECT enrollment_id, status FROM enrollments WHERE user_id = ? AND course_id = ? LIMIT 1");
        $stChk->execute([$uid, $courseId]);
        $old = $stChk->fetch(PDO::FETCH_ASSOC);

        if ($old) {
            if (strtolower($old['status'] ?? '') !== 'active') {
                $stUpd = $pdo->prepare("UPDATE enrollments SET status='active', enrolled_at=NOW() WHERE enrollment_id = ?");
                $stUpd->execute([$old['enrollment_id']]);
            }
        } else {
            // แทรกใหม่เป็น active
            $stIns = $pdo->prepare("INSERT INTO enrollments(user_id, course_id, status, enrolled_at) VALUES(?, ?, 'active', NOW())");
            $stIns->execute([$uid, $courseId]);
        }

        $pdo->commit();
        back_with(ok: "ลงทะเบียน \"{$title}\" สำเร็จ");

    } else { // withdraw
        // ถ้าคุณอยาก "ลบออกเลย" ให้ใช้ DELETE; ที่นี่ใช้เปลี่ยนสถานะเป็น cancelled
        $stDrop = $pdo->prepare("UPDATE enrollments SET status='cancelled' WHERE user_id = ? AND course_id = ? AND status = 'active'");
        $stDrop->execute([$uid, $courseId]);

        // ถ้าไม่มีแถวถูกอัปเดต อาจจะเคยถูกยกเลิกไปแล้ว
        $pdo->commit();

        if ($stDrop->rowCount() > 0) {
            back_with(ok: "ถอนรายวิชา \"{$title}\" เรียบร้อย");
        } else {
            back_with(ok: "ไม่มีรายการ active ของวิชานี้ (อาจถูกถอนไปแล้ว)");
        }
    }

} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    back_with(err: "ทำรายการไม่สำเร็จ: " . $e->getMessage());
}
