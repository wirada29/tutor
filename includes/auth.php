<?php
// includes/auth.php

// ฟังก์ชันบังคับให้ต้องล็อกอิน
function require_login() {
    if (empty($_SESSION['user'])) {
        header("Location: login.php");
        exit;
    }
}

// คืนค่า user_id ของผู้ใช้ปัจจุบัน
function current_user_id(): ?int {
    return isset($_SESSION['user']['user_id'])
        ? (int)$_SESSION['user']['user_id']
        : null;
}

// ตรวจสอบว่าเป็นแอดมินไหม
function is_admin(): bool {
    return strtolower($_SESSION['user']['role'] ?? '') === 'admin';
}
