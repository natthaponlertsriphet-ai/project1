<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// 1. Language Handler
if (isset($_GET['lang'])) {
    $lang = $_GET['lang'] === 'th' ? 'th' : 'en';
    $_SESSION['lang'] = $lang;
} elseif (isset($_SESSION['lang'])) {
    $lang = $_SESSION['lang'];
} else {
    $lang = 'en'; // default
    $_SESSION['lang'] = $lang;
}

// 2. Translate helper function
if (!function_exists('t')) {
    function t($en, $th) {
        global $lang;
        return $lang === 'th' ? $th : $en;
    }
}

// 3. Helper to build URL preserving other query params but modifying 'lang'
function getLangUrl($target_lang) {
    $params = $_GET;
    $params['lang'] = $target_lang;
    return '?' . http_build_query($params);
}

// 4. Check active page class
$current_page = basename($_SERVER['PHP_SELF']);
function is_active($page) {
    global $current_page;
    return ($current_page === $page) ? 'text-warning border-bottom border-warning border-2' : 'text-light';
}
?>
<!DOCTYPE html>
<html lang="<?php echo $lang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CHIT HOLE CNX - โรงเบียร์ชิตโฮลเชียงใหม่</title>
    <!-- Bootstrap 5 CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Google Fonts & Icons -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Arvo:wght@400;700&family=Pridi:wght@300;400;500;600;700&family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0&display=swap" rel="stylesheet">
    
    <style>
        html {
            margin: 0 !important;
            padding: 0 !important;
            background-color: #131313 !important;
            overscroll-behavior-y: none;
        }
        body {
            margin: 0 !important;
            padding: 0 !important;
            background-color: #131313 !important;
            color: #e5e2e1;
            font-family: 'Rockwell', 'Pridi', 'Arvo', serif;
            overflow-x: hidden;
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
        html[lang="th"] textarea,
        html[lang="th"] .font-anton {
            font-weight: 600 !important;
        }
        .font-anton {
            font-family: 'Rockwell', 'Pridi', 'Arvo', serif;
        }
        /* Custom navbar styles */
        .navbar-custom {
            background-color: rgba(19, 19, 19, 0.75) !important;
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        .btn-custom-gold {
            background-color: #ffd782;
            color: #3f2e00;
            font-family: 'Rockwell', 'Pridi', 'Arvo', serif;
            border: none;
            transition: all 0.3s;
            border-radius: 4px;
            font-size: 14px;
            letter-spacing: 0.05em;
        }
        .btn-custom-gold:hover {
            background-color: #fff6df;
            box-shadow: 0 0 20px rgba(255, 215, 130, 0.4);
            color: #3f2e00;
        }
        .hover-gold:hover {
            color: #ffd782 !important;
            transition: color 0.2s ease-in-out;
        }
        /* Glassmorphism containers */
        .glass-card {
            background: rgba(32, 31, 31, 0.8);
            border: 1px solid rgba(255, 255, 255, 0.05);
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
            input.style.borderColor = '#dc3545';
            input.style.boxShadow = '0 0 0 0.25rem rgba(220, 53, 69, 0.3), 0 0 15px rgba(220, 53, 69, 0.2)';
            
            setTimeout(function() {
                input.classList.remove('animate-premium-shake');
            }, 400);

            var badge = document.createElement('div');
            badge.className = 'premium-field-error-badge mt-2 d-inline-flex align-items-center gap-2 px-3 py-1.5 border border-danger border-opacity-50 rounded-2 shadow-sm font-sans';
            badge.style.fontSize = '12px';
            badge.style.backdropFilter = 'blur(8px)';
            badge.style.backgroundColor = 'rgba(60, 10, 15, 0.85)';
            badge.style.color = '#ff99a8';
            badge.innerHTML = '<span class="material-symbols-outlined fs-6 align-middle text-danger">error</span><span class="fw-medium tracking-wide">' + msg + '</span>';

            if (input.nextSibling) {
                input.parentNode.insertBefore(badge, input.nextSibling);
            } else {
                input.parentNode.appendChild(badge);
            }

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
            var parent = input.parentNode;
            if (parent) {
                var existing = parent.querySelector('.premium-field-error-badge');
                if (existing) existing.remove();
            }
        }
    });
    </script>
</head>
<body>

    <!-- Header Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark navbar-custom fixed-top py-3">
        <div class="container px-4 px-lg-5">
            <!-- Brand Logo -->
            <a href="index.php" class="navbar-brand d-flex align-items-center gap-3">
                <div class="rounded overflow-hidden border border-warning-subtle shadow-sm bg-dark d-flex align-items-center justify-content-center" style="width: 48px; height: 48px;">
                    <img src="images/logo/755221157_122278964708129427_8713818424547983601_n.jpg" alt="CHIT logo" style="width: 100%; height: 100%; object-fit: cover;">
                </div>
                <div class="d-flex flex-column">
                    <span class="font-anton text-warning text-uppercase tracking-wider fs-4 lh-1">CHIT HOLE CNX</span>
                    <span class="text-uppercase text-secondary tracking-widest" style="font-size: 9px; font-weight: bold;"><?php echo t("Chiang Mai Brewing", "โรงเบียร์ชิตโฮลเชียงใหม่"); ?></span>
                </div>
            </a>
            
            <button class="navbar-toggler border-0 shadow-none" type="button" data-bs-toggle="collapse" data-bs-target="#navbarText" aria-controls="navbarText" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarText">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0 mx-auto gap-2 text-uppercase font-anton">
                    <li class="nav-item">
                        <a class="nav-link px-3 <?php echo is_active('reservation.php'); ?>" href="reservation.php"><?php echo t("Booking", "จองโต๊ะ"); ?></a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link px-3 <?php echo is_active('tap-list.php'); ?>" href="tap-list.php"><?php echo t("Beer Menu", "เมนูเบียร์สด"); ?></a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link px-3 <?php echo is_active('promotions.php'); ?>" href="promotions.php"><?php echo t("Promotions", "โปรโมชัน"); ?></a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link px-3 <?php echo is_active('live-music.php'); ?>" href="live-music.php"><?php echo t("Live Music", "ดนตรีสด"); ?></a>
                    </li>
                </ul>
                
                <div class="d-flex align-items-center gap-3">
                    <!-- Language Selection Toggle Link -->
                    <a href="<?php echo getLangUrl($lang === 'en' ? 'th' : 'en'); ?>" class="text-warning text-decoration-none font-anton tracking-wider text-uppercase" style="font-size: 13px;">
                        <?php echo $lang === 'en' ? 'TH 🇹🇭' : 'EN 🇺🇸'; ?>
                    </a>
                    
                    <!-- Admin Login/Console Button -->
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <a href="admin/index.php" class="btn btn-outline-warning btn-sm font-anton text-uppercase px-3"><?php echo t("Console", "แดชบอร์ด"); ?></a>
                    <?php else: ?>
                        <a href="login.php" class="btn btn-outline-secondary btn-sm text-light font-anton text-uppercase px-3"><?php echo t("Admin", "แอดมิน"); ?></a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>
    <div style="height: 80px;"></div>
