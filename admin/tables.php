<?php
require_once '../db.php';

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Security Check: Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

// Temporary Translation Helper (defined locally before admin_header.php load)
if (!function_exists('t')) {
    function t($en, $th) {
        $lang = $_SESSION['lang'] ?? 'en';
        return $lang === 'th' ? $th : $en;
    }
}

$success = $_SESSION['action_success'] ?? null;
$error = $_SESSION['action_error'] ?? null;
unset($_SESSION['action_success'], $_SESSION['action_error']);

// Form inputs state variables
$is_editing = false;
$edit_id = '';
$number = '';
$zone = 'INDOOR';
$capacity = 4;
$status = 'AVAILABLE';
$image = '';

// Role-based Action Restrictions: STAFF can only toggle status
if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'STAFF') {
    if (isset($_GET['action']) && in_array($_GET['action'], ['delete', 'edit'])) {
        header("Location: tables.php");
        exit;
    }
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        header("Location: tables.php");
        exit;
    }
}

// Handle Quick Toggle status (AVAILABLE / OCCUPIED)
if (isset($_GET['action']) && $_GET['action'] === 'toggle_status' && isset($_GET['id'])) {
    $toggle_id = $_GET['id'];
    try {
        $stmt = $pdo->prepare("SELECT table_status AS status FROM `table` WHERE table_id = ?");
        $stmt->execute([$toggle_id]);
        $current_status = $stmt->fetchColumn();
        
        $new_status = ($current_status === 'AVAILABLE') ? 'OCCUPIED' : 'AVAILABLE';
        
        $stmt = $pdo->prepare("UPDATE `table` SET table_status = ? WHERE table_id = ?");
        $stmt->execute([$new_status, $toggle_id]);
        
        // Pass message via session to persist across redirect
        $_SESSION['action_success'] = t("Table status toggled successfully.", "สลับสถานะโต๊ะเรียบร้อยแล้ว.");
    } catch (Exception $e) {
        $_SESSION['action_error'] = "Error: " . $e->getMessage();
    }
    header("Location: tables.php");
    exit;
}

// Handle DELETE table request
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $del_id = $_GET['id'];
    try {
        $stmt = $pdo->prepare("DELETE FROM `table` WHERE table_id = ?");
        $stmt->execute([$del_id]);
        $_SESSION['action_success'] = t("Table deleted successfully.", "ลบโต๊ะเรียบร้อยแล้ว.");
    } catch (Exception $e) {
        $_SESSION['action_error'] = "Error: " . $e->getMessage();
    }
    header("Location: tables.php");
    exit;
}

// Handle GET table loader for edit
if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])) {
    $edit_id = $_GET['id'];
    $stmt = $pdo->prepare("SELECT table_id AS id, table_number AS number, zone, capacity, table_status AS status, image FROM `table` WHERE table_id = ?");
    $stmt->execute([$edit_id]);
    $table = $stmt->fetch();
    
    if ($table) {
        $is_editing = true;
        $number = $table['number'];
        $zone = $table['zone'];
        $capacity = (int)$table['capacity'];
        $status = $table['status'];
        $image = $table['image'] ?? '';
    }
}

