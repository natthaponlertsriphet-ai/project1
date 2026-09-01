<?php
require_once '../db.php';
require_once '../line_helper.php';
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

// AJAX Live Polling Endpoint for Real-time Dashboard Counts
if (isset($_GET['action']) && $_GET['action'] === 'get_live_dashboard_counts') {
    header('Content-Type: application/json');
    try {
        $p_count = (int)$pdo->query("SELECT COUNT(*) FROM reservation WHERE reservation_status = 'PENDING'")->fetchColumn();
        $c_count = (int)$pdo->query("SELECT COUNT(*) FROM reservation WHERE reservation_status = 'CONFIRMED'")->fetchColumn();
        $comp_count = (int)$pdo->query("SELECT COUNT(*) FROM reservation WHERE reservation_status = 'COMPLETED'")->fetchColumn();
        $cr_count = (int)$pdo->query("SELECT COUNT(*) FROM reservation WHERE reservation_status = 'CANCEL_REQUESTED'")->fetchColumn();
        $cl_count = (int)$pdo->query("SELECT COUNT(*) FROM reservation WHERE reservation_status = 'CANCELLED'")->fetchColumn();

        echo json_encode([
            'p_count' => $p_count,
            'c_count' => $c_count,
            'comp_count' => $comp_count,
            'cr_count' => $cr_count,
            'cl_count' => $cl_count
        ]);
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}

// Handle Quick Toggle table status (AVAILABLE / OCCUPIED)
if (isset($_GET['action']) && $_GET['action'] === 'toggle_status' && isset($_GET['id'])) {
    $toggle_id = $_GET['id'];
    $is_ajax = (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') || isset($_GET['ajax']);
    try {
        $stmt = $pdo->prepare("SELECT table_status AS status FROM `table` WHERE table_id = ?");
        $stmt->execute([$toggle_id]);
        $current_status = $stmt->fetchColumn();
        
        $new_status = ($current_status === 'AVAILABLE') ? 'OCCUPIED' : 'AVAILABLE';
        
        $stmt = $pdo->prepare("UPDATE `table` SET table_status = ? WHERE table_id = ?");
        $stmt->execute([$new_status, $toggle_id]);
        
        if ($is_ajax) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'id' => $toggle_id,
                'status' => $new_status,
                'message' => t("Table status toggled successfully.", "สลับสถานะโต๊ะเรียบร้อยแล้ว.")
            ]);
            exit;
        }

        $_SESSION['action_success'] = t("Table status toggled successfully.", "สลับสถานะโต๊ะเรียบร้อยแล้ว.");
    } catch (Exception $e) {
        if ($is_ajax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            exit;
        }
        $_SESSION['action_error'] = "Error: " . $e->getMessage();
    }
    header("Location: index.php");
    exit;
}

// Handle Clear Table action (Freeing up table after customer leaves)
if (isset($_GET['action']) && $_GET['action'] === 'clear_table' && isset($_GET['booking_id'])) {
    $b_id = $_GET['booking_id'];
    
    // Check role: Only STAFF is allowed to modify bookings. ADMIN can only view.
    if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'ADMIN') {
        $_SESSION['action_error'] = t("Admins can only view. Approval management is restricted to staff members.", "แอดมินมีสิทธิ์ดูข้อมูลเท่านั้น การจัดการอนุมัติเป็นหน้าที่ของพนักงาน");
        header("Location: index.php?tab=confirmed");
        exit;
    }
    
    try {
        // Fetch booking details to get the table ID
        $stmt = $pdo->prepare("SELECT table_id FROM reservation WHERE reservation_id = ?");
        $stmt->execute([$b_id]);
        $table_id = $stmt->fetchColumn();
        
        // 1. Update booking status to 'COMPLETED'
        $stmt = $pdo->prepare("UPDATE reservation SET reservation_status = 'COMPLETED' WHERE reservation_id = ?");
        $stmt->execute([$b_id]);
        
        // 2. Set table status to 'AVAILABLE' in tables table
        if ($table_id) {
            $stmt = $pdo->prepare("UPDATE `table` SET table_status = 'AVAILABLE' WHERE table_id = ?");
            $stmt->execute([$table_id]);
        }
        

        
        $_SESSION['action_success'] = t("Table cleared and made available successfully.", "เคลียร์โต๊ะและคืนสถานะโต๊ะว่างเรียบร้อยแล้ว.");
    } catch (Exception $e) {
        $_SESSION['action_error'] = "Error: " . $e->getMessage();
    }
    header("Location: index.php?tab=confirmed");
    exit;
}

