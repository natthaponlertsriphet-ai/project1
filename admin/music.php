<?php
require_once '../db.php';
require_once 'admin_header.php';

$error = null;
$success = null;

// Form inputs state variables
$is_editing = false;
$edit_id = '';
$day = 'Mon';
$time = '';
$artist = '';
$description = '';
$status = 'REGULAR';

// Handle Live Music Lineup DELETE
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $del_id = $_GET['id'];
    try {
        $stmt = $pdo->prepare("DELETE FROM live_music WHERE id = ?");
        $stmt->execute([$del_id]);
        $success = t("Performance schedule deleted successfully.", "ลบกำหนดการโชว์เรียบร้อยแล้ว.");
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// Handle GET lineup details loader for edit
if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])) {
    $edit_id = $_GET['id'];
    $stmt = $pdo->prepare("SELECT * FROM live_music WHERE id = ?");
    $stmt->execute([$edit_id]);
    $event = $stmt->fetch();
    
    if ($event) {
        $is_editing = true;
        $day = $event['day'];
        $time = $event['time'];
        $artist = $event['artist'];
        $description = $event['description'];
        $status = $event['status'];
    }
}

// Handle Lineup POST submissions (Create/Update)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && in_array($_POST['action'], ['create_lineup', 'update_lineup'])) {
    $day = trim($_POST['day'] ?? 'Mon');
    $time = trim($_POST['time'] ?? '');
    $artist = trim($_POST['artist'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $status = trim($_POST['status'] ?? 'REGULAR');
    
    if (!$time || !$artist) {
        $error = "Please fill in all required fields.";
    } else {
        if ($_POST['action'] === 'create_lineup') {
            try {
                $id = 'music_' . uniqid();
                $stmt = $pdo->prepare("INSERT INTO live_music (id, day, time, artist, description, status) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$id, $day, $time, $artist, $description, $status]);
                
                // Reset fields
                $time = $artist = $description = '';
                $day = 'Mon';
                $status = 'REGULAR';
                $success = t("Live music schedule added successfully!", "เพิ่มกำหนดการดนตรีสดสำเร็จ!");
            } catch (Exception $e) {
                $error = "Error: " . $e->getMessage();
            }
        } elseif ($_POST['action'] === 'update_lineup') {
            $id = $_POST['edit_id'];
            try {
                $stmt = $pdo->prepare("UPDATE live_music SET day = ?, time = ?, artist = ?, description = ?, status = ? WHERE id = ?");
                $stmt->execute([$day, $time, $artist, $description, $status, $id]);
                
                // Reset fields
                $is_editing = false;
                $time = $artist = $description = '';
                $day = 'Mon';
                $status = 'REGULAR';
                $success = t("Live music schedule updated successfully!", "แก้ไขกำหนดการดนตรีสดสำเร็จ!");
            } catch (Exception $e) {
                $error = "Error: " . $e->getMessage();
            }
        }
    }
}

// Handle Gallery Photo Delete
if (isset($_GET['action']) && $_GET['action'] === 'delete_photo' && isset($_GET['filename'])) {
    $filename = basename($_GET['filename']); // Prevent directory traversal
    $photo_path = __DIR__ . '/../public/images/live-music/' . $filename;
    
    // Protect core default photos from deletion
    $prioritized_files = [
        "ตะวันเเดง.png",
        "738937743_122276917376129427_8193348514824766749_n.jpg",
        "738937745_122276917136129427_7543848296007284686_n.jpg",
        "738954237_122276917142129427_6744998899276086597_n.jpg",
        "738991031_122276917346129427_5053537453678874944_n.jpg",
        "739165132_122276917202129427_2386633770452052507_n.jpg",
        "739175342_122276917274129427_3351981840669348872_n.jpg",
        "739451187_122276917094129427_1972973537428140580_n.jpg",
        "750355503_122278340186129427_706892172589075451_n.jpg"
    ];
    
    if (in_array($filename, $prioritized_files)) {
        $error = "Cannot delete core default stage photos.";
    } else {
        if (file_exists($photo_path)) {
            unlink($photo_path);
            $success = t("Atmosphere photo deleted successfully.", "ลบรูปภาพบรรยากาศเรียบร้อยแล้ว.");
        } else {
            $error = "Photo file not found.";
        }
    }
}

// Handle Photo Upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'upload_photo') {
    if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
        $file_tmp = $_FILES['file']['tmp_name'];
        $file_name = $_FILES['file']['name'];
        $file_type = $_FILES['file']['type'];
        
        if (strpos($file_type, 'image/') !== 0) {
            $error = "Please upload a valid image file.";
        } else {
            $upload_dir = __DIR__ . '/../images/live-music/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $clean_name = preg_replace("/[^a-zA-Z0-9.-]/", "_", $file_name);
            $new_name = 'uploaded_' . time() . '_' . $clean_name;
            $dest_path = $upload_dir . $new_name;
            
            if (move_uploaded_file($file_tmp, $dest_path)) {
                $success = t("Photo uploaded successfully!", "อัปโหลดรูปภาพบรรยากาศสำเร็จ!");
            } else {
                $error = "Failed to save uploaded photo.";
            }
        }
    } else {
        $error = "No photo selected or upload error occurred.";
    }
}

