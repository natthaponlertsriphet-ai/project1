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

// Handle Live Music Lineup DELETE
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $del_id = $_GET['id'];
    try {
        $stmt = $pdo->prepare("DELETE FROM music WHERE music_id = ?");
        $stmt->execute([$del_id]);
        $success = t("Performance schedule deleted successfully.", "ลบกำหนดการโชว์เรียบร้อยแล้ว.");
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// Handle GET lineup details loader for edit
if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])) {
    $edit_id = $_GET['id'];
    $stmt = $pdo->prepare("SELECT music_id AS id, show_day AS day, show_time AS time, artist, description FROM music WHERE music_id = ?");
    $stmt->execute([$edit_id]);
    $event = $stmt->fetch();
    
    if ($event) {
        $is_editing = true;
        $day = $event['day'];
        $time = $event['time'];
        $artist = $event['artist'];
        $description = $event['description'];
    }
}

// Handle Lineup POST submissions (Create/Update)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && in_array($_POST['action'], ['create_lineup', 'update_lineup'])) {
    $day = trim($_POST['day'] ?? 'Mon');
    $time = trim($_POST['time'] ?? '');
    $artist = trim($_POST['artist'] ?? '');
    $description = trim($_POST['description'] ?? '');
    
    if (!$time || !$artist) {
        $error = "Please fill in all required fields.";
    } else {
        if ($_POST['action'] === 'create_lineup') {
            try {
                $id = 'music_' . uniqid();
                $stmt = $pdo->prepare("INSERT INTO music (music_id, show_day, show_time, artist, description) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$id, $day, $time, $artist, $description]);
                
                // Reset fields
                $time = $artist = $description = '';
                $day = 'Mon';
                $success = t("Live music schedule added successfully!", "เพิ่มกำหนดการดนตรีสดสำเร็จ!");
            } catch (Exception $e) {
                $error = "Error: " . $e->getMessage();
            }
        } elseif ($_POST['action'] === 'update_lineup') {
            $id = $_POST['edit_id'];
            try {
                $stmt = $pdo->prepare("UPDATE music SET show_day = ?, show_time = ?, artist = ?, description = ? WHERE music_id = ?");
                $stmt->execute([$day, $time, $artist, $description, $id]);
                
                // Reset fields
                $is_editing = false;
                $time = $artist = $description = '';
                $day = 'Mon';
                $success = t("Live music schedule updated successfully!", "แก้ไขกำหนดการดนตรีสดสำเร็จ!");
            } catch (Exception $e) {
                $error = "Error: " . $e->getMessage();
            }
        }
    }
}

// Helper functions for Atmosphere Photos ordering
function getSortedAtmospherePhotos($dir) {
    $images = [];
    if (is_dir($dir)) {
        $files = scandir($dir);
        foreach ($files as $file) {
            $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
                $images[] = $file;
            }
        }
    }

    $order_file = $dir . '/photo_order.json';
    if (file_exists($order_file)) {
        $order = json_decode(file_get_contents($order_file), true);
        if (is_array($order)) {
            usort($images, function($a, $b) use ($order) {
                $pos_a = array_search($a, $order);
                $pos_b = array_search($b, $order);
                if ($pos_a === false) $pos_a = 9999;
                if ($pos_b === false) $pos_b = 9999;
                return $pos_a <=> $pos_b;
            });
        }
    }
    return array_values($images);
}

function saveAtmospherePhotoOrder($dir, $images) {
    $order_file = $dir . '/photo_order.json';
    file_put_contents($order_file, json_encode(array_values($images), JSON_PRETTY_PRINT));
}

