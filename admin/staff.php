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
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$del_id]);
            $success = t("Staff account deleted successfully.", "ลบบัญชีพนักงานเรียบร้อยแล้ว.");
        } catch (Exception $e) {
            $error = "Error: " . $e->getMessage();
        }
    }
}

// Handle GET loader for edit
if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])) {
    $edit_id = $_GET['id'];
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$edit_id]);
    $user_details = $stmt->fetch();
    
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
                    // Check duplicate email
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
                    $stmt->execute([$email]);
                    if ($stmt->fetchColumn() > 0) {
                        $error = "This Email Address is already registered.";
                    } else {
                        $id = 'usr_' . uniqid();
                        $pw_hash = password_hash($password, PASSWORD_DEFAULT);
                        $stmt = $pdo->prepare("INSERT INTO users (id, email, password_hash, name, role) VALUES (?, ?, ?, ?, ?)");
                        $stmt->execute([$id, $email, $pw_hash, $name, $role]);
                        
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
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ? AND id != ?");
                $stmt->execute([$email, $id]);
                if ($stmt->fetchColumn() > 0) {
                    $error = "This Email Address is already registered to another user.";
                } else {
                    if ($password) {
                        // Update with new password
                        $pw_hash = password_hash($password, PASSWORD_DEFAULT);
                        $stmt = $pdo->prepare("UPDATE users SET name = ?, email = ?, password_hash = ?, role = ? WHERE id = ?");
                        $stmt->execute([$name, $email, $pw_hash, $role, $id]);
                    } else {
                        // Update without password change
                        $stmt = $pdo->prepare("UPDATE users SET name = ?, email = ?, role = ? WHERE id = ?");
                        $stmt->execute([$name, $email, $role, $id]);
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
$stmt = $pdo->query("SELECT * FROM users ORDER BY role, name");
$all_staff = $stmt->fetchAll();
?>

<div class="flex justify-between items-center border-b border-zinc-900 pb-4 mb-6">
    <div>
        <h1 class="font-anton text-warning text-uppercase tracking-wider text-2xl m-0"><?php echo t("Staff Credentials Manager", "จัดการทีมงานพนักงาน"); ?></h1>
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
                    <input type="text" name="name" required placeholder="e.g. Somchai" class="shadcn-input" value="<?php echo htmlspecialchars($name); ?>">
                </div>

                <div class="flex flex-col gap-1.5">
                    <label class="text-xs uppercase text-zinc-400 font-medium tracking-wider"><?php echo t("Email Address", "อีเมลล็อกอิน"); ?></label>
                    <input type="email" name="email" required placeholder="e.g. staff@chithole.com" class="shadcn-input" value="<?php echo htmlspecialchars($email); ?>">
                </div>

                <div class="flex flex-col gap-1.5">
                    <label class="text-xs uppercase text-zinc-400 font-medium tracking-wider">
                        <?php echo t("Password", "รหัสผ่าน"); ?>
                        <?php if ($is_editing): ?>
                            <span class="text-zinc-500 text-[10px] lowercase normal-case"><?php echo t("(Leave blank to keep current)", "(เว้นว่างไว้เพื่อรักษารหัสผ่านเดิม)"); ?></span>
                        <?php endif; ?>
                    </label>
                    <input type="password" name="password" placeholder="••••••••" class="shadcn-input" <?php echo $is_editing ? '' : 'required'; ?>>
                </div>

                <div class="flex flex-col gap-1.5">
                    <label class="text-xs uppercase text-zinc-400 font-medium tracking-wider"><?php echo t("System Role", "ระดับสิทธิ์ระบบ"); ?></label>
                    <select name="role" required class="shadcn-input bg-zinc-950">
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
                                                <a href="staff.php?action=delete&id=<?php echo $usr['id']; ?>" class="p-1 text-zinc-400 hover:text-red-400 transition-colors" onclick="return confirm('<?php echo t('Are you sure you want to delete this staff account?', 'คุณแน่ใจว่าต้องการลบพนักงานคนนี้?'); ?>')" title="Delete"><span class="material-symbols-outlined text-lg leading-none">delete</span></a>
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

<?php require_once 'admin_footer.php'; ?>