// Handle Action (Confirm/Cancel Bookings) with Post-Redirect-Get
if (isset($_GET['action']) && isset($_GET['booking_id'])) {
    $b_id = $_GET['booking_id'];
    $act = $_GET['action'];
    $target_tab = $_GET['tab'] ?? 'pending';
    
    // Check role: Only STAFF is allowed to modify bookings. ADMIN can only view.
    if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'ADMIN') {
        $_SESSION['action_error'] = t("Admins can only view. Approval management is restricted to staff members.", "แอดมินมีสิทธิ์ดูข้อมูลเท่านั้น การจัดการอนุมัติเป็นหน้าที่ของพนักงาน");
        header("Location: index.php?tab=" . urlencode($target_tab));
        exit;
    }
    
    try {
        // Fetch booking details first
        $stmt = $pdo->prepare("SELECT reservation_id AS id, customer_name, customer_phone, reservation_date AS date, reservation_time AS time_slot, guest_count AS pax, table_id, reservation_status AS status, cancel_reason, created_at, updated_at FROM reservation WHERE reservation_id = ?");
        $stmt->execute([$b_id]);
        $b_details = $stmt->fetch();
        
        if ($b_details) {
            if ($act === 'confirm') {
                // Prevent re-confirming past cancelled bookings
                if ($b_details['status'] === 'CANCELLED' && $b_details['date'] < date('Y-m-d')) {
                    $_SESSION['action_error'] = t("Cannot re-approve past reservations. Re-approval is allowed day-by-day or for future dates only.", "ไม่สามารถอนุมัติรายการจองที่ผ่านมาแล้วใหม่ได้ การอนุมัติใหม่ทำได้เฉพาะวันต่อวันหรือวันล่วงหน้าเท่านั้น");
                    header("Location: index.php?tab=" . urlencode($target_tab));
                    exit;
                }

                // Confirm booking
                $stmt = $pdo->prepare("UPDATE reservation SET reservation_status = 'CONFIRMED' WHERE reservation_id = ?");
                $stmt->execute([$b_id]);
                
                // Update live physical table status to OCCUPIED ONLY if booking is for TODAY
                if ($b_details['date'] === date('Y-m-d') && !empty($b_details['table_id'])) {
                    $stmt = $pdo->prepare("UPDATE `table` SET table_status = 'OCCUPIED' WHERE table_id = ?");
                    $stmt->execute([$b_details['table_id']]);
                }
                
                $_SESSION['action_success'] = t("Booking confirmed successfully!", "ยืนยันรายการจองเรียบร้อยแล้ว!");
            } elseif ($act === 'cancel') {
                // Cancel booking
                $reason = trim($_GET['reason'] ?? '');
                $stmt = $pdo->prepare("UPDATE reservation SET reservation_status = 'CANCELLED', cancel_reason = ? WHERE reservation_id = ?");
                $stmt->execute([$reason, $b_id]);
                
                // Update live physical table status to AVAILABLE ONLY if booking was for TODAY
                if ($b_details['date'] === date('Y-m-d') && !empty($b_details['table_id'])) {
                    $stmt = $pdo->prepare("UPDATE `table` SET table_status = 'AVAILABLE' WHERE table_id = ?");
                    $stmt->execute([$b_details['table_id']]);
                }
                
                $_SESSION['action_success'] = t("Booking cancelled successfully.", "ยกเลิกรายการจองเรียบร้อยแล้ว.");
            } elseif ($act === 'approve_cancel') {
                // Confirm/approve cancel request
                $stmt = $pdo->prepare("UPDATE reservation SET reservation_status = 'CANCELLED' WHERE reservation_id = ?");
                $stmt->execute([$b_id]);
                
                // Update live physical table status to AVAILABLE ONLY if booking was for TODAY
                if ($b_details['date'] === date('Y-m-d') && !empty($b_details['table_id'])) {
                    $stmt = $pdo->prepare("UPDATE `table` SET table_status = 'AVAILABLE' WHERE table_id = ?");
                    $stmt->execute([$b_details['table_id']]);
                }
                
                $_SESSION['action_success'] = t("Booking cancellation confirmed successfully.", "ยืนยันการยกเลิกการจองเรียบร้อยแล้ว.");
            } elseif ($act === 'reject_cancel') {
                // Reject cancel request (keep the booking confirmed)
                $stmt = $pdo->prepare("UPDATE reservation SET reservation_status = 'CONFIRMED' WHERE reservation_id = ?");
                $stmt->execute([$b_id]);

                // Ensure live physical table status is OCCUPIED ONLY if booking is for TODAY
                if ($b_details['date'] === date('Y-m-d') && !empty($b_details['table_id'])) {
                    $stmt = $pdo->prepare("UPDATE `table` SET table_status = 'OCCUPIED' WHERE table_id = ?");
                    $stmt->execute([$b_details['table_id']]);
                }

                $_SESSION['action_success'] = t("Cancellation request rejected. Booking is kept confirmed.", "ปฏิเสธคำขอยกเลิกแล้ว และคงสถานะการจองตามเดิม");
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
if (!in_array($active_tab, ['pending', 'confirmed', 'completed', 'cancelled', 'cancel_requests'])) {
    $active_tab = 'pending';
}

// Fetch analytics counts
$active_beers_count = $pdo->query("SELECT COUNT(*) FROM menu WHERE is_active = 1")->fetchColumn();
$pending_bookings_count = $pdo->query("SELECT COUNT(*) FROM reservation WHERE reservation_status = 'PENDING'")->fetchColumn();
$active_promos_count = $pdo->query("SELECT COUNT(*) FROM promotion WHERE is_active = 1")->fetchColumn();

// Fetch bookings by status (Ordered by creation time DESC - Newest first)
try {
    $stmt = $pdo->query("SELECT b.reservation_id AS id, b.customer_name, b.customer_phone, b.reservation_date AS date, b.reservation_time AS time_slot, b.guest_count AS pax, b.table_id, b.reservation_status AS status, b.cancel_reason, b.created_at, b.updated_at, t.table_number, t.zone AS table_zone FROM reservation b LEFT JOIN `table` t ON b.table_id = t.table_id WHERE b.reservation_status = 'PENDING' ORDER BY b.created_at DESC");
    $pending_bookings = $stmt->fetchAll();
} catch (Exception $e) {
    $pending_bookings = [];
}

try {
    $stmt = $pdo->query("SELECT b.reservation_id AS id, b.customer_name, b.customer_phone, b.reservation_date AS date, b.reservation_time AS time_slot, b.guest_count AS pax, b.table_id, b.reservation_status AS status, b.cancel_reason, b.created_at, b.updated_at, t.table_number, t.zone AS table_zone FROM reservation b LEFT JOIN `table` t ON b.table_id = t.table_id WHERE b.reservation_status = 'CONFIRMED' ORDER BY b.created_at DESC");
    $confirmed_bookings = $stmt->fetchAll();
} catch (Exception $e) {
    $confirmed_bookings = [];
}

try {
    $stmt = $pdo->query("SELECT b.reservation_id AS id, b.customer_name, b.customer_phone, b.reservation_date AS date, b.reservation_time AS time_slot, b.guest_count AS pax, b.table_id, b.reservation_status AS status, b.cancel_reason, b.created_at, b.updated_at, t.table_number, t.zone AS table_zone FROM reservation b LEFT JOIN `table` t ON b.table_id = t.table_id WHERE b.reservation_status = 'CANCEL_REQUESTED' ORDER BY b.created_at DESC");
    $cancel_requests_bookings = $stmt->fetchAll();
} catch (Exception $e) {
    $cancel_requests_bookings = [];
}

// Read filter parameters for completed/cancelled tabs
$filter_type = $_GET['filter_type'] ?? 'all';
$filter_val = '';
if ($filter_type === 'day') {
    $filter_val = $_GET['filter_val_day'] ?? date('Y-m-d');
} elseif ($filter_type === 'month') {
    $filter_val = $_GET['filter_val_month'] ?? date('Y-m');
} elseif ($filter_type === 'year') {
    $filter_val = $_GET['filter_val_year'] ?? date('Y');
}

$comp_total_count = 0;
$cl_total_count = 0;

try {
    $comp_total_count = $pdo->query("SELECT COUNT(*) FROM reservation WHERE reservation_status = 'COMPLETED'")->fetchColumn();
} catch (Exception $e) {}

try {
    $cl_total_count = $pdo->query("SELECT COUNT(*) FROM reservation WHERE reservation_status = 'CANCELLED'")->fetchColumn();
} catch (Exception $e) {}

try {
    $sql = "SELECT b.reservation_id AS id, b.customer_name, b.customer_phone, b.reservation_date AS date, b.reservation_time AS time_slot, b.guest_count AS pax, b.table_id, b.reservation_status AS status, b.cancel_reason, b.created_at, b.updated_at, t.table_number, t.zone AS table_zone FROM reservation b LEFT JOIN `table` t ON b.table_id = t.table_id WHERE b.reservation_status = 'CANCELLED'";
    $params = [];
    if ($active_tab === 'cancelled' && $filter_type !== 'all' && !empty($filter_val)) {
        if ($filter_type === 'day') {
            $sql .= " AND b.reservation_date = ?";
            $params[] = $filter_val;
        } elseif ($filter_type === 'month') {
            $sql .= " AND SUBSTRING(b.reservation_date, 1, 7) = ?";
            $params[] = $filter_val;
        } elseif ($filter_type === 'year') {
            $sql .= " AND SUBSTRING(b.reservation_date, 1, 4) = ?";
            $params[] = $filter_val;
        }
    }
    $sql .= " ORDER BY b.created_at DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $cancelled_bookings = $stmt->fetchAll();
} catch (Exception $e) {
    $cancelled_bookings = [];
}

try {
    $sql = "SELECT b.reservation_id AS id, b.customer_name, b.customer_phone, b.reservation_date AS date, b.reservation_time AS time_slot, b.guest_count AS pax, b.table_id, b.reservation_status AS status, b.cancel_reason, b.created_at, b.updated_at, t.table_number, t.zone AS table_zone FROM reservation b LEFT JOIN `table` t ON b.table_id = t.table_id WHERE b.reservation_status = 'COMPLETED'";
    $params = [];
    if ($active_tab === 'completed' && $filter_type !== 'all' && !empty($filter_val)) {
        if ($filter_type === 'day') {
            $sql .= " AND b.reservation_date = ?";
            $params[] = $filter_val;
        } elseif ($filter_type === 'month') {
            $sql .= " AND SUBSTRING(b.reservation_date, 1, 7) = ?";
            $params[] = $filter_val;
        } elseif ($filter_type === 'year') {
            $sql .= " AND SUBSTRING(b.reservation_date, 1, 4) = ?";
            $params[] = $filter_val;
        }
    }
    $sql .= " ORDER BY b.created_at DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $completed_bookings = $stmt->fetchAll();
} catch (Exception $e) {
    $completed_bookings = [];
}

$display_bookings = [];
if ($active_tab === 'pending') {
    $display_bookings = $pending_bookings;
} elseif ($active_tab === 'confirmed') {
    $display_bookings = $confirmed_bookings;
} elseif ($active_tab === 'completed') {
    $display_bookings = $completed_bookings;
} elseif ($active_tab === 'cancel_requests') {
    $display_bookings = $cancel_requests_bookings;
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

// Analytics POS Date Filter Processing
$analytics_mode = $_GET['analytics_mode'] ?? 'all';
$analytics_start = $_GET['analytics_start'] ?? date('Y-m-d');
$analytics_end = $_GET['analytics_end'] ?? date('Y-m-d');
$analytics_month = $_GET['analytics_month'] ?? date('Y-m');
$analytics_year = $_GET['analytics_year'] ?? date('Y');

$analytics_where_clauses = [];
$analytics_params = [];
$analytics_label_summary = t("All Time Records", "ประวัติย้อนหลังทั้งหมด");

if ($analytics_mode === 'today') {
    $analytics_where_clauses[] = "reservation_date = ?";
    $analytics_params[] = date('Y-m-d');
    $analytics_label_summary = t("Today", "ประจำวันนี้") . " (" . formatDateStr(date('Y-m-d')) . ")";
} elseif ($analytics_mode === '7days') {
    $seven_days_ago = date('Y-m-d', strtotime('-6 days'));
    $analytics_where_clauses[] = "reservation_date >= ? AND reservation_date <= ?";
    $analytics_params[] = $seven_days_ago;
    $analytics_params[] = date('Y-m-d');
    $analytics_label_summary = t("7 Days Range", "7 วันล่าสุด") . " (" . formatDateStr($seven_days_ago) . " - " . formatDateStr(date('Y-m-d')) . ")";
} elseif ($analytics_mode === 'day' && !empty($analytics_start)) {
    $analytics_where_clauses[] = "reservation_date = ?";
    $analytics_params[] = $analytics_start;
    $analytics_label_summary = t("Date:", "ประจำวันที่:") . " " . formatDateStr($analytics_start);
} elseif ($analytics_mode === 'range' && !empty($analytics_start) && !empty($analytics_end)) {
    $analytics_where_clauses[] = "reservation_date >= ? AND reservation_date <= ?";
    $analytics_params[] = $analytics_start;
    $analytics_params[] = $analytics_end;
    $analytics_label_summary = t("Custom Range:", "ช่วงวันที่:") . " " . formatDateStr($analytics_start) . " - " . formatDateStr($analytics_end);
} elseif ($analytics_mode === 'month' && !empty($analytics_month)) {
    $analytics_where_clauses[] = "SUBSTRING(reservation_date, 1, 7) = ?";
    $analytics_params[] = $analytics_month;
    $analytics_label_summary = t("Month:", "ประจำเดือน:") . " " . formatMonth($analytics_month);
} elseif ($analytics_mode === 'year' && !empty($analytics_year)) {
    $analytics_where_clauses[] = "SUBSTRING(reservation_date, 1, 4) = ?";
    $analytics_params[] = $analytics_year;
    $analytics_label_summary = t("Year:", "ประจำปี:") . " " . $analytics_year;
}

$where_sql = count($analytics_where_clauses) > 0 ? " WHERE " . implode(" AND ", $analytics_where_clauses) : "";

// Filtered Period Stats
$period_total_count = 0;
$period_completed_count = 0;
$period_pending_count = 0;
$period_cancelled_count = 0;

try {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM reservation" . $where_sql);
    $stmt->execute($analytics_params);
    $period_total_count = (int)$stmt->fetchColumn();

    $completed_sql = count($analytics_where_clauses) > 0 ? $where_sql . " AND reservation_status IN ('CONFIRMED','COMPLETED')" : " WHERE reservation_status IN ('CONFIRMED','COMPLETED')";
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM reservation" . $completed_sql);
    $stmt->execute($analytics_params);
    $period_completed_count = (int)$stmt->fetchColumn();

    $pending_sql = count($analytics_where_clauses) > 0 ? $where_sql . " AND reservation_status = 'PENDING'" : " WHERE reservation_status = 'PENDING'";
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM reservation" . $pending_sql);
    $stmt->execute($analytics_params);
    $period_pending_count = (int)$stmt->fetchColumn();

    $cancelled_sql = count($analytics_where_clauses) > 0 ? $where_sql . " AND reservation_status = 'CANCELLED'" : " WHERE reservation_status = 'CANCELLED'";
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM reservation" . $cancelled_sql);
    $stmt->execute($analytics_params);
    $period_cancelled_count = (int)$stmt->fetchColumn();
} catch (Exception $e) {}

// Fetch filtered summaries
try {
    $sql = "
        SELECT reservation_date AS date, COUNT(*) as total, 
               SUM(CASE WHEN reservation_status = 'CONFIRMED' THEN 1 ELSE 0 END) as confirmed,
               SUM(CASE WHEN reservation_status = 'COMPLETED' THEN 1 ELSE 0 END) as completed,
               SUM(CASE WHEN reservation_status = 'CANCELLED' THEN 1 ELSE 0 END) as cancelled
        FROM reservation 
        {$where_sql}
        GROUP BY reservation_date 
        ORDER BY reservation_date DESC 
        LIMIT 30
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($analytics_params);
    $daily_summary = $stmt->fetchAll();
} catch (Exception $e) {
    $daily_summary = [];
}

try {
    $sql = "
        SELECT SUBSTRING(reservation_date, 1, 7) as month, COUNT(*) as total, 
               SUM(CASE WHEN reservation_status = 'CONFIRMED' THEN 1 ELSE 0 END) as confirmed,
               SUM(CASE WHEN reservation_status = 'COMPLETED' THEN 1 ELSE 0 END) as completed,
               SUM(CASE WHEN reservation_status = 'CANCELLED' THEN 1 ELSE 0 END) as cancelled
        FROM reservation 
        {$where_sql}
        GROUP BY month 
        ORDER BY month DESC 
        LIMIT 12
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($analytics_params);
    $monthly_summary = $stmt->fetchAll();
} catch (Exception $e) {
    $monthly_summary = [];
}

try {
    $sql = "
        SELECT SUBSTRING(reservation_date, 1, 4) as year, COUNT(*) as total, 
               SUM(CASE WHEN reservation_status = 'CONFIRMED' THEN 1 ELSE 0 END) as confirmed,
               SUM(CASE WHEN reservation_status = 'COMPLETED' THEN 1 ELSE 0 END) as completed,
               SUM(CASE WHEN reservation_status = 'CANCELLED' THEN 1 ELSE 0 END) as cancelled
        FROM reservation 
        {$where_sql}
        GROUP BY year 
        ORDER BY year DESC
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($analytics_params);
    $yearly_summary = $stmt->fetchAll();
} catch (Exception $e) {
    $yearly_summary = [];
}

try {
    $stmt = $pdo->query("SELECT table_id AS id, table_number AS number, zone, capacity, table_status AS status, image FROM `table` ORDER BY zone, table_number");
    $all_tables = $stmt->fetchAll();
} catch (Exception $e) {
    $all_tables = [];
}
?>

<?php if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'ADMIN'): ?>
    <div class="flex justify-between items-center border-b border-zinc-900 pb-4 mb-6">
        <div>
            <h1 class="font-anton text-warning text-uppercase tracking-wider text-2xl m-0"><?php echo t("Dashboard Overview", "ภาพรวมตารางแดชบอร์ด"); ?></h1>
            <p class="text-zinc-500 text-xs mt-1 uppercase tracking-widest font-mono"><?php echo t("Admin Console / Main Analytics", "คอนโซลผู้จัดการ / ข้อมูลวิเคราะห์หลัก"); ?></p>
        </div>
    </div>
<?php else: ?>
    <div class="flex justify-between items-center border-b border-zinc-900 pb-4 mb-6">
        <div>
            <h1 class="font-anton text-warning text-uppercase tracking-wider text-2xl m-0"><?php echo t("Reservation Management", "ระบบจัดการคิวจองโต๊ะ"); ?></h1>
            <p class="text-zinc-500 text-xs mt-1 uppercase tracking-widest font-mono"><?php echo t("Staff Console / Booking Queue Operations", "คอนโซลพนักงาน / จัดการคิวจองโต๊ะร้าน"); ?></p>
        </div>
    </div>
<?php endif; ?>

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

<?php if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'ADMIN'): ?>
<!-- iOS / iPhone Style Analytics Calendar Control Panel -->
<div class="bg-zinc-950/90 border border-zinc-800/90 rounded-2xl p-5 mb-6 shadow-2xl backdrop-blur-md">
    <form method="GET" action="index.php" id="analytics-filter-form" class="space-y-4">
        <input type="hidden" name="tab" value="<?php echo htmlspecialchars($active_tab); ?>">
        
        <!-- Header & iOS Style Segmented Control -->
        <div class="flex flex-wrap items-center justify-between gap-4 border-b border-zinc-800/80 pb-4">
            <div class="flex items-center gap-3">
                <!-- iOS App Style Icon Box -->
                <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-yellow-500 to-amber-600 flex flex-col items-center justify-center text-zinc-950 font-bold shadow-lg shadow-yellow-500/20">
                    <span class="material-symbols-outlined text-xl leading-none">calendar_month</span>
                </div>
                <div>
                    <h2 class="font-anton text-warning text-lg uppercase tracking-wider m-0 leading-tight">
                        <?php echo t("Booking Analytics & Filter", "ข้อมูลสถิติการจองโต๊ะ ประจำวัน/เดือน/ปี"); ?>
                    </h2>
                    <span class="text-zinc-400 text-xs font-mono block mt-0.5">
                        <?php echo t("Booking statistics overview by Day, Month, and Year", "ข้อมูลสถิติตารางจองโต๊ะ ประจำวัน/เดือน/ปี"); ?>
                    </span>
                </div>
            </div>
            
            <!-- iOS Segmented Control Bar -->
            <div class="inline-flex p-1 bg-zinc-900/90 border border-zinc-800 rounded-xl font-mono text-xs shadow-inner gap-1 flex-wrap">
                <a href="index.php?analytics_mode=all" class="px-3 py-1.5 rounded-lg transition-all flex items-center gap-1.5 <?php echo $analytics_mode === 'all' ? 'bg-warning text-zinc-950 font-bold shadow-md shadow-warning/10' : 'text-zinc-400 hover:text-zinc-100 hover:bg-zinc-800/60'; ?>" title="<?php echo t("Show all records without date filter", "แสดงรายการทั้งหมดโดยไม่มีตัวกรองวันที่"); ?>">
                    <span class="material-symbols-outlined text-base">restart_alt</span>
                    <span><?php echo t("All", "ทั้งหมด"); ?></span>
                </a>
                <a href="index.php?analytics_mode=today" class="px-3 py-1.5 rounded-lg transition-all flex items-center gap-1.5 <?php echo $analytics_mode === 'today' ? 'bg-warning text-zinc-950 font-bold shadow-md shadow-warning/10' : 'text-zinc-400 hover:text-zinc-100 hover:bg-zinc-800/60'; ?>">
                    <span class="material-symbols-outlined text-base">today</span>
                    <span><?php echo t("Today", "วันนี้"); ?></span>
                </a>
                <a href="index.php?analytics_mode=7days" class="px-3 py-1.5 rounded-lg transition-all flex items-center gap-1.5 <?php echo $analytics_mode === '7days' ? 'bg-warning text-zinc-950 font-bold shadow-md shadow-warning/10' : 'text-zinc-400 hover:text-zinc-100 hover:bg-zinc-800/60'; ?>">
                    <span class="material-symbols-outlined text-base">date_range</span>
                    <span><?php echo t("7 Days", "7 วันล่าสุด"); ?></span>
                </a>
                <a href="index.php?analytics_mode=month&analytics_month=<?php echo date('Y-m'); ?>" class="px-3 py-1.5 rounded-lg transition-all flex items-center gap-1.5 <?php echo ($analytics_mode === 'month' && $analytics_month === date('Y-m')) ? 'bg-warning text-zinc-950 font-bold shadow-md shadow-warning/10' : 'text-zinc-400 hover:text-zinc-100 hover:bg-zinc-800/60'; ?>">
                    <span class="material-symbols-outlined text-base">calendar_view_month</span>
                    <span><?php echo t("This Month", "เดือนนี้"); ?></span>
                </a>
                <a href="index.php?analytics_mode=year&analytics_year=<?php echo date('Y'); ?>" class="px-3 py-1.5 rounded-lg transition-all flex items-center gap-1.5 <?php echo ($analytics_mode === 'year' && $analytics_year === date('Y')) ? 'bg-warning text-zinc-950 font-bold shadow-md shadow-warning/10' : 'text-zinc-400 hover:text-zinc-100 hover:bg-zinc-800/60'; ?>">
                    <span class="material-symbols-outlined text-base">event_note</span>
                    <span><?php echo t("This Year", "ปีนี้"); ?></span>
                </a>
            </div>
        </div>

        <!-- Direct Pure-Click Day / Month / Year Calendar Pickers Grid -->
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4 items-end pt-1">
            <!-- 1. Click By Day (Date Dropdown + Calendar Sheet Trigger) -->
            <div class="bg-zinc-900/60 border border-zinc-800/80 rounded-xl p-3 hover:border-zinc-700 transition-colors">
                <label class="block text-zinc-300 text-xs font-mono uppercase tracking-wider mb-1.5 flex items-center justify-between">
                    <span class="flex items-center gap-1.5">
                        <span class="material-symbols-outlined text-sm text-warning">event</span>
                        <span><?php echo t("Select By Day", "เลือกตามวัน (คลิกเลือกวัน)"); ?></span>
                    </span>
                </label>
                <div class="relative">
                    <input type="date" value="<?php echo ($analytics_mode === 'day') ? htmlspecialchars($analytics_start) : ''; ?>" onfocus="if(this.showPicker) this.showPicker();" onclick="if(this.showPicker) this.showPicker();" onchange="submitCalendarFilter('day', this.value)" class="w-full bg-zinc-950 border border-zinc-800 rounded-lg px-3 py-2 text-zinc-100 text-xs font-mono focus:outline-none focus:border-warning cursor-pointer">
                </div>
            </div>

            <!-- 2. Click By Month (Dropdown Selection) -->
            <div class="bg-zinc-900/60 border border-zinc-800/80 rounded-xl p-3 hover:border-zinc-700 transition-colors">
                <label class="block text-zinc-300 text-xs font-mono uppercase tracking-wider mb-1.5 flex items-center justify-between">
                    <span class="flex items-center gap-1.5">
                        <span class="material-symbols-outlined text-sm text-warning">calendar_view_month</span>
                        <span><?php echo t("Select By Month", "เลือกตามเดือน (คลิกเลือกเดือน)"); ?></span>
                    </span>
                </label>
                <select onchange="submitCalendarFilter('month', this.value)" class="w-full bg-zinc-950 border border-zinc-800 rounded-lg px-3 py-2 text-zinc-100 text-xs font-mono focus:outline-none focus:border-warning cursor-pointer">
                    <option value=""><?php echo t("-- Select Month --", "-- คลิกเลือกเดือน --"); ?></option>
                    <?php 
                    $curr_y = date('Y');
                    $thai_months_arr = [
                        '01' => 'มกราคม', '02' => 'กุมภาพันธ์', '03' => 'มีนาคม', '04' => 'เมษายน',
                        '05' => 'พฤษภาคม', '06' => 'มิถุนายน', '07' => 'กรกฎาคม', '08' => 'สิงหาคม',
                        '09' => 'กันยายน', '10' => 'ตุลาคม', '11' => 'พฤศจิกายน', '12' => 'ธันวาคม'
                    ];
                    foreach ($thai_months_arr as $m_num => $m_name):
                        $m_val = "$curr_y-$m_num";
                        $sel = ($analytics_mode === 'month' && $analytics_month === $m_val) ? 'selected' : '';
                        $th_y_display = (int)$curr_y + 543;
                    ?>
                        <option value="<?php echo $m_val; ?>" <?php echo $sel; ?>>
                            <?php echo "$m_name $th_y_display ($m_val)"; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- 3. Click By Year (Dropdown Selection) -->
            <div class="bg-zinc-900/60 border border-zinc-800/80 rounded-xl p-3 hover:border-zinc-700 transition-colors">
                <label class="block text-zinc-300 text-xs font-mono uppercase tracking-wider mb-1.5 flex items-center justify-between">
                    <span class="flex items-center gap-1.5">
                        <span class="material-symbols-outlined text-sm text-warning">calendar_today</span>
                        <span><?php echo t("Select By Year", "เลือกตามปี (คลิกเลือกปี)"); ?></span>
                    </span>
                </label>
                <select onchange="submitCalendarFilter('year', this.value)" class="w-full bg-zinc-950 border border-zinc-800 rounded-lg px-3 py-2 text-zinc-100 text-xs font-mono focus:outline-none focus:border-warning cursor-pointer">
                    <option value=""><?php echo t("-- Select Year --", "-- คลิกเลือกปี ค.ศ. --"); ?></option>
                    <?php 
                    $current_yr = (int)date('Y');
                    for ($y = $current_yr; $y >= $current_yr - 5; $y--): 
                        $sel = ($analytics_mode === 'year' && (string)$analytics_year === (string)$y) ? 'selected' : '';
                    ?>
                        <option value="<?php echo $y; ?>" <?php echo $sel; ?>>
                            <?php echo t("Year ", "ปี ค.ศ. ") . $y . " (" . ($y + 543) . ")"; ?>
                        </option>
                    <?php endfor; ?>
                </select>
            </div>

            <!-- Reset & Reset All -->
            <div class="flex items-center gap-2">
                <a href="index.php?analytics_mode=all" class="flex-1 bg-zinc-900 hover:bg-zinc-800 text-zinc-300 border border-zinc-800 py-2.5 px-3 rounded-xl text-xs font-mono transition-all flex items-center justify-center gap-1.5 active:scale-95 shadow-sm" title="<?php echo t("Show all records without date filter", "แสดงรายการทั้งหมดโดยไม่มีตัวกรองวันที่"); ?>">
                    <span class="material-symbols-outlined text-base text-warning">restart_alt</span>
                    <span><?php echo t("Show All Records", "ดูสถิติทั้งหมด"); ?></span>
                </a>
            </div>
    </form>
</div>

<!-- Active Filter Status Banner -->
<div class="flex flex-wrap items-center justify-between gap-3 bg-zinc-950/80 border border-zinc-800/80 rounded-xl px-4 py-3 mb-6 font-mono text-xs shadow-sm">
    <div class="flex items-center gap-2">
        <div class="w-2 h-2 rounded-full bg-warning animate-pulse"></div>
        <span class="text-zinc-400 uppercase tracking-wider"><?php echo t("Selected Period:", "รายงานสรุปช่วงเวลาที่เลือก:"); ?></span>
        <span class="bg-warning/10 text-warning border border-warning/30 px-2.5 py-1 rounded-md font-bold text-xs"><?php echo $analytics_label_summary; ?></span>
    </div>
    <div class="flex items-center gap-2 text-zinc-400">
        <span class="material-symbols-outlined text-sm text-zinc-500">format_list_bulleted</span>
        <span><?php echo t("Total Queues in Filter:", "ยอดคิวตามเงื่อนไข:"); ?></span>
        <span class="bg-zinc-900 border border-zinc-800 text-zinc-100 font-bold px-2.5 py-1 rounded-md text-xs"><?php echo number_format($period_total_count); ?> รายการ</span>
    </div>
</div>

<!-- Period Filter Summary Stat Cards -->
<div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4 mb-8 font-sans">
    <div class="shadcn-card border-t border-t-yellow-500/80">
        <div class="flex justify-between items-center">
            <div>
                <span class="text-xs uppercase text-zinc-500 font-medium tracking-wider"><?php echo t("Period Total", "ยอดจองรวมช่วงที่เลือก"); ?></span>
                <span class="font-anton text-warning text-3xl mt-1 block"><?php echo sprintf("%02d", $period_total_count); ?></span>
            </div>
            <span class="material-symbols-outlined text-zinc-600 text-3xl">receipt_long</span>
        </div>
    </div>
    
    <div class="shadcn-card border-t border-t-emerald-500/80">
        <div class="flex justify-between items-center">
            <div>
                <span class="text-xs uppercase text-zinc-500 font-medium tracking-wider"><?php echo t("Approved / Completed", "ยืนยันแล้ว / เสร็จสิ้น"); ?></span>
                <span class="font-anton text-emerald-500 text-3xl mt-1 block"><?php echo sprintf("%02d", $period_completed_count); ?></span>
            </div>
            <span class="material-symbols-outlined text-emerald-950 text-3xl">verified</span>
        </div>
    </div>

    <div class="shadcn-card border-t border-t-amber-500/80">
        <div class="flex justify-between items-center">
            <div>
                <span class="text-xs uppercase text-zinc-500 font-medium tracking-wider"><?php echo t("Pending Queues", "รอดำเนินการ"); ?></span>
                <span class="font-anton text-amber-500 text-3xl mt-1 block"><?php echo sprintf("%02d", $period_pending_count); ?></span>
            </div>
            <span class="material-symbols-outlined text-amber-950 text-3xl">pending_actions</span>
        </div>
    </div>

    <div class="shadcn-card border-t border-t-rose-500/80">
        <div class="flex justify-between items-center">
            <div>
                <span class="text-xs uppercase text-zinc-500 font-medium tracking-wider"><?php echo t("Cancelled Queues", "ยกเลิกแล้ว"); ?></span>
                <span class="font-anton text-rose-500 text-3xl mt-1 block"><?php echo sprintf("%02d", $period_cancelled_count); ?></span>
            </div>
            <span class="material-symbols-outlined text-rose-950 text-3xl">event_busy</span>
        </div>
    </div>
</div>


<?php
// Prepare chart data (reverse arrays for chronological order)
$chart_daily = array_reverse($daily_summary);
$chart_monthly = array_reverse($monthly_summary);

$daily_labels = [];
$daily_total = [];
$daily_confirmed = [];
$daily_cancelled = [];
foreach ($chart_daily as $d) {
    $daily_labels[] = formatDateStr($d['date']);
    $daily_total[] = (int)$d['total'];
    $daily_confirmed[] = (int)($d['completed'] ?? 0);
    $daily_cancelled[] = (int)($d['cancelled'] ?? 0);
}

$monthly_labels = [];
$monthly_total = [];
$monthly_confirmed = [];
$monthly_cancelled = [];
foreach ($chart_monthly as $m) {
    $monthly_labels[] = formatMonth($m['month']);
    $monthly_total[] = (int)$m['total'];
    $monthly_confirmed[] = (int)($m['completed'] ?? 0);
    $monthly_cancelled[] = (int)($m['cancelled'] ?? 0);
}
?>

<!-- Charts Section -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
    <!-- Daily Booking Chart -->
    <div class="shadcn-card">
        <h3 class="font-anton text-warning text-uppercase tracking-wider mb-4 flex items-center gap-2 text-sm border-b border-zinc-900 pb-2">
            <span class="material-symbols-outlined text-base text-zinc-500">show_chart</span>
            <span><?php echo t("Daily Booking Trend (Last 6 Days)", "แนวโน้มยอดจองรายวัน (6 วันล่าสุด)"); ?></span>
        </h3>
        <div style="position: relative; height:220px;">
            <canvas id="dailyChart"></canvas>
        </div>
    </div>

    <!-- Monthly Booking Chart -->
    <div class="shadcn-card">
        <h3 class="font-anton text-warning text-uppercase tracking-wider mb-4 flex items-center gap-2 text-sm border-b border-zinc-900 pb-2">
            <span class="material-symbols-outlined text-base text-zinc-500">bar_chart</span>
            <span><?php echo t("Monthly Booking Trend (Last 6 Months)", "แนวโน้มยอดจองรายเดือน (6 เดือนล่าสุด)"); ?></span>
        </h3>
        <div style="position: relative; height:220px;">
            <canvas id="monthlyChart"></canvas>
        </div>
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
                        <th class="pb-2 text-center font-semibold text-emerald-500"><?php echo t("Approved", "ยืนยันแล้ว"); ?></th>
                        <th class="pb-2 text-end font-semibold text-rose-500"><?php echo t("Cancelled", "ยกเลิกแล้ว"); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($daily_summary)): ?>
                        <tr>
                            <td colspan="4" class="text-center py-4 text-zinc-600"><?php echo t("No records", "ไม่มีประวัติ"); ?></td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($daily_summary as $d): ?>
                            <tr class="border-b border-zinc-900/40 last:border-0 hover:bg-zinc-900/10">
                                <td class="py-2 text-zinc-300 font-mono"><?php echo formatDateStr($d['date']); ?></td>
                                <td class="py-2 text-center font-bold text-zinc-200"><?php echo $d['total']; ?></td>
                                <td class="py-2 text-center text-emerald-500 font-bold"><?php echo $d['completed']; ?></td>
                                <td class="py-2 text-end text-rose-500 font-bold"><?php echo $d['cancelled']; ?></td>
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
                        <th class="pb-2 text-center font-semibold text-emerald-500"><?php echo t("Approved", "ยืนยันแล้ว"); ?></th>
                        <th class="pb-2 text-end font-semibold text-rose-500"><?php echo t("Cancelled", "ยกเลิกแล้ว"); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($monthly_summary)): ?>
                        <tr>
                            <td colspan="4" class="text-center py-4 text-zinc-600"><?php echo t("No records", "ไม่มีประวัติ"); ?></td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($monthly_summary as $m): ?>
                            <tr class="border-b border-zinc-900/40 last:border-0 hover:bg-zinc-900/10">
                                <td class="py-2 text-zinc-300 font-semibold"><?php echo formatMonth($m['month']); ?></td>
                                <td class="py-2 text-center font-bold text-zinc-200"><?php echo $m['total']; ?></td>
                                <td class="py-2 text-center text-emerald-500 font-bold"><?php echo $m['completed']; ?></td>
                                <td class="py-2 text-end text-rose-500 font-bold"><?php echo $m['cancelled']; ?></td>
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
                        <th class="pb-2 text-center font-semibold text-emerald-500"><?php echo t("Approved", "ยืนยันแล้ว"); ?></th>
                        <th class="pb-2 text-end font-semibold text-rose-500"><?php echo t("Cancelled", "ยกเลิกแล้ว"); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($yearly_summary)): ?>
                        <tr>
                            <td colspan="4" class="text-center py-4 text-zinc-600"><?php echo t("No records", "ไม่มีประวัติ"); ?></td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($yearly_summary as $y): ?>
                            <tr class="border-b border-zinc-900/40 last:border-0 hover:bg-zinc-900/10">
                                <td class="py-2 text-zinc-300 font-bold font-mono"><?php echo $lang === 'th' ? ($y['year'] + 543) : $y['year']; ?></td>
                                <td class="py-2 text-center font-bold text-zinc-200"><?php echo $y['total']; ?></td>
                                <td class="py-2 text-center text-emerald-500 font-bold"><?php echo $y['completed']; ?></td>
                                <td class="py-2 text-end text-rose-500 font-bold"><?php echo $y['cancelled']; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>
