<?php
require_once 'db.php';

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Language Handler
if (isset($_GET['lang'])) {
    $lang = $_GET['lang'] === 'th' ? 'th' : 'en';
    $_SESSION['lang'] = $lang;
} elseif (isset($_SESSION['lang'])) {
    $lang = $_SESSION['lang'];
} else {
    $lang = 'en'; // default
    $_SESSION['lang'] = $lang;
}

// Translate helper function
if (!function_exists('t')) {
    function t($en, $th) {
        global $lang;
        return $lang === 'th' ? $th : $en;
    }
}

// Helper to build URL preserving other query params but modifying 'lang'
function getLangUrl($target_lang) {
    $params = $_GET;
    $params['lang'] = $target_lang;
    return '?' . http_build_query($params);
}

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    header("Location: admin/index.php");
    exit;
}

$login_error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');
    
    if (!$email || !$password) {
        $login_error = t("Please fill in all fields.", "กรุณากรอกอีเมลและรหัสผ่านให้ครบถ้วน");
    } else {
        try {
            // Check admin table first
            $stmt = $pdo->prepare("SELECT * FROM admin WHERE admin_email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            
            if ($user && password_verify($password, $user['admin_password_hash'])) {
                $_SESSION['user_id'] = $user['admin_id'];
                $_SESSION['user_name'] = $user['admin_name'];
                $_SESSION['user_email'] = $user['admin_email'];
                $_SESSION['user_role'] = $user['role'];
                
                header("Location: admin/index.php");
                exit;
            }
            
            // Check staff table second
            $stmt = $pdo->prepare("SELECT * FROM staff WHERE staff_email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            
            if ($user && password_verify($password, $user['staff_password_hash'])) {
                $_SESSION['user_id'] = $user['staff_id'];
                $_SESSION['user_name'] = $user['staff_name'];
                $_SESSION['user_email'] = $user['staff_email'];
                $_SESSION['user_role'] = $user['role'];
                
                header("Location: admin/index.php");
                exit;
            }
            
            $login_error = t("Invalid email or password.", "อีเมลหรือรหัสผ่านไม่ถูกต้อง กรุณาลองใหม่อีกครั้ง");
        } catch (Exception $e) {
            $login_error = "Database error: " . $e->getMessage();
        }
    }
}

?>
<!DOCTYPE html>
<html lang="<?php echo $lang; ?>" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CHIT HOLE CNX - Admin Portal Sign In</title>
    
    <!-- Google Fonts & Material Symbols -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Arvo:wght@400;700&family=Pridi:wght@300;400;500;600;700&family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0&display=swap" rel="stylesheet">
    
    <!-- Tailwind CSS v4 Browser Compiler -->
    <script src="https://unpkg.com/@tailwindcss/browser@4"></script>
    
    <!-- Standard CSS Styles -->
    <style>
        body {
            background-color: #09090b;
            color: #fafafa;
            font-family: 'Rockwell', 'Pridi', 'Arvo', serif;
            min-height: 100vh;
        }

        /* Make Thai text bolder for readability */
        html[lang="th"] body,
        html[lang="th"] p,
        html[lang="th"] span,
        html[lang="th"] a,
        html[lang="th"] button,
        html[lang="th"] h1,
        html[lang="th"] h2,
        html[lang="th"] h3,
        html[lang="th"] h4,
        html[lang="th"] h5,
        html[lang="th"] h6,
        html[lang="th"] input,
        html[lang="th"] select,
        html[lang="th"] textarea {
            font-weight: 600 !important;
        }

        .pearl-white-btn {
            background: linear-gradient(135deg, #ffffff 0%, #f4efe6 100%) !important;
            color: #111113 !important;
            border: 1px solid #ffffff !important;
            box-shadow: 0 4px 20px rgba(255, 255, 255, 0.4), 0 0 25px rgba(244, 239, 230, 0.3) !important;
            transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1) !important;
        }
        .pearl-white-btn:hover {
            background: linear-gradient(135deg, #ffffff 0%, #ffffff 100%) !important;
            color: #000000 !important;
            box-shadow: 0 0 35px rgba(255, 255, 255, 0.85), 0 0 15px rgba(255, 255, 255, 0.6) !important;
            transform: translateY(-2px) !important;
        }
        .pearl-white-btn:active {
            transform: translateY(0) !important;
        }
        .pearl-white-btn span {
            color: #111113 !important;
            font-weight: 700 !important;
        }

        .glass-login-card {
            background: rgba(18, 18, 22, 0.88);
            backdrop-filter: blur(24px);
            -webkit-backdrop-filter: blur(24px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.9), 0 0 30px rgba(0, 0, 0, 0.5);
        }

        /* Premium Validation Error Styles */
        @keyframes premiumShake {
            0%, 100% { transform: translateX(0); }
            20%, 60% { transform: translateX(-4px); }
            40%, 80% { transform: translateX(4px); }
        }
        .animate-premium-shake {
            animation: premiumShake 0.35s ease-in-out;
        }
        .premium-field-error-badge {
            animation: fadeInSlide 0.2s cubic-bezier(0.16, 1, 0.3, 1);
        }
        @keyframes fadeInSlide {
            from { opacity: 0; transform: translateY(-4px) scale(0.98); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }
    </style>
    
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('form').forEach(function(f) {
            f.setAttribute('novalidate', 'novalidate');
        });

        document.addEventListener('submit', function(e) {
            var form = e.target;
            if (!form) return;

            var requiredInputs = form.querySelectorAll('input[required], select[required], textarea[required]');
            var isValid = true;
            var firstInvalid = null;

            requiredInputs.forEach(function(input) {
                var val = input.value ? input.value.trim() : '';
                if (!val) {
                    isValid = false;
                    if (!firstInvalid) firstInvalid = input;
                    showPremiumFieldError(input);
                } else {
                    clearPremiumFieldError(input);
                }
            });

            if (!isValid) {
                e.preventDefault();
                e.stopPropagation();
                if (firstInvalid) firstInvalid.focus();
                return false;
            }
        }, true);

        document.addEventListener('invalid', function(e) {
            e.preventDefault();
            var input = e.target;
            if (input) showPremiumFieldError(input);
        }, true);

        function showPremiumFieldError(input) {
            var msg = input.getAttribute('data-error') || input.dataset.customError;
            if (!msg) {
                var customVal = input.getAttribute('oninvalid');
                if (customVal && customVal.indexOf('setCustomValidity') !== -1) {
                    var match = customVal.match(/setCustomValidity\(['"]([^'"]+)['"]\)/);
                    if (match && match[1]) msg = match[1];
                }
            }
            if (!msg || msg.indexOf('Please fill') !== -1 || msg.indexOf('กรุณากรอกข้อมูลในฟิลด์นี้') !== -1) {
                msg = '⚠️ กรุณาระบุข้อมูลในช่องนี้';
            }

            clearPremiumFieldError(input);

            input.classList.add('animate-premium-shake');
            input.style.borderColor = '#f43f5e';
            input.style.boxShadow = '0 0 0 2px rgba(244, 63, 94, 0.4), 0 0 15px rgba(244, 63, 94, 0.25)';
            
            setTimeout(function() {
                input.classList.remove('animate-premium-shake');
            }, 400);

            var parentDiv = input.closest('.input-wrapper') || input.parentNode;
            var badge = document.createElement('div');
            badge.className = 'premium-field-error-badge mt-1.5 inline-flex items-center gap-1.5 px-3 py-1.5 bg-rose-950/90 border border-rose-500/60 text-rose-200 text-xs font-sans rounded-md shadow-xl backdrop-blur-md';
            badge.innerHTML = '<span class="material-symbols-outlined text-rose-400 text-sm shrink-0">error</span><span class="font-medium tracking-wide">' + msg + '</span>';

            parentDiv.appendChild(badge);

            var clearHandler = function() {
                clearPremiumFieldError(input);
                input.removeEventListener('input', clearHandler);
                input.removeEventListener('change', clearHandler);
            };
            input.addEventListener('input', clearHandler);
            input.addEventListener('change', clearHandler);
        }

        function clearPremiumFieldError(input) {
            input.style.borderColor = '';
            input.style.boxShadow = '';
            var parentDiv = input.closest('.input-wrapper') || input.parentNode;
            if (parentDiv) {
                var existing = parentDiv.querySelector('.premium-field-error-badge');
                if (existing) existing.remove();
            }
        }
    });

    function togglePasswordVisibility() {
        const input = document.getElementById('password-input');
        const icon = document.getElementById('password-toggle-icon');
        if (!input || !icon) return;

        if (input.type === 'password') {
            input.type = 'text';
            icon.innerText = 'visibility_off';
        } else {
            input.type = 'password';
            icon.innerText = 'visibility';
        }
    }
    </script>
</head>
<body class="relative flex items-center justify-center min-h-screen p-4 sm:p-6 overflow-x-hidden selection:bg-amber-500/30 selection:text-amber-200">

    <!-- Ambient Dark Background Image Wallpaper with Blur -->
    <div class="fixed inset-0 z-0 bg-cover bg-center bg-no-repeat opacity-25 scale-105" style="background-image: url('images/home-booking/749356007_122278339724129427_2108767678100899836_n.jpg'); mix-blend-mode: luminosity;"></div>
    <div class="fixed inset-0 z-0 bg-gradient-to-t from-[#09090b] via-[#09090b]/80 to-[#09090b]/90"></div>
    <div class="fixed inset-0 z-0 opacity-40 pointer-events-none" style="background: radial-gradient(circle at 50% 25%, rgba(255, 215, 130, 0.15) 0%, rgba(9, 9, 11, 0) 70%);"></div>

    <!-- Top Language & Navigation Bar -->
    <div class="fixed top-0 inset-x-0 z-20 flex justify-between items-center px-6 py-4">
        <a href="index.php" class="inline-flex items-center gap-2 text-xs font-mono uppercase tracking-widest text-zinc-400 hover:text-amber-400 transition-colors">
            <span class="material-symbols-outlined text-sm">arrow_back</span>
            <span><?php echo t("Back to Site", "กลับหน้าเว็บหลัก"); ?></span>
        </a>

        <!-- Language Selector -->
        <a href="<?php echo getLangUrl($lang === 'en' ? 'th' : 'en'); ?>" class="text-amber-400 hover:text-amber-300 font-anton text-xs uppercase tracking-wider text-decoration-none border border-amber-500/30 bg-amber-950/40 px-3 py-1.5 rounded-full backdrop-blur-md transition-all">
            <?php echo $lang === 'en' ? 'TH 🇹🇭' : 'EN 🇺🇸'; ?>
        </a>
    </div>

    <!-- Central Glassmorphism Login Card -->
    <div class="relative z-10 w-full max-w-md glass-login-card rounded-2xl p-6 sm:p-8 flex flex-col gap-6 overflow-hidden my-12">
        
        <!-- Glowing Top Accent Line -->
        <div class="absolute top-0 inset-x-0 h-1 bg-gradient-to-r from-amber-500/20 via-amber-400 to-amber-500/20"></div>

        <!-- Logo & Title Section -->
        <div class="flex flex-col items-center text-center">
            <div class="rounded-xl overflow-hidden border-2 border-amber-500/40 shadow-2xl bg-zinc-950 flex items-center justify-center mb-4 transition-transform hover:scale-105" style="width: 56px; height: 56px; box-shadow: 0 0 25px rgba(255, 215, 130, 0.25);">
                <img src="images/logo/755221157_122278964708129427_8713818424547983601_n.jpg" alt="CHIT logo" class="w-full h-full object-cover">
            </div>
            
            <span class="inline-block text-[10px] font-mono font-bold text-amber-400 uppercase tracking-widest bg-amber-500/10 border border-amber-500/30 px-3 py-1 rounded-full mb-2">
                [ <?php echo t("ADMIN & STAFF CONSOLE", "ระบบจัดการหลังบ้านผู้ดูแลระบบ"); ?> ]
            </span>

            <h1 class="font-anton text-amber-400 text-2xl sm:text-3xl tracking-wide uppercase mb-1">
                CHIT HOLE CNX
            </h1>
            <p class="text-zinc-400 text-xs font-sans tracking-wide">
                <?php echo t("Sign in to access restaurant management console", "เข้าสู่ระบบเพื่อจัดการจองโต๊ะ เบียร์สด และข้อมูลร้าน"); ?>
            </p>
        </div>

        <!-- Error Notification Badge -->
        <?php if ($login_error): ?>
            <div class="bg-rose-950/50 border border-rose-500/50 text-rose-300 p-3.5 rounded-xl text-xs font-mono flex items-center gap-2.5 shadow-xl animate-premium-shake">
                <span class="material-symbols-outlined text-rose-400 text-base shrink-0">error</span>
                <span><?php echo $login_error; ?></span>
            </div>
        <?php endif; ?>

        <!-- Form -->
        <form action="login.php" method="POST" novalidate class="flex flex-col gap-4">
            
            <!-- Email Field -->
            <div class="input-wrapper flex flex-col gap-1.5">
                <label class="text-xs uppercase text-zinc-300 font-medium tracking-wider flex items-center justify-between">
                    <span><?php echo t("Email Address", "อีเมลผู้ใช้"); ?></span>
                    <span class="text-rose-400 text-xs">*</span>
                </label>
                <div class="relative flex items-center">
                    <span class="material-symbols-outlined absolute left-3 text-zinc-500 text-lg pointer-events-none">mail</span>
                    <input type="email" name="email" required oninvalid="this.setCustomValidity('<?php echo t('⚠️ Please enter your registered login email.', '⚠️ กรุณาระบุอีเมลผู้ใช้งานสำหรับเข้าสู่ระบบ'); ?>')" oninput="this.setCustomValidity('')" placeholder="admin@chithole.com" class="w-full bg-zinc-950/80 border border-zinc-800 text-zinc-100 rounded-xl pl-10 pr-4 py-3 text-sm outline-none focus:border-amber-500/70 focus:ring-2 focus:ring-amber-500/20 transition-all font-sans">
                </div>
            </div>

            <!-- Password Field -->
            <div class="input-wrapper flex flex-col gap-1.5">
                <label class="text-xs uppercase text-zinc-300 font-medium tracking-wider flex items-center justify-between">
                    <span><?php echo t("Password", "รหัสผ่าน"); ?></span>
                    <span class="text-rose-400 text-xs">*</span>
                </label>
                <div class="relative flex items-center">
                    <span class="material-symbols-outlined absolute left-3 text-zinc-500 text-lg pointer-events-none">lock</span>
                    <input type="password" id="password-input" name="password" required oninvalid="this.setCustomValidity('<?php echo t('⚠️ Please enter your account password.', '⚠️ กรุณาระบุรหัสผ่านเพื่อเข้าสู่ระบบ'); ?>')" oninput="this.setCustomValidity('')" placeholder="••••••••" class="w-full bg-zinc-950/80 border border-zinc-800 text-zinc-100 rounded-xl pl-10 pr-11 py-3 text-sm outline-none focus:border-amber-500/70 focus:ring-2 focus:ring-amber-500/20 transition-all font-sans">
                    <button type="button" onclick="togglePasswordVisibility()" class="absolute right-3 text-zinc-500 hover:text-zinc-300 transition-colors p-1 flex items-center justify-center">
                        <span id="password-toggle-icon" class="material-symbols-outlined text-lg">visibility</span>
                    </button>
                </div>
            </div>

            <!-- Submit Button (Pearl White Gradient Background with Clear Dark Text) -->
            <button type="submit" class="pearl-white-btn w-full mt-3 py-3.5 px-4 rounded-xl font-anton text-sm uppercase tracking-wider font-bold flex items-center justify-center gap-2 cursor-pointer" style="background: linear-gradient(135deg, #ffffff 0%, #f4efe6 100%) !important; color: #111113 !important; border: 1px solid #ffffff !important; box-shadow: 0 4px 20px rgba(255, 255, 255, 0.4), 0 0 25px rgba(244, 239, 230, 0.3) !important;">
                <span class="material-symbols-outlined text-lg" style="color: #111113 !important;">login</span>
                <span style="color: #111113 !important; font-weight: 700 !important;"><?php echo t("Sign In to Dashboard", "เข้าสู่ระบบหลังบ้าน"); ?></span>
            </button>
        </form>

        <!-- Card Footer -->
        <div class="text-center pt-3 border-t border-zinc-800/80 flex justify-between items-center text-xs font-sans text-zinc-500">
            <span>© <?php echo date('Y'); ?> CHIT HOLE CNX</span>
            <a href="index.php" class="hover:text-amber-400 transition-colors text-decoration-none">
                <?php echo t("Taproom Website", "หน้าหลักร้าน"); ?> &rarr;
            </a>
        </div>
    </div>

</body>
</html>
