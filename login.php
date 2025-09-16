<?php
session_start();
require_once __DIR__ . '/config/db.php'; // ต้องมี $pdo (PDO)

// เก็บค่าที่กรอกไว้เพื่อแสดงกลับ
$initialRole = strtolower(trim($_POST['role'] ?? 'student'));
$emailVal    = $_POST['email'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $roleSel  = $initialRole; // บทบาทที่เลือกจากฟอร์ม

    try {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            $error = "❌ ไม่พบบัญชีผู้ใช้นี้";
        } else {
            // เทียบรหัสผ่านตามระบบเดิม (ยังไม่ hash)
            $passOk = ($password === ($user['password'] ?? ''));

            if (!$passOk) {
                $error = "❌ อีเมลหรือรหัสผ่านไม่ถูกต้อง";
            } else {
                $roleDb = strtolower($user['role'] ?? 'student');
                if ($roleSel !== $roleDb) {
                    $error = "⚠️ บทบาทที่เลือกไม่ตรงกับสิทธิ์ในระบบ (คุณคือ '{$roleDb}')";
                } else {
                    // Login สำเร็จ: เก็บ session
                    $_SESSION['user'] = [
                        'user_id' => $user['user_id'] ?? ($user['id'] ?? null),
                        'name'    => $user['name']    ?? '',
                        'email'   => $user['email']   ?? '',
                        'role'    => $roleDb
                    ];

                    // ค่าโชว์ในแดชบอร์ด (ตามเดิม)
                    $_SESSION['behavior_score'] = 92;
                    $_SESSION['total_score']    = 430;
                    $_SESSION['total_max']      = 500;

                    // ➜ เด้งไปตามบทบาท
                    $target = match ($roleDb) {
                        'teacher' => 'teacher_dashboard.php',
                        'admin'   => 'admin_dashboard.php', 
                        default   => 'dashboard.php',
                    };

                    if (!is_file(__DIR__ . "/{$target}")) {
                        $target = 'dashboard.php';
                    }

                    header("Location: {$target}");
                    exit();
                }
            }
        }
    } catch (Throwable $e) {
        $error = "เกิดข้อผิดพลาดในการเชื่อมต่อฐานข้อมูล";
    }
}
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <title>เข้าสู่ระบบ | สถาบันติวเตอร์</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;600&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root {
            --c1: #36d1dc;
            --c2: #5b86e5;
            --ink: #0f172a;
            --muted: #64748b;
            --card: #ffffff;
            --ok: #16a34a;
            --err: #e11d48;
            --line: #e5e7eb;
            --brand: #ffb703;
        }

        * { box-sizing: border-box }

        body {
            margin: 0;
            font-family: 'Prompt', sans-serif;
            color: var(--ink);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, var(--c1), var(--c2));
            padding: 24px;
        }

        .wrap {
            width: min(520px, 100%);
            background: linear-gradient(180deg, rgba(255, 255, 255, .9), rgba(255, 255, 255, .95));
            border-radius: 20px;
            box-shadow: 0 20px 50px rgba(0, 0, 0, .18);
            overflow: hidden;
            backdrop-filter: blur(4px);
        }

        .hero {
            padding: 20px 24px;
            background: linear-gradient(90deg, #4cb5da, #5b86e5);
            color: #fff;
        }

        .hero h2 {
            margin: 0;
            font-size: 28px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        .hero i {
            font-size: 36px;
        }

        .brand-title {
            color: var(--brand);
            font-size: 32px;
            font-weight: 900;
        }

        .login-text {
            font-size: 22px;
            font-weight: 600;
            color: #fff;
        }

        .hero p {
            margin: 6px 0 0;
            opacity: .95;
            font-size: 14px
        }

        .card {
            padding: 26px;
            background: var(--card);
        }

        label { display: block; font-size: 14px; color: var(--muted); margin: 10px 0 6px }
        .group { position: relative }
        .group i { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #94a3b8; font-size: 18px; }
        input, button { width: 100%; padding: 12px 12px 12px 38px; font-size: 16px; border-radius: 12px; border: 1px solid var(--line); background: #fff; transition: border-color .2s, box-shadow .2s; }
        .pw-toggle { position: absolute; right: 12px; top: 50%; transform: translateY(-50%); border: none; background: transparent; cursor: pointer; color: #64748b; font-size: 18px; padding: 0; }

        .roles { display: flex; gap: 10px; margin: 10px 0 4px }
        .role { flex: 1; position: relative; display: flex; align-items: center; justify-content: center; gap: 8px; padding: 10px 8px; border-radius: 10px; border: 1px solid #cbd5e1; cursor: pointer; user-select: none; transition: border-color .2s, box-shadow .2s, transform .05s; background: #f8fafc; }
        .role:hover { border-color: #5b86e5; box-shadow: 0 0 0 3px rgba(91, 134, 229, .12) }
        .role input { position: absolute; inset: 0; opacity: 0; cursor: pointer }
        .role.active { border-color: #5b86e5; box-shadow: 0 0 0 3px rgba(91, 134, 229, .18); background: #fff }

        .btn { margin-top: 14px; display: flex; align-items: center; justify-content: center; gap: 8px; padding: 12px; font-weight: 700; border: none; border-radius: 12px; cursor: pointer; background: linear-gradient(90deg, var(--c1), var(--c2)); color: #fff; box-shadow: 0 6px 16px rgba(91, 134, 229, .25); }
        .btn:hover { filter: brightness(1.05) }

        .subtitle { color: var(--muted); font-size: 14px; text-align: center; margin: 8px 0 }
        .msg { text-align: center; font-size: 14px; margin: 8px 0 }
        .msg.err { color: var(--err) }
        .msg.ok { color: var(--ok) }

        .link { text-align: center; margin-top: 12px; font-size: 14px; color: var(--muted) }
        .link a { color: #5b86e5; font-weight: 700; text-decoration: none }
    </style>
</head>

<body>
    <div class="wrap">
        <div class="hero">
            <h2>
                <i class="bi bi-mortarboard-fill"></i>
                <span class="brand-title">สถาบันติวเตอร์</span>
                <span class="login-text">| เข้าสู่ระบบ</span>
            </h2>
            <p>เลือกบทบาทให้ตรงกับสิทธิ์ของบัญชีคุณ</p>
        </div>

        <div class="card">
            <?php if (!empty($_SESSION['flash'])): ?>
                <p class="msg ok"><?= htmlspecialchars($_SESSION['flash']); unset($_SESSION['flash']); ?></p>
            <?php endif; ?>
            <?php if (isset($error)): ?>
                <p class="msg err"><?= htmlspecialchars($error) ?></p>
            <?php endif; ?>

            <form method="POST" autocomplete="off">
                <label>อีเมล</label>
                <div class="group">
                    <i class="bi bi-envelope-fill"></i>
                    <input type="email" name="email" placeholder="your@email.com"
                        value="<?= htmlspecialchars($emailVal) ?>" required>
                </div>

                <label>รหัสผ่าน</label>
                <div class="group">
                    <i class="bi bi-shield-lock-fill"></i>
                    <input type="password" name="password" placeholder=" ••••••••" required>
                </div>

                <label>บทบาท</label>
                <div class="roles" id="roles">
                    <label class="role <?= $initialRole === 'student' ? 'active' : '' ?>">
                        <input type="radio" name="role" value="student" <?= $initialRole === 'student' ? 'checked' : '' ?>>
                        👨‍🎓 Student
                    </label>
                    <label class="role <?= $initialRole === 'teacher' ? 'active' : '' ?>">
                        <input type="radio" name="role" value="teacher" <?= $initialRole === 'teacher' ? 'checked' : '' ?>>
                        👩‍🏫 Teacher
                    </label>
                    <label class="role <?= $initialRole === 'admin' ? 'active' : '' ?>">
                        <input type="radio" name="role" value="admin" <?= $initialRole === 'admin' ? 'checked' : '' ?>>
                        🛡️ Admin
                    </label>
                </div>

                <button class="btn" type="submit"><i class="bi bi-box-arrow-in-right"></i> เข้าสู่ระบบ</button>
            </form>

            <p class="link" id="regWrap">
                ยังไม่มีบัญชี?
                <a id="regLink" href="register_student.php">ลงทะเบียน (เฉพาะนักเรียน)</a>
            </p>
        </div>
    </div>

    <script>
        const ROLE_LINK = {
            student: { href: 'register_student.php', text: 'ลงทะเบียน (เฉพาะนักเรียน)' },
            teacher: { href: 'register_teacher.php', text: 'ลงทะเบียน (เฉพาะครู)' },
            admin: { href: 'register_admin.php', text: 'ลงทะเบียน (เฉพาะแอดมิน)' }
        };
        const regLink = document.getElementById('regLink');
        const roleRadios = document.querySelectorAll('input[name="role"]');
        const roleBoxes = document.querySelectorAll('.role');

        function getSelectedRole() {
            const r = document.querySelector('input[name="role"]:checked');
            return r ? r.value : 'student';
        }

        function updateRegisterLink(role) {
            const cfg = ROLE_LINK[role] || ROLE_LINK.student;
            regLink.href = cfg.href;
            regLink.textContent = cfg.text;
        }

        function syncActive(role) {
            roleBoxes.forEach(lb => {
                const input = lb.querySelector('input[type="radio"]');
                lb.classList.toggle('active', !!input && input.value === role);
            });
        }

        function syncUI() {
            const role = getSelectedRole();
            updateRegisterLink(role);
            syncActive(role);
        }

        roleRadios.forEach(r => r.addEventListener('change', syncUI));
        roleBoxes.forEach(lb => lb.addEventListener('click', () => {
            const input = lb.querySelector('input[type="radio"]');
            if (input) {
                input.checked = true;
                syncUI();
            }
        }));

        document.addEventListener('DOMContentLoaded', syncUI);
    </script>
</body>
</html>