<?php endif; ?>

<!-- Reservation Management Tabs -->
<div class="flex gap-2 mb-6 border-b border-zinc-900 pb-px">
    <?php 
    $p_count = count($pending_bookings);
    $c_count = count($confirmed_bookings);
    $comp_count = $comp_total_count;
    $cr_count = count($cancel_requests_bookings);
    $cl_count = $cl_total_count;
    $is_admin = isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'ADMIN';
    ?>

    <!-- Pending Requests Tab -->
    <a href="index.php?tab=pending" class="py-2.5 px-4 text-xs font-anton text-uppercase tracking-wider border-b-2 transition-all <?php echo $active_tab === 'pending' ? 'text-warning border-warning' : 'text-zinc-500 border-transparent hover:text-zinc-300'; ?>">
        <?php echo t("Pending Requests", "รายการส่งคำขอรออนุมัติ"); ?> (<span id="count-pending"><?php echo $p_count; ?></span>)
    </a>

    <!-- Confirmed Bookings Tab -->
    <a href="index.php?tab=confirmed" class="py-2.5 px-4 text-xs font-anton text-uppercase tracking-wider border-b-2 transition-all <?php echo $active_tab === 'confirmed' ? 'text-emerald-500 border-emerald-500' : 'text-zinc-500 border-transparent hover:text-zinc-300'; ?>">
        <?php echo t("Confirmed Bookings", "รายการที่ยืนยันแล้ว"); ?> (<span id="count-confirmed"><?php echo $c_count; ?></span>)
    </a>

    <!-- Completed Bookings Tab -->
    <a href="index.php?tab=completed" class="py-2.5 px-4 text-xs font-anton text-uppercase tracking-wider border-b-2 transition-all <?php echo $active_tab === 'completed' ? 'text-emerald-400 border-emerald-500' : 'text-zinc-500 border-transparent hover:text-zinc-300'; ?>">
        <?php echo t("Completed", "ใช้งานเสร็จแล้ว"); ?> (<span id="count-completed"><?php echo $comp_count; ?></span>)
    </a>

    <!-- Cancel Requests Tab -->
    <a href="index.php?tab=cancel_requests" class="py-2.5 px-4 text-xs font-anton text-uppercase tracking-wider border-b-2 transition-all <?php echo $active_tab === 'cancel_requests' ? 'text-sky-500 border-sky-500' : 'text-zinc-500 border-transparent hover:text-zinc-300'; ?>">
        <?php echo t("Cancel Requests", "คำขอยกเลิกการจอง"); ?> (<span id="count-cancel_requests"><?php echo $cr_count; ?></span>)
    </a>

    <!-- Cancelled Bookings Tab -->
    <a href="index.php?tab=cancelled" class="py-2.5 px-4 text-xs font-anton text-uppercase tracking-wider border-b-2 transition-all <?php echo $active_tab === 'cancelled' ? 'text-rose-500 border-rose-500' : 'text-zinc-500 border-transparent hover:text-zinc-300'; ?>">
        <?php echo t("Cancelled Bookings", "รายการที่ถูกยกเลิก"); ?> (<span id="count-cancelled"><?php echo $cl_count; ?></span>)
    </a>
