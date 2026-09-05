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
        $stmt = $pdo->prepare("DELETE FROM promotion WHERE promo_id = ?");
        $stmt->execute([$del_id]);
        $success = t("Promotion offer deleted successfully.", "ลบโปรโมชันเรียบร้อยแล้ว.");
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// Handle Quick Toggle promotion status (ACTIVE / EXPIRED)
if (isset($_GET['action']) && $_GET['action'] === 'toggle_status' && isset($_GET['id'])) {
    $toggle_id = $_GET['id'];
    $is_ajax = (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') || isset($_GET['ajax']);
    try {
        $stmt = $pdo->prepare("SELECT is_active FROM promotion WHERE promo_id = ?");
        $stmt->execute([$toggle_id]);
        $current_active = $stmt->fetchColumn();
        
        $new_active = $current_active ? 0 : 1;
        
        $stmt = $pdo->prepare("UPDATE promotion SET is_active = ? WHERE promo_id = ?");
        $stmt->execute([$new_active, $toggle_id]);
        
        if ($is_ajax) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'id' => $toggle_id,
                'is_active' => $new_active,
                'message' => t("Promotion status toggled successfully.", "สลับสถานะโปรโมชันสำเร็จ.")
            ]);
            exit;
        }

        $success = t("Promotion status toggled successfully.", "สลับสถานะโปรโมชันสำเร็จ.");
    } catch (Exception $e) {
        if ($is_ajax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            exit;
        }
        $error = "Error: " . $e->getMessage();
    }
    header("Location: promotions.php");
    exit;
}

