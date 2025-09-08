<?php
if (!isset($current)) { $current = basename($_SERVER['PHP_SELF']); }
$role = strtolower($_SESSION['user']['role'] ?? $_SESSION['role'] ?? 'student');
$base = '/school_system'; // ปรับให้ตรงโฟลเดอร์จริงของคุณ
?>
<div class="sidebar">
  <h2>📘 สถาบันติวเตอร์</h2>
  <a href="<?= $base ?>/dashboard.php"     class="<?= $current==='dashboard.php'?'active':'' ?>"><i class="bi bi-house-fill"></i> หน้าแรก</a>
  <a href="<?= $base ?>/student.php"       class="<?= $current==='student.php'?'active':'' ?>"><i class="bi bi-person-circle"></i> นักเรียน</a>
  <a href="<?= $base ?>/courses.php"       class="<?= $current==='courses.php'?'active':'' ?>"><i class="bi bi-journal-bookmark-fill"></i> รายวิชา</a>
  <a href="<?= $base ?>/grades.php"        class="<?= $current==='grades.php'?'active':'' ?>"><i class="bi bi-bar-chart-line-fill"></i> ผลการเรียน</a>
  <a href="<?= $base ?>/notifications.php" class="<?= $current==='notifications.php'?'active':'' ?>"><i class="bi bi-bell-fill"></i> แจ้งเตือน</a>
  <?php if ($role === 'admin'): ?>
    <a href="<?= $base ?>/users.php"       class="<?= $current==='users.php'?'active':'' ?>"><i class="bi bi-people-fill"></i> ผู้ใช้ทั้งหมด</a>
  <?php endif; ?>
  <a href="<?= $base ?>/logout.php"><i class="bi bi-box-arrow-right"></i> ออกจากระบบ</a>
</div>