// Handle Photo Reordering (Move Left / Right)
if (isset($_GET['action']) && $_GET['action'] === 'move_photo' && isset($_GET['filename']) && isset($_GET['dir'])) {
    $filename = basename($_GET['filename']);
    $direction = $_GET['dir'];
    $gallery_dir = __DIR__ . '/../images/live-music';
    $images = getSortedAtmospherePhotos($gallery_dir);
    $idx = array_search($filename, $images);
    
    if ($idx !== false) {
        if ($direction === 'left' && $idx > 0) {
            $swap = $images[$idx - 1];
            $images[$idx - 1] = $images[$idx];
            $images[$idx] = $swap;
            saveAtmospherePhotoOrder($gallery_dir, $images);
            $success = t("Photo order updated successfully!", "จัดลำดับรูปภาพบรรยากาศร้านสำเร็จ!");
        } elseif ($direction === 'right' && $idx < count($images) - 1) {
            $swap = $images[$idx + 1];
            $images[$idx + 1] = $images[$idx];
            $images[$idx] = $swap;
            saveAtmospherePhotoOrder($gallery_dir, $images);
            $success = t("Photo order updated successfully!", "จัดลำดับรูปภาพบรรยากาศร้านสำเร็จ!");
        }
    }
}

// Handle Gallery Photo Delete
if (isset($_GET['action']) && $_GET['action'] === 'delete_photo' && isset($_GET['filename'])) {
    $filename = basename($_GET['filename']); // Prevent directory traversal
    $photo_path = __DIR__ . '/../images/live-music/' . $filename;
    
    if (file_exists($photo_path)) {
        unlink($photo_path);
        $gallery_dir = __DIR__ . '/../images/live-music';
        $images = getSortedAtmospherePhotos($gallery_dir);
        $images = array_values(array_filter($images, function($f) use ($filename) { return $f !== $filename; }));
        saveAtmospherePhotoOrder($gallery_dir, $images);
        $success = t("Atmosphere photo deleted successfully.", "ลบรูปภาพบรรยากาศเรียบร้อยแล้ว.");
    } else {
        $error = "Photo file not found.";
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
            
            $ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
            $raw_filename = pathinfo($file_name, PATHINFO_FILENAME);
            $clean_name = preg_replace("/[^a-zA-Z0-9.-]/", "_", $raw_filename);
            
            if ($ext === 'heic' || $ext === 'heif') {
                $new_name = 'uploaded_' . time() . '_' . $clean_name . '.jpg';
                $dest_path = $upload_dir . $new_name;
                $temp_heic = $upload_dir . 'temp_' . time() . '_' . rand(1000, 9999) . '.' . $ext;
                
                if (move_uploaded_file($file_tmp, $temp_heic)) {
                    exec('sips -s format jpeg ' . escapeshellarg($temp_heic) . ' --out ' . escapeshellarg($dest_path));
                    @unlink($temp_heic);
                    $images = getSortedAtmospherePhotos($upload_dir);
                    array_unshift($images, $new_name);
                    saveAtmospherePhotoOrder($upload_dir, $images);
                    $success = t("Photo uploaded and converted to JPG successfully!", "อัปโหลดและแปลงไฟล์รูปภาพบรรยากาศสำเร็จ!");
                } else {
                    $error = "Failed to save uploaded photo.";
                }
            } else {
                $new_name = 'uploaded_' . time() . '_' . $clean_name . '.' . $ext;
                $dest_path = $upload_dir . $new_name;
                
                if (move_uploaded_file($file_tmp, $dest_path)) {
                    $images = getSortedAtmospherePhotos($upload_dir);
                    array_unshift($images, $new_name);
                    saveAtmospherePhotoOrder($upload_dir, $images);
                    $success = t("Photo uploaded successfully!", "อัปโหลดรูปภาพบรรยากาศสำเร็จ!");
                } else {
                    $error = "Failed to save uploaded photo.";
                }
            }
        }
    } else {
        $error = "No photo selected or upload error occurred.";
    }
}