</div>

<!-- Reservations Table Container -->
<div class="shadcn-card">
    <?php if (in_array($active_tab, ['completed', 'cancelled'])): ?>
        <?php
        // 1. Parse filter values
        $filter_type = $_GET['filter_type'] ?? 'all';
        $selected_date = $_GET['filter_val_day'] ?? date('Y-m-d');
        if (strtotime($selected_date) === false) {
            $selected_date = date('Y-m-d');
        }
        
        $selected_month = $_GET['filter_val_month'] ?? date('Y-m');
        if (strtotime($selected_month . '-01') === false) {
            $selected_month = date('Y-m');
        }
        
        $selected_year = $_GET['filter_val_year'] ?? date('Y');
        
        // Determine filter value based on active filter type
        $filter_val = '';
        if ($filter_type === 'day') {
            $filter_val = $selected_date;
        } elseif ($filter_type === 'month') {
            $filter_val = $selected_month;
        } elseif ($filter_type === 'year') {
            $filter_val = $selected_year;
        }
        
        // 2. Prepare variables for Calendar Grid (Daily view)
        $view_month = $_GET['view_month'] ?? substr($selected_date, 0, 7);
        if (strtotime($view_month . '-01') === false) {
            $view_month = substr($selected_date, 0, 7);
        }
        $c_year = (int)substr($view_month, 0, 4);
        $c_month = (int)substr($view_month, 5, 2);
        
        $first_day_time = strtotime("$c_year-$c_month-01");
        $days_in_month = (int)date('t', $first_day_time);
        $first_day_of_week = (int)date('w', $first_day_time); // 0 (Sun) to 6 (Sat)
        
        $prev_month = date('Y-m', strtotime('-1 month', $first_day_time));
        $next_month = date('Y-m', strtotime('+1 month', $first_day_time));
        
        // 3. Prepare variables for Month Grid (Monthly view)
        $view_year = (int)($_GET['view_year'] ?? substr($selected_month, 0, 4));
        $prev_year = $view_year - 1;
        $next_year = $view_year + 1;
        
        // 4. Fetch days with events (bookings with active status) for calendar dots
        $status_db = ($active_tab === 'completed') ? 'COMPLETED' : 'CANCELLED';
        $event_days = [];
        $event_months = [];
        try {
            // Daily dots
            $stmt = $pdo->prepare("SELECT reservation_date AS date, COUNT(*) as count FROM reservation WHERE reservation_status = ? AND reservation_date LIKE ? GROUP BY reservation_date");
            $stmt->execute([$status_db, "$view_month-%"]);
            foreach ($stmt->fetchAll() as $r) {
                $event_days[$r['date']] = (int)$r['count'];
            }
            
            // Monthly dots
            $stmt = $pdo->prepare("SELECT SUBSTRING(reservation_date, 1, 7) as month, COUNT(*) as count FROM reservation WHERE reservation_status = ? AND reservation_date LIKE ? GROUP BY month");
            $stmt->execute([$status_db, "$view_year-%"]);
            foreach ($stmt->fetchAll() as $r) {
                $event_months[$r['month']] = (int)$r['count'];
            }
        } catch (Exception $e) {}
        ?>
        
        <div class="mb-6 pb-6 border-b border-zinc-900/60 flex flex-col md:flex-row gap-6 items-start w-full">
            <div class="w-full md:w-auto">
                <!-- iOS Segmented Control -->
                <div class="grid grid-cols-4 bg-zinc-950 p-1 rounded-xl w-full md:w-max border border-zinc-900">
                    <a href="index.php?tab=<?php echo $active_tab; ?>&filter_type=all" 
                       class="px-2 md:px-5 py-2 rounded-lg text-xs font-bold font-sans tracking-wide transition-all text-center text-decoration-none <?php echo $filter_type === 'all' ? 'bg-zinc-900 text-warning border border-zinc-800 shadow-sm' : 'text-zinc-400 hover:text-zinc-200'; ?>">
                        <?php echo t("Show All", "ทั้งหมด"); ?>
                    </a>
                    <a href="index.php?tab=<?php echo $active_tab; ?>&filter_type=day&filter_val_day=<?php echo $selected_date; ?>&view_month=<?php echo $view_month; ?>" 
                       class="px-2 md:px-5 py-2 rounded-lg text-xs font-bold font-sans tracking-wide transition-all text-center text-decoration-none <?php echo $filter_type === 'day' ? 'bg-zinc-900 text-warning border border-zinc-800 shadow-sm' : 'text-zinc-400 hover:text-zinc-200'; ?>">
                        <?php echo t("Daily", "รายวัน"); ?>
                    </a>
                    <a href="index.php?tab=<?php echo $active_tab; ?>&filter_type=month&filter_val_month=<?php echo $selected_month; ?>&view_year=<?php echo $view_year; ?>" 
                       class="px-2 md:px-5 py-2 rounded-lg text-xs font-bold font-sans tracking-wide transition-all text-center text-decoration-none <?php echo $filter_type === 'month' ? 'bg-zinc-900 text-warning border border-zinc-800 shadow-sm' : 'text-zinc-400 hover:text-zinc-200'; ?>">
                        <?php echo t("Monthly", "รายเดือน"); ?>
                    </a>
                    <a href="index.php?tab=<?php echo $active_tab; ?>&filter_type=year&filter_val_year=<?php echo $selected_year; ?>" 
                       class="px-2 md:px-5 py-2 rounded-lg text-xs font-bold font-sans tracking-wide transition-all text-center text-decoration-none <?php echo $filter_type === 'year' ? 'bg-zinc-900 text-warning border border-zinc-800 shadow-sm' : 'text-zinc-400 hover:text-zinc-200'; ?>">
                        <?php echo t("Yearly", "รายปี"); ?>
                    </a>
                </div>
            </div>
            
            <!-- Dynamic iOS Calendar Panel -->
            <div class="w-full max-w-[340px] bg-white p-4 rounded-2xl border border-zinc-200 shadow-lg text-zinc-950">
                <?php if ($filter_type === 'all'): ?>
                    <div class="text-zinc-400 text-xs font-mono text-center py-6">
                        <?php echo t("Showing all records without date filtering.", "แสดงรายการทั้งหมดโดยไม่มีตัวกรองวันที่"); ?>
                    </div>
                
                <?php elseif ($filter_type === 'day'): ?>
                    <!-- iPhone Month Grid Calendar -->
                    <div class="flex justify-between items-center mb-4 px-1">
                        <span class="text-xs font-bold text-zinc-800">
                            <?php 
                            $thai_month_names = [
                                1 => 'มกราคม', 2 => 'กุมภาพันธ์', 3 => 'มีนาคม', 4 => 'เมษายน', 5 => 'พฤษภาคม', 6 => 'มิถุนายน',
                                7 => 'กรกฎาคม', 8 => 'สิงหาคม', 9 => 'กันยายน', 10 => 'ตุลาคม', 11 => 'พฤศจิกายน', 12 => 'ธันวาคม'
                            ];
                            $en_month_names = [
                                1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April', 5 => 'May', 6 => 'June',
                                7 => 'July', 8 => 'August', 9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'
                            ];
                            $disp_month = t($en_month_names[$c_month], $thai_month_names[$c_month]);
                            $disp_year = $lang === 'th' ? ($c_year + 543) : $c_year;
                            echo "$disp_month $disp_year";
                            ?>
                        </span>
                        <div class="flex gap-3 text-zinc-500">
                            <a href="index.php?tab=<?php echo $active_tab; ?>&filter_type=day&filter_val_day=<?php echo $selected_date; ?>&view_month=<?php echo $prev_month; ?>" 
                               class="hover:text-warning transition-colors flex items-center justify-center p-1 rounded-full hover:bg-zinc-100">
                                <span class="material-symbols-outlined text-base">chevron_left</span>
                            </a>
                            <a href="index.php?tab=<?php echo $active_tab; ?>&filter_type=day&filter_val_day=<?php echo $selected_date; ?>&view_month=<?php echo $next_month; ?>" 
                               class="hover:text-warning transition-colors flex items-center justify-center p-1 rounded-full hover:bg-zinc-100">
                                <span class="material-symbols-outlined text-base">chevron_right</span>
                            </a>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-7 gap-1 text-[9px] uppercase font-bold tracking-wider text-zinc-950 text-center mb-2">
                        <span><?php echo t("Su", "อา"); ?></span>
                        <span><?php echo t("Mo", "จ"); ?></span>
                        <span><?php echo t("Tu", "อ"); ?></span>
                        <span><?php echo t("We", "พ"); ?></span>
                        <span><?php echo t("Th", "พฤ"); ?></span>
                        <span><?php echo t("Fr", "ศ"); ?></span>
                        <span><?php echo t("Sa", "ส"); ?></span>
                    </div>
                    
                    <div class="grid grid-cols-7 gap-1 text-center text-xs">
                        <?php for ($i = 0; $i < $first_day_of_week; $i++): ?>
                            <span class="aspect-square flex items-center justify-center text-zinc-300">-</span>
                        <?php endfor; ?>
                        
                        <?php for ($d = 1; $d <= $days_in_month; $d++): ?>
                            <?php 
                            $day_str = sprintf('%s-%02d', $view_month, $d);
                            $is_active = ($day_str === $selected_date);
                            $is_today = ($day_str === date('Y-m-d'));
                            $has_events = isset($event_days[$day_str]);
                            ?>
                            <a href="index.php?tab=<?php echo $active_tab; ?>&filter_type=day&filter_val_day=<?php echo $day_str; ?>&view_month=<?php echo $view_month; ?>" 
                               class="aspect-square flex flex-col items-center justify-center rounded-full relative transition-all text-decoration-none <?php 
                                   echo $is_active 
                                       ? 'bg-warning text-zinc-950 font-bold shadow-md' 
                                       : ($is_today 
                                           ? 'border border-warning text-warning font-bold' 
                                           : 'text-zinc-800 hover:bg-zinc-100'); 
                               ?>" style="width: 100%;">
                                <span class="leading-none mt-[-2px]"><?php echo $d; ?></span>
                                <?php if ($has_events): ?>
                                    <span class="w-1 h-1 rounded-full absolute bottom-1 <?php echo $is_active ? 'bg-zinc-950' : 'bg-zinc-400'; ?>"></span>
                                <?php endif; ?>
                            </a>
                        <?php endfor; ?>
                    </div>
                    
                    <div class="mt-4 pt-4 border-t border-zinc-100 flex justify-between items-center">
                        <span class="text-[10px] text-zinc-400"><?php echo t("Select other date:", "เลือกวันที่อื่น:"); ?></span>
                        <input type="date" value="<?php echo $selected_date; ?>" 
                               onchange="window.location.href='index.php?tab=<?php echo $active_tab; ?>&filter_type=day&filter_val_day=' + this.value + '&view_month=' + this.value.substring(0,7)" 
                               class="max-w-[140px] text-xs font-mono bg-zinc-50 text-zinc-800 border border-zinc-200 rounded p-1" style="height: 28px;">
                    </div>
                    
                <?php elseif ($filter_type === 'month'): ?>
                    <!-- iPhone Month Grid (12 Months of the selected year) -->
                    <div class="flex justify-between items-center mb-4 px-1">
                        <span class="text-xs font-bold text-zinc-800">
                            <?php echo t("Select Month of ", "เลือกเดือนของปี ") . ($lang === 'th' ? ($view_year + 543) : $view_year); ?>
                        </span>
                        <div class="flex gap-3 text-zinc-500">
                            <a href="index.php?tab=<?php echo $active_tab; ?>&filter_type=month&filter_val_month=<?php echo $selected_month; ?>&view_year=<?php echo $prev_year; ?>" 
                               class="hover:text-warning transition-colors flex items-center justify-center p-1 rounded-full hover:bg-zinc-100">
                                <span class="material-symbols-outlined text-base">chevron_left</span>
                            </a>
                            <a href="index.php?tab=<?php echo $active_tab; ?>&filter_type=month&filter_val_month=<?php echo $selected_month; ?>&view_year=<?php echo $next_year; ?>" 
                               class="hover:text-warning transition-colors flex items-center justify-center p-1 rounded-full hover:bg-zinc-100">
                                <span class="material-symbols-outlined text-base">chevron_right</span>
                            </a>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-3 gap-2 text-center text-xs">
                        <?php 
                        $thai_months = [
                            1 => 'ม.ค.', 2 => 'ก.พ.', 3 => 'มี.ค.', 4 => 'เม.ย.', 5 => 'พ.ค.', 6 => 'มิ.ย.',
                            7 => 'ก.ค.', 8 => 'ส.ค.', 9 => 'ก.ย.', 10 => 'ต.ค.', 11 => 'พ.ย.', 12 => 'ธ.ค.'
                        ];
                        $en_months = [
                            1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr', 5 => 'May', 6 => 'Jun',
                            7 => 'Jul', 8 => 'Aug', 9 => 'Sep', 10 => 'Oct', 11 => 'Nov', 12 => 'Dec'
                        ];
                        
                        for ($m = 1; $m <= 12; $m++):
                            $m_str = sprintf('%d-%02d', $view_year, $m);
                            $is_active = ($m_str === $selected_month);
                            $is_current_month = ($m_str === date('Y-m'));
                            $has_events = isset($event_months[$m_str]);
                            $m_label = t($en_months[$m], $thai_months[$m]);
                            ?>
                            <a href="index.php?tab=<?php echo $active_tab; ?>&filter_type=month&filter_val_month=<?php echo $m_str; ?>&view_year=<?php echo $view_year; ?>" 
                               class="h-10 rounded-xl border flex flex-col items-center justify-center relative transition-all text-decoration-none <?php 
                                   echo $is_active 
                                       ? 'bg-warning text-zinc-950 font-bold border-warning shadow-sm' 
                                       : ($is_current_month 
                                           ? 'border border-warning text-warning font-bold' 
                                           : 'text-zinc-800 border-zinc-200 hover:bg-zinc-100'); 
                               ?>">
                                <span class="leading-none"><?php echo $m_label; ?></span>
                                <?php if ($has_events): ?>
                                    <span class="w-1 h-1 rounded-full absolute bottom-1 <?php echo $is_active ? 'bg-zinc-950' : 'bg-zinc-400'; ?>"></span>
                                <?php endif; ?>
                            </a>
                        <?php endfor; ?>
                    </div>
                
                <?php elseif ($filter_type === 'year'): ?>
                    <!-- iPhone Year Grid (Last 6 Years) -->
                    <div class="text-xs font-bold text-zinc-800 mb-4 px-1">
                        <?php echo t("Select Year", "เลือกปีจอง"); ?>
                    </div>
                    
                    <div class="grid grid-cols-3 gap-2 text-center text-xs">
                        <?php 
                        $curr_year = (int)date('Y');
                        for ($y = $curr_year; $y >= $curr_year - 5; $y--):
                            $is_active = ((string)$y === (string)$selected_year);
                            $is_current_year = ($y === (int)date('Y'));
                            $th_y = $y + 543;
                            $disp_y = $lang === 'th' ? $th_y : $y;
                            ?>
                            <a href="index.php?tab=<?php echo $active_tab; ?>&filter_type=year&filter_val_year=<?php echo $y; ?>" 
                               class="h-10 rounded-xl border flex items-center justify-center transition-all text-decoration-none <?php 
                                   echo $is_active 
                                       ? 'bg-warning text-zinc-950 font-bold border-warning shadow-sm' 
                                       : ($is_current_year 
                                           ? 'border border-warning text-warning font-bold' 
                                           : 'text-zinc-800 border-zinc-200 hover:bg-zinc-100'); 
                               ?>">
                                <span><?php echo $disp_y; ?></span>
                            </a>
                        <?php endfor; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <?php if ($filter_type !== 'all' && !empty($filter_val)): ?>
            <div class="text-xs text-zinc-500 font-mono mb-4 uppercase tracking-wider flex items-center gap-1.5">
                <span class="material-symbols-outlined text-sm leading-none text-zinc-600">filter_alt</span>
                <span>
                    <?php 
                    $count_display = count($display_bookings);
                    if ($filter_type === 'day') {
                        $formatted_val = date('d/m/Y', strtotime($filter_val));
                        echo t("Filtered by day: $formatted_val (Found $count_display items)", "กรองข้อมูลรายวัน: $formatted_val (พบ $count_display รายการ)");
                    } elseif ($filter_type === 'month') {
                        $formatted_val = formatMonth($filter_val);
                        echo t("Filtered by month: $formatted_val (Found $count_display items)", "กรองข้อมูลรายเดือน: $formatted_val (พบ $count_display รายการ)");
                    } elseif ($filter_type === 'year') {
                        $th_y = (int)$filter_val + 543;
                        $formatted_val = $lang === 'th' ? $th_y : $filter_val;
                        echo t("Filtered by year: $formatted_val (Found $count_display items)", "กรองข้อมูลรายปี: $formatted_val (พบ $count_display รายการ)");
                    }
                    ?>
                </span>
            </div>
        <?php endif; ?>
    <?php endif; ?>

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
                            } elseif ($active_tab === 'cancel_requests') {
                                echo t("No cancellation requests at the moment.", "ขณะนี้ไม่มีคำขอยกเลิกการจอง");
                            } else {
                                echo t("No cancelled reservations.", "ยังไม่มีคิวจองโต๊ะที่ถูกยกเลิก");
                            }
                            ?>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($display_bookings as $b): ?>
                        <tr>
                            <td class="font-semibold text-zinc-100">
                                <?php echo htmlspecialchars($b['customer_name']); ?>
                                <?php if (($active_tab === 'cancelled' || $active_tab === 'cancel_requests') && !empty($b['cancel_reason'])): ?>
                                    <div class="text-rose-400 text-xs mt-1 font-normal font-sans">
                                        <strong><?php echo t("Reason", "หมายเหตุ"); ?>:</strong> <?php echo htmlspecialchars($b['cancel_reason']); ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td class="text-zinc-400"><?php echo htmlspecialchars($b['customer_phone']); ?></td>
                            <td class="text-zinc-400">
                                <span class="text-zinc-200"><?php echo htmlspecialchars($b['date']); ?></span> @ <?php echo htmlspecialchars($b['time_slot']); ?>
                                <?php if ($b['date'] === date('Y-m-d')): ?>
                                    <span class="badge bg-emerald-950 text-emerald-400 border border-emerald-900/60 px-1.5 py-0.5 rounded text-[9px] block mt-1 w-max font-bold font-sans">
                                        <?php echo t("TODAY", "วันนี้"); ?>
                                    </span>
                                <?php endif; ?>
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
                                    <?php if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'ADMIN'): ?>
                                        <!-- Admin View: Only static status badges -->
                                        <?php if ($active_tab === 'pending'): ?>
                                            <span class="badge bg-amber-950 text-amber-400 border border-amber-900/60 px-2.5 py-1 text-xs rounded me-2"><?php echo t("Pending Approval", "รออนุมัติ"); ?></span>
                                        <?php elseif ($active_tab === 'confirmed'): ?>
                                            <span class="badge bg-emerald-950 text-emerald-400 border border-emerald-900/60 px-2.5 py-1 text-xs rounded me-2"><?php echo t("Approved", "อนุมัติแล้ว"); ?></span>
                                        <?php elseif ($active_tab === 'completed'): ?>
                                            <span class="badge inline-flex items-center gap-1 bg-emerald-950/80 text-emerald-400 border border-emerald-800/80 px-2.5 py-1 text-xs rounded font-semibold me-2"><span class="material-symbols-outlined text-sm leading-none text-emerald-400">check_circle</span><?php echo t("Completed", "ใช้งานเสร็จแล้ว"); ?></span>
                                        <?php elseif ($active_tab === 'cancel_requests'): ?>
                                            <span class="badge bg-sky-950 text-sky-400 border border-sky-900/60 px-2.5 py-1 text-xs rounded me-2"><?php echo t("Cancel Requested", "ส่งคำขอยกเลิกแล้ว"); ?></span>
                                        <?php else: ?>
                                            <span class="badge bg-rose-950 text-rose-400 border border-rose-900/60 px-2.5 py-1 text-xs rounded me-2"><?php echo t("Cancelled", "ยกเลิกแล้ว"); ?></span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <!-- Staff View: Interactive action buttons -->
                                        <?php if ($active_tab === 'pending'): ?>
                                            <a href="index.php?action=confirm&booking_id=<?php echo $b['id']; ?>&tab=pending" class="shadcn-btn-success py-1 px-3 text-xs uppercase font-anton tracking-wider"><?php echo t("Confirm", "ยืนยัน"); ?></a>
                                            <a href="javascript:void(0)" onclick="cancelBooking('<?php echo $b['id']; ?>', 'pending')" class="shadcn-btn-danger py-1.5 px-3 text-xs uppercase font-anton tracking-wider"><?php echo t("Cancel", "ปฏิเสธ"); ?></a>
                                        <?php elseif ($active_tab === 'confirmed'): ?>
                                            <span class="badge bg-emerald-950 text-emerald-400 border border-emerald-900/60 px-2.5 py-1 text-xs rounded me-2"><?php echo t("Approved", "อนุมัติแล้ว"); ?></span>
                                            <a href="javascript:void(0)" onclick="confirmClearTable('<?php echo $b['id']; ?>', '<?php echo htmlspecialchars($b['table_number'] ?? $b['table_id'] ?? '-'); ?>', '<?php echo htmlspecialchars($b['customer_name']); ?>')" class="shadcn-btn-success py-1.5 px-3 text-xs uppercase font-anton tracking-wider me-2"><?php echo t("Clear Table", "เคลียร์โต๊ะ"); ?></a>
                                            <a href="javascript:void(0)" onclick="cancelBooking('<?php echo $b['id']; ?>', 'confirmed')" class="shadcn-btn-danger py-1.5 px-3 text-xs uppercase font-anton tracking-wider"><?php echo t("Cancel", "ยกเลิก"); ?></a>
                                        <?php elseif ($active_tab === 'completed'): ?>
                                            <span class="badge inline-flex items-center gap-1 bg-emerald-950/80 text-emerald-400 border border-emerald-800/80 px-2.5 py-1 text-xs rounded font-semibold me-2"><span class="material-symbols-outlined text-sm leading-none text-emerald-400">check_circle</span><?php echo t("Completed", "ใช้งานเสร็จแล้ว"); ?></span>
                                        <?php elseif ($active_tab === 'cancel_requests'): ?>
                                            <a href="javascript:void(0)" onclick="confirmApproveCancel('<?php echo $b['id']; ?>', '<?php echo htmlspecialchars($b['customer_name']); ?>')" class="shadcn-btn-danger py-1.5 px-3 text-xs uppercase font-anton tracking-wider"><?php echo t("Approve Cancel", "ยืนยันยกเลิก"); ?></a>
                                            <a href="index.php?action=reject_cancel&booking_id=<?php echo $b['id']; ?>&tab=cancel_requests" class="shadcn-btn-success py-1 px-3 text-xs uppercase font-anton tracking-wider"><?php echo t("Reject Request", "คงสิทธิ์การจอง"); ?></a>
                                        <?php else: ?>
                                            <span class="badge bg-rose-950 text-rose-400 border border-rose-900/60 px-2.5 py-1 text-xs rounded me-2"><?php echo t("Cancelled", "ยกเลิกแล้ว"); ?></span>
                                            <?php if (isset($b['date']) && $b['date'] >= date('Y-m-d')): ?>
                                                <a href="index.php?action=confirm&booking_id=<?php echo $b['id']; ?>&tab=cancelled" class="shadcn-btn-success py-1 px-3 text-xs uppercase font-anton tracking-wider"><?php echo t("Re-confirm", "อนุมัติใหม่"); ?></a>
                                            <?php endif; ?>
                                        <?php endif; ?>
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

