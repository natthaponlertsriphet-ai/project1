<?php
require_once '../db.php';
require_once 'admin_header.php';

$error = null;
$success = null;

// Form inputs state variables
$is_editing = false;
$edit_id = '';
$name = '';
$email = '';
$role = 'STAFF';

// Handle DELETE staff request
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $del_id = $_GET['id'];
    
    // Prevent self-deletion
    if ($del_id === $_SESSION['user_id']) {
        $error = "You cannot delete your own account.";
    } else {
        try {
            $stmt = $pdo->prepare("DELETE FROM admin WHERE admin_id = ?");
            $stmt->execute([$del_id]);
            
            $stmt2 = $pdo->prepare("DELETE FROM staff WHERE staff_id = ?");
            $stmt2->execute([$del_id]);
            
            $success = t("Staff account deleted successfully.", "ลบบัญชีพนักงานเรียบร้อยแล้ว.");
        } catch (Exception $e) {
            $error = "Error: " . $e->getMessage();
        }
    }
}

// Handle GET loader for edit
if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])) {
    $edit_id = $_GET['id'];
    
    $stmt = $pdo->prepare("SELECT admin_id AS id, admin_name AS name, admin_email AS email, role FROM admin WHERE admin_id = ?");
    $stmt->execute([$edit_id]);
    $user_details = $stmt->fetch();
    
    if (!$user_details) {
        $stmt = $pdo->prepare("SELECT staff_id AS id, staff_name AS name, staff_email AS email, role FROM staff WHERE staff_id = ?");
        $stmt->execute([$edit_id]);
        $user_details = $stmt->fetch();
    }
    
    if ($user_details) {
        $is_editing = true;
        $name = $user_details['name'];
        $email = $user_details['email'];
        $role = $user_details['role'];
    }
}

// Handle POST submissions (Create/Update with Password Hashing)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $role = trim($_POST['role'] ?? 'STAFF');
    
    if (!$name || !$email) {
        $error = "Please fill in all required fields.";
    } else {
        if ($_POST['action'] === 'create_staff') {
            if (!$password) {
                $error = "Password is required for new accounts.";
            } else {
                try {
                    // Check duplicate email in both tables
                    $stmt1 = $pdo->prepare("SELECT COUNT(*) FROM admin WHERE admin_email = ?");
                    $stmt1->execute([$email]);
                    $stmt2 = $pdo->prepare("SELECT COUNT(*) FROM staff WHERE staff_email = ?");
                    $stmt2->execute([$email]);
                    
                    if ($stmt1->fetchColumn() > 0 || $stmt2->fetchColumn() > 0) {
                        $error = "This Email Address is already registered.";
                    } else {
                        $pw_hash = password_hash($password, PASSWORD_DEFAULT);
                        if ($role === 'ADMIN') {
                            $id = 'admin_' . uniqid();
                            $stmt = $pdo->prepare("INSERT INTO admin (admin_id, admin_email, admin_password_hash, admin_name, role) VALUES (?, ?, ?, ?, 'ADMIN')");
                            $stmt->execute([$id, $email, $pw_hash, $name]);
                        } else {
                            $id = 'staff_' . uniqid();
                            $stmt = $pdo->prepare("INSERT INTO staff (staff_id, staff_email, staff_password_hash, staff_name, role) VALUES (?, ?, ?, ?, 'STAFF')");
                            $stmt->execute([$id, $email, $pw_hash, $name]);
                        }
                        
                        // Reset
                        $name = $email = '';
                        $role = 'STAFF';
                        $success = t("Staff registered successfully!", "เพิ่มบัญชีพนักงานใหม่เรียบร้อยแล้ว!");
                    }
                } catch (Exception $e) {
                    $error = "Error: " . $e->getMessage();
                }
            }
        } elseif ($_POST['action'] === 'update_staff') {
            $id = $_POST['edit_id'];
            try {
                // Check duplicate email (excluding itself)
                $stmt1 = $pdo->prepare("SELECT COUNT(*) FROM admin WHERE admin_email = ? AND admin_id != ?");
                $stmt1->execute([$email, $id]);
                $stmt2 = $pdo->prepare("SELECT COUNT(*) FROM staff WHERE staff_email = ? AND staff_id != ?");
                $stmt2->execute([$email, $id]);
                
                if ($stmt1->fetchColumn() > 0 || $stmt2->fetchColumn() > 0) {
                    $error = "This Email Address is already registered to another user.";
                } else {
                    // Fetch existing hash first
                    $stmtCheckA = $pdo->prepare("SELECT admin_password_hash FROM admin WHERE admin_id = ?");
                    $stmtCheckA->execute([$id]);
                    $old_hash = $stmtCheckA->fetchColumn();
                    
                    if (!$old_hash) {
                        $stmtCheckS = $pdo->prepare("SELECT staff_password_hash FROM staff WHERE staff_id = ?");
                        $stmtCheckS->execute([$id]);
                        $old_hash = $stmtCheckS->fetchColumn();
                    }
                    
                    $pw_hash = $password ? password_hash($password, PASSWORD_DEFAULT) : $old_hash;
                    
                    // Clean up and write to target table (handles potential role switches cleanly)
                    $pdo->prepare("DELETE FROM admin WHERE admin_id = ?")->execute([$id]);
                    $pdo->prepare("DELETE FROM staff WHERE staff_id = ?")->execute([$id]);
                    
                    if ($role === 'ADMIN') {
                        $stmt = $pdo->prepare("INSERT INTO admin (admin_id, admin_email, admin_password_hash, admin_name, role) VALUES (?, ?, ?, ?, 'ADMIN')");
                        $stmt->execute([$id, $email, $pw_hash, $name]);
                    } else {
                        $stmt = $pdo->prepare("INSERT INTO staff (staff_id, staff_email, staff_password_hash, staff_name, role) VALUES (?, ?, ?, ?, 'STAFF')");
                        $stmt->execute([$id, $email, $pw_hash, $name]);
                    }
                    
                    // Reset
                    $is_editing = false;
                    $name = $email = '';
                    $role = 'STAFF';
                    $success = t("Staff credentials updated successfully!", "แก้ไขข้อมูลพนักงานสำเร็จ!");
                }
            } catch (Exception $e) {
                $error = "Error: " . $e->getMessage();
            }
        }
    }
}