// Fetch all live music schedules
$stmt = $pdo->query("SELECT music_id AS id, show_day AS day, show_time AS time, artist, description FROM music ORDER BY 
    CASE show_day
        WHEN 'Mon' THEN 1
        WHEN 'Tue' THEN 2
        WHEN 'Wed' THEN 3
        WHEN 'Thu' THEN 4
        WHEN 'Fri' THEN 5
        WHEN 'Sat' THEN 6
        WHEN 'Sun' THEN 7
    END, show_time");
$music_events = $stmt->fetchAll();

// Scan gallery photos with custom order
$gallery_dir = __DIR__ . '/../images/live-music';
$gallery_images = getSortedAtmospherePhotos($gallery_dir);
?>
?>

<div class="flex justify-between items-center border-b border-zinc-800 pb-4 mb-6">
    <div>
        <h1 class="font-anton text-amber-400 text-uppercase tracking-wider text-2xl m-0 flex items-center gap-2.5">
            <span class="material-symbols-outlined text-amber-400 text-2xl leading-none">music_note</span>
            <span><?php echo t("Live Music Manager", "จัดการตารางเวลาการแสดงดนตรีสด"); ?></span>
        </h1>
        <p class="text-zinc-300 text-xs mt-1 uppercase tracking-widest font-mono"><?php echo t("Admin Dashboard / Live Sessions Control", "แผงควบคุมผู้ดูแลระบบ / จัดการวงดนตรีสดและการขึ้นโชว์"); ?></p>
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

<div class="grid grid-cols-1 lg:grid-cols-12 gap-8 mb-8">
    
    <!-- Left Column: Add/Edit Lineup Form -->
    <div class="lg:col-span-4">
        <div class="shadcn-card border border-amber-500/30 bg-zinc-900/90 shadow-xl shadow-amber-500/5 rounded-xl p-6">
            <h3 class="font-anton text-amber-400 text-uppercase tracking-wider mb-6 flex items-center gap-2 text-lg border-b border-zinc-800 pb-3">
                <span class="material-symbols-outlined text-amber-400 text-xl leading-none">music_video</span>
                <span><?php echo $is_editing ? t("Edit Performance", "แก้ไขข้อมูลวงดนตรี") : t("Register Performance", "เพิ่มวงดนตรีใหม่"); ?></span>
            </h3>
            
            <form action="music.php" method="POST" class="flex flex-col gap-4">
                <input type="hidden" name="action" value="<?php echo $is_editing ? 'update_lineup' : 'create_lineup'; ?>">
                <?php if ($is_editing): ?>
                    <input type="hidden" name="edit_id" value="<?php echo $edit_id; ?>">
                <?php endif; ?>

                <div class="flex flex-col gap-1.5">
                    <label class="text-xs uppercase text-zinc-200 font-semibold tracking-wider flex items-center gap-1.5">
                        <span class="material-symbols-outlined text-amber-400 text-sm">calendar_today</span>
                        <span><?php echo t("Day", "วันแสดง"); ?></span>
                    </label>
                    <select name="day" required oninvalid="this.setCustomValidity('<?php echo t('⚠️ Please select the live performance day.', '⚠️ กรุณาระบุวันแสดงดนตรีสด'); ?>')" onchange="this.setCustomValidity('')" class="shadcn-input border-zinc-700 bg-zinc-950 text-zinc-100 focus:border-amber-400">
                        <?php foreach (['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'] as $d): ?>
                            <option value="<?php echo $d; ?>" <?php echo $day === $d ? 'selected' : ''; ?>>
                                <?php echo t($d, $d); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="flex flex-col gap-1.5">
                    <label class="text-xs uppercase text-zinc-200 font-semibold tracking-wider flex items-center gap-1.5">
                        <span class="material-symbols-outlined text-amber-400 text-sm">schedule</span>
                        <span><?php echo t("Time Slot", "ช่วงเวลาโชว์"); ?></span>
                    </label>
                    <input type="text" name="time" required oninvalid="this.setCustomValidity('<?php echo t('⚠️ Please specify the performance time slot.', '⚠️ กรุณาระบุช่วงเวลาการแสดงดนตรีสด'); ?>')" oninput="this.setCustomValidity('')" placeholder="e.g. 19:30 - 20:30" class="shadcn-input border-zinc-700 bg-zinc-950 text-zinc-100 placeholder:text-zinc-500 focus:border-amber-400 font-mono" value="<?php echo htmlspecialchars($time); ?>">
                </div>

                <div class="flex flex-col gap-1.5">
                    <label class="text-xs uppercase text-zinc-200 font-semibold tracking-wider flex items-center gap-1.5">
                        <span class="material-symbols-outlined text-amber-400 text-sm">mic</span>
                        <span><?php echo t("Band / Artist", "ชื่อวงดนตรี / ศิลปิน"); ?></span>
                    </label>
                    <input type="text" name="artist" required oninvalid="this.setCustomValidity('<?php echo t('⚠️ Please specify the band or artist name.', '⚠️ กรุณาระบุชื่อวงดนตรีหรือศิลปินผู้แสดง'); ?>')" oninput="this.setCustomValidity('')" placeholder="e.g. Band Name" class="shadcn-input border-zinc-700 bg-zinc-950 text-zinc-100 placeholder:text-zinc-500 focus:border-amber-400" value="<?php echo htmlspecialchars($artist); ?>">
                </div>

                <div class="flex flex-col gap-1.5">
                    <label class="text-xs uppercase text-zinc-200 font-semibold tracking-wider flex items-center gap-1.5">
                        <span class="material-symbols-outlined text-amber-400 text-sm">queue_music</span>
                        <span><?php echo t("Genre Description", "แนวเพลงหรือคำบรรยาย"); ?></span>
                    </label>
                    <textarea name="description" placeholder="Acoustic session..." class="shadcn-input border-zinc-700 bg-zinc-950 text-zinc-100 placeholder:text-zinc-500 focus:border-amber-400 min-h-[80px]" rows="3" style="resize: none;"><?php echo htmlspecialchars($description); ?></textarea>
                </div>

                <div class="flex gap-2 mt-4">
                    <button type="submit" class="w-full bg-gradient-to-r from-amber-500 to-amber-400 hover:from-amber-400 hover:to-amber-300 text-zinc-950 font-bold py-2.5 px-4 rounded-lg shadow-lg shadow-amber-500/20 transition-all flex items-center justify-center gap-2 text-sm uppercase tracking-wider">
                        <span class="material-symbols-outlined text-base">save</span>
                        <span><?php echo $is_editing ? t("Update Lineup", "อัปเดตตารางโชว์") : t("Register Lineup", "บันทึกตารางโชว์"); ?></span>
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
        <div class="shadcn-card border border-amber-500/30 bg-zinc-900/90 shadow-xl shadow-amber-500/5 rounded-xl p-6">
            <h3 class="font-anton text-amber-400 text-uppercase tracking-wider mb-6 flex items-center gap-2 text-lg border-b border-zinc-800 pb-3">
                <span class="material-symbols-outlined text-amber-400 text-xl leading-none">calendar_view_week</span>
                <span><?php echo t("Weekly Gigs Rotation", "ข้อมูลตารางเวลาการแสดงดนตรีสด"); ?> (<?php echo count($music_events); ?>)</span>
            </h3>
            
            <div class="shadcn-table-container">
                <table class="shadcn-table">
                    <thead>
                        <tr class="border-b border-zinc-800">
                            <th class="font-sans text-xs uppercase tracking-wider text-zinc-200 font-semibold"><?php echo t("Day", "วัน"); ?></th>
                            <th class="font-sans text-xs uppercase tracking-wider text-zinc-200 font-semibold"><?php echo t("Time", "เวลา"); ?></th>
                            <th class="font-sans text-xs uppercase tracking-wider text-zinc-200 font-semibold"><?php echo t("Artist / Band", "วงดนตรี"); ?></th>
                            <th class="font-sans text-xs uppercase tracking-wider text-zinc-200 font-semibold"><?php echo t("Description", "คำอธิบาย"); ?></th>
                            <th class="font-sans text-xs uppercase tracking-wider text-zinc-200 font-semibold text-center" style="width: 15%;"><?php echo t("Actions", "จัดการ"); ?></th>
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
                                    <td class="font-anton text-amber-400 text-base"><?php echo t($event['day'], $event['day']); ?></td>
                                    <td class="text-zinc-200"><?php echo htmlspecialchars($event['time']); ?></td>
                                    <td class="font-semibold text-zinc-100"><?php echo htmlspecialchars($event['artist']); ?></td>
                                    <td class="text-zinc-400" style="max-width: 200px;"><?php echo htmlspecialchars($event['description']); ?></td>
                                    <td class="text-center">
                                        <div class="flex justify-center gap-1">
                                            <a href="music.php?action=edit&id=<?php echo $event['id']; ?>" class="p-1 text-zinc-400 hover:text-amber-400 transition-colors" title="Edit"><span class="material-symbols-outlined text-lg leading-none">edit</span></a>
                                            <a href="javascript:void(0)" onclick="confirmDeleteMusic('<?php echo $event['id']; ?>', '<?php echo htmlspecialchars($event['artist']); ?>', '<?php echo htmlspecialchars($event['day']); ?>', '<?php echo htmlspecialchars($event['time']); ?>')" class="p-1 text-zinc-400 hover:text-red-400 transition-colors" title="<?php echo t('Delete Schedule', 'ลบกำหนดการแสดง'); ?>"><span class="material-symbols-outlined text-lg leading-none">delete</span></a>
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
<div class="shadcn-card border border-amber-500/30 bg-zinc-900/90 shadow-xl shadow-amber-500/5 rounded-xl p-6 mt-8">
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 border-b border-zinc-800 pb-4 mb-6">
        <div>
            <h3 class="font-anton text-amber-400 text-uppercase tracking-wider m-0 text-lg flex items-center gap-2">
                <span class="material-symbols-outlined text-amber-400 text-xl leading-none">photo_library</span>
                <span><?php echo t("Manage Venue Atmosphere Photos", "จัดการรูปภาพบรรยากาศร้าน"); ?></span>
            </h3>
            <p class="text-zinc-300 text-xs m-0 mt-1"><?php echo t("Upload new photos to show in the live music atmosphere gallery.", "อัปโหลดรูปภาพใหม่เพื่อนำไปแสดงผลบนหน้าเว็บลูกค้า"); ?></p>
        </div>
        
        <!-- File Uploader Form Link -->
        <form action="music.php" method="POST" enctype="multipart/form-data" class="flex items-center gap-2 shrink-0">
            <input type="hidden" name="action" value="upload_photo">
            <label class="bg-gradient-to-r from-amber-500 to-amber-400 hover:from-amber-400 hover:to-amber-300 text-zinc-950 font-bold py-2 px-4 rounded-lg shadow-lg shadow-amber-500/20 transition-all flex items-center gap-2 text-xs uppercase tracking-wider cursor-pointer">
                <span class="material-symbols-outlined text-base leading-none">upload</span>
                <span><?php echo t("Select Photo", "เลือกรูปภาพใหม่"); ?></span>
                <input type="file" name="file" accept="image/*,.heic,.heif" class="hidden" onchange="this.form.submit()">
            </label>
        </form>
    </div>

    <!-- Gallery Grid -->
    <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-4">
        <?php foreach ($gallery_images as $index => $img): ?>
            <div class="relative overflow-hidden rounded-xl bg-zinc-950 border border-zinc-800 shadow-lg group flex flex-col justify-between">
                <!-- Order Position Badge -->
                <div class="absolute top-2 left-2 z-10 bg-amber-500/90 text-zinc-950 font-bold px-2 py-0.5 rounded text-[11px] shadow font-mono">
                    #<?php echo $index + 1; ?>
                </div>

                <!-- Image Thumbnail -->
                <div class="aspect-square overflow-hidden relative">
                    <img src="../images/live-music/<?php echo htmlspecialchars($img); ?>" alt="Gallery" class="w-full h-full object-cover opacity-85 group-hover:scale-105 group-hover:opacity-100 transition-all duration-300">
                </div>

                <!-- Card Action Footer: Reorder & Delete -->
                <div class="p-2 bg-zinc-900 border-t border-zinc-800 flex items-center justify-between gap-1">
                    <!-- Move Left / Up Button -->
                    <?php if ($index > 0): ?>
                        <a href="music.php?action=move_photo&dir=left&filename=<?php echo urlencode($img); ?>" class="p-1.5 rounded bg-zinc-800 hover:bg-amber-500 hover:text-zinc-950 text-zinc-300 transition-colors" title="<?php echo t("Move Left", "ย้ายไปซ้าย"); ?>">
                            <span class="material-symbols-outlined text-sm leading-none block">arrow_back</span>
                        </a>
                    <?php else: ?>
                        <span class="p-1.5 rounded bg-zinc-800/30 text-zinc-600 cursor-not-allowed">
                            <span class="material-symbols-outlined text-sm leading-none block">arrow_back</span>
                        </span>
                    <?php endif; ?>

                    <!-- Delete Button -->
                    <a href="javascript:void(0)" onclick="confirmDeletePhoto('<?php echo urlencode($img); ?>')" class="py-1 px-2 rounded bg-red-950/60 hover:bg-red-600 text-red-300 hover:text-white transition-colors text-[10px] font-sans font-medium flex items-center gap-1 border border-red-900/40">
                        <span class="material-symbols-outlined text-xs leading-none">delete</span>
                        <span><?php echo t("Delete", "ลบ"); ?></span>
                    </a>

                    <!-- Move Right / Down Button -->
                    <?php if ($index < count($gallery_images) - 1): ?>
                        <a href="music.php?action=move_photo&dir=right&filename=<?php echo urlencode($img); ?>" class="p-1.5 rounded bg-zinc-800 hover:bg-amber-500 hover:text-zinc-950 text-zinc-300 transition-colors" title="<?php echo t("Move Right", "ย้ายไปขวา"); ?>">
                            <span class="material-symbols-outlined text-sm leading-none block">arrow_forward</span>
                        </a>
                    <?php else: ?>
                        <span class="p-1.5 rounded bg-zinc-800/30 text-zinc-600 cursor-not-allowed">
                            <span class="material-symbols-outlined text-sm leading-none block">arrow_forward</span>
                        </span>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<script>
    function confirmDeleteMusic(musicId, artist, day, time) {
        document.getElementById('delete-music-artist-display').innerText = artist;
        document.getElementById('delete-music-time-display').innerText = day + ' (' + time + ')';
        document.getElementById('confirm-delete-music-btn').href = 'music.php?action=delete&id=' + encodeURIComponent(musicId);
        document.getElementById('deleteMusicModal').classList.remove('hidden');
    }

    function closeDeleteMusicModal() {
        document.getElementById('deleteMusicModal').classList.add('hidden');
    }

    function confirmDeletePhoto(filename) {
        document.getElementById('confirm-delete-photo-btn').href = 'music.php?action=delete_photo&filename=' + filename;
        document.getElementById('deletePhotoModal').classList.remove('hidden');
    }

    function closeDeletePhotoModal() {
        document.getElementById('deletePhotoModal').classList.add('hidden');
    }
</script>

<!-- Custom Delete Live Music Schedule Modal -->
<div id="deleteMusicModal" class="fixed inset-0 z-50 hidden flex items-center justify-center bg-black/80 backdrop-blur-sm p-4">
    <div class="bg-zinc-950 border border-zinc-800 rounded-2xl shadow-2xl w-full max-w-md overflow-hidden transform transition-all">
        <!-- Modal Header -->
        <div class="bg-zinc-900/90 px-5 py-4 border-b border-zinc-800 flex items-center justify-between">
            <div class="flex items-center gap-2.5">
                <div class="w-9 h-9 rounded-xl bg-red-950/80 border border-red-900 flex items-center justify-center text-red-500">
                    <span class="material-symbols-outlined text-xl">music_off</span>
                </div>
                <div>
                    <h3 class="font-anton text-warning tracking-wider text-lg uppercase m-0 leading-none">
                        <?php echo t("Confirm Schedule Deletion", "ยืนยันการลบกำหนดการแสดง"); ?>
                    </h3>
                    <span class="text-zinc-400 text-xs font-mono block mt-1">
                        <?php echo t("Remove artist performance from timetable", "ลบโชว์ดนตรีสดออกจากตารางประจำสัปดาห์"); ?>
                    </span>
                </div>
            </div>
            <button onclick="closeDeleteMusicModal()" type="button" class="text-zinc-400 hover:text-white transition-colors">
                <span class="material-symbols-outlined text-xl">close</span>
            </button>
        </div>

        <!-- Modal Body -->
        <div class="p-5">
            <p class="text-zinc-300 text-sm mb-4 font-sans leading-relaxed">
                <?php echo t("Are you sure you want to delete this live music performance schedule?", "คุณแน่ใจหรือไม่ว่าต้องการลบกำหนดการแสดงดนตรีสดนี้ออกจากตารางประจำสัปดาห์?"); ?>
            </p>

            <!-- Music Info Badge -->
            <div class="bg-zinc-900/90 border border-zinc-800 rounded-xl p-3.5 mb-4 font-mono text-xs space-y-2">
                <div class="flex justify-between items-center border-b border-zinc-800/80 pb-2">
                    <span class="text-zinc-400"><?php echo t("Artist / Band:", "ศิลปิน / วงดนตรี:"); ?></span>
                    <span id="delete-music-artist-display" class="font-semibold text-warning text-sm"></span>
                </div>
                <div class="flex justify-between items-center pt-0.5">
                    <span class="text-zinc-400"><?php echo t("Showtime Slot:", "วันและเวลาขึ้นแสดง:"); ?></span>
                    <span id="delete-music-time-display" class="text-zinc-200"></span>
                </div>
            </div>

            <!-- Caution Alert -->
            <div class="bg-red-950/40 border border-red-900/60 text-red-300 p-3 rounded-lg text-xs font-mono flex items-start gap-2">
                <span class="material-symbols-outlined text-sm leading-none mt-0.5 shrink-0 text-red-400">warning</span>
                <span><?php echo t("Action cannot be undone. Customers will no longer see this session.", "การดำเนินการนี้จะไม่สามารถย้อนกลับได้ กำหนดการจะถูกลบออกทันที"); ?></span>
            </div>
        </div>

        <!-- Modal Footer -->
        <div class="bg-zinc-900/60 px-5 py-3.5 border-t border-zinc-800 flex items-center justify-end gap-2.5">
            <button onclick="closeDeleteMusicModal()" type="button" class="px-4 py-2 bg-zinc-900 hover:bg-zinc-800 text-zinc-300 border border-zinc-800 rounded-xl text-xs font-mono transition-colors">
                <?php echo t("Cancel", "ยกเลิก"); ?>
            </button>
            <a id="confirm-delete-music-btn" href="#" class="px-4 py-2 bg-red-600 hover:bg-red-500 text-white font-bold rounded-xl text-xs font-mono transition-all flex items-center gap-1.5 shadow-lg shadow-red-600/20 active:scale-95 text-decoration-none">
                <span class="material-symbols-outlined text-base">delete</span>
                <span><?php echo t("Confirm Delete", "ยืนยันการลบกำหนดการ"); ?></span>
            </a>
        </div>
    </div>
</div>

<!-- Custom Delete Photo Modal -->
<div id="deletePhotoModal" class="fixed inset-0 z-50 hidden flex items-center justify-center bg-black/80 backdrop-blur-sm p-4">
    <div class="bg-zinc-950 border border-zinc-800 rounded-2xl shadow-2xl w-full max-w-sm overflow-hidden transform transition-all text-center p-6">
        <div class="w-12 h-12 rounded-2xl bg-red-950/80 border border-red-900 mx-auto mb-3 flex items-center justify-center text-red-500">
            <span class="material-symbols-outlined text-2xl">no_photography</span>
        </div>
        <h3 class="font-anton text-warning tracking-wider text-lg uppercase m-0 mb-1">
            <?php echo t("Delete Atmosphere Photo?", "ยืนยันการลบรูปภาพบรรยากาศ"); ?>
        </h3>
        <p class="text-zinc-400 text-xs font-sans mb-5 leading-relaxed">
            <?php echo t("Are you sure you want to delete this photo from gallery?", "คุณแน่ใจหรือไม่ว่าต้องการลบรูปภาพบรรยากาศชิ้นนี้ออกจากแกลเลอรีร้าน?"); ?>
        </p>
        <div class="flex items-center justify-center gap-2">
            <button onclick="closeDeletePhotoModal()" type="button" class="flex-1 px-4 py-2 bg-zinc-900 hover:bg-zinc-800 text-zinc-300 border border-zinc-800 rounded-xl text-xs font-mono transition-colors">
                <?php echo t("Cancel", "ยกเลิก"); ?>
            </button>
            <a id="confirm-delete-photo-btn" href="#" class="flex-1 px-4 py-2 bg-red-600 hover:bg-red-500 text-white font-bold rounded-xl text-xs font-mono transition-all flex items-center justify-center gap-1 shadow-lg shadow-red-600/20 active:scale-95 text-decoration-none">
                <span><?php echo t("Delete Photo", "ยืนยันลบภาพ"); ?></span>
            </a>
        </div>
    </div>
</div>

<?php require_once 'admin_footer.php'; ?>