// Handle GET edit details loader
if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])) {
    $edit_id = $_GET['id'];
    $stmt = $pdo->prepare("SELECT promo_id AS id, promo_title AS title, description, offer, promo_period AS period, image_path AS image, is_active AS active FROM promotion WHERE promo_id = ?");
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
                    $stmt = $pdo->prepare("INSERT INTO promotion (promo_id, promo_title, description, offer, promo_period, image_path, is_active) VALUES (?, ?, ?, ?, ?, ?, ?)");
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
                    $stmt = $pdo->prepare("UPDATE promotion SET promo_title = ?, description = ?, offer = ?, promo_period = ?, image_path = ?, is_active = ? WHERE promo_id = ?");
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
$stmt = $pdo->query("SELECT promo_id AS id, promo_title AS title, description, offer, promo_period AS period, image_path AS image, is_active AS active FROM promotion ORDER BY is_active DESC, promo_id");
$all_promos = $stmt->fetchAll();
?>

<div class="flex justify-between items-center border-b border-zinc-900 pb-4 mb-6">
    <div>
        <h1 class="font-anton text-warning text-uppercase tracking-wider text-2xl m-0"><?php echo t("Promotions Manager", "จัดการรายการโปรโมชัน"); ?></h1>
        <p class="text-zinc-500 text-xs mt-1 uppercase tracking-widest font-mono"><?php echo t("Admin Dashboard / Promotions Control", "แผงควบคุมผู้ดูแลระบบ / จัดการข้อเสนอและกิจกรรม"); ?></p>
    </div>
</div>

<?php if ($success): ?>
    <div class="bg-emerald-950/40 border border-emerald-500/40 text-emerald-300 p-3.5 rounded-lg text-xs font-mono mb-6 flex items-center gap-2.5 shadow-lg shadow-emerald-950/30">
        <span class="material-symbols-outlined text-emerald-400 text-base leading-none">check_circle</span>
        <span>[SUCCESS]: <?php echo $success; ?></span>
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="bg-red-950/40 border border-red-500/40 text-red-300 p-3.5 rounded-lg text-xs font-mono mb-6 flex items-center gap-2.5 shadow-lg shadow-red-950/30">
        <span class="material-symbols-outlined text-red-400 text-base leading-none">error</span>
        <span>[ERROR]: <?php echo $error; ?></span>
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
                    <input type="text" name="title" required oninvalid="this.setCustomValidity('<?php echo t('⚠️ Please specify the promotion campaign title.', '⚠️ กรุณาระบุหัวข้อกิจกรรมโปรโมชัน'); ?>')" oninput="this.setCustomValidity('')" placeholder="e.g. Happy Hour: Buy 1 Get 1" class="shadcn-input" value="<?php echo htmlspecialchars($title); ?>">
                </div>

                <div class="flex flex-col gap-1.5">
                    <label class="text-xs uppercase text-zinc-400 font-medium tracking-wider"><?php echo t("Period / Schedule", "ช่วงเวลาจัด"); ?></label>
                    <input type="text" name="period" required oninvalid="this.setCustomValidity('<?php echo t('⚠️ Please specify the campaign period.', '⚠️ กรุณาระบุช่วงเวลาจัดกิจกรรมโปรโมชัน'); ?>')" oninput="this.setCustomValidity('')" placeholder="e.g. Every Thursday" class="shadcn-input" value="<?php echo htmlspecialchars($period); ?>">
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
                    <textarea name="description" required oninvalid="this.setCustomValidity('<?php echo t('⚠️ Please provide detailed promo offer terms.', '⚠️ กรุณาระบุคำอธิบายเงื่อนไขและรายละเอียดโปรโมชัน'); ?>')" oninput="this.setCustomValidity('')" placeholder="Double the impact..." class="shadcn-input min-h-[80px]" rows="3" style="resize: none;"><?php echo htmlspecialchars($description); ?></textarea>
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
                                         <a href="javascript:void(0)" onclick="togglePromoStatusRealtime(event, '<?php echo $promo['id']; ?>', this)" class="inline-block text-decoration-none" data-promo-id="<?php echo $promo['id']; ?>" data-promo-active="<?php echo $promo['active'] ? '1' : '0'; ?>" title="<?php echo t('Click to toggle status', 'คลิกเพื่อสลับสถานะโปรโมชัน'); ?>">
                                             <span class="promo-status-badge badge py-1 px-2.5 rounded text-xs transition-all hover:scale-105 cursor-pointer" style="
                                                 <?php echo $promo['active'] ? 'background-color: rgba(25, 135, 84, 0.1); border: 1px solid rgba(25, 135, 84, 0.25); color: #75b798;' : 'background-color: rgba(63, 63, 70, 0.2); border: 1px solid rgba(63, 63, 70, 0.3); color: #a1a1aa;'; ?>
                                             ">
                                                 <?php echo $promo['active'] ? t("Active", "กำลังจัดอยู่") : t("Expired", "หมดเขต"); ?>
                                             </span>
                                         </a>
                                     </td>
                                    <td class="text-center">
                                        <div class="flex justify-center gap-1">
                                            <a href="promotions.php?action=edit&id=<?php echo $promo['id']; ?>" class="p-1 text-zinc-400 hover:text-warning transition-colors" title="Edit"><span class="material-symbols-outlined text-lg leading-none">edit</span></a>
                                            <a href="javascript:void(0)" onclick="confirmDeletePromo('<?php echo $promo['id']; ?>', '<?php echo htmlspecialchars($promo['title']); ?>', '<?php echo htmlspecialchars($promo['period']); ?>')" class="p-1 text-zinc-400 hover:text-red-400 transition-colors" title="<?php echo t('Delete Promotion', 'ลบโปรโมชัน'); ?>"><span class="material-symbols-outlined text-lg leading-none">delete</span></a>
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
function togglePromoStatusRealtime(event, promoId, el) {
    if (event) event.preventDefault();
    const linkEl = el || document.querySelector(`[data-promo-id="${promoId}"]`);
    if (!linkEl) return;

    const badgeSpan = linkEl.querySelector('.promo-status-badge') || linkEl.querySelector('span');
    const currentActive = linkEl.getAttribute('data-promo-active') === '1';
    const newActive = !currentActive;

    // Instant optimistic UI update (0ms)
    linkEl.setAttribute('data-promo-active', newActive ? '1' : '0');
    if (badgeSpan) {
        if (newActive) {
            badgeSpan.style.backgroundColor = 'rgba(25, 135, 84, 0.1)';
            badgeSpan.style.border = '1px solid rgba(25, 135, 84, 0.25)';
            badgeSpan.style.color = '#75b798';
            badgeSpan.innerText = '<?php echo t("Active", "กำลังจัดอยู่"); ?>';
        } else {
            badgeSpan.style.backgroundColor = 'rgba(63, 63, 70, 0.2)';
            badgeSpan.style.border = '1px solid rgba(63, 63, 70, 0.3)';
            badgeSpan.style.color = '#a1a1aa';
            badgeSpan.innerText = '<?php echo t("Expired", "หมดเขต"); ?>';
        }
    }

    fetch(`promotions.php?action=toggle_status&id=${encodeURIComponent(promoId)}&ajax=1`)
        .then(res => res.json())
        .then(data => {
            if (!data.success) {
                // Revert on failure
                linkEl.setAttribute('data-promo-active', currentActive ? '1' : '0');
            }
        })
        .catch(err => console.error("Error toggling promo status:", err));
}

    function confirmDeletePromo(promoId, promoTitle, promoPeriod) {
        document.getElementById('delete-promo-title-display').innerText = promoTitle;
        document.getElementById('delete-promo-period-display').innerText = promoPeriod;
        document.getElementById('confirm-delete-promo-btn').href = 'promotions.php?action=delete&id=' + encodeURIComponent(promoId);
        document.getElementById('deletePromoModal').classList.remove('hidden');
    }

    function closeDeletePromoModal() {
        document.getElementById('deletePromoModal').classList.add('hidden');
    }
</script>

<!-- Custom Delete Promotion Modal Dialog -->
<div id="deletePromoModal" class="fixed inset-0 z-50 hidden flex items-center justify-center bg-black/80 backdrop-blur-sm p-4">
    <div class="bg-zinc-950 border border-zinc-800 rounded-2xl shadow-2xl w-full max-w-md overflow-hidden transform transition-all">
        <!-- Modal Header -->
        <div class="bg-zinc-900/90 px-5 py-4 border-b border-zinc-800 flex items-center justify-between">
            <div class="flex items-center gap-2.5">
                <div class="w-9 h-9 rounded-xl bg-red-950/80 border border-red-900 flex items-center justify-center text-red-500">
                    <span class="material-symbols-outlined text-xl">local_offer</span>
                </div>
                <div>
                    <h3 class="font-anton text-warning tracking-wider text-lg uppercase m-0 leading-none">
                        <?php echo t("Confirm Promotion Deletion", "ยืนยันการลบรายการโปรโมชัน"); ?>
                    </h3>
                    <span class="text-zinc-400 text-xs font-mono block mt-1">
                        <?php echo t("Remove promotion campaign from store", "ลบสิทธิพิเศษออกจากหน้าร้าน"); ?>
                    </span>
                </div>
            </div>
            <button onclick="closeDeletePromoModal()" type="button" class="text-zinc-400 hover:text-white transition-colors">
                <span class="material-symbols-outlined text-xl">close</span>
            </button>
        </div>

        <!-- Modal Body -->
        <div class="p-5">
            <p class="text-zinc-300 text-sm mb-4 font-sans leading-relaxed">
                <?php echo t("Are you sure you want to delete this promotion campaign?", "คุณแน่ใจหรือไม่ว่าต้องการลบรายการโปรโมชันนี้ออกจากระบบ?"); ?>
            </p>

            <!-- Promo Info Badge -->
            <div class="bg-zinc-900/90 border border-zinc-800 rounded-xl p-3.5 mb-4 font-mono text-xs space-y-2">
                <div class="flex justify-between items-center border-b border-zinc-800/80 pb-2">
                    <span class="text-zinc-400"><?php echo t("Campaign Title:", "ชื่อโปรโมชัน:"); ?></span>
                    <span id="delete-promo-title-display" class="font-semibold text-zinc-100 text-sm"></span>
                </div>
                <div class="flex justify-between items-center pt-0.5">
                    <span class="text-zinc-400"><?php echo t("Promo Period:", "ระยะเวลาสิทธิพิเศษ:"); ?></span>
                    <span id="delete-promo-period-display" class="text-zinc-300"></span>
                </div>
            </div>

            <!-- Caution Alert -->
            <div class="bg-red-950/40 border border-red-900/60 text-red-300 p-3 rounded-lg text-xs font-mono flex items-start gap-2">
                <span class="material-symbols-outlined text-sm leading-none mt-0.5 shrink-0 text-red-400">warning</span>
                <span><?php echo t("Action cannot be undone. Customers will no longer see this offer.", "การดำเนินการนี้จะไม่สามารถย้อนกลับได้ โปรโมชันจะถูกยกเลิกทันที"); ?></span>
            </div>
        </div>

        <!-- Modal Footer -->
        <div class="bg-zinc-900/60 px-5 py-3.5 border-t border-zinc-800 flex items-center justify-end gap-2.5">
            <button onclick="closeDeletePromoModal()" type="button" class="px-4 py-2 bg-zinc-900 hover:bg-zinc-800 text-zinc-300 border border-zinc-800 rounded-xl text-xs font-mono transition-colors">
                <?php echo t("Cancel", "ยกเลิก"); ?>
            </button>
            <a id="confirm-delete-promo-btn" href="#" class="px-4 py-2 bg-red-600 hover:bg-red-500 text-white font-bold rounded-xl text-xs font-mono transition-all flex items-center gap-1.5 shadow-lg shadow-red-600/20 active:scale-95 text-decoration-none">
                <span class="material-symbols-outlined text-base">delete</span>
                <span><?php echo t("Confirm Delete", "ยืนยันการลบโปรโมชัน"); ?></span>
            </a>
        </div>
    </div>
</div>

<?php require_once 'admin_footer.php'; ?>