// Fetch all staff users
$stmt = $pdo->query("
    SELECT admin_id AS id, admin_name AS name, admin_email AS email, role FROM admin
    UNION ALL
    SELECT staff_id AS id, staff_name AS name, staff_email AS email, role FROM staff
    ORDER BY role, name
");
$all_staff = $stmt->fetchAll();
?>

<div class="flex justify-between items-center border-b border-zinc-900 pb-4 mb-6">
    <div>
        <h1 class="font-anton text-warning text-uppercase tracking-wider text-2xl m-0"><?php echo t("Staff Credentials Manager", "จัดการข้อมูลพนักงาน"); ?></h1>
        <p class="text-zinc-500 text-xs mt-1 uppercase tracking-widest font-mono"><?php echo t("Admin Dashboard / User Accounts", "แผงควบคุมผู้ดูแลระบบ / จัดการสิทธิ์และบัญชีทีมงาน"); ?></p>
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
                <span class="material-symbols-outlined text-xl leading-none">manage_accounts</span>
                <span><?php echo $is_editing ? t("Edit User Details", "แก้ไขข้อมูลทีมงาน") : t("Register Team User", "เพิ่มบัญชีทีมงาน"); ?></span>
            </h3>
            
            <form action="staff.php" method="POST" class="flex flex-col gap-4">
                <input type="hidden" name="action" value="<?php echo $is_editing ? 'update_staff' : 'create_staff'; ?>">
                <?php if ($is_editing): ?>
                    <input type="hidden" name="edit_id" value="<?php echo $edit_id; ?>">
                <?php endif; ?>

                <div class="flex flex-col gap-1.5">
                    <label class="text-xs uppercase text-zinc-400 font-medium tracking-wider"><?php echo t("Full Name", "ชื่อ-นามสกุล"); ?></label>
                    <input type="text" name="name" required oninvalid="this.setCustomValidity('<?php echo t('Please enter full name.', 'กรุณากรอกชื่อ-นามสกุลพนักงาน'); ?>')" oninput="this.setCustomValidity('')" placeholder="e.g. Somchai" class="shadcn-input" value="<?php echo htmlspecialchars($name); ?>">
                </div>

                <div class="flex flex-col gap-1.5">
                    <label class="text-xs uppercase text-zinc-400 font-medium tracking-wider"><?php echo t("Email Address", "อีเมลล็อกอิน"); ?></label>
                    <input type="email" name="email" required oninvalid="this.setCustomValidity('<?php echo t('Please enter email address.', 'กรุณากรอกอีเมลล็อกอิน'); ?>')" oninput="this.setCustomValidity('')" placeholder="e.g. staff@chithole.com" class="shadcn-input" value="<?php echo htmlspecialchars($email); ?>">
                </div>

                <div class="flex flex-col gap-1.5">
                    <label class="text-xs uppercase text-zinc-400 font-medium tracking-wider">
                        <?php echo t("Password", "รหัสผ่าน"); ?>
                        <?php if ($is_editing): ?>
                            <span class="text-zinc-500 text-[10px] lowercase normal-case"><?php echo t("(Leave blank to keep current)", "(เว้นว่างไว้เพื่อรักษารหัสผ่านเดิม)"); ?></span>
                        <?php endif; ?>
                    </label>
                    <input type="password" name="password" placeholder="••••••••" class="shadcn-input" <?php echo $is_editing ? '' : 'required oninvalid="this.setCustomValidity(\'' . t('Please enter password.', 'กรุณากรอกรหัสผ่าน') . '\')" oninput="this.setCustomValidity(\'\')"'; ?>>
                </div>

                <div class="flex flex-col gap-1.5">
                    <label class="text-xs uppercase text-zinc-400 font-medium tracking-wider"><?php echo t("System Role", "ระดับสิทธิ์ระบบ"); ?></label>
                    <select name="role" required oninvalid="this.setCustomValidity('<?php echo t('Please select a system role.', 'กรุณาเลือกระดับสิทธิ์ระบบ'); ?>')" onchange="this.setCustomValidity('')" class="shadcn-input bg-zinc-950">
                        <option value="STAFF" <?php echo $role === 'STAFF' ? 'selected' : ''; ?>><?php echo t("STAFF (พนักงานบริการลูกค้า)", "STAFF (พนักงานบริการลูกค้า)"); ?></option>
                        <option value="ADMIN" <?php echo $role === 'ADMIN' ? 'selected' : ''; ?>><?php echo t("ADMIN (ผู้ดูแลระบบหลังบ้าน)", "ADMIN (ผู้ดูแลระบบหลังบ้าน)"); ?></option>
                    </select>
                </div>

                <div class="flex gap-2 mt-2">
                    <button type="submit" class="shadcn-btn-primary flex-grow">
                        <?php echo $is_editing ? t("Update Staff", "อัปเดตสิทธิ์") : t("Register Staff", "บันทึกบัญชีพนักงาน"); ?>
                    </button>
                    <?php if ($is_editing): ?>
                        <a href="staff.php" class="shadcn-btn-outline"><?php echo t("Cancel", "ยกเลิก"); ?></a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <!-- Right Column: Inventory Table -->
    <div class="lg:col-span-8">
        <div class="shadcn-card">
            <h3 class="font-anton text-warning text-uppercase tracking-wider mb-6 flex items-center gap-2 text-lg">
                <span class="material-symbols-outlined text-xl leading-none">groups</span>
                <span><?php echo t("Team Accounts Inventory", "รายชื่อผู้มีสิทธิ์ใช้งานหลังบ้านทั้งหมด"); ?> (<?php echo count($all_staff); ?>)</span>
            </h3>
            
            <div class="shadcn-table-container">
                <table class="shadcn-table">
                    <thead>
                        <tr>
                            <th class="font-sans text-xs uppercase tracking-wider text-zinc-400"><?php echo t("Name", "ชื่อพนักงาน"); ?></th>
                            <th class="font-sans text-xs uppercase tracking-wider text-zinc-400"><?php echo t("Email", "อีเมล"); ?></th>
                            <th class="font-sans text-xs uppercase tracking-wider text-zinc-400 text-center"><?php echo t("Role", "ระดับสิทธิ์"); ?></th>
                            <th class="font-sans text-xs uppercase tracking-wider text-zinc-400 text-center" style="width: 15%;"><?php echo t("Actions", "จัดการ"); ?></th>
                        </tr>
                    </thead>
                    <tbody class="font-sans text-sm text-zinc-300">
                        <?php if (empty($all_staff)): ?>
                            <tr>
                                <td colspan="4" class="text-center py-8 text-zinc-500">
                                    <?php echo t("No staff registered.", "ยังไม่มีการลงทะเบียนพนักงาน"); ?>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($all_staff as $usr): ?>
                                <tr>
                                    <td class="font-semibold text-zinc-100"><?php echo htmlspecialchars($usr['name']); ?></td>
                                    <td class="text-zinc-400"><?php echo htmlspecialchars($usr['email']); ?></td>
                                    <td class="text-center">
                                        <span class="badge py-1 px-2.5 rounded text-xs" style="
                                            <?php echo $usr['role'] === 'ADMIN' ? 'background-color: rgba(234, 179, 8, 0.1); border: 1px solid rgba(234, 179, 8, 0.25); color: #facc15;' : 'background-color: rgba(59, 130, 246, 0.1); border: 1px solid rgba(59, 130, 246, 0.25); color: #60a5fa;'; ?>
                                        ">
                                            <?php echo $usr['role']; ?>
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <div class="flex justify-center gap-1">
                                            <a href="staff.php?action=edit&id=<?php echo $usr['id']; ?>" class="p-1 text-zinc-400 hover:text-warning transition-colors" title="Edit"><span class="material-symbols-outlined text-lg leading-none">edit</span></a>
                                            <?php if ($usr['id'] !== $_SESSION['user_id']): ?>
                                                <a href="javascript:void(0)" onclick="confirmDeleteStaff('<?php echo $usr['id']; ?>', '<?php echo htmlspecialchars($usr['name']); ?>', '<?php echo htmlspecialchars($usr['email']); ?>', '<?php echo htmlspecialchars($usr['role']); ?>')" class="p-1 text-zinc-400 hover:text-red-400 transition-colors" title="<?php echo t('Delete Staff Account', 'ลบข้อมูลพนักงาน'); ?>"><span class="material-symbols-outlined text-lg leading-none">delete</span></a>
                                            <?php else: ?>
                                                <span class="p-1 text-zinc-600 opacity-50" title="Self-Account (Locked)"><span class="material-symbols-outlined text-lg leading-none">lock</span></span>
                                            <?php endif; ?>
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
    function confirmDeleteStaff(staffId, staffName, staffEmail, staffRole) {
        document.getElementById('delete-staff-name-display').innerText = staffName;
        document.getElementById('delete-staff-email-display').innerText = staffEmail;
        document.getElementById('delete-staff-role-display').innerText = staffRole;
        document.getElementById('confirm-delete-staff-btn').href = 'staff.php?action=delete&id=' + encodeURIComponent(staffId);
        document.getElementById('deleteStaffModal').classList.remove('hidden');
    }

    function closeDeleteStaffModal() {
        document.getElementById('deleteStaffModal').classList.add('hidden');
    }
</script>

<!-- Custom Delete Staff Modal Dialog -->
<div id="deleteStaffModal" class="fixed inset-0 z-50 hidden flex items-center justify-center bg-black/80 backdrop-blur-sm p-4">
    <div class="bg-zinc-950 border border-zinc-800 rounded-2xl shadow-2xl w-full max-w-md overflow-hidden transform transition-all">
        <!-- Modal Header -->
        <div class="bg-zinc-900/90 px-5 py-4 border-b border-zinc-800 flex items-center justify-between">
            <div class="flex items-center gap-2.5">
                <div class="w-9 h-9 rounded-xl bg-red-950/80 border border-red-900 flex items-center justify-center text-red-500">
                    <span class="material-symbols-outlined text-xl">person_remove</span>
                </div>
                <div>
                    <h3 class="font-anton text-warning tracking-wider text-lg uppercase m-0 leading-none">
                        <?php echo t("Confirm Staff Deletion", "ยืนยันการลบข้อมูลพนักงาน"); ?>
                    </h3>
                    <span class="text-zinc-400 text-xs font-mono block mt-1">
                        <?php echo t("Revoke user access & staff account", "ลบสิทธิ์การเข้าใช้งานและบัญชีผู้ใช้ระบบ"); ?>
                    </span>
                </div>
            </div>
            <button onclick="closeDeleteStaffModal()" type="button" class="text-zinc-400 hover:text-white transition-colors">
                <span class="material-symbols-outlined text-xl">close</span>
            </button>
        </div>

        <!-- Modal Body -->
        <div class="p-5">
            <p class="text-zinc-300 text-sm mb-4 font-sans leading-relaxed">
                <?php echo t("Are you sure you want to delete this staff account from the system?", "คุณแน่ใจหรือไม่ว่าต้องการลบพนักงานคนนี้ออกจากระบบ? บัญชีและสิทธิ์การเข้าใช้งานระบบของผู้ใช้รายนี้จะถูกยกเลิกทันที"); ?>
            </p>

            <!-- Staff Info Badge -->
            <div class="bg-zinc-900/90 border border-zinc-800 rounded-xl p-3.5 mb-4 font-mono text-xs space-y-2">
                <div class="flex justify-between items-center border-b border-zinc-800/80 pb-2">
                    <span class="text-zinc-400"><?php echo t("Staff Name:", "ชื่อพนักงาน:"); ?></span>
                    <span id="delete-staff-name-display" class="font-semibold text-zinc-100 text-sm"></span>
                </div>
                <div class="flex justify-between items-center border-b border-zinc-800/80 pb-2">
                    <span class="text-zinc-400"><?php echo t("Email / Username:", "อีเมล / ชื่อบัญชี:"); ?></span>
                    <span id="delete-staff-email-display" class="text-zinc-300"></span>
                </div>
                <div class="flex justify-between items-center pt-0.5">
                    <span class="text-zinc-400"><?php echo t("System Role:", "ตำแหน่ง / สิทธิ์:"); ?></span>
                    <span id="delete-staff-role-display" class="bg-blue-950 text-blue-400 border border-blue-900 px-2 py-0.5 rounded font-bold text-[10px]"></span>
                </div>
            </div>

            <!-- Caution Alert -->
            <div class="bg-red-950/40 border border-red-900/60 text-red-300 p-3 rounded-lg text-xs font-mono flex items-start gap-2">
                <span class="material-symbols-outlined text-sm leading-none mt-0.5 shrink-0 text-red-400">warning</span>
                <span><?php echo t("Action cannot be undone. The staff member will no longer be able to log in.", "การดำเนินการนี้จะไม่สามารถย้อนกลับได้ พนักงานจะไม่สามารถเข้าสู่ระบบได้อีก"); ?></span>
            </div>
        </div>

        <!-- Modal Footer -->
        <div class="bg-zinc-900/60 px-5 py-3.5 border-t border-zinc-800 flex items-center justify-end gap-2.5">
            <button onclick="closeDeleteStaffModal()" type="button" class="px-4 py-2 bg-zinc-900 hover:bg-zinc-800 text-zinc-300 border border-zinc-800 rounded-xl text-xs font-mono transition-colors">
                <?php echo t("Cancel", "ยกเลิก"); ?>
            </button>
            <a id="confirm-delete-staff-btn" href="#" class="px-4 py-2 bg-red-600 hover:bg-red-500 text-white font-bold rounded-xl text-xs font-mono transition-all flex items-center gap-1.5 shadow-lg shadow-red-600/20 active:scale-95 text-decoration-none">
                <span class="material-symbols-outlined text-base">person_remove</span>
                <span><?php echo t("Confirm Delete", "ยืนยันการลบพนักงาน"); ?></span>
            </a>
        </div>
    </div>
</div>

<?php require_once 'admin_footer.php'; ?>