<?php if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'STAFF'): ?>
<!-- STAFF View: Visual Seat Map Control Card (Merged from tables.php) -->
<div class="shadcn-card border border-warning/10 shadow-lg mt-8">
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
            <a href="javascript:void(0)" onclick="toggleTableStatusRealtime(event, '<?php echo $t['id']; ?>', this)" class="admin-map-btn block no-underline transition-transform duration-200 hover:-translate-y-0.5" data-zone="<?php echo $t['zone']; ?>" data-table-id="<?php echo $t['id']; ?>" data-table-status="<?php echo $t['status']; ?>" title="<?php echo t('Click to toggle status', 'คลิกเพื่อสลับสถานะโต๊ะ'); ?>">
                <div class="table-card-inner flex flex-col items-center justify-center border rounded-lg p-3 w-full aspect-square transition-all duration-200 hover:shadow-lg cursor-pointer" 
                     style="<?php echo $t['status'] === 'AVAILABLE' ? 'background-color: rgba(34, 197, 94, 0.05); border-color: rgba(34, 197, 94, 0.2); color: #4ade80;' : 'background-color: rgba(239, 68, 68, 0.05); border-color: rgba(239, 68, 68, 0.2); color: #f87171;'; ?>">
                    <span class="font-anton text-xl leading-none"><?php echo htmlspecialchars($t['number']); ?></span>
                    <span class="text-[10px] font-mono text-zinc-500 mt-1"><?php echo $t['capacity']; ?> Pax</span>
                    <span class="table-status-dot w-1.5 h-1.5 rounded-full mt-2 animate-pulse" style="<?php echo $t['status'] === 'AVAILABLE' ? 'background-color: #22c55e; box-shadow: 0 0 8px #22c55e;' : 'background-color: #ef4444; box-shadow: 0 0 8px #ef4444;'; ?>"></span>
                </div>
            </a>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
