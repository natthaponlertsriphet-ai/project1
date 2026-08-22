<?php
require_once '../db.php';
// Start session for flash messaging if not active
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Temporary Translation Helper (defined locally before admin_header.php load)
if (!function_exists('t')) {
    function t($en, $th) {
        $lang = $_SESSION['lang'] ?? 'th';
        return $lang === 'th' ? $th : $en;
    }
}

// Handle Action (Confirm/Cancel Bookings) with Post-Redirect-Get
if (isset($_GET['action']) && isset($_GET['booking_id'])) {
    $b_id = $_GET['booking_id'];
    $act = $_GET['action'];
    $target_tab = $_GET['tab'] ?? 'pending';
    
    try {
        // Fetch booking details first
        $stmt = $pdo->prepare("SELECT * FROM bookings WHERE id = ?");
        $stmt->execute([$b_id]);
        $b_details = $stmt->fetch();
        
        if ($b_details) {
            if ($act === 'confirm') {
                // Confirm booking
                $stmt = $pdo->prepare("UPDATE bookings SET status = 'CONFIRMED' WHERE id = ?");
                $stmt->execute([$b_id]);
                
                // Update table status to OCCUPIED if booking is for today
                if ($b_details['date'] === date('Y-m-d')) {
                    $stmt = $pdo->prepare("UPDATE tables SET status = 'OCCUPIED' WHERE id = ?");
                    $stmt->execute([$b_details['table_id']]);
                }
                $_SESSION['action_success'] = t("Booking confirmed successfully!", "ยืนยันรายการจองเรียบร้อยแล้ว!");
            } elseif ($act === 'cancel') {
                // Cancel booking
                $stmt = $pdo->prepare("UPDATE bookings SET status = 'CANCELLED' WHERE id = ?");
                $stmt->execute([$b_id]);
                
                // Update table status to AVAILABLE if booking was today
                if ($b_details['date'] === date('Y-m-d')) {
                    $stmt = $pdo->prepare("UPDATE tables SET status = 'AVAILABLE' WHERE id = ?");
                    $stmt->execute([$b_details['table_id']]);
                }
                $_SESSION['action_success'] = t("Booking cancelled successfully.", "ยกเลิกรายการจองเรียบร้อยแล้ว.");
            }
        } else {
            $_SESSION['action_error'] = t("Booking not found.", "ไม่พบข้อมูลการจองดังกล่าว.");
        }
    } catch (Exception $e) {
        $_SESSION['action_error'] = "Error: " . $e->getMessage();
    }
    
    header("Location: index.php?tab=" . urlencode($target_tab));
    exit;
}

require_once 'admin_header.php';

// Retrieve session flash notifications
$success_msg = $_SESSION['action_success'] ?? null;
$error_msg = $_SESSION['action_error'] ?? null;
unset($_SESSION['action_success'], $_SESSION['action_error']);

// Tab Handler
$active_tab = $_GET['tab'] ?? 'pending';
if (!in_array($active_tab, ['pending', 'confirmed', 'cancelled'])) {
    $active_tab = 'pending';
}

// Fetch analytics counts
$active_beers_count = $pdo->query("SELECT COUNT(*) FROM beers WHERE active = 1")->fetchColumn();
$pending_bookings_count = $pdo->query("SELECT COUNT(*) FROM bookings WHERE status = 'PENDING'")->fetchColumn();
$active_promos_count = $pdo->query("SELECT COUNT(*) FROM promotions WHERE active = 1")->fetchColumn();

// Fetch bookings by status
try {
    $stmt = $pdo->query("SELECT b.*, t.number AS table_number, t.zone AS table_zone FROM bookings b LEFT JOIN tables t ON b.table_id = t.id WHERE b.status = 'PENDING' ORDER BY b.date, b.time_slot");
    $pending_bookings = $stmt->fetchAll();
} catch (Exception $e) {
    $pending_bookings = [];
}