// Fetch all live music schedules
$stmt = $pdo->query("SELECT * FROM live_music ORDER BY 
    CASE day
        WHEN 'Mon' THEN 1
        WHEN 'Tue' THEN 2
        WHEN 'Wed' THEN 3
        WHEN 'Thu' THEN 4
        WHEN 'Fri' THEN 5
        WHEN 'Sat' THEN 6
        WHEN 'Sun' THEN 7
    END, time");
$music_events = $stmt->fetchAll();

// Scan gallery photos
$gallery_dir = __DIR__ . '/../images/live-music';
$gallery_images = [];
if (is_dir($gallery_dir)) {
    $files = scandir($gallery_dir);
    foreach ($files as $file) {
        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
            $gallery_images[] = $file;
        }
    }
}
?>

<div class="flex justify-between items-center border-b border-zinc-900 pb-4 mb-6">
    <div>
        <h1 class="font-anton text-warning text-uppercase tracking-wider text-2xl m-0"><?php echo t("Live Music Manager", "จัดการตารางดนตรีสด"); ?></h1>
        <p class="text-zinc-500 text-xs mt-1 uppercase tracking-widest font-mono"><?php echo t("Admin Dashboard / Live Sessions Control", "แผงควบคุมผู้ดูแลระบบ / จัดการวงดนตรีสดและการขึ้นโชว์"); ?></p>
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