// Handle POST submissions (Create/Update)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $number = trim($_POST['number'] ?? '');
    $zone = trim($_POST['zone'] ?? 'INDOOR');
    $capacity = (int)($_POST['capacity'] ?? 0);
    $status = trim($_POST['status'] ?? 'AVAILABLE');
    $image_url = trim($_POST['image_url'] ?? '');
    
    // Check if remove image is checked
    if (isset($_POST['remove_image']) && $_POST['remove_image'] === '1') {
        $image_url = '';
    }

    if (!$number || $capacity <= 0) {
        $error = "Please fill in all required fields.";
    } else {
        // Handle file upload if provided
        $image_path = $image_url;
        if (isset($_FILES['image_file']) && $_FILES['image_file']['name'] !== '') {
            $upload_err = $_FILES['image_file']['error'];
            if ($upload_err === UPLOAD_ERR_OK) {
                $file_tmp = $_FILES['image_file']['tmp_name'];
                $file_name = $_FILES['image_file']['name'];
                $file_type = $_FILES['image_file']['type'];
                
                $clean_name = preg_replace("/[^a-zA-Z0-9.-]/", "_", $file_name);
                $ext = strtolower(pathinfo($clean_name, PATHINFO_EXTENSION));
                
                if (strpos($file_type, 'image/') === 0 || in_array($ext, ['heic', 'heif'])) {
                    $upload_dir = __DIR__ . '/../images/tables/';
                    if (!is_dir($upload_dir)) {
                        mkdir($upload_dir, 0777, true);
                    }
                    
                    $new_name = 'uploaded_' . time() . '_' . $clean_name;
                    
                    if (move_uploaded_file($file_tmp, $upload_dir . $new_name)) {
                        $image_path = 'images/tables/' . $new_name;
                        
                        // Auto-convert HEIC/HEIF to JPEG on macOS using native sips tool
                        if (in_array($ext, ['heic', 'heif'])) {
                            $jpg_name = pathinfo($new_name, PATHINFO_FILENAME) . '.jpg';
                            $full_heic_path = $upload_dir . $new_name;
                            $full_jpg_path = $upload_dir . $jpg_name;
                            
                            $cmd = "sips -s format jpeg " . escapeshellarg($full_heic_path) . " --out " . escapeshellarg($full_jpg_path) . " 2>&1";
                            exec($cmd, $output, $return_var);
                            
                            if ($return_var === 0 && file_exists($full_jpg_path)) {
                                unlink($full_heic_path);
                                $image_path = 'images/tables/' . $jpg_name;
                            }
                        }
                    } else {
                        $error = t(
                            "Failed to move uploaded file. Check folder write permissions.",
                            "ไม่สามารถบันทึกไฟล์ภาพได้ กรุณาตรวจสอบสิทธิ์การเขียนโฟลเดอร์"
                        );
                    }
                } else {
                    $error = t(
                        "Invalid file type. Please upload an image (PNG, JPG, JPEG, HEIC).",
                        "ประเภทไฟล์ไม่ถูกต้อง กรุณาอัปโหลดไฟล์รูปภาพเท่านั้น (รองรับ PNG, JPG, JPEG, HEIC)"
                    );
                }
            }
        }

        if (!$error) {
            if ($_POST['action'] === 'create_table') {
                try {
                    // Check duplicate table number
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM `table` WHERE table_number = ?");
                    $stmt->execute([$number]);
                    if ($stmt->fetchColumn() > 0) {
                        $error = "This Table Number already exists.";
                    } else {
                        $id = 'tbl_' . uniqid();
                        $stmt = $pdo->prepare("INSERT INTO `table` (table_id, table_number, zone, capacity, table_status, image) VALUES (?, ?, ?, ?, ?, ?)");
                        $stmt->execute([$id, $number, $zone, $capacity, $status, $image_path]);
                        
                        // Reset
                        $number = '';
                        $zone = 'INDOOR';
                        $capacity = 4;
                        $status = 'AVAILABLE';
                        $image = '';
                        
                        $_SESSION['action_success'] = t("Table registered successfully!", "เพิ่มโต๊ะบริการใหม่เรียบร้อยแล้ว!");
                    }
                } catch (Exception $e) {
                    $error = "Error: " . $e->getMessage();
                }
            } elseif ($_POST['action'] === 'update_table') {
                $id = $_POST['edit_id'];
                try {
                    // Check duplicate table number (excluding itself)
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM `table` WHERE table_number = ? AND table_id != ?");
                    $stmt->execute([$number, $id]);
                    if ($stmt->fetchColumn() > 0) {
                        $error = "This Table Number is already in use.";
                    } else {
                        $stmt = $pdo->prepare("UPDATE `table` SET table_number = ?, zone = ?, capacity = ?, table_status = ?, image = ? WHERE table_id = ?");
                        $stmt->execute([$number, $zone, $capacity, $status, $image_path, $id]);
                        
                        // Reset
                        $is_editing = false;
                        $number = '';
                        $zone = 'INDOOR';
                        $capacity = 4;
                        $status = 'AVAILABLE';
                        $image = '';
                        
                        $_SESSION['action_success'] = t("Table configuration updated!", "แก้ไขข้อมูลโต๊ะสำเร็จ!");
                    }
                } catch (Exception $e) {
                    $error = "Error: " . $e->getMessage();
                }
            }
        }
        
        // Redirect after POST to avoid double submissions
        if (!$error) {
            header("Location: tables.php");
            exit;
        }
    }
}