try {
    $stmt = $pdo->query("SELECT b.*, t.number AS table_number, t.zone AS table_zone FROM bookings b LEFT JOIN tables t ON b.table_id = t.id WHERE b.status = 'CONFIRMED' ORDER BY b.date DESC, b.time_slot DESC");
    $confirmed_bookings = $stmt->fetchAll();
} catch (Exception $e) {
    $confirmed_bookings = [];
}

try {
    $stmt = $pdo->query("SELECT b.*, t.number AS table_number, t.zone AS table_zone FROM bookings b LEFT JOIN tables t ON b.table_id = t.id WHERE b.status = 'CANCELLED' ORDER BY b.date DESC, b.time_slot DESC");
    $cancelled_bookings = $stmt->fetchAll();
} catch (Exception $e) {
    $cancelled_bookings = [];
}

$display_bookings = [];
if ($active_tab === 'pending') {
    $display_bookings = $pending_bookings;
} elseif ($active_tab === 'confirmed') {
    $display_bookings = $confirmed_bookings;
} else {
    $display_bookings = $cancelled_bookings;
}

if (!function_exists('formatDateStr')) {
    function formatDateStr($date_str) {
        $parts = explode('-', $date_str);
        if (count($parts) !== 3) return $date_str;
        
        $year = (int)$parts[0];
        $month = (int)$parts[1];
        $day = (int)$parts[2];
        
        $thai_months = [
            1 => 'ม.ค.', 2 => 'ก.พ.', 3 => 'มี.ค.', 4 => 'เม.ย.', 5 => 'พ.ค.', 6 => 'มิ.ย.',
            7 => 'ก.ค.', 8 => 'ส.ค.', 9 => 'ก.ย.', 10 => 'ต.ค.', 11 => 'พ.ย.', 12 => 'ธ.ค.'
        ];
        
        $en_months = [
            1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr', 5 => 'May', 6 => 'Jun',
            7 => 'Jul', 8 => 'Aug', 9 => 'Sep', 10 => 'Oct', 11 => 'Nov', 12 => 'Dec'
        ];
        
        global $lang;
        if ($lang === 'th') {
            $thai_year = $year + 543;
            return $day . ' ' . $thai_months[$month] . ' ' . substr($thai_year, 2);
        } else {
            return $day . ' ' . $en_months[$month] . ' ' . substr($year, 2);
        }
    }
}

if (!function_exists('formatMonth')) {
    function formatMonth($month_str) {
        $parts = explode('-', $month_str);
        if (count($parts) !== 2) return $month_str;
        
        $year = (int)$parts[0];
        $month = (int)$parts[1];
        
        $thai_months = [
            1 => 'ม.ค.', 2 => 'ก.พ.', 3 => 'มี.ค.', 4 => 'เม.ย.', 5 => 'พ.ค.', 6 => 'มิ.ย.',
            7 => 'ก.ค.', 8 => 'ส.ค.', 9 => 'ก.ย.', 10 => 'ต.ค.', 11 => 'พ.ย.', 12 => 'ธ.ค.'
        ];
        
        $en_months = [
            1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr', 5 => 'May', 6 => 'Jun',
            7 => 'Jul', 8 => 'Aug', 9 => 'Sep', 10 => 'Oct', 11 => 'Nov', 12 => 'Dec'
        ];
        
        global $lang;
        if ($lang === 'th') {
            $thai_year = $year + 543;
            return $thai_months[$month] . ' ' . $thai_year;
        } else {
            return $en_months[$month] . ' ' . $year;
        }
    }
}

