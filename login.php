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
        $login_error = "Please fill in all fields.";
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
            
            $login_error = "Invalid email or password.";
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
    <title>CHIT HOLE CNX - Admin Login</title>
    <link href="https://fonts.googleapis.com/css2?family=Arvo:wght@400;700&family=Pridi:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Tailwind CSS v4 Browser Compiler -->
    <script src="https://unpkg.com/@tailwindcss/browser@4"></script>
    
    <!-- Custom theme variables & Utilities mimicking Shadcn UI -->
    <style type="tailwindcss">
        @theme {
            --font-sans: 'Rockwell', 'Pridi', 'Arvo', serif;
            --font-anton: 'Rockwell', 'Pridi', 'Arvo', serif;
        }

        body {
            background-color: #09090b; /* zinc-950 */
            color: #fafafa; /* zinc-50 */
            font-family: 'Rockwell', 'Pridi', 'Arvo', serif;
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

        @utility shadcn-card {
            background-color: #18181b; /* zinc-900 */
            border: 1px solid #27272a; /* zinc-800 */
            border-radius: 0.5rem;
            padding: 2rem;
        }

        @utility shadcn-input {
            background-color: #09090b; /* zinc-950 */
            border: 1px solid #27272a; /* zinc-800 */
            color: #fafafa; /* zinc-50 */
            border-radius: 0.375rem;
            padding: 0.5rem 0.75rem;
            font-size: 0.875rem;
            outline: none;
            transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
            width: 100%;
        }

        @utility shadcn-input:focus {
            border-color: #71717a; /* zinc-500 */
            box-shadow: 0 0 0 1px #71717a;
        }

        @utility shadcn-btn-primary {
            background-color: #fafafa; /* zinc-50 */
            color: #09090b; /* zinc-950 */
            font-weight: 500;
            border-radius: 0.375rem;
            padding: 0.625rem 1rem;
            font-size: 0.875rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: background-color 0.15s ease;
            cursor: pointer;
            border: 1px solid transparent;
        }

        @utility shadcn-btn-primary:hover {
            background-color: #e4e4e7; /* zinc-200 */
        }
    </style>
</head>
<body class="h-full flex items-center justify-center p-6 bg-zinc-950 text-zinc-50">

    <div class="w-full max-w-sm shadcn-card shadow-xl flex flex-col gap-6 relative overflow-hidden">
        <!-- Logo & Title -->
        <div class="flex flex-col items-center text-center">
            <div class="rounded-lg overflow-hidden border border-zinc-800 shadow-md bg-zinc-950 flex items-center justify-center mb-4" style="width: 48px; height: 48px;">
                <img src="images/logo/755221157_122278964708129427_8713818424547983601_n.jpg" alt="CHIT logo" class="w-full h-full object-cover">
            </div>
            <h2 class="font-anton text-warning text-2xl tracking-wider uppercase mb-1">CHIT HOLE CNX</h2>
            <p class="text-zinc-400 text-sm"><?php echo t("Admin Portal Sign In", "เข้าสู่ระบบจัดการหลังบ้าน"); ?></p>
        </div>

        <!-- Error Notification -->
        <?php if ($login_error): ?>
            <div class="bg-red-500/10 border border-red-500/30 text-red-300 p-3 rounded-md text-xs font-mono text-center">
                [ERROR]: <?php echo $login_error; ?>
            </div>
        <?php endif; ?>

        <!-- Form -->
        <form action="login.php" method="POST" class="flex flex-col gap-4">
            <div class="flex flex-col gap-1.5">
                <label class="text-xs uppercase text-zinc-400 font-medium tracking-wider"><?php echo t("Email Address", "อีเมลผู้ใช้"); ?></label>
                <input type="email" name="email" required oninvalid="this.setCustomValidity('<?php echo t('Please enter your email address.', 'กรุณากรอกอีเมลผู้ใช้'); ?>')" oninput="this.setCustomValidity('')" placeholder="admin@chithole.com" class="shadcn-input">
            </div>

            <div class="flex flex-col gap-1.5">
                <label class="text-xs uppercase text-zinc-400 font-medium tracking-wider"><?php echo t("Password", "รหัสผ่าน"); ?></label>
                <input type="password" name="password" required oninvalid="this.setCustomValidity('<?php echo t('Please enter your password.', 'กรุณากรอกรหัสผ่าน'); ?>')" oninput="this.setCustomValidity('')" placeholder="••••••••" class="shadcn-input">
            </div>

            <button type="submit" class="shadcn-btn-primary w-full mt-2 text-uppercase font-bold tracking-wider">
                <?php echo t("Login", "เข้าสู่ระบบ"); ?>
            </button>
        </form>

        <!-- Back to site link -->
        <div class="text-center pt-2 border-t border-zinc-900">
            <a href="index.php" class="text-xs text-zinc-500 hover:text-zinc-300 transition-colors">
                &larr; <?php echo t("Back to Customer Website", "กลับไปหน้าเว็บหลักของร้าน"); ?>
            </a>
        </div>
    </div>

</body>
</html>
