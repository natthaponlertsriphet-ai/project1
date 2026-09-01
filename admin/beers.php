<?php
require_once '../db.php';

if (session_status() === PHP_SESSION_NONE) {
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

// Handle quick toggle active status (SOLD OUT / ACTIVE)
if (isset($_GET['action']) && $_GET['action'] === 'toggle_status' && isset($_GET['id'])) {
    $toggle_id = $_GET['id'];
    try {
        $stmt = $pdo->prepare("SELECT is_active AS active FROM menu WHERE menu_id = ?");
        $stmt->execute([$toggle_id]);
        $current_active = $stmt->fetchColumn();
        
        $new_active = $current_active ? 0 : 1;
        
        $stmt = $pdo->prepare("UPDATE menu SET is_active = ? WHERE menu_id = ?");
        $stmt->execute([$new_active, $toggle_id]);
        
        $_SESSION['action_success'] = t("Beer tap availability status toggled successfully.", "สลับสถานะเปิด/ปิดขายแท็ปเบียร์สำเร็จ.");
    } catch (Exception $e) {
        $_SESSION['action_error'] = "Error: " . $e->getMessage();
    }
    header("Location: beers.php");
    exit;
}

// Handle DELETE request
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $del_id = $_GET['id'];
    try {
        $stmt = $pdo->prepare("DELETE FROM menu WHERE menu_id = ?");
        $stmt->execute([$del_id]);
        $_SESSION['action_success'] = t("Beer tap deleted successfully.", "ลบแท็ปเบียร์เรียบร้อยแล้ว.");
    } catch (Exception $e) {
        $_SESSION['action_error'] = "Error: " . $e->getMessage();
    }
    header("Location: beers.php");
    exit;
}

// Retrieve flash feedback
$success = $_SESSION['action_success'] ?? null;
$error = $_SESSION['action_error'] ?? null;
unset($_SESSION['action_success'], $_SESSION['action_error']);

require_once 'admin_header.php';

// Form inputs state variables
$is_editing = false;
$edit_id = '';
$tap_number = '';
$name = '';
$type = '';
$abv = '';
$active = 1;

// Handle GET edit details loader
if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])) {
    $edit_id = $_GET['id'];
    $stmt = $pdo->prepare("SELECT menu_id AS id, tap_number, menu_name AS name, beer_type AS type, abv, is_active AS active FROM menu WHERE menu_id = ?");
    $stmt->execute([$edit_id]);
    $beer = $stmt->fetch();
    
    if ($beer) {
        $is_editing = true;
        $tap_number = $beer['tap_number'];
        $name = $beer['name'];
        $type = $beer['type'];
        $abv = $beer['abv'];
        $active = (int)$beer['active'];
    }
}

// Handle POST submissions (Create/Update)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $tap_number = trim($_POST['tap_number'] ?? '');
    $name = trim($_POST['name'] ?? '');
    $type = trim($_POST['type'] ?? '');
    $abv = trim($_POST['abv'] ?? '');
    $active = isset($_POST['active']) ? 1 : 0;
    
    if (!$tap_number || !$name || !$type || !$abv) {
        $error = "Please fill in all required fields.";
    } else {
        if ($_POST['action'] === 'create_beer') {
            try {
                // Check duplicate tap number
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM menu WHERE tap_number = ?");
                $stmt->execute([$tap_number]);
                if ($stmt->fetchColumn() > 0) {
                    $error = "This Tap Number already exists.";
                } else {
                    $id = 'beer_' . uniqid();
                    $stmt = $pdo->prepare("INSERT INTO menu (menu_id, tap_number, menu_name, beer_type, abv, is_active) VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$id, $tap_number, $name, $type, $abv, $active]);
                    
                    $_SESSION['action_success'] = t("Beer tap registered successfully!", "เพิ่มข้อมูลแท็ปเบียร์สำเร็จ!");
                    header("Location: beers.php");
                    exit;
                }
            } catch (Exception $e) {
                $error = "Error: " . $e->getMessage();
            }
        } elseif ($_POST['action'] === 'update_beer') {
            $id = $_POST['edit_id'];
            try {
                // Check duplicate tap number (excluding itself)
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM menu WHERE tap_number = ? AND menu_id != ?");
                $stmt->execute([$tap_number, $id]);
                if ($stmt->fetchColumn() > 0) {
                    $error = "This Tap Number is already in use by another beer.";
                } else {
                    $stmt = $pdo->prepare("UPDATE menu SET tap_number = ?, menu_name = ?, beer_type = ?, abv = ?, is_active = ? WHERE menu_id = ?");
                    $stmt->execute([$tap_number, $name, $type, $abv, $active, $id]);
                    
                    $_SESSION['action_success'] = t("Beer tap configuration updated!", "แก้ไขข้อมูลแท็ปเบียร์สำเร็จ!");
                    header("Location: beers.php");
                    exit;
                }
            } catch (Exception $e) {
                $error = "Error: " . $e->getMessage();
            }
        }
    }
}