// Fetch summaries
try {
    $stmt = $pdo->query("
        SELECT date, COUNT(*) as total, SUM(CASE WHEN status = 'CONFIRMED' THEN 1 ELSE 0 END) as confirmed 
        FROM bookings 
        GROUP BY date 
        ORDER BY date DESC 
        LIMIT 6
    ");
    $daily_summary = $stmt->fetchAll();
} catch (Exception $e) {
    $daily_summary = [];
}

try {
    $stmt = $pdo->query("
        SELECT SUBSTRING(date, 1, 7) as month, COUNT(*) as total, SUM(CASE WHEN status = 'CONFIRMED' THEN 1 ELSE 0 END) as confirmed 
        FROM bookings 
        GROUP BY month 
        ORDER BY month DESC 
        LIMIT 6
    ");
    $monthly_summary = $stmt->fetchAll();
} catch (Exception $e) {
    $monthly_summary = [];
}

try {
    $stmt = $pdo->query("
        SELECT SUBSTRING(date, 1, 4) as year, COUNT(*) as total, SUM(CASE WHEN status = 'CONFIRMED' THEN 1 ELSE 0 END) as confirmed 
        FROM bookings 
        GROUP BY year 
        ORDER BY year DESC
    ");
    $yearly_summary = $stmt->fetchAll();
} catch (Exception $e) {
    $yearly_summary = [];
}
?>

<div class="flex justify-between items-center border-b border-zinc-900 pb-4 mb-6">
    <div>
        <h1 class="font-anton text-warning text-uppercase tracking-wider text-2xl m-0"><?php echo t("Dashboard Overview", "ภาพรวมระบบจัดการ"); ?></h1>
        <p class="text-zinc-500 text-xs mt-1 uppercase tracking-widest font-mono"><?php echo t("Admin Console / Main Analytics", "คอนโซลผู้จัดการ / ข้อมูลวิเคราะห์หลัก"); ?></p>
    </div>
</div>

<!-- Flash Notifications -->
<?php if ($success_msg): ?>
    <div class="bg-emerald-950/40 border border-emerald-900 text-emerald-400 p-4 rounded-md text-xs font-mono mb-6 flex items-center gap-2">
        <span class="material-symbols-outlined text-sm leading-none">check_circle</span>
        <span>[SUCCESS]: <?php echo $success_msg; ?></span>
    </div>
<?php endif; ?>
<?php if ($error_msg): ?>
    <div class="bg-rose-950/40 border border-rose-900 text-rose-400 p-4 rounded-md text-xs font-mono mb-6 flex items-center gap-2">
        <span class="material-symbols-outlined text-sm leading-none">error</span>
        <span>[ERROR]: <?php echo $error_msg; ?></span>
    </div>
<?php endif; ?>

<!-- Statistics Card Row -->
<div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
    <div class="shadcn-card flex justify-between items-center">
        <div>
            <span class="text-xs uppercase text-zinc-500 font-medium tracking-wider"><?php echo t("Active Beers on Tap", "เบียร์สดที่บริการ"); ?></span>
            <span class="font-anton text-warning text-4xl mt-2 block"><?php echo sprintf("%02d", $active_beers_count); ?></span>
        </div>
        <span class="material-symbols-outlined text-zinc-600 text-4xl">sports_bar</span>
    </div>
    <div class="shadcn-card flex justify-between items-center border-t border-t-red-900/50">
        <div>
            <span class="text-xs uppercase text-zinc-500 font-medium tracking-wider"><?php echo t("Pending Bookings", "คิวจองที่รอการตรวจสอบ"); ?></span>
            <span class="font-anton text-red-500 text-4xl mt-2 block"><?php echo sprintf("%02d", $pending_bookings_count); ?></span>
        </div>
        <span class="material-symbols-outlined text-zinc-600 text-4xl">notifications_active</span>
    </div>

    <div class="shadcn-card flex justify-between items-center border-t border-t-blue-900/50">
        <div>
            <span class="text-xs uppercase text-zinc-500 font-medium tracking-wider"><?php echo t("Active Promotions", "โปรโมชันที่กำลังจัด"); ?></span>
            <span class="font-anton text-blue-500 text-4xl mt-2 block"><?php echo sprintf("%02d", $active_promos_count); ?></span>
        </div>
        <span class="material-symbols-outlined text-zinc-600 text-4xl">local_offer</span>
    </div>
</div>

<!-- Booking Summaries (Day, Month, Year) Grid -->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8 font-sans">
    
    <!-- Daily Summary Card -->
    <div class="shadcn-card">
        <h3 class="font-anton text-warning text-uppercase tracking-wider mb-4 flex items-center gap-2 text-sm border-b border-zinc-900 pb-2">
            <span class="material-symbols-outlined text-base text-zinc-500">calendar_today</span>
            <span><?php echo t("Daily Summary", "สรุปยอดจองรายวัน"); ?></span>
        </h3>
        <div class="overflow-x-auto">
            <table class="w-full text-xs text-left text-zinc-400">
                <thead>
                    <tr class="border-b border-zinc-900 text-zinc-500 uppercase">
                        <th class="pb-2 font-semibold"><?php echo t("Date", "วันที่"); ?></th>
                        <th class="pb-2 text-center font-semibold"><?php echo t("Total", "ยอดจอง"); ?></th>
                        <th class="pb-2 text-end font-semibold"><?php echo t("Approved", "ยืนยันแล้ว"); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($daily_summary)): ?>
                        <tr>
                            <td colspan="3" class="text-center py-4 text-zinc-600"><?php echo t("No records", "ไม่มีประวัติ"); ?></td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($daily_summary as $d): ?>
                            <tr class="border-b border-zinc-900/40 last:border-0 hover:bg-zinc-900/10">
                                <td class="py-2 text-zinc-300 font-mono"><?php echo formatDateStr($d['date']); ?></td>
                                <td class="py-2 text-center font-bold text-zinc-200"><?php echo $d['total']; ?></td>
                                <td class="py-2 text-end text-emerald-500 font-bold"><?php echo $d['confirmed']; ?> / <?php echo $d['total']; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Monthly Summary Card -->
    <div class="shadcn-card">
        <h3 class="font-anton text-warning text-uppercase tracking-wider mb-4 flex items-center gap-2 text-sm border-b border-zinc-900 pb-2">
            <span class="material-symbols-outlined text-base text-zinc-500">calendar_month</span>
            <span><?php echo t("Monthly Summary", "สรุปยอดจองรายเดือน"); ?></span>
        </h3>
        <div class="overflow-x-auto">
            <table class="w-full text-xs text-left text-zinc-400">
                <thead>
                    <tr class="border-b border-zinc-900 text-zinc-500 uppercase">
                        <th class="pb-2 font-semibold"><?php echo t("Month", "เดือน"); ?></th>
                        <th class="pb-2 text-center font-semibold"><?php echo t("Total", "ยอดจอง"); ?></th>
                        <th class="pb-2 text-end font-semibold"><?php echo t("Approved", "ยืนยันแล้ว"); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($monthly_summary)): ?>
                        <tr>
                            <td colspan="3" class="text-center py-4 text-zinc-600"><?php echo t("No records", "ไม่มีประวัติ"); ?></td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($monthly_summary as $m): ?>
                            <tr class="border-b border-zinc-900/40 last:border-0 hover:bg-zinc-900/10">
                                <td class="py-2 text-zinc-300 font-semibold"><?php echo formatMonth($m['month']); ?></td>
                                <td class="py-2 text-center font-bold text-zinc-200"><?php echo $m['total']; ?></td>
                                <td class="py-2 text-end text-emerald-500 font-bold"><?php echo $m['confirmed']; ?> / <?php echo $m['total']; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Yearly Summary Card -->
    <div class="shadcn-card">
        <h3 class="font-anton text-warning text-uppercase tracking-wider mb-4 flex items-center gap-2 text-sm border-b border-zinc-900 pb-2">
            <span class="material-symbols-outlined text-base text-zinc-500">date_range</span>
            <span><?php echo t("Yearly Summary", "สรุปยอดจองรายปี"); ?></span>
        </h3>
        <div class="overflow-x-auto">
            <table class="w-full text-xs text-left text-zinc-400">
                <thead>
                    <tr class="border-b border-zinc-900 text-zinc-500 uppercase">
                        <th class="pb-2 font-semibold"><?php echo t("Year", "ปี"); ?></th>
                        <th class="pb-2 text-center font-semibold"><?php echo t("Total", "ยอดจอง"); ?></th>
                        <th class="pb-2 text-end font-semibold"><?php echo t("Approved", "ยืนยันแล้ว"); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($yearly_summary)): ?>
                        <tr>
                            <td colspan="3" class="text-center py-4 text-zinc-600"><?php echo t("No records", "ไม่มีประวัติ"); ?></td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($yearly_summary as $y): ?>
                            <tr class="border-b border-zinc-900/40 last:border-0 hover:bg-zinc-900/10">
                                <td class="py-2 text-zinc-300 font-bold font-mono"><?php echo $lang === 'th' ? ($y['year'] + 543) : $y['year']; ?></td>
                                <td class="py-2 text-center font-bold text-zinc-200"><?php echo $y['total']; ?></td>
                                <td class="py-2 text-end text-emerald-500 font-bold"><?php echo $y['confirmed']; ?> / <?php echo $y['total']; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<!-- Reservation Management Tabs -->
