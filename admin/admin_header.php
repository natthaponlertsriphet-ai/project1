<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Security Check: Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

// Role-based Access Control: Restrict STAFF users to index.php and tables.php
$current_admin_page = basename($_SERVER['PHP_SELF']);
if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'STAFF') {
    $allowed_staff_pages = ['index.php', 'tables.php'];
    if (!in_array($current_admin_page, $allowed_staff_pages)) {
        header("Location: index.php");
        exit;
    }
}

// Language Handler (Defaults to 'th' for Admin Dashboard)
if (isset($_GET['lang'])) {
    $lang = $_GET['lang'] === 'en' ? 'en' : 'th';
    $_SESSION['lang'] = $lang;
} elseif (isset($_SESSION['lang'])) {
    $lang = $_SESSION['lang'];
} else {
    $lang = 'th'; // Default to Thai
    $_SESSION['lang'] = $lang;
}

if (!function_exists('t')) {
    function t($en, $th) {
        global $lang;
        return $lang === 'th' ? $th : $en;
    }
}

if (!function_exists('getLangUrl')) {
    function getLangUrl($target_lang) {
        $params = $_GET;
        $params['lang'] = $target_lang;
        return '?' . http_build_query($params);
    }
}
function is_admin_active($page) {
    global $current_admin_page;
    return ($current_admin_page === $page) 
        ? 'bg-zinc-800 text-zinc-50 font-medium' 
        : 'text-zinc-400 hover:text-zinc-100 hover:bg-zinc-900/60';
}
?>
<!DOCTYPE html>
<html lang="<?php echo $lang; ?>" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CHIT HOLE CNX - Admin Control Panel</title>
    <!-- Google Fonts & Material Icons (IBM Plex Sans Thai & Anton) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Arvo:wght@400;700&family=Pridi:wght@300;400;500;600;700&family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0&display=swap" rel="stylesheet">
    
    <!-- Tailwind CSS v4 Browser Compiler -->
    <script src="https://unpkg.com/@tailwindcss/browser@4"></script>
    
    <!-- Custom theme variables & Utilities mimicking Shadcn UI -->
    <style type="tailwindcss">
        @theme {
            --font-sans: 'Rockwell', 'Pridi', 'Arvo', serif;
            --font-anton: 'Rockwell', 'Pridi', 'Arvo', serif;
            --color-warning: #ffd700;
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
            padding: 1.5rem;
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
            padding: 0.5rem 1rem;
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

        @utility shadcn-btn-outline {
            background-color: transparent;
            border: 1px solid #27272a; /* zinc-800 */
            color: #fafafa; /* zinc-50 */
            font-weight: 500;
            border-radius: 0.375rem;
            padding: 0.5rem 1rem;
            font-size: 0.875rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: background-color 0.15s ease, border-color 0.15s ease;
            cursor: pointer;
        }

        @utility shadcn-btn-outline:hover {
            background-color: #18181b; /* zinc-900 */
        }

        @utility shadcn-btn-destructive {
            background-color: rgba(239, 68, 68, 0.1); /* red-500/10 */
            border: 1px solid rgba(239, 68, 68, 0.25);
            color: #fca5a5; /* red-300 */
            font-weight: 500;
            border-radius: 0.375rem;
            padding: 0.5rem 1rem;
            font-size: 0.875rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: background-color 0.15s ease, border-color 0.15s ease;
            cursor: pointer;
        }

        @utility shadcn-btn-destructive:hover {
            background-color: rgba(239, 68, 68, 0.2);
        }

        @utility shadcn-btn-success {
            background-color: #22c55e; /* solid green */
            color: #ffffff;
            font-weight: 600;
            border-radius: 0.375rem;
            padding: 0.5rem 1rem;
            font-size: 0.875rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: background-color 0.15s ease;
            cursor: pointer;
            border: 1px solid transparent;
        }

        @utility shadcn-btn-success:hover {
            background-color: #16a34a; /* darker green */
        }

        @utility shadcn-btn-danger {
            background-color: #ef4444; /* solid red */
            color: #ffffff;
            font-weight: 600;
            border-radius: 0.375rem;
            padding: 0.5rem 1rem;
            font-size: 0.875rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: background-color 0.15s ease;
            cursor: pointer;
            border: 1px solid transparent;
        }

        @utility shadcn-btn-danger:hover {
            background-color: #dc2626; /* darker red */
        }

        /* custom tables styling to look like Shadcn UI table */
        .shadcn-table-container {
            width: 100%;
            overflow-x: auto;
            border: 1px solid #27272a;
            border-radius: 0.5rem;
            background-color: #18181b;
        }
        .shadcn-table {
            width: 100%;
            caption-side: bottom;
            font-size: 0.875rem;
            border-collapse: collapse;
        }
        .shadcn-table thead tr {
            border-bottom: 1px solid #27272a;
            background-color: rgba(255, 255, 255, 0.01);
        }
        .shadcn-table th {
            height: 2.75rem;
            padding: 0 1rem;
            text-align: left;
            vertical-align: middle;
            font-weight: 500;
            color: #a1a1aa; /* zinc-400 */
        }
        .shadcn-table tbody tr {
            border-bottom: 1px solid #27272a;
            transition: background-color 0.15s ease;
        }
        .shadcn-table tbody tr:last-child {
            border-bottom: 0;
        }
        .shadcn-table tbody tr:hover {
            background-color: rgba(255, 255, 255, 0.02);
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
        document.addEventListener('invalid', function(e) {
            e.preventDefault();
            var input = e.target;
            if (!input) return;

            var msg = input.validationMessage || '⚠️ กรุณาระบุข้อมูลในช่องนี้';
            
            clearPremiumFieldError(input);

            input.classList.add('animate-premium-shake');
            input.style.borderColor = '#f43f5e';
            input.style.boxShadow = '0 0 0 2px rgba(244, 63, 94, 0.4), 0 0 15px rgba(244, 63, 94, 0.25)';
            
            setTimeout(function() {
                input.classList.remove('animate-premium-shake');
            }, 400);

            var badge = document.createElement('div');
            badge.className = 'premium-field-error-badge mt-1.5 inline-flex items-center gap-1.5 px-3 py-1.5 bg-rose-950/90 border border-rose-500/60 text-rose-200 text-xs font-sans rounded-md shadow-xl backdrop-blur-md';
            badge.innerHTML = '<span class="material-symbols-outlined text-rose-400 text-sm shrink-0">error</span><span class="font-medium tracking-wide">' + msg + '</span>';

            if (input.nextSibling) {
                input.parentNode.insertBefore(badge, input.nextSibling);
            } else {
                input.parentNode.appendChild(badge);
            }

            input.focus();

            var clearHandler = function() {
                clearPremiumFieldError(input);
                input.removeEventListener('input', clearHandler);
                input.removeEventListener('change', clearHandler);
            };
            input.addEventListener('input', clearHandler);
            input.addEventListener('change', clearHandler);
        }, true);

        function clearPremiumFieldError(input) {
            input.style.borderColor = '';
            input.style.boxShadow = '';
            var parent = input.parentNode;
            if (parent) {
                var existing = parent.querySelector('.premium-field-error-badge');
                if (existing) existing.remove();
            }
        }
    });
    </script>