function toggleTableStatusRealtime(event, tableId, el) {
    if (event) event.preventDefault();

    const linkEl = el || document.querySelector(`[data-table-id="${tableId}"]`);
    if (!linkEl) return;

    const innerCard = linkEl.querySelector('.table-card-inner') || linkEl.querySelector('div');
    const statusDot = linkEl.querySelector('.table-status-dot') || linkEl.querySelector('span:last-child');
    const currentStatus = linkEl.getAttribute('data-table-status') || 'AVAILABLE';
    const newStatus = (currentStatus === 'AVAILABLE') ? 'OCCUPIED' : 'AVAILABLE';

    // 1. Instant Optimistic UI Update (0ms delay)
    linkEl.setAttribute('data-table-status', newStatus);
    if (innerCard) {
        if (newStatus === 'AVAILABLE') {
            innerCard.style.backgroundColor = 'rgba(34, 197, 94, 0.05)';
            innerCard.style.borderColor = 'rgba(34, 197, 94, 0.2)';
            innerCard.style.color = '#4ade80';
        } else {
            innerCard.style.backgroundColor = 'rgba(239, 68, 68, 0.05)';
            innerCard.style.borderColor = 'rgba(239, 68, 68, 0.2)';
            innerCard.style.color = '#f87171';
        }
    }
    if (statusDot) {
        if (newStatus === 'AVAILABLE') {
            statusDot.style.backgroundColor = '#22c55e';
            statusDot.style.boxShadow = '0 0 8px #22c55e';
        } else {
            statusDot.style.backgroundColor = '#ef4444';
            statusDot.style.boxShadow = '0 0 8px #ef4444';
        }
    }

    // 2. Perform AJAX background update
    fetch(`index.php?action=toggle_status&id=${encodeURIComponent(tableId)}&ajax=1`)
        .then(res => res.json())
        .then(data => {
            if (!data.success) {
                // Revert on failure
                linkEl.setAttribute('data-table-status', currentStatus);
                if (innerCard) {
                    if (currentStatus === 'AVAILABLE') {
                        innerCard.style.backgroundColor = 'rgba(34, 197, 94, 0.05)';
                        innerCard.style.borderColor = 'rgba(34, 197, 94, 0.2)';
                        innerCard.style.color = '#4ade80';
                    } else {
                        innerCard.style.backgroundColor = 'rgba(239, 68, 68, 0.05)';
                        innerCard.style.borderColor = 'rgba(239, 68, 68, 0.2)';
                        innerCard.style.color = '#f87171';
                    }
                }
            }
        })
        .catch(err => console.error("Error toggling table status:", err));
}