<div class="flex gap-2 mb-6 border-b border-zinc-900 pb-px">
    <a href="index.php?tab=pending" class="py-2.5 px-4 text-xs font-anton text-uppercase tracking-wider border-b-2 transition-all <?php echo $active_tab === 'pending' ? 'text-warning border-warning' : 'text-zinc-500 border-transparent hover:text-zinc-300'; ?>">
        <?php echo t("Pending Requests", "รายการส่งคำขอรออนุมัติ"); ?> (<?php echo count($pending_bookings); ?>)
    </a>
    <a href="index.php?tab=confirmed" class="py-2.5 px-4 text-xs font-anton text-uppercase tracking-wider border-b-2 transition-all <?php echo $active_tab === 'confirmed' ? 'text-emerald-500 border-emerald-500' : 'text-zinc-500 border-transparent hover:text-zinc-300'; ?>">
        <?php echo t("Confirmed Bookings", "รายการที่ยืนยันแล้ว"); ?> (<?php echo count($confirmed_bookings); ?>)
    </a>
    <a href="index.php?tab=cancelled" class="py-2.5 px-4 text-xs font-anton text-uppercase tracking-wider border-b-2 transition-all <?php echo $active_tab === 'cancelled' ? 'text-rose-500 border-rose-500' : 'text-zinc-500 border-transparent hover:text-zinc-300'; ?>">
        <?php echo t("Cancelled Bookings", "รายการที่ถูกยกเลิก"); ?> (<?php echo count($cancelled_bookings); ?>)
    </a>