<div class="grid grid-cols-1 lg:grid-cols-12 gap-8 mb-8">
    
    <!-- Left Column: Add/Edit Lineup Form -->
    <div class="lg:col-span-4">
        <div class="shadcn-card">
            <h3 class="font-anton text-warning text-uppercase tracking-wider mb-6 flex items-center gap-2 text-lg">
                <span class="material-symbols-outlined text-xl leading-none">music_video</span>
                <span><?php echo $is_editing ? t("Edit Performance", "แก้ไขข้อมูลวงดนตรี") : t("Register Performance", "เพิ่มวงดนตรีใหม่"); ?></span>
            </h3>
            
            <form action="music.php" method="POST" class="flex flex-col gap-4">
                <input type="hidden" name="action" value="<?php echo $is_editing ? 'update_lineup' : 'create_lineup'; ?>">
                <?php if ($is_editing): ?>
                    <input type="hidden" name="edit_id" value="<?php echo $edit_id; ?>">
                <?php endif; ?>

                <div class="flex flex-col gap-1.5">
                    <label class="text-xs uppercase text-zinc-400 font-medium tracking-wider"><?php echo t("Day", "วันแสดง"); ?></label>
                    <select name="day" required class="shadcn-input bg-zinc-950">
                        <?php foreach (['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'] as $d): ?>
                            <option value="<?php echo $d; ?>" <?php echo $day === $d ? 'selected' : ''; ?>>
                                <?php echo t($d, $d); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="flex flex-col gap-1.5">
                    <label class="text-xs uppercase text-zinc-400 font-medium tracking-wider"><?php echo t("Time Slot", "ช่วงเวลาโชว์"); ?></label>
                    <input type="text" name="time" required placeholder="e.g. 19:30 - 20:30" class="shadcn-input" value="<?php echo htmlspecialchars($time); ?>">
                </div>

                <div class="flex flex-col gap-1.5">
                    <label class="text-xs uppercase text-zinc-400 font-medium tracking-wider"><?php echo t("Band / Artist", "ชื่อวงดนตรี / ศิลปิน"); ?></label>
                    <input type="text" name="artist" required placeholder="e.g. Band Name" class="shadcn-input" value="<?php echo htmlspecialchars($artist); ?>">
                </div>

                <div class="flex flex-col gap-1.5">
                    <label class="text-xs uppercase text-zinc-400 font-medium tracking-wider"><?php echo t("Genre Description", "แนวเพลงหรือคำบรรยาย"); ?></label>
                    <textarea name="description" placeholder="Acoustic session..." class="shadcn-input min-h-[80px]" rows="3" style="resize: none;"><?php echo htmlspecialchars($description); ?></textarea>
                </div>

                <div class="flex gap-2 mt-2">
                    <button type="submit" class="shadcn-btn-primary flex-grow">
                        <?php echo $is_editing ? t("Update Lineup", "อัปเดตตารางโชว์") : t("Register Lineup", "บันทึกตารางโชว์"); ?>
                    </button>
                    <?php if ($is_editing): ?>
                        <a href="music.php" class="shadcn-btn-outline"><?php echo t("Cancel", "ยกเลิก"); ?></a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <!-- Right Column: Lineups Rotation Table -->
    <div class="lg:col-span-8">
        <div class="shadcn-card">
            <h3 class="font-anton text-warning text-uppercase tracking-wider mb-6 flex items-center gap-2 text-lg">
                <span class="material-symbols-outlined text-xl leading-none">calendar_view_week</span>
                <span><?php echo t("Weekly Gigs Rotation", "ตารางหมุนเวียนโชว์สัปดาห์นี้"); ?> (<?php echo count($music_events); ?>)</span>
            </h3>
            
            <div class="shadcn-table-container">
                <table class="shadcn-table">
                    <thead>
                        <tr>
                            <th class="font-sans text-xs uppercase tracking-wider text-zinc-400"><?php echo t("Day", "วัน"); ?></th>
                            <th class="font-sans text-xs uppercase tracking-wider text-zinc-400"><?php echo t("Time", "เวลา"); ?></th>
                            <th class="font-sans text-xs uppercase tracking-wider text-zinc-400"><?php echo t("Artist / Band", "วงดนตรี"); ?></th>
                            <th class="font-sans text-xs uppercase tracking-wider text-zinc-400"><?php echo t("Description", "คำอธิบาย"); ?></th>
                            <th class="font-sans text-xs uppercase tracking-wider text-zinc-400 text-center" style="width: 15%;"><?php echo t("Actions", "จัดการ"); ?></th>
                        </tr>
                    </thead>
                    <tbody class="font-sans text-sm text-zinc-300">
                        <?php if (empty($music_events)): ?>
                            <tr>
                                <td colspan="5" class="text-center py-8 text-zinc-500">
                                    <?php echo t("No music gigs scheduled.", "ยังไม่มีการลงบันทึกดนตรีสด"); ?>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($music_events as $event): ?>
                                <tr>
                                    <td class="font-anton text-warning text-base"><?php echo t($event['day'], $event['day']); ?></td>
                                    <td class="text-zinc-200"><?php echo htmlspecialchars($event['time']); ?></td>
                                    <td class="font-semibold text-zinc-100"><?php echo htmlspecialchars($event['artist']); ?></td>
                                    <td class="text-zinc-400" style="max-width: 200px;"><?php echo htmlspecialchars($event['description']); ?></td>
                                    <td class="text-center">
                                        <div class="flex justify-center gap-1">
                                            <a href="music.php?action=edit&id=<?php echo $event['id']; ?>" class="p-1 text-zinc-400 hover:text-warning transition-colors" title="Edit"><span class="material-symbols-outlined text-lg leading-none">edit</span></a>
                                            <a href="music.php?action=delete&id=<?php echo $event['id']; ?>" class="p-1 text-zinc-400 hover:text-red-400 transition-colors" onclick="return confirm('<?php echo t('Are you sure you want to delete this schedule?', 'คุณแน่ใจว่าต้องการลบกำหนดการแสดงนี้?'); ?>')" title="Delete"><span class="material-symbols-outlined text-lg leading-none">delete</span></a>
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