// Fetch all tables
$stmt = $pdo->query("SELECT table_id AS id, table_number AS number, zone, capacity, table_status AS status, image FROM `table` ORDER BY zone, table_number");
$all_tables = $stmt->fetchAll();

require_once 'admin_header.php';
?>

<div class="flex justify-between items-center border-b border-zinc-900 pb-4 mb-6">
    <div>
        <h1 class="font-anton text-warning text-uppercase tracking-wider text-2xl m-0"><?php echo t("Table Layout Manager", "จัดการข้อมูลผังที่นั่งและระบบหมายเลขโต๊ะ"); ?></h1>
        <p class="text-zinc-500 text-xs mt-1 uppercase tracking-widest font-mono"><?php echo t("Admin Dashboard / Seat Map Control", "แผงควบคุมผู้ดูแลระบบ / จัดการโต๊ะและแผนผังที่นั่ง"); ?></p>
    </div>
</div>

<?php if ($success): ?>
    <div class="bg-zinc-900 border border-zinc-800 text-zinc-300 p-3 rounded-md text-xs font-mono mb-6 uppercase">
        [SUCCESS]: <?php echo $success; ?>
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="bg-zinc-900 border border-zinc-800 text-red-300 p-3 rounded-md text-xs font-mono mb-6 uppercase">
        [ERROR]: <?php echo $error; ?>
    </div>
<?php endif; ?>