</div>

<!-- Reservations Table Container -->
<div class="shadcn-card">
    <div class="shadcn-table-container">
        <table class="shadcn-table">
            <thead>
                <tr>
                    <th class="font-sans text-xs uppercase tracking-wider text-zinc-400"><?php echo t("Customer", "ชื่อลูกค้า"); ?></th>
                    <th class="font-sans text-xs uppercase tracking-wider text-zinc-400"><?php echo t("Phone", "เบอร์โทรศัพท์"); ?></th>
                    <th class="font-sans text-xs uppercase tracking-wider text-zinc-400"><?php echo t("Date & Time", "วัน / เวลา"); ?></th>
                    <th class="font-sans text-xs uppercase tracking-wider text-zinc-400 text-center"><?php echo t("Table", "โต๊ะ"); ?></th>
                    <th class="font-sans text-xs uppercase tracking-wider text-zinc-400 text-center"><?php echo t("Pax", "จำนวนคน"); ?></th>
                    <th class="font-sans text-xs uppercase tracking-wider text-zinc-400 text-center" style="width: 25%;"><?php echo t("Review Operations", "การจัดการอนุมัติ"); ?></th>
                </tr>
            </thead>
            <tbody class="font-sans text-sm text-zinc-300">
                <?php if (empty($display_bookings)): ?>
                    <tr>
                        <td colspan="6" class="text-center py-8 text-zinc-500">
                            <?php 
                            if ($active_tab === 'pending') {
                                echo t("No pending reservations at the moment.", "ขณะนี้ไม่มีคิวจองโต๊ะที่รอตรวจสอบ");
                            } elseif ($active_tab === 'confirmed') {
                                echo t("No confirmed reservations found.", "ยังไม่มีคิวจองโต๊ะที่ยืนยันแล้ว");
                            } else {
                                echo t("No cancelled reservations.", "ยังไม่มีคิวจองโต๊ะที่ถูกยกเลิก");
                            }
                            ?>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($display_bookings as $b): ?>
                        <tr>
                            <td class="font-semibold text-zinc-100"><?php echo htmlspecialchars($b['customer_name']); ?></td>
                            <td class="text-zinc-400"><?php echo htmlspecialchars($b['customer_phone']); ?></td>
                            <td class="text-zinc-400">
                                <span class="text-zinc-200"><?php echo htmlspecialchars($b['date']); ?></span> @ <?php echo htmlspecialchars($b['time_slot']); ?>
                            </td>
                            <td class="text-center text-warning font-anton text-lg">
                                <?php echo htmlspecialchars($b['table_number'] ?? 'N/A'); ?>
                                <span class="text-[10px] text-zinc-500 block font-sans font-medium tracking-normal">
                                    <?php 
                                    if ($b['table_zone'] === 'INDOOR') echo t("Indoor AC", "ห้องแอร์");
                                    elseif ($b['table_zone'] === 'OUTDOOR') echo t("Outdoor Breeze", "ด้านนอก");
                                    else echo t("Stage Front", "หน้าเวที");
                                    ?>
                                </span>
                            </td>
                            <td class="text-center text-zinc-400"><?php echo $b['pax']; ?> Pax</td>
                            <td class="text-center">
                                <div class="flex justify-center gap-2">
                                    <?php if ($active_tab === 'pending'): ?>
                                        <a href="index.php?action=confirm&booking_id=<?php echo $b['id']; ?>&tab=pending" class="shadcn-btn-primary py-1 px-3 text-xs uppercase font-anton tracking-wider"><?php echo t("Confirm", "ยืนยัน"); ?></a>
                                        <a href="index.php?action=cancel&booking_id=<?php echo $b['id']; ?>&tab=pending" class="shadcn-btn-destructive py-1.5 px-3 text-xs uppercase font-anton tracking-wider"><?php echo t("Cancel", "ปฏิเสธ"); ?></a>
                                    <?php elseif ($active_tab === 'confirmed'): ?>
                                        <span class="badge bg-emerald-950 text-emerald-400 border border-emerald-900/60 px-2.5 py-1 text-xs rounded me-2"><?php echo t("Approved", "อนุมัติแล้ว"); ?></span>
                                        <a href="index.php?action=cancel&booking_id=<?php echo $b['id']; ?>&tab=confirmed" class="shadcn-btn-destructive py-1.5 px-3 text-xs uppercase font-anton tracking-wider"><?php echo t("Cancel", "ยกเลิก"); ?></a>
                                    <?php else: ?>
                                        <span class="badge bg-rose-950 text-rose-400 border border-rose-900/60 px-2.5 py-1 text-xs rounded me-2"><?php echo t("Cancelled", "ยกเลิกแล้ว"); ?></span>
                                        <a href="index.php?action=confirm&booking_id=<?php echo $b['id']; ?>&tab=cancelled" class="shadcn-btn-primary py-1 px-3 text-xs uppercase font-anton tracking-wider"><?php echo t("Re-confirm", "อนุมัติใหม่"); ?></a>
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

<?php require_once 'admin_footer.php'; ?>