<!-- Dynamic Atmosphere Photos Uploader Grid Section -->
<div class="shadcn-card">
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 border-b border-zinc-900 pb-4 mb-6">
        <div>
            <h3 class="font-anton text-warning text-uppercase tracking-wider m-0 text-lg"><?php echo t("Manage Stage Atmosphere Photos", "จัดการรูปภาพบรรยากาศเวทีและร้าน"); ?></h3>
            <p class="text-zinc-500 text-xs m-0 mt-1"><?php echo t("Upload new photos to show in the live music atmosphere gallery. System default photos are protected.", "อัปโหลดรูปภาพใหม่เพื่อนำไปแสดงผลบนหน้าเว็บลูกค้า รูปภาพเริ่มต้นของระบบจะไม่สามารถลบได้"); ?></p>
        </div>
        
        <!-- File Uploader Form Link -->
        <form action="music.php" method="POST" enctype="multipart/form-data" class="flex items-center gap-2 shrink-0">
            <input type="hidden" name="action" value="upload_photo">
            <label class="shadcn-btn-primary py-2 px-4 flex items-center gap-2 cursor-pointer text-xs">
                <span class="material-symbols-outlined text-base leading-none">upload</span>
                <span><?php echo t("Select Photo", "เลือกรูปภาพใหม่"); ?></span>
                <input type="file" name="file" accept="image/*" class="hidden" onchange="this.form.submit()">
            </label>
        </form>
    </div>

    <!-- Gallery Grid -->
    <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-4">
        <?php foreach ($gallery_images as $img): ?>
            <?php 
            $is_protected = in_array($img, [
                "ตะวันเเดง.png",
                "738937743_122276917376129427_8193348514824766749_n.jpg",
                "738937745_122276917136129427_7543848296007284686_n.jpg",
                "738954237_122276917142129427_6744998899276086597_n.jpg",
                "738991031_122276917346129427_5053537453678874944_n.jpg",
                "739165132_122276917202129427_2386633770452052507_n.jpg",
                "739175342_122276917274129427_3351981840669348872_n.jpg",
                "739451187_122276917094129427_1972973537428140580_n.jpg",
                "750355503_122278340186129427_706892172589075451_n.jpg"
            ]);
            ?>
            <div class="relative overflow-hidden rounded-lg bg-zinc-950 border border-zinc-900 aspect-square group">
                <img src="../images/live-music/<?php echo $img; ?>" alt="Gallery" class="w-full h-full object-cover opacity-80 group-hover:scale-105 group-hover:opacity-100 transition-all duration-300">
                <div class="absolute bottom-0 left-0 right-0 p-2 bg-zinc-950/90 flex justify-center items-center border-t border-zinc-900">
                    <?php if ($is_protected): ?>
                        <span class="text-zinc-500 font-mono text-[9px] font-bold"><?php echo t("Default", "เริ่มต้นระบบ"); ?></span>
                    <?php else: ?>
                        <a href="music.php?action=delete_photo&filename=<?php echo urlencode($img); ?>" class="shadcn-btn-destructive py-1 px-2.5 text-[10px] w-full text-center" onclick="return confirm('<?php echo t('Are you sure you want to delete this photo?', 'คุณแน่ใจว่าต้องการลบรูปภาพบรรยากาศชิ้นนี้?'); ?>')"><?php echo t("Delete", "ลบภาพ"); ?></a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<?php require_once 'admin_footer.php'; ?>