<?php 
$show_form = isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'ADMIN';
?>
<div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
    
    <?php if ($show_form): ?>
    <!-- Left Column: Add/Edit Form (Only visible to ADMIN) -->
    <div class="lg:col-span-4">
        <div class="shadcn-card">
            <h3 class="font-anton text-warning text-uppercase tracking-wider mb-6 flex items-center gap-2 text-lg">
                <span class="material-symbols-outlined text-xl leading-none">table_restaurant</span>
                <span><?php echo $is_editing ? t("Edit Table Details", "แก้ไขข้อมูลโต๊ะ") : t("Register Table", "เพิ่มโต๊ะใหม่"); ?></span>
            </h3>
            
            <form action="tables.php" method="POST" enctype="multipart/form-data" class="flex flex-col gap-4">
                <input type="hidden" name="action" value="<?php echo $is_editing ? 'update_table' : 'create_table'; ?>">
                <input type="hidden" name="image_url" value="<?php echo htmlspecialchars($image); ?>">
                <?php if ($is_editing): ?>
                    <input type="hidden" name="edit_id" value="<?php echo $edit_id; ?>">
                <?php endif; ?>

                <div class="flex flex-col gap-1.5">
                    <label class="text-xs uppercase text-zinc-400 font-medium tracking-wider"><?php echo t("Table Number", "หมายเลขโต๊ะ"); ?></label>
                    <input type="text" name="number" required placeholder="e.g. T1" class="shadcn-input disabled:opacity-50 disabled:cursor-not-allowed" value="<?php echo htmlspecialchars($number); ?>" <?php echo (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'STAFF') ? 'disabled' : ''; ?>>
                </div>

                <div class="flex flex-col gap-1.5">
                    <label class="text-xs uppercase text-zinc-400 font-medium tracking-wider"><?php echo t("Zone / Area", "โซนที่ตั้งโต๊ะ"); ?></label>
                    <select name="zone" required class="shadcn-input bg-zinc-950 disabled:opacity-50 disabled:cursor-not-allowed" <?php echo (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'STAFF') ? 'disabled' : ''; ?>>
                        <option value="INDOOR_CENTER" <?php echo $zone === 'INDOOR_CENTER' ? 'selected' : ''; ?>><?php echo t("INDOOR CENTER (ตรงกลางห้องแอร์)", "INDOOR CENTER (ตรงกลางห้องแอร์)"); ?></option>
                        <option value="INDOOR_WINDOW" <?php echo $zone === 'INDOOR_WINDOW' ? 'selected' : ''; ?>><?php echo t("INDOOR WINDOW (ติดกระจก)", "INDOOR WINDOW (ติดกระจก)"); ?></option>
                        <option value="INDOOR" <?php echo $zone === 'INDOOR' ? 'selected' : ''; ?>><?php echo t("INDOOR GENERAL (ห้องแอร์ทั่วไป)", "INDOOR GENERAL (ห้องแอร์ทั่วไป)"); ?></option>
                        <option value="OUTDOOR" <?php echo $zone === 'OUTDOOR' ? 'selected' : ''; ?>><?php echo t("OUTDOOR (ด้านนอก)", "OUTDOOR (ด้านนอก)"); ?></option>
                        <option value="STAGE" <?php echo $zone === 'STAGE' ? 'selected' : ''; ?>><?php echo t("STAGE (หน้าเวที)", "STAGE (หน้าเวที)"); ?></option>
                        <option value="BAR" <?php echo $zone === 'BAR' ? 'selected' : ''; ?>><?php echo t("BAR (หน้าบาร์)", "BAR (หน้าบาร์)"); ?></option>
                        <option value="WALKWAY" <?php echo $zone === 'WALKWAY' ? 'selected' : ''; ?>><?php echo t("WALKWAY (โซนทางเดิน)", "WALKWAY (โซนทางเดิน)"); ?></option>
                    </select>
                </div>

                <div class="flex flex-col gap-1.5">
                    <label class="text-xs uppercase text-zinc-400 font-medium tracking-wider"><?php echo t("Capacity (Seats)", "ความจุที่นั่ง (ท่าน)"); ?></label>
                    <input type="number" name="capacity" required min="1" max="50" class="shadcn-input disabled:opacity-50 disabled:cursor-not-allowed" value="<?php echo $capacity; ?>" <?php echo (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'STAFF') ? 'disabled' : ''; ?>>
                </div>

                <div class="flex flex-col gap-1.5">
                    <label class="text-xs uppercase text-zinc-400 font-medium tracking-wider"><?php echo t("Table Image", "รูปภาพโต๊ะ"); ?></label>
                    <?php if ($image): ?>
                        <div class="mb-2 relative w-32 aspect-video rounded overflow-hidden border border-zinc-800 bg-zinc-950">
                            <img src="../<?php echo htmlspecialchars($image); ?>" alt="Preview" class="w-full h-full object-cover">
                        </div>
                        <div class="flex items-center gap-2 mb-2">
                            <input type="checkbox" name="remove_image" id="remove-image" value="1" class="w-4 h-4 accent-warning cursor-pointer">
                            <label for="remove-image" class="text-xs uppercase text-red-400 font-medium cursor-pointer select-none"><?php echo t("Remove Image", "ลบรูปภาพออก"); ?></label>
                        </div>
                    <?php endif; ?>
                    <input type="file" name="image_file" accept="image/*" class="shadcn-input disabled:opacity-50 disabled:cursor-not-allowed" <?php echo (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'STAFF') ? 'disabled' : ''; ?>>
                </div>

                <div class="flex flex-col gap-1.5">
                    <label class="text-xs uppercase text-zinc-400 font-medium tracking-wider"><?php echo t("Initial Status", "สถานะการจองโต๊ะ"); ?></label>
                    <select name="status" required class="shadcn-input bg-zinc-950 disabled:opacity-50 disabled:cursor-not-allowed" <?php echo (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'STAFF') ? 'disabled' : ''; ?>>
                        <option value="AVAILABLE" <?php echo $status === 'AVAILABLE' ? 'selected' : ''; ?>><?php echo t("AVAILABLE (ว่าง)", "AVAILABLE (ว่าง)"); ?></option>
                        <option value="OCCUPIED" <?php echo $status === 'OCCUPIED' ? 'selected' : ''; ?>><?php echo t("OCCUPIED (ไม่ว่าง)", "OCCUPIED (ไม่ว่าง)"); ?></option>
                    </select>
                </div>

                <div class="flex gap-2 mt-2">
                    <?php if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'STAFF'): ?>
                        <div class="bg-amber-500/10 border border-amber-500/25 text-amber-400 p-3 rounded-md text-xs font-mono text-center w-full uppercase">
                            [<?php echo t("ADMIN Privilege Required", "เฉพาะผู้ดูแลระบบ"); ?>]
                        </div>
                    <?php else: ?>
                        <button type="submit" class="shadcn-btn-primary flex-grow">
                            <?php echo $is_editing ? t("Update Table", "อัปเดตข้อมูลโต๊ะ") : t("Register Table", "บันทึกโต๊ะใหม่"); ?>
                        </button>
                        <?php if ($is_editing): ?>
                            <a href="tables.php" class="shadcn-btn-outline"><?php echo t("Cancel", "ยกเลิก"); ?></a>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <!-- Right Column: Inventory Table & Visual Map -->
    <div class="<?php echo $show_form ? 'lg:col-span-8' : 'lg:col-span-12'; ?> flex flex-col gap-6">
        
        <!-- Visual Seat Map Control Card -->
        <div class="shadcn-card border border-warning/10 shadow-lg">
            <h3 class="font-anton text-warning text-uppercase tracking-wider mb-2 flex items-center gap-2 text-lg">
                <span class="material-symbols-outlined text-xl leading-none">grid_view</span>
                <span><?php echo t("Visual Seat Map (Click to Toggle)", "ผังที่นั่งร้านแบบโต้ตอบ (คลิกที่โต๊ะเพื่อเปิด/ปิดให้บริการ)"); ?></span>
            </h3>
            <p class="text-zinc-400 text-xs mb-6">
                <?php echo t("Green tables are Available. Red tables are Occupied. Click on any table to instantly toggle its status.", "สีเขียวหมายถึงโต๊ะว่าง สีแดงหมายถึงโต๊ะไม่ว่าง/ปิดบริการ คลิกที่โต๊ะใดก็ได้เพื่อสลับสถานะทันที"); ?>
            </p>
            
            <!-- Interactive Zone Tabs -->
            <div class="flex gap-1 p-1 bg-zinc-950 border border-zinc-900 rounded-lg mb-6 max-w-md" id="zone-filter-container">
                <button type="button" class="flex-1 py-1.5 text-xs font-medium rounded-md text-center transition-all duration-150 cursor-pointer text-zinc-400 hover:text-zinc-200 hover:bg-zinc-900/50" onclick="filterMapZone('ALL', this)"><?php echo t("All Zones", "ทุกโซน"); ?></button>
                <button type="button" class="flex-1 py-1.5 text-xs font-medium rounded-md text-center transition-all duration-150 cursor-pointer text-zinc-400 hover:text-zinc-200 hover:bg-zinc-900/50" onclick="filterMapZone('INDOOR', this)"><?php echo t("Indoor AC", "ห้องแอร์"); ?></button>
                <button type="button" class="flex-1 py-1.5 text-xs font-medium rounded-md text-center transition-all duration-150 cursor-pointer text-zinc-400 hover:text-zinc-200 hover:bg-zinc-900/50" onclick="filterMapZone('OUTDOOR', this)"><?php echo t("Outdoor Breeze", "ด้านนอก"); ?></button>
                <button type="button" class="flex-1 py-1.5 text-xs font-medium rounded-md text-center transition-all duration-150 cursor-pointer text-zinc-400 hover:text-zinc-200 hover:bg-zinc-900/50" onclick="filterMapZone('STAGE', this)"><?php echo t("Stage Front", "หน้าเวที"); ?></button>
            </div>

            <!-- Stage Orientation Visual indicator -->
            <div id="stage-visual-indicator" class="w-full bg-gradient-to-r from-zinc-900 via-zinc-900/50 to-zinc-900 border border-zinc-800/80 rounded-lg py-2.5 text-center mb-6 shadow-sm">
                <span class="font-anton text-zinc-500 text-xs tracking-widest uppercase flex items-center justify-center gap-1.5">
                    <span class="material-symbols-outlined text-sm text-warning animate-pulse">music_note</span>
                    <?php echo t("LIVE BAND STAGE / เวทีการแสดงดนตรีสด", "LIVE BAND STAGE / เวทีการแสดงดนตรีสด"); ?>
                </span>
            </div>

            <div class="grid grid-cols-3 sm:grid-cols-4 md:grid-cols-5 xl:grid-cols-6 gap-3.5 p-5 bg-zinc-950 border border-zinc-900 rounded-lg">
                <?php foreach ($all_tables as $t): ?>
                    <a href="tables.php?action=toggle_status&id=<?php echo $t['id']; ?>" class="admin-map-btn block no-underline transition-transform duration-200 hover:-translate-y-0.5" data-zone="<?php echo $t['zone']; ?>" title="<?php echo t('Click to toggle status', 'คลิกเพื่อสลับสถานะโต๊ะ'); ?>">
                        <div class="flex flex-col items-center justify-center border rounded-lg p-3 w-full aspect-square transition-all duration-200 hover:shadow-lg cursor-pointer" 
                             style="<?php echo $t['status'] === 'AVAILABLE' ? 'background-color: rgba(34, 197, 94, 0.05); border-color: rgba(34, 197, 94, 0.2); color: #4ade80;' : 'background-color: rgba(239, 68, 68, 0.05); border-color: rgba(239, 68, 68, 0.2); color: #f87171;'; ?>">
                            <span class="font-anton text-xl leading-none"><?php echo htmlspecialchars($t['number']); ?></span>
                            <span class="text-[10px] font-mono text-zinc-500 mt-1"><?php echo $t['capacity']; ?> Pax</span>
                            <span class="w-1.5 h-1.5 rounded-full mt-2 animate-pulse" style="<?php echo $t['status'] === 'AVAILABLE' ? 'background-color: #22c55e; box-shadow: 0 0 8px #22c55e;' : 'background-color: #ef4444; box-shadow: 0 0 8px #ef4444;'; ?>"></span>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="shadcn-card shadow-lg">
            <h3 class="font-anton text-warning text-uppercase tracking-wider mb-6 flex items-center gap-2 text-lg">
                <span class="material-symbols-outlined text-xl leading-none">chair</span>
                <span><?php echo t("Tables Inventory", "รายการโต๊ะในร้านทั้งหมด"); ?> (<?php echo count($all_tables); ?>)</span>
            </h3>
            
            <div class="shadcn-table-container">
                <table class="shadcn-table">
                    <thead>
                        <tr>
                            <th class="font-sans text-xs uppercase tracking-wider text-zinc-400" style="width: 12%;"><?php echo t("Image", "รูปภาพ"); ?></th>
                            <th class="font-sans text-xs uppercase tracking-wider text-zinc-400"><?php echo t("Number", "หมายเลขโต๊ะ"); ?></th>
                            <th class="font-sans text-xs uppercase tracking-wider text-zinc-400"><?php echo t("Zone", "โซนที่ตั้ง"); ?></th>
                            <th class="font-sans text-xs uppercase tracking-wider text-zinc-400 text-center"><?php echo t("Seats", "ความจุที่นั่ง"); ?></th>
                            <th class="font-sans text-xs uppercase tracking-wider text-zinc-400 text-center"><?php echo t("Status (Click to Toggle)", "สถานะโต๊ะ (คลิกสลับสถานะ)"); ?></th>
                            <?php if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'ADMIN'): ?>
                            <th class="font-sans text-xs uppercase tracking-wider text-zinc-400 text-center" style="width: 15%;"><?php echo t("Actions", "จัดการ"); ?></th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody class="font-sans text-sm text-zinc-300">
                        <?php if (empty($all_tables)): ?>
                            <tr>
                                <td colspan="<?php echo (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'ADMIN') ? '5' : '4'; ?>" class="text-center py-8 text-zinc-500">
                                    <?php echo t("No tables registered.", "ยังไม่มีการเพิ่มข้อมูลโต๊ะ"); ?>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($all_tables as $t): ?>
                                <tr>
                                    <td class="py-2.5">
                                        <div class="w-14 h-10 rounded overflow-hidden border border-zinc-800 bg-zinc-950 flex items-center justify-center">
                                            <?php if ($t['image']): ?>
                                                <img src="../<?php echo htmlspecialchars($t['image']); ?>" alt="Table" class="w-full h-full object-cover">
                                            <?php else: ?>
                                                <?php 
                                                $formattedNum = strtolower($t['number']);
                                                $defaultPath = "images/tables/table_{$formattedNum}.jpg";
                                                if (file_exists(__DIR__ . '/../' . $defaultPath)): 
                                                ?>
                                                    <img src="../<?php echo $defaultPath; ?>" alt="Table" class="w-full h-full object-cover">
                                                <?php else: ?>
                                                    <span class="material-symbols-outlined text-zinc-600 text-lg">image</span>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td class="font-anton text-warning text-lg"><?php echo htmlspecialchars($t['number']); ?></td>
                                    <td class="font-semibold text-zinc-100">
                                        <?php 
                                        if ($t['zone'] === 'INDOOR') echo t("Indoor AC Room", "ห้องแอร์ด้านใน");
                                        elseif ($t['zone'] === 'OUTDOOR') echo t("Outdoor Breeze", "ลานระเบียงด้านนอก");
                                        else echo t("Stage Front", "หน้าเวทีการแสดง");
                                        ?>
                                    </td>
                                    <td class="text-center text-zinc-400"><?php echo $t['capacity']; ?> Guests</td>
                                    <td class="text-center">
                                        <a href="tables.php?action=toggle_status&id=<?php echo $t['id']; ?>" class="inline-block" title="<?php echo t('Click to toggle status', 'คลิกเพื่อสลับสถานะโต๊ะ'); ?>">
                                            <span class="badge py-1.5 px-3 rounded text-xs transition-all duration-150 hover:scale-105 hover:brightness-110 cursor-pointer" style="
                                                <?php echo $t['status'] === 'AVAILABLE' ? 'background-color: rgba(34, 197, 94, 0.1); border: 1px solid rgba(34, 197, 94, 0.25); color: #4ade80;' : 'background-color: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.25); color: #f87171;'; ?>
                                            ">
                                                <?php echo $t['status'] === 'AVAILABLE' ? t("AVAILABLE", "โต๊ะว่าง") : t("OCCUPIED", "ไม่ว่าง"); ?>
                                            </span>
                                        </a>
                                    </td>
                                    <?php if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'ADMIN'): ?>
                                    <td class="text-center">
                                        <div class="flex justify-center gap-1">
                                            <a href="tables.php?action=edit&id=<?php echo $t['id']; ?>" class="p-1 text-zinc-400 hover:text-warning transition-colors" title="Edit"><span class="material-symbols-outlined text-lg leading-none">edit</span></a>
                                            <a href="javascript:void(0)" onclick="confirmDeleteTable('<?php echo $t['id']; ?>', '<?php echo htmlspecialchars($t['number']); ?>', '<?php echo htmlspecialchars($t['zone']); ?>')" class="p-1 text-zinc-400 hover:text-red-400 transition-colors" title="<?php echo t('Delete Table', 'ลบโต๊ะ'); ?>"><span class="material-symbols-outlined text-lg leading-none">delete</span></a>
                                        </div>
                                    </td>
                                    <?php endif; ?>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>

