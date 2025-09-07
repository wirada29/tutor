<?php
session_start();
session_unset();     // เคลียร์ตัวแปร session ทั้งหมด
session_destroy();   // ทำลาย session

header("Location: login.php"); // กลับไปหน้า Login
exit();
?>