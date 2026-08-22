<?php
require_once '../db.php';
require_once 'admin_header.php';

$error = null;
$success = null;

// Form inputs state variables
$is_editing = false;
$edit_id = '';
$title = '';
$description = '';
$offer = '';
$period = '';
$image = '';
$active = 1;

// Handle DELETE request
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $del_id = $_GET['id'];
    try {
        $stmt = $pdo->prepare("DELETE FROM promotions WHERE id = ?");
        $stmt->execute([$del_id]);
        $success = t("Promotion offer deleted successfully.", "ลบโปรโมชันเรียบร้อยแล้ว.");
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// Handle GET edit details loader
if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])) {
    $edit_id = $_GET['id'];
    $stmt = $pdo->prepare("SELECT * FROM promotions WHERE id = ?");
    $stmt->execute([$edit_id]);
    $promo = $stmt->fetch();
    
    if ($promo) {
        $is_editing = true;
        $title = $promo['title'];
        $description = $promo['description'];
        $offer = $promo['offer'];
        $period = $promo['period'];
        $image = $promo['image'];
        $active = (int)$promo['active'];
    }
}

// Handle POST submissions (Create/Update with File Upload)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $offer = '';
    $period = trim($_POST['period'] ?? '');
    $image_url = trim($_POST['image_url'] ?? '');
    $active = isset($_POST['active']) ? 1 : 0;
    
    if (!$title || !$description || !$period) {
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
                
                if (strpos($file_type, 'image/') === 0) {
                    $upload_dir = __DIR__ . '/../images/promotions/';
                    if (!is_dir($upload_dir)) {
                        mkdir($upload_dir, 0777, true);
                    }
                    
                    $clean_name = preg_replace("/[^a-zA-Z0-9.-]/", "_", $file_name);
                    $new_name = 'uploaded_' . time() . '_' . $clean_name;
                    
                    if (move_uploaded_file($file_tmp, $upload_dir . $new_name)) {
                        $image_path = 'images/promotions/' . $new_name;
                    } else {
                        $error = t(
                            "Failed to move uploaded file. Check folder write permissions (chmod 777).",
                            "ไม่สามารถบันทึกไฟล์ภาพได้ กรุณาตรวจสอบสิทธิ์การเขียนโฟลเดอร์ (chmod 777)"
                        );
                    }
                } else {
                    $error = t(
                        "Invalid file type. Please upload an image (PNG, JPG, JPEG).",
                        "ประเภทไฟล์ไม่ถูกต้อง กรุณาอัปโหลดไฟล์รูปภาพเท่านั้น"
                    );
                }
            } else {
                switch ($upload_err) {
                    case UPLOAD_ERR_INI_SIZE:
                        $error = t("The uploaded file exceeds the upload_max_filesize limit in php.ini.", "ไฟล์มีขนาดใหญ่เกินกว่าที่กำหนดใน php.ini");
                        break;
                    case UPLOAD_ERR_FORM_SIZE:
                        $error = t("The uploaded file exceeds the MAX_FILE_SIZE limit in the form.", "ไฟล์มีขนาดใหญ่เกินขนาดที่กำหนดในฟอร์ม");
                        break;
                    case UPLOAD_ERR_PARTIAL:
                        $error = t("The file was only partially uploaded.", "ไฟล์ถูกอัปโหลดขึ้นมาไม่สมบูรณ์");
                        break;
                    case UPLOAD_ERR_NO_TMP_DIR:
                        $error = t("Missing a temporary folder on server.", "ไม่พบโฟลเดอร์ชั่วคราวสำหรับอัปโหลดบนเซิร์ฟเวอร์");
                        break;
                    case UPLOAD_ERR_CANT_WRITE:
                        $error = t("Failed to write file to disk.", "ไม่สามารถบันทึกไฟล์ลงบนดิสก์ของเซิร์ฟเวอร์ได้");
                        break;
                    default:
                        $error = t("File upload failed with error code: ", "การอัปโหลดไฟล์ล้มเหลว รหัสข้อผิดพลาด: ") . $upload_err;
                        break;
                }
            }
        }

        
        if (!$error) {
            if ($_POST['action'] === 'create_promo') {
                try {
                    $id = 'promo_' . uniqid();
                    $stmt = $pdo->prepare("INSERT INTO promotions (id, title, description, offer, period, image, active) VALUES (?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$id, $title, $description, $offer, $period, $image_path, $active]);
                    
                    // Reset
                    $title = $description = $offer = $period = $image = '';
                    $active = 1;
                    $success = t("Promotion registered successfully!", "สร้างโปรโมชันใหม่เรียบร้อยแล้ว!");
                } catch (Exception $e) {
                    $error = "Error: " . $e->getMessage();
                }
            } elseif ($_POST['action'] === 'update_promo') {
                $id = $_POST['edit_id'];
                try {
                    $stmt = $pdo->prepare("UPDATE promotions SET title = ?, description = ?, offer = ?, period = ?, image = ?, active = ? WHERE id = ?");
                    $stmt->execute([$title, $description, $offer, $period, $image_path, $active, $id]);
                    
                    // Reset
                    $is_editing = false;
                    $title = $description = $offer = $period = $image = '';
                    $active = 1;
                    $success = t("Promotion details updated successfully!", "แก้ไขโปรโมชันสำเร็จ!");
                } catch (Exception $e) {
                    $error = "Error: " . $e->getMessage();
                }
            }
        }
    }
}