<script>
    // Live Map Zone Filter
    function filterMapZone(zone, button) {
        // Toggle active button style
        const buttons = button.parentNode.querySelectorAll('button');
        buttons.forEach(btn => {
            btn.classList.remove('bg-zinc-900', 'text-zinc-50', 'border', 'border-zinc-800', 'shadow-sm');
            btn.classList.add('text-zinc-400', 'hover:text-zinc-200', 'hover:bg-zinc-900/50');
        });
        
        button.classList.add('bg-zinc-900', 'text-zinc-50', 'border', 'border-zinc-800', 'shadow-sm');
        button.classList.remove('text-zinc-400', 'hover:text-zinc-200', 'hover:bg-zinc-900/50');

        // Show/hide stage orientation indicator depending on zone selected
        const stageIndicator = document.getElementById('stage-visual-indicator');
        if (stageIndicator) {
            if (zone === 'OUTDOOR') {
                stageIndicator.style.display = 'none';
            } else {
                stageIndicator.style.display = 'block';
            }
        }

        // Filter map buttons
        const mapBtns = document.querySelectorAll('.admin-map-btn');
        mapBtns.forEach(btn => {
            const btnZone = btn.getAttribute('data-zone');
            let match = false;
            if (zone === 'ALL') {
                match = true;
            } else if (zone === 'INDOOR') {
                match = btnZone.startsWith('INDOOR') || btnZone === 'BAR' || btnZone === 'WALKWAY';
            } else {
                match = btnZone === zone;
            }

            if (match) {
                btn.style.display = 'block';
            } else {
                btn.style.display = 'none';
            }
        });
    }

    // Set initial active state style for "All Zones"
    document.addEventListener('DOMContentLoaded', () => {
        const allBtn = document.querySelector('#zone-filter-container button');
        if (allBtn) {
            allBtn.classList.add('bg-zinc-900', 'text-zinc-50', 'border', 'border-zinc-800', 'shadow-sm');
        }
    });

    function confirmDeleteTable(tableId, tableNum, tableZone) {
        document.getElementById('delete-table-num-display').innerText = '<?php echo t("Table ", "โต๊ะหมายเลข "); ?>' + tableNum;
        document.getElementById('delete-table-zone-display').innerText = tableZone;
        document.getElementById('confirm-delete-table-btn').href = 'tables.php?action=delete&id=' + encodeURIComponent(tableId);
        document.getElementById('deleteTableModal').classList.remove('hidden');
    }

    function closeDeleteTableModal() {
        document.getElementById('deleteTableModal').classList.add('hidden');
    }