// Fetch all beers
$stmt = $pdo->query("SELECT menu_id AS id, tap_number, menu_name AS name, beer_type AS type, abv, is_active AS active FROM menu ORDER BY CAST(tap_number AS UNSIGNED)");
$all_beers = $stmt->fetchAll();
?>

<div class="flex justify-between items-center border-b border-zinc-900 pb-4 mb-6">
    <div>
        <h1 class="font-anton text-warning text-uppercase tracking-wider text-2xl m-0"><?php echo t("Draft Beer Manager", "จัดการข้อมูลรายการเครื่องดื่ม"); ?></h1>
        <p class="text-zinc-500 text-xs mt-1 uppercase tracking-widest font-mono"><?php echo t("Admin Dashboard / Live Beer Control", "แผงควบคุมผู้ดูแลระบบ / จัดการเบียร์สดหน้าร้าน"); ?></p>
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

<div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
    
    <!-- Left Column: Add/Edit Form -->
    <div class="lg:col-span-4">
        <div class="shadcn-card">
            <h3 class="font-anton text-warning text-uppercase tracking-wider mb-6 flex items-center gap-2 text-lg">
                <span class="material-symbols-outlined text-xl leading-none">add_circle</span>
                <span><?php echo $is_editing ? t("Edit Tap Configuration", "แก้ไขข้อมูลแท็ปเบียร์") : t("Register New Tap", "เพิ่มแท็ปเบียร์ใหม่"); ?></span>
            </h3>
            
            <form action="beers.php" method="POST" class="flex flex-col gap-4">
                <input type="hidden" name="action" value="<?php echo $is_editing ? 'update_beer' : 'create_beer'; ?>">
                <?php if ($is_editing): ?>
                    <input type="hidden" name="edit_id" value="<?php echo $edit_id; ?>">
                <?php endif; ?>

                <div class="flex flex-col gap-1.5">
                    <label class="text-xs uppercase text-zinc-400 font-medium tracking-wider"><?php echo t("Tap Number", "เลขแท็ป"); ?></label>
                    <input type="text" name="tap_number" required placeholder="e.g. 01" class="shadcn-input" value="<?php echo htmlspecialchars($tap_number); ?>">
                </div>

                <div class="flex flex-col gap-1.5">
                    <label class="text-xs uppercase text-zinc-400 font-medium tracking-wider"><?php echo t("Brand / Brewery", "แบรนด์ / โรงผลิต"); ?></label>
                    <input type="text" name="type" required placeholder="e.g. Moonshine" class="shadcn-input" value="<?php echo htmlspecialchars($type); ?>">
                </div>

                <div class="flex flex-col gap-1.5">
                    <label class="text-xs uppercase text-zinc-400 font-medium tracking-wider"><?php echo t("Beer Name", "ชื่อเบียร์"); ?></label>
                    <input type="text" name="name" required placeholder="e.g. Lager Light" class="shadcn-input" value="<?php echo htmlspecialchars($name); ?>">
                </div>

                <div class="flex flex-col gap-1.5">
                    <label class="text-xs uppercase text-zinc-400 font-medium tracking-wider"><?php echo t("ABV (%)", "ระดับแอลกอฮอล์ (ABV)"); ?></label>
                    <input type="text" name="abv" required placeholder="e.g. 5.0%" class="shadcn-input" value="<?php echo htmlspecialchars($abv); ?>">
                </div>



                <div class="flex items-center gap-2 py-1">
                    <input type="checkbox" name="active" id="beer-active" class="w-4 h-4 accent-warning cursor-pointer" <?php echo $active ? 'checked' : ''; ?>>
                    <label for="beer-active" class="text-xs uppercase text-zinc-400 font-medium tracking-wider cursor-pointer select-none"><?php echo t("Active on tap", "กำลังเปิดขายแท็ปนี้"); ?></label>
                </div>

                <div class="flex gap-2 mt-2">
                    <button type="submit" class="shadcn-btn-primary flex-grow">
                        <?php echo $is_editing ? t("Update Tap", "อัปเดตแท็ปเบียร์") : t("Register Tap", "บันทึกแท็ปเบียร์"); ?>
                    </button>
                    <?php if ($is_editing): ?>
                        <a href="beers.php" class="shadcn-btn-outline"><?php echo t("Cancel", "ยกเลิก"); ?></a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <!-- Right Column: Inventory Table -->
    <div class="lg:col-span-8">
        <div class="shadcn-card">
            <h3 class="font-anton text-warning text-uppercase tracking-wider mb-6 flex items-center gap-2 text-lg">
                <span class="material-symbols-outlined text-xl leading-none">inventory_2</span>
                <span><?php echo t("Beers Inventory", "รายการแท็ปเบียร์สดทั้งหมดในบอร์ด"); ?> (<?php echo count($all_beers); ?>)</span>
            </h3>
            
            <div class="shadcn-table-container">
                <table class="shadcn-table">
                    <thead>
                        <tr>
                            <th class="font-sans text-xs uppercase tracking-wider text-zinc-400"><?php echo t("Tap", "แท็ป"); ?></th>
                            <th class="font-sans text-xs uppercase tracking-wider text-zinc-400"><?php echo t("Name", "ชื่อเบียร์"); ?></th>
                            <th class="font-sans text-xs uppercase tracking-wider text-zinc-400"><?php echo t("Brand", "แบรนด์"); ?></th>
                            <th class="font-sans text-xs uppercase tracking-wider text-zinc-400"><?php echo t("ABV", "แอลกอฮอล์"); ?></th>
                            <th class="font-sans text-xs uppercase tracking-wider text-zinc-400 text-center"><?php echo t("Status", "สถานะ"); ?></th>
                            <th class="font-sans text-xs uppercase tracking-wider text-zinc-400 text-center" style="width: 15%;"><?php echo t("Actions", "จัดการ"); ?></th>
                        </tr>
                    </thead>
                    <tbody class="font-sans text-sm text-zinc-300">
                        <?php if (empty($all_beers)): ?>
                            <tr>
                                <td colspan="6" class="text-center py-8 text-zinc-500">
                                    <?php echo t("No beer taps registered.", "ยังไม่มีการเพิ่มแท็ปเบียร์สด"); ?>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($all_beers as $b): ?>
                                <tr>
                                    <td class="font-anton text-warning text-lg"><?php echo sprintf("%02d", $b['tap_number']); ?></td>
                                    <td class="font-semibold text-zinc-100"><?php echo htmlspecialchars($b['name']); ?></td>
                                    <td class="text-zinc-400"><?php echo htmlspecialchars($b['type']); ?></td>
                                    <td class="text-zinc-400">
                                        <span class="text-zinc-200"><?php echo htmlspecialchars($b['abv']); ?></span>
                                    </td>
                                    <td class="text-center">
                                        <a href="beers.php?action=toggle_status&id=<?php echo $b['id']; ?>" class="inline-block text-decoration-none" title="<?php echo t('Click to toggle status', 'คลิกเพื่อสลับสถานะเปิด/ปิดขาย'); ?>">
                                            <span class="badge py-1 px-2.5 rounded text-[11px] font-medium transition-all hover:scale-105 cursor-pointer" style="
                                                <?php echo $b['active'] ? 'background-color: rgba(16, 185, 129, 0.1); border: 1px solid rgba(16, 185, 129, 0.25); color: #34d399;' : 'background-color: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.25); color: #f87171;'; ?>
                                            ">
                                                <?php echo $b['active'] ? t("ACTIVE", "เปิดขาย") : t("SOLD OUT", "หมด / ปิดขาย"); ?>
                                            </span>
                                        </a>
                                    </td>
                                    <td class="text-center">
                                        <div class="flex justify-center gap-1">
                                            <a href="beers.php?action=edit&id=<?php echo $b['id']; ?>" class="p-1 text-zinc-400 hover:text-warning transition-colors" title="Edit"><span class="material-symbols-outlined text-lg leading-none">edit</span></a>
                                            <a href="javascript:void(0)" onclick="confirmDeleteBeer('<?php echo $b['id']; ?>', '<?php echo htmlspecialchars($b['tap_number']); ?>', '<?php echo htmlspecialchars($b['name']); ?>', '<?php echo htmlspecialchars($b['abv']); ?>')" class="p-1 text-zinc-400 hover:text-red-400 transition-colors" title="<?php echo t('Delete Beer Tap', 'ลบเบียร์แท็ป'); ?>"><span class="material-symbols-outlined text-lg leading-none">delete</span></a>
                                        </div>
                                    </td>
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
    function confirmDeleteBeer(beerId, tapNum, beerName, abv) {
        document.getElementById('delete-beer-tap-display').innerText = '<?php echo t("TAP #", "แท็ปหมายเลข "); ?>' + tapNum;
        document.getElementById('delete-beer-name-display').innerText = beerName;
        document.getElementById('delete-beer-abv-display').innerText = 'ABV: ' + abv;
        document.getElementById('confirm-delete-beer-btn').href = 'beers.php?action=delete&id=' + encodeURIComponent(beerId);
        document.getElementById('deleteBeerModal').classList.remove('hidden');
    }

    function closeDeleteBeerModal() {
        document.getElementById('deleteBeerModal').classList.add('hidden');
    }