function cancelBooking(bookingId, currentTab) {
    document.getElementById('admin-cancel-booking-id').value = bookingId;
    document.getElementById('admin-cancel-tab').value = currentTab;
    document.getElementById('admin-cancel-ref').innerText = '#' + bookingId;
    document.getElementById('admin-cancel-reason').value = '';
    
    const alertBox = document.getElementById('admin-cancel-alert');
    alertBox.classList.add('hidden');
    alertBox.innerText = '';

    const modal = document.getElementById('adminCancelModal');
    if (modal) {
        modal.classList.remove('hidden');
    }
}

function closeAdminCancelModal() {
    const modal = document.getElementById('adminCancelModal');
    if (modal) {
        modal.classList.add('hidden');
    }
}

function submitAdminCancel() {
    const bookingId = document.getElementById('admin-cancel-booking-id').value;
    const currentTab = document.getElementById('admin-cancel-tab').value;
    const reason = document.getElementById('admin-cancel-reason').value.trim();
    const alertBox = document.getElementById('admin-cancel-alert');

    if (!reason) {
        alertBox.innerText = "<?php echo t('Please enter cancellation reason.', 'กรุณาระบุหมายเหตุหรือเหตุผลในการยกเลิกการจอง'); ?>";
        alertBox.classList.remove('hidden');
        document.getElementById('admin-cancel-reason').focus();
        return;
    }

    window.location.href = `index.php?action=cancel&booking_id=${bookingId}&tab=${currentTab}&reason=${encodeURIComponent(reason)}`;
}

function submitCalendarFilter(type, val) {
    if (!val) return;
    if (type === 'day') {
        window.location.href = `index.php?analytics_mode=day&analytics_start=${encodeURIComponent(val)}`;
    } else if (type === 'month') {
        window.location.href = `index.php?analytics_mode=month&analytics_month=${encodeURIComponent(val)}`;
    } else if (type === 'year') {
        window.location.href = `index.php?analytics_mode=year&analytics_year=${encodeURIComponent(val)}`;
    }
}

<?php if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'ADMIN'): ?>
document.addEventListener("DOMContentLoaded", function() {
    const dailyCtx = document.getElementById('dailyChart').getContext('2d');
    new Chart(dailyCtx, {
        type: 'bar',
        data: {
            labels: <?php echo json_encode($daily_labels); ?>,
            datasets: [
                {
                    label: '<?php echo t("Total Bookings", "ยอดจองทั้งหมด"); ?>',
                    data: <?php echo json_encode($daily_total); ?>,
                    backgroundColor: 'rgba(234, 179, 8, 0.8)',
                    borderColor: '#eab308',
                    borderWidth: 1
                },
                {
                    label: '<?php echo t("Approved", "ยืนยันแล้ว"); ?>',
                    data: <?php echo json_encode($daily_confirmed); ?>,
                    backgroundColor: 'rgba(16, 185, 129, 0.8)',
                    borderColor: '#10b981',
                    borderWidth: 1
                },
                {
                    label: '<?php echo t("Cancelled", "ยกเลิกแล้ว"); ?>',
                    data: <?php echo json_encode($daily_cancelled); ?>,
                    borderColor: '#ef4444', // red
                    backgroundColor: 'rgba(239, 68, 68, 0.8)',
                    borderWidth: 1
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    labels: { color: 'rgba(255, 255, 255, 0.7)', font: { family: 'IBM Plex Sans Thai' } }
                }
            },
            scales: {
                x: {
                    grid: { color: 'rgba(255, 255, 255, 0.05)' },
                    ticks: { color: 'rgba(255, 255, 255, 0.6)', font: { family: 'IBM Plex Sans Thai' } }
                },
                y: {
                    grid: { color: 'rgba(255, 255, 255, 0.05)' },
                    ticks: { color: 'rgba(255, 255, 255, 0.6)', stepSize: 1, precision: 0 }
                }
            }
        }
    });

    const monthlyCtx = document.getElementById('monthlyChart').getContext('2d');
    new Chart(monthlyCtx, {
        type: 'bar',
        data: {
            labels: <?php echo json_encode($monthly_labels); ?>,
            datasets: [
                {
                    label: '<?php echo t("Total Bookings", "ยอดจองทั้งหมด"); ?>',
                    data: <?php echo json_encode($monthly_total); ?>,
                    backgroundColor: 'rgba(234, 179, 8, 0.8)',
                    borderColor: '#eab308',
                    borderWidth: 1
                },
                {
                    label: '<?php echo t("Approved Bookings", "ยืนยันแล้ว"); ?>',
                    data: <?php echo json_encode($monthly_confirmed); ?>,
                    backgroundColor: 'rgba(16, 185, 129, 0.8)',
                    borderColor: '#10b981',
                    borderWidth: 1
                },
                {
                    label: '<?php echo t("Cancelled Bookings", "ยกเลิกแล้ว"); ?>',
                    data: <?php echo json_encode($monthly_cancelled); ?>,
                    backgroundColor: 'rgba(239, 68, 68, 0.8)',
                    borderColor: '#ef4444',
                    borderWidth: 1
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    labels: { color: 'rgba(255, 255, 255, 0.7)', font: { family: 'IBM Plex Sans Thai' } }
                }
            },
            scales: {
                x: {
                    grid: { color: 'rgba(255, 255, 255, 0.05)' },
                    ticks: { color: 'rgba(255, 255, 255, 0.6)', font: { family: 'IBM Plex Sans Thai' } }
                },
                y: {
                    grid: { color: 'rgba(255, 255, 255, 0.05)' },
                    ticks: { color: 'rgba(255, 255, 255, 0.6)', stepSize: 1, precision: 0 }
                }
            }
        }
    });
});
<?php endif; ?>