</script>

<!-- Custom Delete Table Modal Dialog -->
<div id="deleteTableModal" class="fixed inset-0 z-50 hidden flex items-center justify-center bg-black/80 backdrop-blur-sm p-4">
    <div class="bg-zinc-950 border border-zinc-800 rounded-2xl shadow-2xl w-full max-w-md overflow-hidden transform transition-all">
        <!-- Modal Header -->
        <div class="bg-zinc-900/90 px-5 py-4 border-b border-zinc-800 flex items-center justify-between">
            <div class="flex items-center gap-2.5">
                <div class="w-9 h-9 rounded-xl bg-red-950/80 border border-red-900 flex items-center justify-center text-red-500">
                    <span class="material-symbols-outlined text-xl">table_restaurant</span>
                </div>
                <div>
                    <h3 class="font-anton text-warning tracking-wider text-lg uppercase m-0 leading-none">
                        <?php echo t("Confirm Table Deletion", "ยืนยันการลบข้อมูลผังที่นั่ง"); ?>
                    </h3>
                    <span class="text-zinc-400 text-xs font-mono block mt-1">
                        <?php echo t("Remove table from system floorplan", "ลบหมายเลขโต๊ะออกจากผังร้าน"); ?>
                    </span>
                </div>
            </div>
            <button onclick="closeDeleteTableModal()" type="button" class="text-zinc-400 hover:text-white transition-colors">
                <span class="material-symbols-outlined text-xl">close</span>
            </button>
        </div>

        <!-- Modal Body -->
        <div class="p-5">
            <p class="text-zinc-300 text-sm mb-4 font-sans leading-relaxed">
                <?php echo t("Are you sure you want to delete this table from the floorplan?", "คุณแน่ใจหรือไม่ว่าต้องการลบข้อมูลโต๊ะนี้ออกจากผังที่นั่งของร้าน?"); ?>
            </p>

            <!-- Table Info Badge -->
            <div class="bg-zinc-900/90 border border-zinc-800 rounded-xl p-3.5 mb-4 font-mono text-xs space-y-1.5">
                <div class="flex justify-between items-center border-b border-zinc-800/80 pb-2">
                    <span class="text-zinc-400"><?php echo t("Table Number:", "หมายเลขโต๊ะ:"); ?></span>
                    <span id="delete-table-num-display" class="font-anton text-warning text-base font-bold"></span>
                </div>
                <div class="flex justify-between items-center pt-1">
                    <span class="text-zinc-400"><?php echo t("Zone Location:", "โซนที่ตั้ง:"); ?></span>
                    <span id="delete-table-zone-display" class="text-zinc-200 font-semibold"></span>
                </div>
            </div>

            <!-- Caution Alert -->
            <div class="bg-red-950/40 border border-red-900/60 text-red-300 p-3 rounded-lg text-xs font-mono flex items-start gap-2">
                <span class="material-symbols-outlined text-sm leading-none mt-0.5 shrink-0 text-red-400">warning</span>
                <span><?php echo t("Action cannot be undone. Any associated historical table data will be updated.", "การดำเนินการนี้จะไม่สามารถย้อนกลับได้ โปรดตรวจสอบความถูกต้องก่อนยืนยัน"); ?></span>
            </div>
        </div>

        <!-- Modal Footer -->
        <div class="bg-zinc-900/60 px-5 py-3.5 border-t border-zinc-800 flex items-center justify-end gap-2.5">
            <button onclick="closeDeleteTableModal()" type="button" class="px-4 py-2 bg-zinc-900 hover:bg-zinc-800 text-zinc-300 border border-zinc-800 rounded-xl text-xs font-mono transition-colors">
                <?php echo t("Cancel", "ยกเลิก"); ?>
            </button>
            <a id="confirm-delete-table-btn" href="#" class="px-4 py-2 bg-red-600 hover:bg-red-500 text-white font-bold rounded-xl text-xs font-mono transition-all flex items-center gap-1.5 shadow-lg shadow-red-600/20 active:scale-95 text-decoration-none">
                <span class="material-symbols-outlined text-base">delete</span>
                <span><?php echo t("Confirm Delete", "ยืนยันการลบโต๊ะ"); ?></span>
            </a>
        </div>
    </div>
</div>

<?php require_once 'admin_footer.php'; ?>