</script>

<!-- Custom Delete Beer Tap Modal Dialog -->
<div id="deleteBeerModal" class="fixed inset-0 z-50 hidden flex items-center justify-center bg-black/80 backdrop-blur-sm p-4">
    <div class="bg-zinc-950 border border-zinc-800 rounded-2xl shadow-2xl w-full max-w-md overflow-hidden transform transition-all">
        <!-- Modal Header -->
        <div class="bg-zinc-900/90 px-5 py-4 border-b border-zinc-800 flex items-center justify-between">
            <div class="flex items-center gap-2.5">
                <div class="w-9 h-9 rounded-xl bg-red-950/80 border border-red-900 flex items-center justify-center text-red-500">
                    <span class="material-symbols-outlined text-xl">sports_bar</span>
                </div>
                <div>
                    <h3 class="font-anton text-warning tracking-wider text-lg uppercase m-0 leading-none">
                        <?php echo t("Confirm Beer Tap Deletion", "ยืนยันการลบข้อมูลเบียร์แท็ป"); ?>
                    </h3>
                    <span class="text-zinc-400 text-xs font-mono block mt-1">
                        <?php echo t("Remove craft beer tap from taplist", "ลบรายการคราฟต์เบียร์ออกจากเมนูร้าน"); ?>
                    </span>
                </div>
            </div>
            <button onclick="closeDeleteBeerModal()" type="button" class="text-zinc-400 hover:text-white transition-colors">
                <span class="material-symbols-outlined text-xl">close</span>
            </button>
        </div>

        <!-- Modal Body -->
        <div class="p-5">
            <p class="text-zinc-300 text-sm mb-4 font-sans leading-relaxed">
                <?php echo t("Are you sure you want to delete this craft beer tap from the system?", "คุณแน่ใจหรือไม่ว่าต้องการลบเบียร์แท็ปนี้ออกจากรายการเมนูของร้าน?"); ?>
            </p>

            <!-- Beer Info Badge -->
            <div class="bg-zinc-900/90 border border-zinc-800 rounded-xl p-3.5 mb-4 font-mono text-xs space-y-2">
                <div class="flex justify-between items-center border-b border-zinc-800/80 pb-2">
                    <span class="text-zinc-400"><?php echo t("Tap Number:", "หมายเลขแท็ป:"); ?></span>
                    <span id="delete-beer-tap-display" class="font-anton text-warning text-base font-bold"></span>
                </div>
                <div class="flex justify-between items-center border-b border-zinc-800/80 pb-2">
                    <span class="text-zinc-400"><?php echo t("Beer Name:", "ชื่อคราฟต์เบียร์:"); ?></span>
                    <span id="delete-beer-name-display" class="font-semibold text-zinc-100 text-sm"></span>
                </div>
                <div class="flex justify-between items-center pt-0.5">
                    <span class="text-zinc-400"><?php echo t("Alcohol Strength:", "ความเข้มแอลกอฮอล์:"); ?></span>
                    <span id="delete-beer-abv-display" class="text-zinc-300"></span>
                </div>
            </div>

            <!-- Caution Alert -->
            <div class="bg-red-950/40 border border-red-900/60 text-red-300 p-3 rounded-lg text-xs font-mono flex items-start gap-2">
                <span class="material-symbols-outlined text-sm leading-none mt-0.5 shrink-0 text-red-400">warning</span>
                <span><?php echo t("Action cannot be undone. Customers will no longer see this tap on the menu.", "การดำเนินการนี้จะไม่สามารถย้อนกลับได้ รายการจะถูกถอนออกจากเมนูทันที"); ?></span>
            </div>
        </div>

        <!-- Modal Footer -->
        <div class="bg-zinc-900/60 px-5 py-3.5 border-t border-zinc-800 flex items-center justify-end gap-2.5">
            <button onclick="closeDeleteBeerModal()" type="button" class="px-4 py-2 bg-zinc-900 hover:bg-zinc-800 text-zinc-300 border border-zinc-800 rounded-xl text-xs font-mono transition-colors">
                <?php echo t("Cancel", "ยกเลิก"); ?>
            </button>
            <a id="confirm-delete-beer-btn" href="#" class="px-4 py-2 bg-red-600 hover:bg-red-500 text-white font-bold rounded-xl text-xs font-mono transition-all flex items-center gap-1.5 shadow-lg shadow-red-600/20 active:scale-95 text-decoration-none">
                <span class="material-symbols-outlined text-base">delete</span>
                <span><?php echo t("Confirm Delete", "ยืนยันการลบแท็ป"); ?></span>
            </a>
        </div>
    </div>
</div>

<?php require_once 'admin_footer.php'; ?>