// Fetch all promotions
$stmt = $pdo->query("SELECT * FROM promotions ORDER BY active DESC, id");
$all_promos = $stmt->fetchAll();
?>

<div class="flex justify-between items-center border-b border-zinc-900 pb-4 mb-6">
    <div>
        <h1 class="font-anton text-warning text-uppercase tracking-wider text-2xl m-0"><?php echo t("Promotions Manager", "จัดการโปรโมชัน"); ?></h1>
        <p class="text-zinc-500 text-xs mt-1 uppercase tracking-widest font-mono"><?php echo t("Admin Dashboard / Promotions Control", "แผงควบคุมผู้ดูแลระบบ / จัดการข้อเสนอและกิจกรรม"); ?></p>
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
    
    <!-- Left Column: Create/Edit Form -->
    <div class="lg:col-span-4">
        <div class="shadcn-card">
            <h3 class="font-anton text-warning text-uppercase tracking-wider mb-6 flex items-center gap-2 text-lg">
                <span class="material-symbols-outlined text-xl leading-none">local_offer</span>
                <span><?php echo $is_editing ? t("Edit Promo Properties", "แก้ไขข้อมูลโปรโมชัน") : t("Create Promotion Offer", "สร้างโปรโมชันใหม่"); ?></span>
            </h3>
            
            <form action="promotions.php" method="POST" enctype="multipart/form-data" class="flex flex-col gap-4">
                <input type="hidden" name="action" value="<?php echo $is_editing ? 'update_promo' : 'create_promo'; ?>">
                <?php if ($is_editing): ?>
                    <input type="hidden" name="edit_id" value="<?php echo $edit_id; ?>">
                <?php endif; ?>

                <div class="flex flex-col gap-1.5">
                    <label class="text-xs uppercase text-zinc-400 font-medium tracking-wider"><?php echo t("Promotion Title", "หัวข้อโปรโมชัน"); ?></label>
                    <input type="text" name="title" required placeholder="e.g. Happy Hour: Buy 1 Get 1" class="shadcn-input" value="<?php echo htmlspecialchars($title); ?>">
                </div>

                <div class="flex flex-col gap-1.5">
                    <label class="text-xs uppercase text-zinc-400 font-medium tracking-wider"><?php echo t("Period / Schedule", "ช่วงเวลาจัด"); ?></label>
                    <input type="text" name="period" required placeholder="e.g. Every Thursday" class="shadcn-input" value="<?php echo htmlspecialchars($period); ?>">
                </div>

                <div class="flex flex-col gap-1.5">
                    <label class="text-xs uppercase text-zinc-400 font-medium tracking-wider"><?php echo t("Image Banner File", "ไฟล์ภาพแบนเนอร์"); ?></label>
                    <input type="file" name="image_file" accept="image/*" class="shadcn-input">
                    <input type="hidden" name="image_url" value="<?php echo htmlspecialchars($image); ?>">
                </div>

                <?php if ($image): ?>
                    <div class="rounded-lg overflow-hidden border border-zinc-800 aspect-video bg-zinc-950">
                        <img src="../<?php echo ltrim($image, '/'); ?>" alt="Preview" class="w-full h-full object-cover">
                    </div>
                <?php endif; ?>

                <div class="flex flex-col gap-1.5">
                    <label class="text-xs uppercase text-zinc-400 font-medium tracking-wider"><?php echo t("Description", "คำอธิบายเงื่อนไข"); ?></label>
                    <textarea name="description" required placeholder="Double the impact..." class="shadcn-input min-h-[80px]" rows="3" style="resize: none;"><?php echo htmlspecialchars($description); ?></textarea>
                </div>

                <div class="flex items-center gap-2 py-1">
                    <input type="checkbox" name="active" id="promo-active" class="w-4 h-4 accent-warning cursor-pointer" <?php echo $active ? 'checked' : ''; ?>>
                    <label for="promo-active" class="text-xs uppercase text-zinc-400 font-medium tracking-wider cursor-pointer select-none"><?php echo t("Active Offer", "เปิดใช้งานข้อเสนอนี้"); ?></label>
                </div>

                <div class="flex gap-2 mt-2">
                    <button type="submit" class="shadcn-btn-primary flex-grow">
                        <?php echo $is_editing ? t("Update Promo", "อัปเดตโปรโมชัน") : t("Create Promo", "บันทึกโปรโมชัน"); ?>
                    </button>
                    <?php if ($is_editing): ?>
                        <a href="promotions.php" class="shadcn-btn-outline"><?php echo t("Cancel", "ยกเลิก"); ?></a>
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
                <span><?php echo t("Promotions Inventory", "รายการโปรโมชันทั้งหมด"); ?> (<?php echo count($all_promos); ?>)</span>
            </h3>
            
            <div class="shadcn-table-container">
                <table class="shadcn-table">
                    <thead>
                        <tr>
                            <th class="font-sans text-xs uppercase tracking-wider text-zinc-400"><?php echo t("Title", "ชื่อโปรโมชัน"); ?></th>
                            <th class="font-sans text-xs uppercase tracking-wider text-zinc-400"><?php echo t("Period", "ช่วงเวลา"); ?></th>
                            <th class="font-sans text-xs uppercase tracking-wider text-zinc-400 text-center"><?php echo t("Status", "สถานะ"); ?></th>
                            <th class="font-sans text-xs uppercase tracking-wider text-zinc-400 text-center" style="width: 15%;"><?php echo t("Actions", "จัดการ"); ?></th>
                        </tr>
                    </thead>
                    <tbody class="font-sans text-sm text-zinc-300">
                        <?php if (empty($all_promos)): ?>
                            <tr>
                                <td colspan="4" class="text-center py-8 text-zinc-500">
                                    <?php echo t("No promotions registered.", "ยังไม่มีการเพิ่มกิจกรรมโปรโมชัน"); ?>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($all_promos as $promo): ?>
                                <tr>
                                    <td class="font-semibold text-zinc-100"><?php echo htmlspecialchars($promo['title']); ?></td>
                                    <td class="text-zinc-400"><?php echo htmlspecialchars($promo['period']); ?></td>
                                    <td class="text-center">
                                        <span class="badge py-1 px-2.5 rounded text-xs" style="
                                            <?php echo $promo['active'] ? 'background-color: rgba(25, 135, 84, 0.1); border: 1px solid rgba(25, 135, 84, 0.25); color: #75b798;' : 'background-color: rgba(63, 63, 70, 0.2); border: 1px solid rgba(63, 63, 70, 0.3); color: #a1a1aa;'; ?>
                                        ">
                                            <?php echo $promo['active'] ? t("Active", "กำลังจัดอยู่") : t("Expired", "หมดเขต"); ?>
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <div class="flex justify-center gap-1">
                                            <a href="promotions.php?action=edit&id=<?php echo $promo['id']; ?>" class="p-1 text-zinc-400 hover:text-warning transition-colors" title="Edit"><span class="material-symbols-outlined text-lg leading-none">edit</span></a>
                                            <a href="promotions.php?action=delete&id=<?php echo $promo['id']; ?>" class="p-1 text-zinc-400 hover:text-red-400 transition-colors" onclick="return confirm('<?php echo t('Are you sure you want to delete this promotion?', 'คุณแน่ใจว่าต้องการลบโปรโมชันนี้?'); ?>')" title="Delete"><span class="material-symbols-outlined text-lg leading-none">delete</span></a>
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

<?php require_once 'admin_footer.php'; ?>