<?php if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'STAFF'): ?>
    // Live Map Zone Filter (from tables.php)
    function filterMapZone(zone, button) {
        const buttons = button.parentNode.querySelectorAll('button');
        buttons.forEach(btn => {
            btn.classList.remove('bg-zinc-900', 'text-zinc-50', 'border', 'border-zinc-800', 'shadow-sm');
            btn.classList.add('text-zinc-400', 'hover:text-zinc-200', 'hover:bg-zinc-900/50');
        });
        
        button.classList.add('bg-zinc-900', 'text-zinc-50', 'border', 'border-zinc-800', 'shadow-sm');
        button.classList.remove('text-zinc-400', 'hover:text-zinc-200', 'hover:bg-zinc-900/50');

        const stageIndicator = document.getElementById('stage-visual-indicator');
        if (stageIndicator) {
            if (zone === 'OUTDOOR') {
                stageIndicator.style.display = 'none';
            } else {
                stageIndicator.style.display = 'block';
            }
        }

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

    document.addEventListener('DOMContentLoaded', () => {
        const allBtn = document.querySelector('#zone-filter-container button');
        if (allBtn) {
            allBtn.classList.add('bg-zinc-900', 'text-zinc-50', 'border', 'border-zinc-800', 'shadow-sm');
        }

        const modeSelect = document.getElementById('analytics_mode_select');
        if (modeSelect) {
            function updateAnalyticsInputs() {
                const mode = modeSelect.value;
                const startWrap = document.getElementById('analytics_start_wrapper');
                const endWrap = document.getElementById('analytics_end_wrapper');
                const monthWrap = document.getElementById('analytics_month_wrapper');
                const yearWrap = document.getElementById('analytics_year_wrapper');
                const startLabel = document.getElementById('analytics_start_label');

                if (startWrap) startWrap.classList.add('hidden');
                if (endWrap) endWrap.classList.add('hidden');
                if (monthWrap) monthWrap.classList.add('hidden');
                if (yearWrap) yearWrap.classList.add('hidden');

                if (mode === 'day') {
                    if (startWrap) startWrap.classList.remove('hidden');
                    if (startLabel) startLabel.innerText = "<?php echo t('Select Date', 'เลือกวันที่ต้องการดู'); ?>";
                } else if (mode === 'range') {
                    if (startWrap) startWrap.classList.remove('hidden');
                    if (endWrap) endWrap.classList.remove('hidden');
                    if (startLabel) startLabel.innerText = "<?php echo t('Start Date', 'วันที่เริ่มต้น'); ?>";
                } else if (mode === 'month') {
                    if (monthWrap) monthWrap.classList.remove('hidden');
                } else if (mode === 'year') {
                    if (yearWrap) yearWrap.classList.remove('hidden');
                }
            }

            modeSelect.addEventListener('change', updateAnalyticsInputs);
            updateAnalyticsInputs();
        }
    });
<?php endif; ?>
</script>

<!-- Admin Cancellation Modal -->
<div id="adminCancelModal" class="fixed inset-0 z-50 hidden flex items-center justify-center bg-black/80 backdrop-blur-sm p-4">
    <div class="bg-zinc-950 border border-zinc-800 rounded-xl shadow-2xl w-full max-w-md overflow-hidden transform transition-all">
        <!-- Modal Header -->
        <div class="bg-zinc-900/90 px-5 py-4 border-b border-zinc-800 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <span class="material-symbols-outlined text-red-500 text-xl">event_busy</span>
                <h3 class="font-anton text-warning tracking-wider text-lg uppercase m-0">
                    <?php echo t("Cancel Reservation", "ระบุเหตุผลการยกเลิกการจอง"); ?>
                </h3>
            </div>
            <button onclick="closeAdminCancelModal()" type="button" class="text-zinc-400 hover:text-white transition-colors">
                <span class="material-symbols-outlined text-xl">close</span>
            </button>
        </div>

        <!-- Modal Body -->
        <div class="p-5">
            <p class="text-zinc-400 text-xs mb-3 font-mono">
                <?php echo t("Please specify the reason or staff notes for cancelling this booking.", "กรุณาระบุหมายเหตุหรือเหตุผลในการยกเลิกรายการจองคิวนี้"); ?>
            </p>

            <div class="bg-zinc-900 border border-zinc-800/80 rounded-lg p-3 mb-4 flex items-center justify-between">
                <span class="text-zinc-500 text-xs font-mono"><?php echo t("Booking Ref:", "หมายเลขการจอง:"); ?></span>
                <span id="admin-cancel-ref" class="font-anton text-warning text-sm tracking-wider"></span>
            </div>

            <div id="admin-cancel-alert" class="hidden mb-3 p-3 bg-red-950/80 border border-red-800 text-red-300 rounded-md text-xs font-mono"></div>

            <form id="admin-cancel-form" onsubmit="event.preventDefault(); submitAdminCancel();">
                <input type="hidden" id="admin-cancel-booking-id">
                <input type="hidden" id="admin-cancel-tab">

                <div class="mb-2">
                    <label for="admin-cancel-reason" class="block text-xs font-mono uppercase tracking-wider text-zinc-400 mb-1.5">
                        <?php echo t("Reason / Remarks", "เหตุผล / หมายเหตุการยกเลิก"); ?> *
                    </label>
                    <textarea id="admin-cancel-reason" rows="3" class="w-full bg-zinc-900 border border-zinc-800 rounded-lg px-3 py-2 text-zinc-100 text-sm focus:outline-none focus:border-warning resize-none font-sans" placeholder="<?php echo t("e.g. Table unavailable, Customer requested cancellation...", "เช่น โต๊ะชำรุด, ลูกค้าติดต่อมาขอยกเลิก ฯลฯ"); ?>" required></textarea>
                </div>
            </form>
        </div>

        <!-- Modal Footer -->
        <div class="bg-zinc-900/60 px-5 py-3 border-t border-zinc-800 flex items-center justify-end gap-2">
            <button onclick="closeAdminCancelModal()" type="button" class="px-4 py-2 bg-zinc-900 hover:bg-zinc-800 text-zinc-300 rounded-lg text-xs font-mono transition-colors">
                <?php echo t("Cancel", "ยกเลิก"); ?>
            </button>
            <button onclick="submitAdminCancel()" type="button" class="px-4 py-2 bg-red-600 hover:bg-red-500 text-white font-semibold rounded-lg text-xs font-mono transition-colors flex items-center gap-1.5 shadow-md">
                <span class="material-symbols-outlined text-sm">check_circle</span>
                <span><?php echo t("Confirm Cancel", "ยืนยันการยกเลิก"); ?></span>
            </button>
        </div>
    </div>
</div>

<script>
    function confirmClearTable(bookingId, tableNum, customerName) {
        document.getElementById('clear-table-num-display').innerText = '<?php echo t("Table ", "โต๊ะหมายเลข "); ?>' + tableNum;
        document.getElementById('clear-table-customer-display').innerText = customerName;
        document.getElementById('confirm-clear-table-btn').href = 'index.php?action=clear_table&booking_id=' + encodeURIComponent(bookingId);
        document.getElementById('clearTableModal').classList.remove('hidden');
    }

    function closeClearTableModal() {
        document.getElementById('clearTableModal').classList.add('hidden');
    }

    function confirmApproveCancel(bookingId, customerName) {
        document.getElementById('approve-cancel-customer-display').innerText = customerName;
        document.getElementById('approve-cancel-ref-display').innerText = '#' + bookingId;
        document.getElementById('confirm-approve-cancel-btn').href = 'index.php?action=approve_cancel&booking_id=' + encodeURIComponent(bookingId) + '&tab=cancel_requests';
        document.getElementById('approveCancelModal').classList.remove('hidden');
    }

    function closeApproveCancelModal() {
        document.getElementById('approveCancelModal').classList.add('hidden');
    }
</script>

<!-- Custom Clear Table Modal Dialog -->
<div id="clearTableModal" class="fixed inset-0 z-50 hidden flex items-center justify-center bg-black/80 backdrop-blur-sm p-4">
    <div class="bg-zinc-950 border border-zinc-800 rounded-2xl shadow-2xl w-full max-w-md overflow-hidden transform transition-all">
        <!-- Modal Header -->
        <div class="bg-zinc-900/90 px-5 py-4 border-b border-zinc-800 flex items-center justify-between">
            <div class="flex items-center gap-2.5">
                <div class="w-9 h-9 rounded-xl bg-emerald-950/80 border border-emerald-900 flex items-center justify-center text-emerald-400">
                    <span class="material-symbols-outlined text-xl">table_restaurant</span>
                </div>
                <div>
                    <h3 class="font-anton text-warning tracking-wider text-lg uppercase m-0 leading-none">
                        <?php echo t("Confirm Clear Table", "ยืนยันการเคลียร์โต๊ะเพื่อรับลูกค้าใหม่"); ?>
                    </h3>
                    <span class="text-zinc-400 text-xs font-mono block mt-1">
                        <?php echo t("Release table & set status to Completed", "เสร็จสิ้นการใช้งานคิวปัจจุบันและคืนสถานะโต๊ะว่าง"); ?>
                    </span>
                </div>
            </div>
            <button onclick="closeClearTableModal()" type="button" class="text-zinc-400 hover:text-white transition-colors">
                <span class="material-symbols-outlined text-xl">close</span>
            </button>
        </div>

        <!-- Modal Body -->
        <div class="p-5">
            <p class="text-zinc-300 text-sm mb-4 font-sans leading-relaxed">
                <?php echo t("Are you sure you want to clear this table for new customer bookings?", "คุณแน่ใจหรือไม่ว่าต้องการเคลียร์โต๊ะนี้เพื่อรับลูกค้ากลุ่มใหม่? สถานะคิวเดิมจะเปลี่ยนเป็นเสร็จสิ้นทันที"); ?>
            </p>

            <!-- Table & Booking Info Badge -->
            <div class="bg-zinc-900/90 border border-zinc-800 rounded-xl p-3.5 mb-4 font-mono text-xs space-y-2">
                <div class="flex justify-between items-center border-b border-zinc-800/80 pb-2">
                    <span class="text-zinc-400"><?php echo t("Table Number:", "หมายเลขโต๊ะ:"); ?></span>
                    <span id="clear-table-num-display" class="font-anton text-warning text-base font-bold"></span>
                </div>
                <div class="flex justify-between items-center pt-0.5">
                    <span class="text-zinc-400"><?php echo t("Current Guest:", "ชื่อลูกค้าผู้เข้าใช้:"); ?></span>
                    <span id="clear-table-customer-display" class="font-semibold text-zinc-100 text-sm"></span>
                </div>
            </div>

            <!-- Status Alert -->
            <div class="bg-emerald-950/40 border border-emerald-900/60 text-emerald-300 p-3 rounded-lg text-xs font-mono flex items-start gap-2">
                <span class="material-symbols-outlined text-sm leading-none mt-0.5 shrink-0 text-emerald-400">check_circle</span>
                <span><?php echo t("Booking status will be set to COMPLETED and the table will become AVAILABLE immediately.", "สถานะคิวจองจะถูกเปลี่ยนเป็นเสร็จสิ้น (COMPLETED) และโต๊ะจะกลับมาเป็นโต๊ะว่างทันที"); ?></span>
            </div>
        </div>

        <!-- Modal Footer -->
        <div class="bg-zinc-900/60 px-5 py-3.5 border-t border-zinc-800 flex items-center justify-end gap-2.5">
            <button onclick="closeClearTableModal()" type="button" class="px-4 py-2 bg-zinc-900 hover:bg-zinc-800 text-zinc-300 border border-zinc-800 rounded-xl text-xs font-mono transition-colors">
                <?php echo t("Cancel", "ยกเลิก"); ?>
            </button>
            <a id="confirm-clear-table-btn" href="#" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-500 text-white font-bold rounded-xl text-xs font-mono transition-all flex items-center gap-1.5 shadow-lg shadow-emerald-600/20 active:scale-95 text-decoration-none">
                <span class="material-symbols-outlined text-base">cleaning_services</span>
                <span><?php echo t("Confirm Clear Table", "ยืนยันเคลียร์โต๊ะ"); ?></span>
            </a>
        </div>
    </div>
</div>

<!-- Custom Approve Cancel Modal Dialog -->
<div id="approveCancelModal" class="fixed inset-0 z-50 hidden flex items-center justify-center bg-black/80 backdrop-blur-sm p-4">
    <div class="bg-zinc-950 border border-zinc-800 rounded-2xl shadow-2xl w-full max-w-md overflow-hidden transform transition-all">
        <!-- Modal Header -->
        <div class="bg-zinc-900/90 px-5 py-4 border-b border-zinc-800 flex items-center justify-between">
            <div class="flex items-center gap-2.5">
                <div class="w-9 h-9 rounded-xl bg-red-950/80 border border-red-900 flex items-center justify-center text-red-500">
                    <span class="material-symbols-outlined text-xl">event_busy</span>
                </div>
                <div>
                    <h3 class="font-anton text-warning tracking-wider text-lg uppercase m-0 leading-none">
                        <?php echo t("Confirm Cancellation Approval", "ยืนยันการอนุมัติยกเลิกการจอง"); ?>
                    </h3>
                    <span class="text-zinc-400 text-xs font-mono block mt-1">
                        <?php echo t("Approve customer cancellation request", "อนุมัติคำขอยกเลิกจากลูกค้าและคืนสถานะโต๊ะว่าง"); ?>
                    </span>
                </div>
            </div>
            <button onclick="closeApproveCancelModal()" type="button" class="text-zinc-400 hover:text-white transition-colors">
                <span class="material-symbols-outlined text-xl">close</span>
            </button>
        </div>

        <!-- Modal Body -->
        <div class="p-5">
            <p class="text-zinc-300 text-sm mb-4 font-sans leading-relaxed">
                <?php echo t("Are you sure you want to approve this customer cancellation request?", "คุณแน่ใจหรือไม่ว่าต้องการอนุมัติคำขอยกเลิกการจองคิวรายการนี้?"); ?>
            </p>

            <!-- Info Badge -->
            <div class="bg-zinc-900/90 border border-zinc-800 rounded-xl p-3.5 mb-4 font-mono text-xs space-y-2">
                <div class="flex justify-between items-center border-b border-zinc-800/80 pb-2">
                    <span class="text-zinc-400"><?php echo t("Customer Name:", "ชื่อลูกค้า:"); ?></span>
                    <span id="approve-cancel-customer-display" class="font-semibold text-zinc-100 text-sm"></span>
                </div>
                <div class="flex justify-between items-center pt-0.5">
                    <span class="text-zinc-400"><?php echo t("Booking Ref:", "รหัสการจอง:"); ?></span>
                    <span id="approve-cancel-ref-display" class="text-warning font-bold"></span>
                </div>
            </div>

            <!-- Caution Alert -->
            <div class="bg-red-950/40 border border-red-900/60 text-red-300 p-3 rounded-lg text-xs font-mono flex items-start gap-2">
                <span class="material-symbols-outlined text-sm leading-none mt-0.5 shrink-0 text-red-400">warning</span>
                <span><?php echo t("Action cannot be undone. Booking status will become CANCELLED.", "การอนุมัติจะเปลี่ยนสถานะคิวจองเป็นยกเลิกแล้ว (CANCELLED) และเปิดโต๊ะให้ว่างทันที"); ?></span>
            </div>
        </div>

        <!-- Modal Footer -->
        <div class="bg-zinc-900/60 px-5 py-3.5 border-t border-zinc-800 flex items-center justify-end gap-2.5">
            <button onclick="closeApproveCancelModal()" type="button" class="px-4 py-2 bg-zinc-900 hover:bg-zinc-800 text-zinc-300 border border-zinc-800 rounded-xl text-xs font-mono transition-colors">
                <?php echo t("Cancel", "ยกเลิก"); ?>
            </button>
            <a id="confirm-approve-cancel-btn" href="#" class="px-4 py-2 bg-red-600 hover:bg-red-500 text-white font-bold rounded-xl text-xs font-mono transition-all flex items-center gap-1.5 shadow-lg shadow-red-600/20 active:scale-95 text-decoration-none">
                <span class="material-symbols-outlined text-base">cancel</span>
                <span><?php echo t("Approve Cancel", "ยืนยันอนุมัติยกเลิก"); ?></span>
            </a>
        </div>
    </div>
</div>

<script>
    // Live Dashboard Counter Auto-Poller (Updates tab numbers in real-time without page refresh)
    function pollLiveDashboardCounts() {
        fetch('index.php?action=get_live_dashboard_counts')
            .then(res => res.json())
            .then(data => {
                if (data && typeof data.p_count !== 'undefined') {
                    const elP = document.getElementById('count-pending');
                    const elC = document.getElementById('count-confirmed');
                    const elComp = document.getElementById('count-completed');
                    const elCR = document.getElementById('count-cancel_requests');
                    const elCL = document.getElementById('count-cancelled');

                    if (elP && elP.innerText !== String(data.p_count)) elP.innerText = data.p_count;
                    if (elC && elC.innerText !== String(data.c_count)) elC.innerText = data.c_count;
                    if (elComp && elComp.innerText !== String(data.comp_count)) elComp.innerText = data.comp_count;
                    if (elCR && elCR.innerText !== String(data.cr_count)) elCR.innerText = data.cr_count;
                    if (elCL && elCL.innerText !== String(data.cl_count)) elCL.innerText = data.cl_count;
                }
            })
            .catch(err => console.log('Live poller:', err));
    }

    // Auto update tab counts every 4 seconds dynamically
    setInterval(pollLiveDashboardCounts, 4000);
</script>

<?php require_once 'admin_footer.php'; ?>
