<?php
// includes/auth.php
// NOTE: เรียกใช้หลังจากที่หน้าหลักทำ session_start() แล้วเสมอ

if (!function_exists('logged_in')) {
    function logged_in(): bool
    {
        return isset($_SESSION['user']) && is_array($_SESSION['user']);
    }
}

if (!function_exists('require_login')) {
    function require_login(): void
    {
        if (!logged_in()) {
            header('Location: login.php');
            exit;
        }
    }
}

if (!function_exists('current_user')) {
    // เผื่ออยากได้ข้อมูล user ทั้งก้อน
    function current_user(): ?array
    {
        return logged_in() ? $_SESSION['user'] : null;
    }
}

if (!function_exists('current_user_id')) {
    function current_user_id(): ?int
    {
        if (!logged_in()) return null;
        $u = $_SESSION['user'];
        return isset($u['user_id']) ? (int)$u['user_id'] : (isset($u['id']) ? (int)$u['id'] : null);
    }
}

if (!function_exists('current_user_role')) {
    function current_user_role(): string
    {
        return strtolower($_SESSION['user']['role'] ?? 'student');
    }
}

if (!function_exists('has_role')) {
    function has_role(string $role): bool
    {
        return current_user_role() === strtolower($role);
    }
}

if (!function_exists('is_admin')) {
    function is_admin(): bool
    {
        return has_role('admin');
    }
}

if (!function_exists('is_teacher')) {
    function is_teacher(): bool
    {
        return has_role('teacher');
    }
}

if (!function_exists('require_role')) {
    // ใช้บังคับ Role เฉพาะ เช่น require_role('teacher')
    function require_role(string $role): void
    {
        require_login();
        if (!has_role($role) && !is_admin()) { // แอดมินผ่านได้ถ้าต้องการ
            header('HTTP/1.1 403 Forbidden');
            echo 'Forbidden: insufficient role';
            exit;
        }
    }
}

if (!function_exists('require_admin')) {
    function require_admin(): void
    {
        require_role('admin');
    }
}

if (!function_exists('require_teacher')) {
    function require_teacher(): void
    {
        require_role('teacher');
    }
}
