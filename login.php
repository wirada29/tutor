<?php
session_start();
require_once __DIR__ . '/config/db.php'; // ต้องมี $pdo (PDO)

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $roleSel  = strtolower(trim($_POST['role'] ?? 'student')); // บทบาทที่ผู้ใช้เลือกจากหน้าเว็บ

    try {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            $error = "❌ ไม่พบบัญชีผู้ใช้นี้";
        } else {
            // เทียบรหัสผ่าน (ตอนนี้เทียบตรง ๆ ตามที่คุณใช้; ถ้าอยากปลอดภัยใช้ password_hash/password_verify ภายหลังได้)
            $passOk = ($password === ($user['password'] ?? ''));

            if (!$passOk) {
                $error = "❌ อีเมลหรือรหัสผ่านไม่ถูกต้อง";
            } else {
                // เช็คบทบาทที่เลือกให้ตรงกับบทบาทใน DB
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

                    // ✅ ค่าที่แดชบอร์ดใช้ (คุณเคยใช้แบบนี้)
                    $_SESSION['behavior_score'] = 92;   // คะแนนประพฤติ (0-100)
                    $_SESSION['total_score']    = 430;  // คะแนนรวมปัจจุบัน
                    $_SESSION['total_max']      = 500;  // คะแนนเต็ม

                    // 🔀 หากต้องการแยกหน้า landing ตาม role ให้ใช้ switch นี้แทน
                    /*
                    switch ($roleDb) {
                        case 'admin':   header("Location: admin_dashboard.php");   break;
                        case 'teacher': header("Location: teacher_dashboard.php"); break;
                        default:        header("Location: dashboard.php");
                    }
                    exit();
                    */

                    // ค่าเริ่มต้น: ใช้แดชบอร์ดเดียวกัน
                    header("Location: dashboard.php");
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
    <title>เข้าสู่ระบบ</title>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --c1: #36d1dc;
            --c2: #5b86e5;
            --ink: #0f172a;
            --muted: #64748b;
            --card: #ffffff;
        }

        * {
            box-sizing: border-box
        }

        body {
            margin: 0;
            font-family: 'Prompt', sans-serif;
            background: linear-gradient(135deg, var(--c1), var(--c2));
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            color: var(--ink);
        }

        .box {
            background: var(--card);
            padding: 34px 34px 28px;
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, .20);
            width: 420px;
        }

        h2 {
            color: var(--c2);
            margin: 0 0 18px;
            font-weight: 700;
            text-align: center
        }

        .subtitle {
            color: var(--muted);
            font-size: 14px;
            text-align: center;
            margin: -6px 0 16px
        }

        label {
            display: block;
            font-size: 14px;
            margin: 10px 0 6px
        }

        input,
        button {
            width: 100%;
            padding: 12px;
            border-radius: 10px;
            border: 1px solid #cbd5e1;
            font-size: 16px;
        }

        input:focus {
            border-color: var(--c2);
            outline: none;
            box-shadow: 0 0 0 3px rgba(91, 134, 229, .12);
        }

        button {
            background: linear-gradient(to right, var(--c1), var(--c2));
            color: white;
            border: none;
            cursor: pointer;
            font-weight: 700;
            margin-top: 12px;
        }

        button:hover {
            opacity: .95;
        }

        .error {
            color: #e11d48;
            margin-top: 10px;
            text-align: center;
        }

        .link {
            margin-top: 14px;
            font-size: 14px;
            text-align: center;
            color: var(--muted);
        }

        .link a {
            color: var(--c2);
            font-weight: 700;
            text-decoration: none;
        }

        /* Role selector */
        .roles {
            display: flex;
            gap: 10px;
            margin: 10px 0 4px;
        }

        .role {
            flex: 1;
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 10px 8px;
            border-radius: 10px;
            border: 1px solid #cbd5e1;
            cursor: pointer;
            user-select: none;
            transition: border-color .2s, box-shadow .2s, transform .05s;
            background: #f8fafc;
        }

        .role:hover {
            border-color: var(--c2);
            box-shadow: 0 0 0 3px rgba(91, 134, 229, .12);
        }

        .role input {
            position: absolute;
            inset: 0;
            opacity: 0;
            cursor: pointer;
        }

        .role.active {
            border-color: var(--c2);
            box-shadow: 0 0 0 3px rgba(91, 134, 229, .18);
            background: #fff;
        }
    </style>
</head>

<body>
    <div class="box">
        <h2>🔑 เข้าสู่ระบบ</h2>
        <p class="subtitle">เลือกบทบาทให้ตรงกับสิทธิ์ในระบบ</p>

        <form method="POST" autocomplete="off">
            <label>อีเมล</label>
            <input type="email" name="email" placeholder="📧 your@email.com" required>

            <label>รหัสผ่าน</label>
            <input type="password" name="password" placeholder="🔒 ••••••••" required>

            <label>บทบาท</label>
            <div class="roles" id="roles">
                <label class="role active">
                    <input type="radio" name="role" value="student" checked>
                    👨‍🎓 Student
                </label>
                <label class="role">
                    <input type="radio" name="role" value="teacher">
                    👩‍🏫 Teacher
                </label>
                <label class="role">
                    <input type="radio" name="role" value="admin">
                    🛡️ Admin
                </label>
            </div>

            <button type="submit">เข้าสู่ระบบ</button>
        </form>

        <?php if (isset($error)): ?>
            <p class="error"><?= htmlspecialchars($error) ?></p>
        <?php endif; ?>

        <p class="link" id="regWrap">
            ยังไม่มีบัญชี?
            <a id="regLink" href="register_student.php">สมัครสมาชิก (เฉพาะนักเรียน)</a>
        </p>


    </div>

    <script>
        // ปุ่มบทบาท (label สวย ๆ)
        const roleBoxes = document.querySelectorAll('.role');
        roleBoxes.forEach(lb => {
            lb.addEventListener('click', () => {
                roleBoxes.forEach(x => x.classList.remove('active'));
                lb.classList.add('active');
                const input = lb.querySelector('input[type="radio"]');
                if (input) input.checked = true;
                // หมายเหตุ: เรามี listener 'change' ด้านล่างอยู่แล้ว
                // แต่บางธีม label อาจไม่ยิง change ทันที จึงเรียก update ซ้ำให้ชัวร์
                if (input) updateRegLink(input.value);
            });
        });

        // อัปเดตลิงก์สมัครสมาชิกตามบทบาท (ใช้กับ radio โดยตรง)
        const regLink = document.getElementById('regLink');
        const roleToLink = {
            student: {
                href: 'register_student.php',
                text: 'สมัครสมาชิก (เฉพาะนักเรียน)'
            },
            teacher: {
                href: 'register_teacher.php',
                text: 'สมัครบัญชี (เฉพาะครู)'
            },
            admin: {
                href: 'register_admin.php',
                text: 'สมัครบัญชี (เฉพาะแอดมิน)'
            }
        };

        function updateRegLink(role) {
            const m = roleToLink[role] || roleToLink.student;
            if (regLink) {
                regLink.href = m.href;
                regLink.textContent = m.text;
            }
        }

        // ฟังการเปลี่ยนค่าจาก radio โดยตรง (แม่นสุด)
        const roleRadios = document.querySelectorAll('input[name="role"]');
        roleRadios.forEach(r => {
            r.addEventListener('change', () => updateRegLink(r.value));
        });

        // ตั้งค่าตอนโหลดหน้า
        document.addEventListener('DOMContentLoaded', () => {
            const checked = document.querySelector('input[name="role"]:checked');
            updateRegLink(checked ? checked.value : 'student');
        });
    </script>
</body>
</html>