</head>
<body class="h-full bg-zinc-950 text-zinc-50">

    <!-- Sidebar Layout -->
    <div class="w-full md:w-64 md:fixed md:top-0 md:left-0 md:bottom-0 border-b md:border-b-0 md:border-r border-zinc-900 flex flex-col justify-between p-4 bg-zinc-950 z-40">
        <div>
            <!-- Header Brand logo -->
            <div class="px-3 mb-6 pb-4 border-b border-zinc-900 flex items-center gap-3">
                <div class="rounded-lg overflow-hidden border border-warning shadow-md bg-zinc-950 flex items-center justify-content-center" style="width: 38px; height: 38px;">
                    <img src="../images/logo/755221157_122278964708129427_8713818424547983601_n.jpg" alt="CHIT logo" class="w-full h-full object-cover">
                </div>
                <div class="flex flex-col">
                    <span class="font-anton text-warning text-uppercase tracking-wider text-lg leading-none">CHIT HOLE CNX</span>
                    <span class="text-uppercase text-zinc-500 tracking-widest font-mono mt-1" style="font-size: 8px; font-weight: bold;"><?php echo t("ADMIN PANEL", "ผู้จัดการหลังบ้าน"); ?></span>
                </div>
            </div>
            
            <!-- Sidebar Navigation Links -->
            <ul class="flex flex-col gap-1 font-sans text-sm">
                <li>
                    <a class="py-2 px-3 rounded-md flex items-center gap-3 transition-colors <?php echo is_admin_active('index.php'); ?>" href="index.php">
                        <span class="material-symbols-outlined text-lg leading-none"><?php echo (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'STAFF') ? 'assignment' : 'dashboard'; ?></span>
                        <span><?php echo (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'STAFF') ? t("Manage Queue", "จัดการคิวจองโต๊ะ") : t("Dashboard", "หน้าหลักแดชบอร์ด"); ?></span>
                    </a>
                </li>
                <?php if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'ADMIN'): ?>
                <li>
                    <a class="py-2 px-3 rounded-md flex items-center gap-3 transition-colors <?php echo is_admin_active('beers.php'); ?>" href="beers.php">
                        <span class="material-symbols-outlined text-lg leading-none">sports_bar</span>
                        <span><?php echo t("Draft Beers", "จัดการข้อมูลรายการเครื่องดื่ม"); ?></span>
                    </a>
                </li>
                <li>
                    <a class="py-2 px-3 rounded-md flex items-center gap-3 transition-colors <?php echo is_admin_active('music.php'); ?>" href="music.php">
                        <span class="material-symbols-outlined text-lg leading-none">music_note</span>
                        <span><?php echo t("Live Schedule", "จัดการตารางเวลาการแสดงดนตรีสด"); ?></span>
                    </a>
                </li>
                <li>
                    <a class="py-2 px-3 rounded-md flex items-center gap-3 transition-colors <?php echo is_admin_active('promotions.php'); ?>" href="promotions.php">
                        <span class="material-symbols-outlined text-lg leading-none">local_offer</span>
                        <span><?php echo t("Promotions", "จัดการรายการโปรโมชัน"); ?></span>
                    </a>
                </li>
                <li>
                    <a class="py-2 px-3 rounded-md flex items-center gap-3 transition-colors <?php echo is_admin_active('tables.php'); ?>" href="tables.php">
                        <span class="material-symbols-outlined text-lg leading-none">table_restaurant</span>
                        <span><?php echo t("Tables Layout", "จัดการข้อมูลผังที่นั่งและระบบหมายเลขโต๊ะ"); ?></span>
                    </a>
                </li>
                <li>
                    <a class="py-2 px-3 rounded-md flex items-center gap-3 transition-colors <?php echo is_admin_active('staff.php'); ?>" href="staff.php">
                        <span class="material-symbols-outlined text-lg leading-none">badge</span>
                        <span><?php echo t("Staff Creds", "จัดการข้อมูลพนักงาน"); ?></span>
                    </a>
                </li>
                <?php endif; ?>
            </ul>
        </div>
        
        <!-- Sidebar Footer Action Panel -->
        <div class="mt-6 pt-4 border-t border-zinc-900 flex flex-col gap-2 font-sans">
            <div class="text-zinc-500 text-xs font-mono text-center mb-1">
                <?php echo t("Session role", "เข้าใช้งานในฐานะ"); ?>: <span class="text-zinc-300 font-semibold"><?php echo $_SESSION['user_role']; ?></span>
            </div>
            
            <!-- Language Selector Segmented Buttons -->
            <div class="flex gap-1 p-0.5 bg-zinc-950 border border-zinc-900 rounded-md mb-2">
                <a href="<?php echo getLangUrl('th'); ?>" class="flex-1 py-1 text-[10px] font-bold text-center rounded transition-all duration-150 <?php echo $lang === 'th' ? 'bg-zinc-900 text-zinc-50 border border-zinc-800 shadow-sm' : 'text-zinc-500 hover:text-zinc-300'; ?>">ไทย</a>
                <a href="<?php echo getLangUrl('en'); ?>" class="flex-1 py-1 text-[10px] font-bold text-center rounded transition-all duration-150 <?php echo $lang === 'en' ? 'bg-zinc-900 text-zinc-50 border border-zinc-800 shadow-sm' : 'text-zinc-500 hover:text-zinc-300'; ?>">EN</a>
            </div>

            <a href="../index.php" class="shadcn-btn-outline py-2 w-full flex items-center justify-center gap-2 text-xs">
                <span class="material-symbols-outlined text-base leading-none">web</span>
                <span><?php echo t("Customer Site", "ดูหน้าเว็บร้าน"); ?></span>
            </a>
            <a href="../logout.php" class="shadcn-btn-destructive py-2 w-full flex items-center justify-center gap-2 text-xs">
                <span class="material-symbols-outlined text-base leading-none">logout</span>
                <span><?php echo t("Log Out", "ออกจากระบบ"); ?></span>
            </a>
        </div>
    </div>
    
    <!-- Main Content Container -->
    <div class="md:ml-64 p-6 md:p-10 min-h-screen bg-zinc-950 text-zinc-50 font-sans">
