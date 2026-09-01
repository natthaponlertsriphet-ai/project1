<?php
require_once 'db.php';
require_once 'line_helper.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Temporary Translation Helper (defined locally before header.php load)
if (!function_exists('t')) {
    function t($en, $th) {
        $lang = $_SESSION['lang'] ?? 'en';
        return $lang === 'th' ? $th : $en;
    }
}

// Handle Search Booking
$search_query = trim($_GET['q'] ?? '');
$search_bookings = [];
$searched = false;
$search_error = null;

if (isset($_GET['action']) && $_GET['action'] === 'search_booking') {
    $searched = true;
    if ($search_query === '') {
        $search_error = t("Please enter your Booking ID, Phone Number, or Name.", "กรุณากรอกรหัสการจอง เบอร์โทรศัพท์ หรือชื่อผู้จอง");
    } else {
        try {
            $today = date('Y-m-d');
            $stmt = $pdo->prepare("
                SELECT b.reservation_id AS id, b.customer_name, b.customer_phone, b.reservation_date AS date, b.reservation_time AS time_slot, b.guest_count AS pax, b.table_id, b.reservation_status AS status, b.cancel_reason, b.created_at, b.updated_at, t.table_number, t.zone AS table_zone 
                FROM reservation b 
                LEFT JOIN `table` t ON b.table_id = t.table_id 
                WHERE (b.reservation_id LIKE ? OR b.customer_phone LIKE ? OR b.customer_name LIKE ?)
                  AND b.reservation_date >= ?
                ORDER BY b.created_at DESC
            ");
            $like_query = "%" . $search_query . "%";
            $stmt->execute([$like_query, $like_query, $like_query, $today]);
            $search_bookings = $stmt->fetchAll();
        } catch (Exception $e) {
            $search_error = "Error: " . $e->getMessage();
        }
    }
}

// Handle Customer Cancel Request
if (isset($_GET['action']) && $_GET['action'] === 'request_cancel') {
    $booking_id = trim($_GET['booking_id'] ?? '');
    $phone = trim($_GET['phone'] ?? '');
    $reason = trim($_GET['reason'] ?? '');
    
    if (!$booking_id || !$phone || !$reason) {
        $_SESSION['booking_error'] = t("Missing required fields for cancellation request.", "กรุณากรอกข้อมูลให้ครบถ้วนเพื่อส่งคำขอยกเลิก");
    } else {
        try {
            // Check if booking exists
            $stmt = $pdo->prepare("SELECT reservation_id, customer_phone, reservation_date, reservation_status FROM reservation WHERE reservation_id = ?");
            $stmt->execute([$booking_id]);
            $b = $stmt->fetch();
            
            if (!$b) {
                $_SESSION['booking_error'] = t("Booking not found.", "ไม่พบข้อมูลการจอง");
            } elseif ($b['customer_phone'] !== $phone) {
                $_SESSION['booking_error'] = t("Incorrect phone number. Cancellation request denied.", "เบอร์โทรศัพท์ไม่ถูกต้อง ไม่สามารถส่งคำขอยกเลิกได้");
            } elseif ($b['reservation_date'] < date('Y-m-d')) {
                $_SESSION['booking_error'] = t("Cannot cancel a past reservation.", "ไม่สามารถขอยกเลิกรายการจองในอดีตได้");
            } elseif ($b['reservation_status'] === 'CANCELLED' || $b['reservation_status'] === 'CANCEL_REQUESTED') {
                $_SESSION['booking_error'] = t("This booking has already been cancelled or has a pending cancellation request.", "รายการจองนี้ถูกยกเลิกหรืออยู่ในระหว่างการขอยกเลิกแล้ว");
            } else {
                // Update booking status to CANCEL_REQUESTED and save reason
                $stmt = $pdo->prepare("UPDATE reservation SET reservation_status = 'CANCEL_REQUESTED', cancel_reason = ? WHERE reservation_id = ?");
                $stmt->execute([$reason, $booking_id]);
                $_SESSION['booking_success_msg'] = t("Cancellation request sent to staff. Please wait for confirmation.", "ส่งคำขอยกเลิกการจองแล้ว กรุณารอพนักงานกดยืนยันการยกเลิก");
            }
        } catch (Exception $e) {
            $_SESSION['booking_error'] = "Error: " . $e->getMessage();
        }
    }
    header("Location: reservation.php?action=search_booking&q=" . urlencode($booking_id));
    exit;
}

// AJAX Request for Instant Live Booking Search
if (isset($_GET['action']) && $_GET['action'] === 'ajax_search_booking') {
    header('Content-Type: application/json');
    $q = trim($_GET['q'] ?? '');
    if ($q === '') {
        echo json_encode(['error' => t('Please enter a Booking Ref ID or Phone Number', 'กรุณากรอกรหัสการจอง หรือ เบอร์โทรศัพท์'), 'bookings' => []]);
        exit;
    }
    
    try {
        $stmt = $pdo->prepare("
            SELECT b.reservation_id AS id, b.customer_name, b.customer_phone, b.reservation_date AS date, b.reservation_time AS time_slot, b.guest_count AS pax, b.table_id, b.reservation_status AS status, b.cancel_reason, b.created_at, t.table_number, t.zone AS table_zone 
            FROM reservation b 
            LEFT JOIN `table` t ON b.table_id = t.table_id 
            WHERE (b.reservation_id LIKE ? OR b.customer_phone LIKE ? OR b.customer_name LIKE ?)
            ORDER BY b.created_at DESC
        ");
        $like_query = "%" . $q . "%";
        $stmt->execute([$like_query, $like_query, $like_query]);
        $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'query' => $q,
            'bookings' => $bookings
        ]);
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage(), 'bookings' => []]);
    }
    exit;
}

// AJAX Request to fetch live booking statuses for a search query
if (isset($_GET['action']) && $_GET['action'] === 'poll_booking_statuses') {
    header('Content-Type: application/json');
    $q = trim($_GET['q'] ?? '');
    if ($q === '') {
        echo json_encode([]);
        exit;
    }
    
    try {
        $today = date('Y-m-d');
        $stmt = $pdo->prepare("
            SELECT b.reservation_id AS id, b.reservation_status AS status, b.cancel_reason, b.customer_phone 
            FROM reservation b 
            WHERE (b.reservation_id LIKE ? OR b.customer_phone LIKE ? OR b.customer_name LIKE ?)
              AND b.reservation_date >= ?
            ORDER BY b.created_at DESC
        ");
        $like_query = "%" . $q . "%";
        $stmt->execute([$like_query, $like_query, $like_query, $today]);
        $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($bookings);
    } catch (Exception $e) {
        echo json_encode([]);
    }
    exit;
}

// AJAX Request to fetch booked tables for a specific date and time slot
if (isset($_GET['action']) && $_GET['action'] === 'get_booked_tables') {
    header('Content-Type: application/json');
    $date = $_GET['date'] ?? '';
    $time_slot = $_GET['time_slot'] ?? '';
    
    if (!$date || !$time_slot) {
        echo json_encode([]);
        exit;
    }
    
    try {
        // Fetch booked tables for this specific date and time slot
        $stmt = $pdo->prepare("SELECT table_id FROM reservation WHERE reservation_date = ? AND reservation_time = ? AND reservation_status IN ('PENDING', 'CONFIRMED', 'CANCEL_REQUESTED') AND table_id IS NOT NULL");
        $stmt->execute([$date, $time_slot]);
        $booked_table_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

        // Include physically OCCUPIED tables ONLY if the target booking date is TODAY
        if ($date === date('Y-m-d')) {
            $stmt2 = $pdo->query("SELECT table_id AS id FROM `table` WHERE table_status = 'OCCUPIED'");
            $occupied_table_ids = $stmt2->fetchAll(PDO::FETCH_COLUMN);
            $reserved_ids = array_unique(array_merge($booked_table_ids, $occupied_table_ids));
        } else {
            $reserved_ids = $booked_table_ids;
        }

        echo json_encode(array_values($reserved_ids));
    } catch (Exception $e) {
        echo json_encode([]);
    }
    exit;
}

// Handle Form Submission (Create Booking)
$booking_success = null;
$booking_error = $_SESSION['booking_error'] ?? null;
unset($_SESSION['booking_error']);

$booking_success_msg = $_SESSION['booking_success_msg'] ?? null;
unset($_SESSION['booking_success_msg']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_booking') {
    $customer_name = trim($_POST['customer_name'] ?? '');
    $customer_phone = trim($_POST['customer_phone'] ?? '');
    $date = trim($_POST['date'] ?? '');
    $time_slot = trim($_POST['time_slot'] ?? '');
    $pax = (int)($_POST['pax'] ?? 0);
    $table_id = trim($_POST['table_id'] ?? '');
    
    if (!$customer_name || !$customer_phone || !$date || !$time_slot || $pax <= 0 || !$table_id) {
        $booking_error = "Please fill in all fields and select a table.";
    } elseif (!preg_match('/^\+?[0-9\s\-()]+$/', $customer_phone)) {
        $booking_error = t("Invalid phone number format.", "เบอร์โทรไม่ถูกต้อง");
    } else {
        try {
            // Check double booking
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM reservation WHERE reservation_date = ? AND reservation_time = ? AND table_id = ? AND reservation_status IN ('PENDING', 'CONFIRMED', 'CANCEL_REQUESTED')");
            $stmt->execute([$date, $time_slot, $table_id]);
            
            if ($stmt->fetchColumn() > 0) {
                $booking_error = "This table has already been reserved for the selected timeslot.";
            } else {
                // Fetch table details to verify capacity and status
                $stmt = $pdo->prepare("SELECT capacity, table_number AS number, table_status AS status FROM `table` WHERE table_id = ?");
                $stmt->execute([$table_id]);
                $table = $stmt->fetch();
                
                if (!$table) {
                    $booking_error = "Invalid table selected.";
                } elseif ($table['status'] === 'OCCUPIED') {
                    $booking_error = t("This table is currently unavailable. It has been occupied or closed by staff.", "ขออภัย โต๊ะนี้ไม่สามารถจองได้เนื่องจากถูกปิดบริการหรือทำเครื่องหมายเป็นไม่ว่างโดยพนักงานร้าน");
                } elseif ($pax > $table['capacity']) {
                    $booking_error = "Selected table capacity is too small for {$pax} guests (Max: {$table['capacity']}).";
                } else {
                    // Create booking
                    $booking_id = 'CHITHOLECNX_' . uniqid();
                    
                    $stmt = $pdo->prepare("INSERT INTO reservation (reservation_id, customer_name, customer_phone, reservation_date, reservation_time, guest_count, table_id, reservation_status) VALUES (?, ?, ?, ?, ?, ?, ?, 'PENDING')");
                    $stmt->execute([$booking_id, $customer_name, $customer_phone, $date, $time_slot, $pax, $table_id]);
                    
                    // Send LINE notification (Admin Only)
                    $stmt = $pdo->prepare("SELECT b.reservation_id AS id, b.customer_name, b.customer_phone, b.reservation_date AS date, b.reservation_time AS time_slot, b.guest_count AS pax, b.table_id, b.reservation_status AS status, b.cancel_reason, b.created_at, b.updated_at, t.table_number, t.zone AS table_zone FROM reservation b LEFT JOIN `table` t ON b.table_id = t.table_id WHERE b.reservation_id = ?");
                    $stmt->execute([$booking_id]);
                    $new_b = $stmt->fetch();
                    if ($new_b) {
                        notifyAdminNewBooking($new_b);
                    }
                    
                    $booking_success = [
                        'id' => $booking_id,
                        'name' => $customer_name,
                        'phone' => $customer_phone,
                        'date' => $date,
                        'time_slot' => $time_slot,
                        'pax' => $pax,
                        'table_number' => $table['number']
                    ];
                }
            }
        } catch (Exception $e) {
            $booking_error = "System error: " . $e->getMessage();
        }
    }
}

// Fetch all tables for initial map layout render
$stmt = $pdo->query("SELECT table_id AS id, table_number AS number, zone, capacity, table_status AS status, image FROM `table` ORDER BY table_number");
$tables = $stmt->fetchAll();

// Hero slider mock images (using local home-booking folder)
$hero_images = [
    "images/home-booking/749356007_122278339724129427_2108767678100899836_n.jpg",
    "images/home-booking/735563412_122276495840129427_6246480903433139955_n.jpg",
    "images/home-booking/726421203_122249018726266045_7649995462205678945_n.jpg",
    "images/home-booking/725803827_122249018930266045_4881032058120241973_n.jpg",
    "images/home-booking/752741095_122278340024129427_3159467724997469637_n.jpg"
];

require_once 'header.php';
?>

<style>
    .hero-slider {
        position: relative;
        height: 60vh;
        width: 100%;
        overflow: hidden;
    }
    .hero-slide {
        position: absolute;
        top: 0; left: 0; width: 100%; height: 100%;
        background-size: cover;
        background-position: center;
        opacity: 0;
        transition: opacity 1.5s ease-in-out;
        z-index: 1;
    }
    .hero-slide.active {
        opacity: 0.35;
        z-index: 2;
    }
    .hero-content {
        position: absolute;
        bottom: 10%;
        left: 5%;
        z-index: 3;
        max-width: 600px;
    }
    .table-btn {
        width: 60px;
        height: 60px;
        margin: 5px;
        font-family: 'Rockwell', 'Pridi', 'Arvo', serif;
        font-size: 16px;
        border-radius: 6px;
        transition: all 0.2s;
        cursor: pointer;
        display: inline-flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        border: 1px solid rgba(255, 255, 255, 0.1);
    }
    .table-capacity {
        font-size: 9px;
        font-family: 'Rockwell', 'Pridi', 'Arvo', serif;
        opacity: 0.8;
    }
    .table-available {
        background-color: #212529;
        color: #fff;
    }
    .table-available:hover {
        background-color: #ffd782;
        color: #3f2e00;
        border-color: #ffd782;
        box-shadow: 0 0 10px rgba(255, 215, 130, 0.5);
    }
    .table-selected {
        background-color: #ffd782 !important;
        color: #3f2e00 !important;
        border-color: #ffd782 !important;
        box-shadow: 0 0 15px rgba(255, 215, 130, 0.8);
    }
    .table-reserved {
        background-color: #842029 !important;
        color: #f8d7da !important;
        border-color: #842029 !important;
        cursor: not-allowed !important;
        opacity: 0.5;
    }

    /* Interactive Table Tooltip styling */
    .table-tooltip {
        position: fixed;
        z-index: 9999;
        width: 250px;
        background-color: #0c0c0e;
        border: 1px solid rgba(255, 255, 255, 0.15);
        border-radius: 8px;
        box-shadow: 0 10px 25px rgba(0,0,0,0.6);
        overflow: hidden;
        pointer-events: none;
        transition: opacity 0.15s ease;
    }
    .table-tooltip img {
        width: 100%;
        height: 130px;
        object-fit: cover;
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    }
    .table-tooltip .tooltip-content {
        padding: 12px;
    }
    .table-tooltip .tooltip-detail {
        display: flex;
        align-items: center;
        gap: 6px;
        font-size: 12px;
        color: #d4d4d8;
        margin-top: 4px;
    }
    .table-tooltip .tooltip-detail .material-symbols-outlined {
        font-size: 16px;
        color: #ffd782;
    }

    #search-query-input::placeholder {
        color: #a1a1aa !important;
        opacity: 0.75 !important;
    }
    #search-query-input {
        color: #ffffff !important;
    }
</style>

<!-- Hero Slider Section -->
<div class="hero-slider bg-black">
    <?php foreach ($hero_images as $index => $img): ?>
        <div class="hero-slide <?php echo $index === 0 ? 'active' : ''; ?>" style="background-image: url('<?php echo $img; ?>');"></div>
    <?php endforeach; ?>
    <div class="absolute inset-0 z-0 bg-gradient-to-t from-[#131313] via-[#131313]/60 to-transparent" style="position:absolute; bottom:0; left:0; right:0; height:150px; background: linear-gradient(to top, #131313, transparent);"></div>
    <div class="hero-content">
        <span class="font-anton text-warning text-uppercase tracking-wider fs-6 d-block mb-2">[ <?php echo t("TAPROOM EXPERIENCE", "ค่ำคืนพิเศษกับแท็ปเบียร์คัดสรร"); ?> ]</span>
        <h1 class="font-anton text-light text-uppercase tracking-wide display-3 lh-1 mb-3">
            <?php echo t("Reserve", "จองโต๊ะ"); ?><br>
            <span class="text-warning"><?php echo t("Your spot", "ที่นั่งของคุณ"); ?></span>
        </h1>
        <p class="text-secondary fs-5">
            <?php echo t("Secure your seat under Chiang Mai's nocturnal sky. Enjoy fresh Original Thai Craft Beer, live music, and warm friendly vibes all night long.", "ล็อกมุมโปรดใต้แสงดาวเชียงใหม่ ดื่มด่ำ Original Thai Craft Beer สดใหม่หลากสไตล์ เคล้าเสียงดนตรีสดและบรรยากาศเป็นกันเองตลอดค่ำคืน"); ?>
        </p>
    </div>
</div>

<div class="container px-4 px-lg-5 py-5">
    
    <!-- Success/Error Feedback Alerts -->
    <?php if ($booking_success_msg): ?>
        <div class="alert alert-success bg-success bg-opacity-20 border border-success text-light p-4 rounded-3 mb-5 font-sans">
            <h4 class="font-anton text-success text-uppercase tracking-wider mb-2"><?php echo t("SUCCESS", "สำเร็จ"); ?></h4>
            <div class="font-sans small">
                <?php echo $booking_success_msg; ?>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($booking_success): ?>
        <div class="alert alert-success bg-success bg-opacity-20 border border-success text-light p-4 rounded-3 mb-5">
            <h4 class="font-anton text-success text-uppercase tracking-wider mb-2"><?php echo t("BOOKING CONFIRMED (PENDING APPROVAL)", "จองโต๊ะสำเร็จ (รอแอดมินอนุมัติ)"); ?></h4>
            <div class="font-mono small">
                <div><strong><?php echo t("Booking Ref ID", "รหัสการจอง"); ?>:</strong> <?php echo $booking_success['id']; ?></div>
                <div><strong><?php echo t("Customer Name", "ชื่อลูกค้า"); ?>:</strong> <?php echo $booking_success['name']; ?></div>
                <div><strong><?php echo t("Phone", "เบอร์โทรศัพท์"); ?>:</strong> <?php echo $booking_success['phone']; ?></div>
                <div><strong><?php echo t("Date & Slot", "วันและเวลา"); ?>:</strong> <?php echo $booking_success['date']; ?> @ <?php echo $booking_success['time_slot']; ?></div>
                <div><strong><?php echo t("Table Number", "โต๊ะที่เลือก"); ?>:</strong> <?php echo $booking_success['table_number']; ?> (<?php echo $booking_success['pax']; ?> Pax)</div>
            </div>
            <div class="mt-3 text-black" style="font-size: 18px;">
                <?php echo t("Please take a screenshot of this receipt. Show it to our staff upon arrival.", "กรุณาแคปหน้าจอหลักฐานชิ้นนี้เพื่อยื่นให้พนักงานร้านตรวจสอบเมื่อคุณเดินทางมาถึง"); ?>
            </div>
            

        </div>
    <?php endif; ?>

    <?php if ($booking_error): ?>
        <div class="alert alert-danger bg-danger bg-opacity-10 border border-danger text-light p-4 rounded-3 mb-5 font-mono">
            [ERROR]: <?php echo $booking_error; ?>
        </div>
    <?php endif; ?>

    <div class="row g-5">
        <!-- Left Side: Table Map Selection -->
        <div class="col-lg-8">
            <div class="glass-card p-4 p-md-5">
                <div class="border-bottom border-secondary border-opacity-25 pb-3 mb-4 d-flex justify-content-between align-items-center">
                    <h2 class="font-anton text-warning text-uppercase tracking-wider m-0"><?php echo t("Select Your Table", "เลือกโต๊ะนั่ง"); ?></h2>
                    <div class="d-flex gap-3 small">
                        <span class="d-flex align-items-center gap-1"><span class="badge bg-dark border border-secondary" style="width: 12px; height: 12px; display:inline-block;"></span> <?php echo t("Available", "ว่าง"); ?></span>
                        <span class="d-flex align-items-center gap-1"><span class="badge bg-danger" style="width: 12px; height: 12px; display:inline-block;"></span> <?php echo t("Reserved", "ไม่ว่าง"); ?></span>
                    </div>
                </div>

                <!-- Interactive Zone Filter -->
                <div class="d-flex flex-wrap gap-2 mb-4">
                    <button class="btn btn-sm btn-outline-warning font-anton text-uppercase active px-3" onclick="filterZone('ALL', this)"><?php echo t("All Zones", "ทุกโซน"); ?></button>
                    <button class="btn btn-sm btn-outline-warning font-anton text-uppercase px-3" onclick="filterZone('OUTDOOR', this)"><?php echo t("Outdoor", "ด้านนอก"); ?></button>
                    <button class="btn btn-sm btn-outline-warning font-anton text-uppercase px-3" onclick="filterZone('INDOOR_WINDOW', this)"><?php echo t("Window Side", "ติดกระจก"); ?></button>
                    <button class="btn btn-sm btn-outline-warning font-anton text-uppercase px-3" onclick="filterZone('INDOOR_CENTER', this)"><?php echo t("Center", "ตรงกลาง"); ?></button>
                    <button class="btn btn-sm btn-outline-warning font-anton text-uppercase px-3" onclick="filterZone('STAGE', this)"><?php echo t("Stage", "หน้าเวที"); ?></button>
                    <button class="btn btn-sm btn-outline-warning font-anton text-uppercase px-3" onclick="filterZone('BAR', this)"><?php echo t("Bar Front", "หน้าบาร์"); ?></button>
                    <button class="btn btn-sm btn-outline-warning font-anton text-uppercase px-3" onclick="filterZone('WALKWAY', this)"><?php echo t("Walkway", "โซนทางเดิน"); ?></button>
                </div>

                <!-- Visual Grid Layout -->
                <div class="d-flex flex-wrap justify-content-start gap-2 p-3 bg-black bg-opacity-50 border border-secondary border-opacity-25 rounded mb-4">
                    <?php foreach ($tables as $t): ?>
                        <div 
                            id="table-<?php echo $t['id']; ?>"
                            class="table-btn <?php echo $t['status'] === 'OCCUPIED' ? 'table-reserved' : 'table-available'; ?>"
                            data-id="<?php echo $t['id']; ?>"
                            data-number="<?php echo $t['number']; ?>"
                            data-capacity="<?php echo $t['capacity']; ?>"
                            data-zone="<?php echo $t['zone']; ?>"
                            data-image="<?php echo htmlspecialchars($t['image'] ?? ''); ?>"
                            onclick="selectTable(this)"
                        >
                            <span><?php echo $t['number']; ?></span>
                            <span class="table-capacity"><?php echo $t['capacity']; ?> P</span>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="text-secondary small">
                    * <?php echo t("Please select Date and Time Slot first to see live availability status.", "กรุณาเลือกวันที่และเวลาจองเพื่ออัปเดตสถานะความว่างของโต๊ะแบบเรียลไทม์"); ?>
                </div>
            </div>
            
            <!-- Booking Status Checker Card -->
            <div class="glass-card p-4 p-md-5 border border-secondary border-opacity-25 mt-4 relative overflow-hidden">
                <div class="absolute top-0 start-0 end-0 bg-warning" style="height: 3px; position:absolute;"></div>
                <h3 class="font-anton text-warning text-uppercase tracking-wider mb-2 mt-1"><?php echo t("Check Booking Status", "ตรวจสอบสถานะการจองโต๊ะ"); ?></h3>
                <p class="text-white small mb-4 opacity-90"><?php echo t("Enter your Booking Ref ID or Phone Number to verify your reservation status.", "กรอกรหัสการจองหรือเบอร์โทรศัพท์ของคุณเพื่อตรวจสอบสถานะการอนุมัติโต๊ะนั่ง"); ?></p>
                
                <form id="search-booking-form" onsubmit="performBookingSearch(event)" action="reservation.php" method="GET" class="row g-2 mb-3">
                    <input type="hidden" name="action" value="search_booking">
                    <div class="col-sm-9">
                        <input type="text" id="search-query-input" name="q" required class="form-control bg-dark border-secondary border-opacity-50 text-white rounded-0 py-2.5 font-sans" placeholder="<?php echo t('Enter Booking Ref ID or Phone Number', 'กรอกรหัสการจอง หรือ เบอร์โทรศัพท์'); ?>" value="<?php echo htmlspecialchars($search_query ?? ''); ?>">
                    </div>
                    <div class="col-sm-3">
                        <button type="submit" id="search-submit-btn" class="btn btn-custom-gold w-100 py-2.5 text-uppercase font-anton">
                            <?php echo t("Search", "ค้นหาข้อมูล"); ?>
                        </button>
                    </div>
                </form>

                <div id="search-results-container">


                <?php if ($searched && !$search_error): ?>
                    <div class="d-flex flex-column gap-4 mt-4">
                        <?php if (empty($search_bookings)): ?>
                            <div class="text-center py-5 bg-black bg-opacity-30 border border-secondary border-opacity-10 rounded">
                                <p class="text-secondary small m-0"><?php echo t("No reservations found matching your query.", "ไม่พบประวัติการจองที่ตรงกับข้อมูลดังกล่าว"); ?></p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($search_bookings as $b): ?>
                                <div class="p-4 bg-black bg-opacity-40 border border-secondary border-opacity-25 rounded font-mono text-sm" id="booking-card-<?php echo $b['id']; ?>">
                                    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 border-bottom border-secondary border-opacity-10 pb-3">
                                        <span class="text-light font-bold fs-6">Ref ID: <?php echo $b['id']; ?></span>
                                        <div class="d-flex gap-2 align-items-center mt-2 mt-sm-0">
                                            <div id="status-badge-<?php echo $b['id']; ?>">
                                                <?php if ($b['status'] === 'CONFIRMED'): ?>
                                                    <span class="badge bg-success border border-success text-black px-2.5 py-1.5 rounded font-sans">
                                                        <?php echo t("CONFIRMED (APPROVED)", "ยืนยันแล้ว (ได้รับอนุมัติ)"); ?>
                                                    </span>
                                                <?php elseif ($b['status'] === 'COMPLETED'): ?>
                                                    <span class="badge bg-secondary border border-secondary text-white px-2.5 py-1.5 rounded font-sans">
                                                        <?php echo t("COMPLETED", "เสร็จสิ้นการใช้งานแล้ว"); ?>
                                                    </span>
                                                <?php elseif ($b['status'] === 'CANCELLED'): ?>
                                                    <span class="badge bg-danger border border-danger text-black px-2.5 py-1.5 rounded font-sans">
                                                        <?php echo t("CANCELLED (REJECTED)", "ยกเลิกแล้ว"); ?>
                                                    </span>
                                                <?php elseif ($b['status'] === 'CANCEL_REQUESTED'): ?>
                                                    <span class="badge bg-info border border-info text-black px-2.5 py-1.5 rounded font-sans">
                                                        <?php echo t("CANCEL REQUESTED (PENDING)", "ส่งคำขอยกเลิกแล้ว (รอพนักงานอนุมัติ)"); ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="badge bg-warning border border-warning text-black px-2.5 py-1.5 rounded font-sans">
                                                        <?php echo t("PENDING APPROVAL", "รอการอนุมัติ"); ?>
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                            
                                            <div id="cancel-btn-container-<?php echo $b['id']; ?>">
                                                <?php if ($b['status'] === 'PENDING' || $b['status'] === 'CONFIRMED'): ?>
                                                    <button class="btn btn-sm btn-outline-danger font-sans px-2.5 py-1 rounded" onclick="requestCancelBooking('<?php echo $b['id']; ?>', '<?php echo htmlspecialchars($b['customer_phone']); ?>')">
                                                        <?php echo t("Cancel Booking", "ยกเลิกจอง"); ?>
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row g-3 text-secondary">
                                        <div class="col-sm-6">
                                            <strong><?php echo t("Customer Name", "ชื่อผู้จอง"); ?>:</strong> 
                                            <span class="text-light font-sans"><?php echo htmlspecialchars($b['customer_name']); ?></span>
                                        </div>
                                        <div class="col-sm-6">
                                            <strong><?php echo t("Phone", "เบอร์โทร"); ?>:</strong> 
                                            <span class="text-light font-sans">
                                                <?php 
                                                $phone = htmlspecialchars($b['customer_phone']);
                                                echo (strlen($phone) > 3) ? substr($phone, 0, -3) . 'xxx' : '***';
                                                ?>
                                            </span>
                                        </div>
                                        <div class="col-sm-6">
                                            <strong><?php echo t("Reservation Date & Time", "วันเวลาจอง"); ?>:</strong> 
                                            <span class="text-warning"><?php echo $b['date']; ?> @ <?php echo $b['time_slot']; ?></span>
                                        </div>
                                        <div class="col-sm-6">
                                            <strong><?php echo t("Table & Size", "โต๊ะและจำนวนที่นั่ง"); ?>:</strong> 
                                            <span class="text-light font-sans">
                                                Table <?php echo htmlspecialchars($b['table_number'] ?? 'N/A'); ?> 
                                                (<?php echo $b['pax']; ?> Pax) - 
                                                <?php 
                                                 if ($b['table_zone'] === 'INDOOR') echo t("Indoor AC", "ห้องแอร์");
                                                 elseif ($b['table_zone'] === 'OUTDOOR') echo t("Outdoor Breeze", "โซนด้านนอก");
                                                 elseif ($b['table_zone'] === 'STAGE') echo t("Stage Front", "หน้าเวที");
                                                 elseif ($b['table_zone'] === 'INDOOR_WINDOW') echo t("Indoor Window", "ติดกระจก");
                                                 elseif ($b['table_zone'] === 'INDOOR_CENTER') echo t("Indoor Center", "ตรงกลาง");
                                                 elseif ($b['table_zone'] === 'BAR') echo t("Bar Front", "หน้าบาร์");
                                                 elseif ($b['table_zone'] === 'WALKWAY') echo t("Walkway Zone", "โซนทางเดิน");
                                                 else echo htmlspecialchars($b['table_zone']);
                                                 ?>
                                            </span>
                                        </div>
                                        
                                        <div class="col-12 mt-3 pt-3 border-top border-secondary border-opacity-10 <?php echo (($b['status'] === 'CANCELLED' || $b['status'] === 'CANCEL_REQUESTED') && !empty($b['cancel_reason'])) ? '' : 'd-none'; ?>" id="cancel-reason-container-<?php echo $b['id']; ?>">
                                            <strong class="text-danger"><?php echo t("Cancellation Reason", "เหตุผลที่ยกเลิก/หมายเหตุ"); ?>:</strong>
                                            <span class="text-light font-sans" id="cancel-reason-text-<?php echo $b['id']; ?>"><?php echo htmlspecialchars($b['cancel_reason'] ?? ''); ?></span>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                <?php elseif ($search_error): ?>
                    <div class="alert alert-danger bg-danger bg-opacity-10 border border-danger text-light p-3 rounded-0 font-mono mt-3">
                        [ERROR]: <?php echo $search_error; ?>
                    </div>
                <?php endif; ?>
                </div><!-- end #search-results-container -->
            </div>
        </div>

        <!-- Right Side: Booking Details Input Form -->
        <div class="col-lg-4">
            <div class="glass-card p-4 border border-warning border-opacity-25 relative">
                <div class="absolute top-0 start-0 end-0 bg-warning" style="height: 3px;"></div>
                <h3 class="font-anton text-warning text-uppercase tracking-wider mb-4 mt-2"><?php echo t("Booking Details", "รายละเอียดการจอง"); ?></h3>
                
                <form action="reservation.php" method="POST" onsubmit="return validateBookingForm()" novalidate>
                    <input type="hidden" name="action" value="create_booking">
                    <input type="hidden" name="table_id" id="form-table-id" value="">

                    <div class="mb-3">
                        <label class="form-label text-uppercase text-secondary font-anton tracking-wider" style="font-size: 11px;"><?php echo t("Date", "วันที่ต้องการจอง"); ?></label>
                        <input type="date" name="date" id="booking-date" required class="form-control bg-dark border-secondary border-opacity-50 text-light rounded-0" min="<?php echo date('Y-m-d'); ?>" value="<?php echo date('Y-m-d'); ?>" onchange="updateAvailability()">
                    </div>

                    <div class="mb-3">
                        <label class="form-label text-uppercase text-secondary font-anton tracking-wider" style="font-size: 11px;"><?php echo t("Time Slot", "เวลาจอง"); ?></label>
                        <input type="time" name="time_slot" id="booking-time" required class="form-control bg-dark border-secondary border-opacity-50 text-light rounded-0" onchange="updateAvailability()" value="19:00">
                    </div>

                    <div class="mb-3">
                        <label class="form-label text-uppercase text-secondary font-anton tracking-wider" style="font-size: 11px;"><?php echo t("Number of Guests (Pax)", "จำนวนคน (ท่าน)"); ?></label>
                        <input type="number" name="pax" id="booking-pax" required min="1" max="15" class="form-control bg-dark border-secondary border-opacity-50 text-light rounded-0" value="2">
                    </div>

                    <div class="mb-3">
                        <label class="form-label text-uppercase text-secondary font-anton tracking-wider" style="font-size: 11px;"><?php echo t("Customer Name", "ชื่อลูกค้า"); ?></label>
                        <input type="text" name="customer_name" required placeholder="e.g. John" class="form-control bg-dark border-secondary border-opacity-50 text-light rounded-0">
                    </div>

                    <div class="mb-3">
                        <label class="form-label text-uppercase text-secondary font-anton tracking-wider" style="font-size: 11px;"><?php echo t("Phone Number", "เบอร์โทรศัพท์"); ?></label>
                        <input type="tel" name="customer_phone" required placeholder="e.g. 0812345678" class="form-control bg-dark border-secondary border-opacity-50 text-light rounded-0">
                    </div>

                    <!-- Beautiful Inline Form Validation Alert Box -->
                    <div id="form-inline-alert" class="d-none mb-3 p-3 bg-warning bg-opacity-15 border border-warning border-opacity-70 rounded-3 text-warning font-sans transition-all">
                        <div class="d-flex align-items-start gap-2.5">
                            <span id="form-alert-icon" class="material-symbols-outlined text-warning fs-5 mt-0.5 shrink-0 me-2">warning</span>
                            <div>
                                <h5 id="form-alert-title" class="font-anton text-warning text-uppercase tracking-wider fs-6 mb-1 m-0">
                                    <?php echo t("Table Selection Required", "กรุณาเลือกโต๊ะนั่ง"); ?>
                                </h5>
                                <div id="form-alert-msg" class="small text-light font-sans opacity-90 leading-relaxed">
                                    <?php echo t("Please select a table from the layout map on the left first.", "กรุณาคลิกเลือกโต๊ะนั่งจากแผนผังผังที่นั่งทางด้านซ้ายก่อนส่งจอง"); ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mb-4 p-3 bg-black bg-opacity-50 border border-secondary border-opacity-25 rounded">
                        <div class="small text-secondary text-uppercase font-anton tracking-wider mb-1"><?php echo t("Selected Spot", "โต๊ะที่คุณเลือก"); ?>:</div>
                        <div id="selection-summary" class="fs-5 font-anton text-warning text-uppercase">
                            <?php echo t("NONE SELECT", "กรุณาเลือกโต๊ะด้านซ้าย"); ?>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-custom-gold w-100 py-3 text-uppercase font-anton tracking-wider fs-5">
                        <?php echo t("Submit Reservation", "ส่งยืนยันจองโต๊ะ"); ?>
                    </button>
                </form>

                <!-- Booking Conditions Footer -->
                <div class="mt-4 pt-3 border-top border-secondary border-opacity-10 text-secondary" style="font-size: 12px; line-height: 1.5;">
                    <div class="font-anton text-warning text-uppercase tracking-wider mb-1.5" style="font-size: 11px; font-weight: bold;">
                        <?php echo t("Booking Conditions", "เงื่อนไขการจอง"); ?>
                    </div>
                    <ul class="mb-2 ps-3">
                        <li><?php echo t("Please arrive 1 hour prior to your booking time.", "มารับโต๊ะ ก่อน 1 ชั่วโมง"); ?></li>
                        <li><?php echo t("Guests must be 20 years of age or older.", "อายุ 20 ปี บริบูรณ์ขึ้นไป"); ?></li>
                    </ul>
                    <div class="font-sans text-warning mt-2 small">
                        📞 <?php echo t("Tel: 064 9546 616", "ติดต่อสอบถามเพิ่มเติม Tel: 064 9546 616"); ?>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

<script>
    // 1. Hero Cover Image Carousel
    let activeSlideIndex = 0;
    const slides = document.querySelectorAll('.hero-slide');
    if (slides.length > 0) {
        setInterval(() => {
            slides[activeSlideIndex].classList.remove('active');
            activeSlideIndex = (activeSlideIndex + 1) % slides.length;
            slides[activeSlideIndex].classList.add('active');
        }, 4500);
    }

    // 2. Select Table Handler
    let selectedTableBtn = null;
    
    function selectTable(element) {
        if (element.classList.contains('table-reserved')) {
            alert("<?php echo t('Sorry, this table is already booked for this slot!', 'ขออภัย โต๊ะนี้ไม่ว่างในรอบเวลาที่คุณเลือก!'); ?>");
            return;
        }
        
        // Remove previous selection
        if (selectedTableBtn) {
            selectedTableBtn.classList.remove('table-selected');
        }
        
        selectedTableBtn = element;
        selectedTableBtn.classList.add('table-selected');
        
        const tableId = element.getAttribute('data-id');
        const number = element.getAttribute('data-number');
        const capacity = element.getAttribute('data-capacity');
        const zone = element.getAttribute('data-zone');
        
        document.getElementById('form-table-id').value = tableId;
        
        const langSummary = "<?php echo $lang; ?>";
        let zoneTh = '';
        if (zone === 'INDOOR') zoneTh = 'ห้องแอร์';
        else if (zone === 'OUTDOOR') zoneTh = 'ด้านนอก';
        else if (zone === 'STAGE') zoneTh = 'หน้าเวที';
        else if (zone === 'INDOOR_WINDOW') zoneTh = 'ติดกระจก';
        else if (zone === 'INDOOR_CENTER') zoneTh = 'ตรงกลาง';
        else if (zone === 'BAR') zoneTh = 'หน้าบาร์';
        else if (zone === 'WALKWAY') zoneTh = 'โซนทางเดิน';
        else zoneTh = zone;
        const zoneText = langSummary === 'th' ? zoneTh : zone;
        
        document.getElementById('selection-summary').innerText = `Table ${number} (${zoneText} - Max ${capacity} Guests)`;
        hideInlineFormAlert();
    }

    // 3. Zone Filters
    function filterZone(zone, button) {
        // Toggle active button
        const buttons = button.parentNode.querySelectorAll('button');
        buttons.forEach(b => b.classList.remove('active'));
        button.classList.add('active');
        
        const tableBtns = document.querySelectorAll('.table-btn');
        tableBtns.forEach(btn => {
            const btnZone = btn.getAttribute('data-zone');
            if (zone === 'ALL' || btnZone === zone) {
                btn.style.display = 'inline-flex';
            } else {
                btn.style.display = 'none';
            }
        });
    }

    // 4. Update table reservation availability via Fetch AJAX
    function updateAvailability(isPolling = false) {
        const date = document.getElementById('booking-date').value;
        const timeSlot = document.getElementById('booking-time').value;
        
        if (!date || !timeSlot) return;
        
        // If not polling (e.g. user manually changed date/time slot), reset current selection
        if (!isPolling && selectedTableBtn) {
            selectedTableBtn.classList.remove('table-selected');
            selectedTableBtn = null;
            document.getElementById('form-table-id').value = '';
            document.getElementById('selection-summary').innerText = "<?php echo t('NONE SELECT', 'กรุณาเลือกโต๊ะด้านซ้าย'); ?>";
        }
        
        fetch(`reservation.php?action=get_booked_tables&date=${date}&time_slot=${timeSlot}`)
            .then(res => res.json())
            .then(bookedTableIds => {
                const tableBtns = document.querySelectorAll('.table-btn');
                tableBtns.forEach(btn => {
                    const id = btn.getAttribute('data-id');
                    if (bookedTableIds.includes(id)) {
                        btn.classList.remove('table-available');
                        btn.classList.add('table-reserved');
                        
                        // If the currently selected table has become reserved/occupied, reset it
                        if (selectedTableBtn && selectedTableBtn.getAttribute('data-id') === id) {
                            selectedTableBtn.classList.remove('table-selected');
                            selectedTableBtn = null;
                            document.getElementById('form-table-id').value = '';
                            document.getElementById('selection-summary').innerText = "<?php echo t('NONE SELECT', 'กรุณาเลือกโต๊ะด้านซ้าย'); ?>";
                            showInlineFormAlert(
                                "<?php echo t('The table you selected has just been marked as occupied/booked. Please select another table.', 'ขออภัย โต๊ะที่คุณเลือกเพิ่งถูกเปลี่ยนสถานะเป็นไม่ว่าง/ปิดบริการ กรุณาเลือกโต๊ะอื่น'); ?>",
                                "<?php echo t('Table Unavailable', 'โต๊ะไม่ว่าง'); ?>",
                                'error'
                            );
                        }
                    } else {
                        btn.classList.remove('table-reserved');
                        btn.classList.add('table-available');
                    }
                });
            });
    }

    // Initialize availability status and setup real-time polling (every 5 seconds)
    window.addEventListener('load', () => {
        updateAvailability(false);
        setInterval(() => {
            updateAvailability(true);
        }, 5000);
    });

    // Inline Form Alert System
    function showInlineFormAlert(message, title = '', type = 'warning') {
        const alertBox = document.getElementById('form-inline-alert');
        const alertTitle = document.getElementById('form-alert-title');
        const alertMsg = document.getElementById('form-alert-msg');
        const alertIcon = document.getElementById('form-alert-icon');

        if (!alertBox) return;

        alertTitle.innerText = title || "<?php echo t('Notice', 'แจ้งเตือนระบบ'); ?>";
        alertMsg.innerText = message;

        if (type === 'danger' || type === 'error') {
            alertBox.className = "mb-3 p-3 bg-danger bg-opacity-20 border border-danger border-opacity-70 rounded-3 text-danger font-sans transition-all";
            alertTitle.className = "font-anton text-danger text-uppercase tracking-wider fs-6 mb-1 m-0";
            alertIcon.className = "material-symbols-outlined text-danger fs-5 mt-0.5 shrink-0 me-2";
            alertIcon.innerText = "error";
        } else {
            alertBox.className = "mb-3 p-3 bg-warning bg-opacity-15 border border-warning border-opacity-70 rounded-3 text-warning font-sans transition-all";
            alertTitle.className = "font-anton text-warning text-uppercase tracking-wider fs-6 mb-1 m-0";
            alertIcon.className = "material-symbols-outlined text-warning fs-5 mt-0.5 shrink-0 me-2";
            alertIcon.innerText = "warning";
        }

        alertBox.classList.remove('d-none');
        alertBox.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }

    function hideInlineFormAlert() {
        const alertBox = document.getElementById('form-inline-alert');
        if (alertBox) alertBox.classList.add('d-none');
    }

    // Form Validator with Custom Inline Alert (no browser native popups)
    function validateBookingForm() {
        // 1. Customer Name Check
        const nameInput = document.querySelector('input[name="customer_name"]');
        const name = nameInput ? nameInput.value.trim() : '';
        if (!name) {
            showInlineFormAlert(
                "<?php echo t('Please enter customer name.', 'กรุณากรอกชื่อลูกค้าผู้ทำการจอง'); ?>",
                "<?php echo t('Customer Name Required', 'กรุณากรอกชื่อลูกค้า'); ?>",
                'warning'
            );
            if (nameInput) nameInput.focus();
            return false;
        }

        // 2. Customer Phone Check
        const phoneInput = document.querySelector('input[name="customer_phone"]');
        const phone = phoneInput ? phoneInput.value.trim() : '';
        if (!phone) {
            showInlineFormAlert(
                "<?php echo t('Please enter phone number.', 'กรุณากรอกเบอร์โทรศัพท์สำหรับติดต่อกลับ'); ?>",
                "<?php echo t('Phone Number Required', 'กรุณากรอกเบอร์โทรศัพท์'); ?>",
                'warning'
            );
            if (phoneInput) phoneInput.focus();
            return false;
        }

        const phonePattern = /^\+?[0-9\s\-()]+$/;
        if (!phonePattern.test(phone)) {
            showInlineFormAlert(
                "<?php echo t('Please enter a valid phone number format.', 'เบอร์โทรไม่ถูกต้อง กรุณากรอกเบอร์โทรศัพท์ใหม่อีกครั้ง'); ?>",
                "<?php echo t('Invalid Phone Number', 'เบอร์โทรศัพท์ไม่ถูกต้อง'); ?>",
                'error'
            );
            if (phoneInput) phoneInput.focus();
            return false;
        }

        // 3. Table Selection Check
        const tableId = document.getElementById('form-table-id').value;
        if (!tableId) {
            showInlineFormAlert(
                "<?php echo t('Please select a table from the layout map on the left first.', 'กรุณาคลิกเลือกโต๊ะนั่งจากแผนผังผังที่นั่งทางด้านซ้ายก่อนส่งจอง'); ?>",
                "<?php echo t('Table Selection Required', 'กรุณาเลือกโต๊ะนั่ง'); ?>",
                'warning'
            );
            return false;
        }
        
        // 4. Check capacity
        const selectedBtn = document.querySelector('.table-selected');
        if (selectedBtn) {
            const capacity = parseInt(selectedBtn.getAttribute('data-capacity'));
            const pax = parseInt(document.getElementById('booking-pax').value);
            
            if (pax > capacity) {
                showInlineFormAlert(
                    "<?php echo t('Error: The selected table capacity is smaller than your group size.', 'ข้อผิดพลาด: จำนวนคนที่จองมากกว่าขนาดความจุสูงสุดของโต๊ะนี้'); ?>",
                    "<?php echo t('Capacity Exceeded', 'ความจุโต๊ะไม่เพียงพอ'); ?>",
                    'error'
                );
                return false;
            }
        }
        hideInlineFormAlert();
        return true;
    }

    // Instant Live AJAX Booking Search (No Page Refresh Required)
    function performBookingSearch(event) {
        if (event) event.preventDefault();
        
        const queryInput = document.getElementById('search-query-input');
        const query = queryInput ? queryInput.value.trim() : '';
        const resultsContainer = document.getElementById('search-results-container');
        const submitBtn = document.getElementById('search-submit-btn');
        
        if (!query) return;

        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status"></span> ' + "<?php echo t('Searching...', 'กำลังค้นหา...'); ?>";
        }

        fetch('reservation.php?action=ajax_search_booking&q=' + encodeURIComponent(query))
            .then(res => res.json())
            .then(data => {
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = "<?php echo t('Search', 'ค้นหาข้อมูล'); ?>";
                }

                if (!data || data.error) {
                    resultsContainer.innerHTML = `
                        <div class="alert alert-danger bg-danger bg-opacity-10 border border-danger text-light p-3 rounded-0 font-mono mt-3">
                            [ERROR]: ${data ? data.error : 'Failed to fetch search results'}
                        </div>
                    `;
                    return;
                }

                const bookings = data.bookings || [];
                if (bookings.length === 0) {
                    resultsContainer.innerHTML = `
                        <div class="text-center py-5 bg-black bg-opacity-30 border border-secondary border-opacity-10 rounded mt-4">
                            <p class="text-secondary small m-0">${"<?php echo t('No reservations found matching your query.', 'ไม่พบประวัติการจองที่ตรงกับข้อมูลดังกล่าว'); ?>"}</p>
                        </div>
                    `;
                    return;
                }

                let html = '<div class="d-flex flex-column gap-4 mt-4">';
                bookings.forEach(b => {
                    let statusBadgeHtml = '';
                    if (b.status === 'CONFIRMED') {
                        statusBadgeHtml = `<span class="badge bg-success border border-success text-black px-2.5 py-1.5 rounded font-sans">${"<?php echo t('CONFIRMED (APPROVED)', 'ยืนยันแล้ว (ได้รับอนุมัติ)'); ?>"}</span>`;
                    } else if (b.status === 'COMPLETED') {
                        statusBadgeHtml = `<span class="badge bg-secondary border border-secondary text-white px-2.5 py-1.5 rounded font-sans">${"<?php echo t('COMPLETED', 'เสร็จสิ้นการใช้งานแล้ว'); ?>"}</span>`;
                    } else if (b.status === 'CANCELLED') {
                        statusBadgeHtml = `<span class="badge bg-danger border border-danger text-black px-2.5 py-1.5 rounded font-sans">${"<?php echo t('CANCELLED (REJECTED)', 'ยกเลิกแล้ว'); ?>"}</span>`;
                    } else if (b.status === 'CANCEL_REQUESTED') {
                        statusBadgeHtml = `<span class="badge bg-info border border-info text-black px-2.5 py-1.5 rounded font-sans">${"<?php echo t('CANCEL REQUESTED (PENDING)', 'ส่งคำขอยกเลิกแล้ว (รอพนักงานอนุมัติ)'); ?>"}</span>`;
                    } else {
                        statusBadgeHtml = `<span class="badge bg-warning border border-warning text-black px-2.5 py-1.5 rounded font-sans">${"<?php echo t('PENDING APPROVAL', 'รอการอนุมัติ'); ?>"}</span>`;
                    }

                    let cancelBtnHtml = '';
                    if (b.status === 'PENDING' || b.status === 'CONFIRMED') {
                        cancelBtnHtml = `<button class="btn btn-sm btn-outline-danger font-sans px-2.5 py-1 rounded" onclick="requestCancelBooking('${b.id}', '${escapeHtml(b.customer_phone)}')">${"<?php echo t('Cancel Booking', 'ยกเลิกจอง'); ?>"}</button>`;
                    }

                    let phoneMasked = escapeHtml(b.customer_phone);
                    if (phoneMasked.length > 3) {
                        phoneMasked = phoneMasked.substring(0, phoneMasked.length - 3) + 'xxx';
                    }

                    let zoneText = escapeHtml(b.table_zone || '');
                    if (b.table_zone === 'INDOOR') zoneText = "<?php echo t('Indoor AC', 'ห้องแอร์'); ?>";
                    else if (b.table_zone === 'OUTDOOR') zoneText = "<?php echo t('Outdoor Breeze', 'โซนด้านนอก'); ?>";
                    else if (b.table_zone === 'STAGE') zoneText = "<?php echo t('Stage Front', 'หน้าเวที'); ?>";
                    else if (b.table_zone === 'INDOOR_WINDOW') zoneText = "<?php echo t('Indoor Window', 'ติดกระจก'); ?>";
                    else if (b.table_zone === 'INDOOR_CENTER') zoneText = "<?php echo t('Indoor Center', 'ตรงกลาง'); ?>";
                    else if (b.table_zone === 'BAR') zoneText = "<?php echo t('Bar Front', 'หน้าบาร์'); ?>";
                    else if (b.table_zone === 'WALKWAY') zoneText = "<?php echo t('Walkway Zone', 'โซนทางเดิน'); ?>";

                    let reasonDisplayClass = ((b.status === 'CANCELLED' || b.status === 'CANCEL_REQUESTED') && b.cancel_reason) ? '' : 'd-none';

                    html += `
                        <div class="p-4 bg-black bg-opacity-40 border border-secondary border-opacity-25 rounded font-mono text-sm" id="booking-card-${b.id}">
                            <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 border-bottom border-secondary border-opacity-10 pb-3">
                                <span class="text-light font-bold fs-6">Ref ID: ${b.id}</span>
                                <div class="d-flex gap-2 align-items-center mt-2 mt-sm-0">
                                    <div id="status-badge-${b.id}">
                                        ${statusBadgeHtml}
                                    </div>
                                    <div id="cancel-btn-container-${b.id}">
                                        ${cancelBtnHtml}
                                    </div>
                                </div>
                            </div>
                            <div class="row g-3 text-secondary">
                                <div class="col-sm-6">
                                    <strong>${"<?php echo t('Customer Name', 'ชื่อผู้จอง'); ?>"}:</strong> 
                                    <span class="text-light font-sans">${escapeHtml(b.customer_name)}</span>
                                </div>
                                <div class="col-sm-6">
                                    <strong>${"<?php echo t('Phone', 'เบอร์โทร'); ?>"}:</strong> 
                                    <span class="text-light font-sans">${phoneMasked}</span>
                                </div>
                                <div class="col-sm-6">
                                    <strong>${"<?php echo t('Reservation Date & Time', 'วันเวลาจอง'); ?>"}:</strong> 
                                    <span class="text-warning">${b.date} @ ${b.time_slot}</span>
                                </div>
                                <div class="col-sm-6">
                                    <strong>${"<?php echo t('Table & Size', 'โต๊ะและจำนวนที่นั่ง'); ?>"}:</strong> 
                                    <span class="text-light font-sans">
                                        Table ${escapeHtml(b.table_number || 'N/A')} (${b.pax} Pax) - ${zoneText}
                                    </span>
                                </div>
                                <div class="col-12 mt-3 pt-3 border-top border-secondary border-opacity-10 ${reasonDisplayClass}" id="cancel-reason-container-${b.id}">
                                    <strong class="text-danger">${"<?php echo t('Cancellation Reason', 'เหตุผลที่ยกเลิก/หมายเหตุ'); ?>"}:</strong>
                                    <span class="text-light font-sans" id="cancel-reason-text-${b.id}">${escapeHtml(b.cancel_reason || '')}</span>
                                </div>
                            </div>
                        </div>
                    `;
                });
                html += '</div>';

                resultsContainer.innerHTML = html;
                resultsContainer.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                startStatusPolling(query);
            })
            .catch(err => {
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = "<?php echo t('Search', 'ค้นหาข้อมูล'); ?>";
                }
                resultsContainer.innerHTML = `
                    <div class="alert alert-danger bg-danger bg-opacity-10 border border-danger text-light p-3 rounded-0 font-mono mt-3">
                        [ERROR]: Connection error while searching.
                    </div>
                `;
            });
    }

    function escapeHtml(str) {
        if (!str) return '';
        return String(str)
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }

    let cancelModalInstance = null;

    function requestCancelBooking(bookingId, correctPhone) {
        document.getElementById('cancel-modal-booking-id').value = bookingId;
        document.getElementById('cancel-modal-correct-phone').value = correctPhone;
        document.getElementById('cancel-modal-ref').innerText = '#' + bookingId;
        document.getElementById('cancel-modal-phone-input').value = '';
        document.getElementById('cancel-modal-reason-input').value = '';
        
        const alertBox = document.getElementById('cancel-modal-alert');
        alertBox.classList.add('d-none');
        alertBox.innerText = '';

        if (!cancelModalInstance) {
            const modalEl = document.getElementById('cancelModal');
            if (modalEl) {
                cancelModalInstance = new bootstrap.Modal(modalEl);
            }
        }
        if (cancelModalInstance) {
            cancelModalInstance.show();
        }
    }

    function submitCancelBooking() {
        const bookingId = document.getElementById('cancel-modal-booking-id').value;
        const correctPhone = document.getElementById('cancel-modal-correct-phone').value;
        const phone = document.getElementById('cancel-modal-phone-input').value.trim();
        const reason = document.getElementById('cancel-modal-reason-input').value.trim();
        const alertBox = document.getElementById('cancel-modal-alert');

        if (!phone) {
            alertBox.innerText = "<?php echo t('Please enter your phone number.', 'กรุณากรอกเบอร์โทรศัพท์ที่ใช้จองเพื่อยืนยันตัวตน'); ?>";
            alertBox.classList.remove('d-none');
            document.getElementById('cancel-modal-phone-input').focus();
            return;
        }

        if (phone !== correctPhone) {
            alertBox.innerText = "<?php echo t('Incorrect phone number. Action denied.', 'เบอร์โทรศัพท์ไม่ถูกต้อง ไม่ตรงกับเบอร์ที่ใช้จอง'); ?>";
            alertBox.classList.remove('d-none');
            document.getElementById('cancel-modal-phone-input').focus();
            return;
        }

        if (!reason) {
            alertBox.innerText = "<?php echo t('Reason is required.', 'กรุณาระบุเหตุผลในการขอยกเลิกการจอง'); ?>";
            alertBox.classList.remove('d-none');
            document.getElementById('cancel-modal-reason-input').focus();
            return;
        }

        // Redirect to send the request
        window.location.href = `reservation.php?action=request_cancel&booking_id=${bookingId}&phone=${encodeURIComponent(phone)}&reason=${encodeURIComponent(reason)}`;
    }
</script>

<!-- Interactive Table Hover Tooltip Container -->
<div id="table-tooltip" class="table-tooltip d-none">
    <img id="tooltip-img" src="" alt="Table Preview">
    <div class="tooltip-content">
        <h5 id="tooltip-title" class="font-anton mb-1 text-warning text-uppercase"></h5>
        <div id="tooltip-zone" class="text-secondary small mb-2 font-mono"></div>
        <div class="tooltip-detail">
            <span class="material-symbols-outlined">group</span>
            <span id="tooltip-capacity"></span>
        </div>
        <div class="tooltip-detail">
            <span class="material-symbols-outlined">recommend</span>
            <span id="tooltip-recommend"></span>
        </div>
    </div>
</div>

<script>
    // Tooltip Hover Listeners
    document.addEventListener('DOMContentLoaded', () => {
        const tooltip = document.getElementById('table-tooltip');
        const tooltipImg = document.getElementById('tooltip-img');
        const tooltipTitle = document.getElementById('tooltip-title');
        const tooltipZone = document.getElementById('tooltip-zone');
        const tooltipCapacity = document.getElementById('tooltip-capacity');
        const tooltipRecommend = document.getElementById('tooltip-recommend');

        const zoneNamesTh = {
            'OUTDOOR': 'โซนด้านนอก (Outdoor)',
            'INDOOR_WINDOW': 'โซนติดกระจก (Window Side)',
            'INDOOR_CENTER': 'โซนตรงกลาง (Center)',
            'STAGE': 'โซนหน้าเวที (Stage Front)',
            'BAR': 'โซนหน้าบาร์ (Bar Front)',
            'WALKWAY': 'โซนทางเดิน (Walkway)'
        };

        const zoneNamesEn = {
            'OUTDOOR': 'Outdoor Area',
            'INDOOR_WINDOW': 'Window Side',
            'INDOOR_CENTER': 'Center Area',
            'STAGE': 'Stage Front',
            'BAR': 'Bar Front',
            'WALKWAY': 'Walkway Zone'
        };

        const isTh = "<?php echo $lang === 'th' ? '1' : '0'; ?>" === '1';
        const zoneNames = isTh ? zoneNamesTh : zoneNamesEn;

        document.querySelectorAll('.table-btn').forEach(btn => {
            btn.addEventListener('mouseenter', (e) => {
                const number = btn.getAttribute('data-number');
                const capacity = parseInt(btn.getAttribute('data-capacity'));
                const zone = btn.getAttribute('data-zone');
                
                const dbImage = btn.getAttribute('data-image');
                if (dbImage) {
                    tooltipImg.src = dbImage;
                    tooltipImg.style.display = 'block';
                    tooltipImg.onerror = function() {
                        this.onerror = null;
                        this.style.display = 'none';
                    };
                } else {
                    tooltipImg.src = '';
                    tooltipImg.style.display = 'none';
                }

                // Populate content
                tooltipTitle.innerText = isTh ? `โต๊ะ ${number}` : `Table ${number}`;
                tooltipZone.innerText = zoneNames[zone] || zone;
                tooltipCapacity.innerText = isTh ? `ความจุสูงสุด: ${capacity} ที่นั่ง` : `Max Capacity: ${capacity} Guests`;
                
                // Recommend pax calculation
                let minPax = Math.max(1, capacity - 2);
                if (capacity === 2) minPax = 1;
                if (capacity === 3) minPax = 2;
                if (capacity === 8) minPax = 5;
                tooltipRecommend.innerText = isTh ? `เหมาะสำหรับ: ${minPax} - ${capacity} ท่าน` : `Recommended: ${minPax} - ${capacity} Pax`;

                tooltip.classList.remove('d-none');
            });

            btn.addEventListener('mousemove', (e) => {
                const xOffset = 15;
                const yOffset = 15;
                
                let left = e.clientX + xOffset;
                let top = e.clientY + yOffset;
                
                // Boundaries prevention
                if (left + 250 > window.innerWidth) {
                    left = e.clientX - 250 - xOffset;
                }
                if (top + 220 > window.innerHeight) {
                    top = e.clientY - 220 - yOffset;
                }

                tooltip.style.left = `${left}px`;
                tooltip.style.top = `${top}px`;
            });

            btn.addEventListener('mouseleave', () => {
                tooltip.classList.add('d-none');
            });
        });
    });

    // Live Real-Time Polling of Booking Statuses
    let activeSearchQuery = <?php echo json_encode($search_query ?? ''); ?>;

    function startStatusPolling(query) {
        if (!query) return;
        activeSearchQuery = query;

        if (!window.statusPollerInterval) {
            window.statusPollerInterval = setInterval(pollBookingStatuses, 3000);
        }
        pollBookingStatuses();
    }

    function pollBookingStatuses() {
        if (!activeSearchQuery) return;

        fetch(`reservation.php?action=poll_booking_statuses&q=${encodeURIComponent(activeSearchQuery)}`)
            .then(res => res.json())
            .then(bookings => {
                if (!Array.isArray(bookings)) return;

                bookings.forEach(b => {
                    // 1. Update status badge
                    const badgeContainer = document.getElementById(`status-badge-${b.id}`);
                    if (badgeContainer) {
                        let badgeHTML = '';
                        if (b.status === 'CONFIRMED') {
                            badgeHTML = `<span class="badge bg-success border border-success text-black px-2.5 py-1.5 rounded font-sans"><?php echo t("CONFIRMED (APPROVED)", "ยืนยันแล้ว (ได้รับอนุมัติ)"); ?></span>`;
                        } else if (b.status === 'COMPLETED') {
                            badgeHTML = `<span class="badge bg-secondary border border-secondary text-white px-2.5 py-1.5 rounded font-sans"><?php echo t("COMPLETED", "เสร็จสิ้นการใช้งานแล้ว"); ?></span>`;
                        } else if (b.status === 'CANCELLED') {
                            badgeHTML = `<span class="badge bg-danger border border-danger text-black px-2.5 py-1.5 rounded font-sans"><?php echo t("CANCELLED (REJECTED)", "ยกเลิกแล้ว"); ?></span>`;
                        } else if (b.status === 'CANCEL_REQUESTED') {
                            badgeHTML = `<span class="badge bg-info border border-info text-black px-2.5 py-1.5 rounded font-sans"><?php echo t("CANCEL REQUESTED (PENDING)", "ส่งคำขอยกเลิกแล้ว (รอพนักงานอนุมัติ)"); ?></span>`;
                        } else {
                            badgeHTML = `<span class="badge bg-warning border border-warning text-black px-2.5 py-1.5 rounded font-sans"><?php echo t("PENDING APPROVAL", "รอการอนุมัติ"); ?></span>`;
                        }

                        if (badgeContainer.innerHTML.trim() !== badgeHTML.trim()) {
                            badgeContainer.innerHTML = badgeHTML;
                        }
                    }

                    // 2. Update cancel button
                    const btnContainer = document.getElementById(`cancel-btn-container-${b.id}`);
                    if (btnContainer) {
                        if (b.status === 'PENDING' || b.status === 'CONFIRMED') {
                            if (!btnContainer.querySelector('button')) {
                                btnContainer.innerHTML = `<button class="btn btn-sm btn-outline-danger font-sans px-2.5 py-1 rounded" onclick="requestCancelBooking('${b.id}', '${escapeHtml(b.customer_phone || '')}')"><?php echo t("Cancel Booking", "ยกเลิกจอง"); ?></button>`;
                            }
                        } else {
                            btnContainer.innerHTML = '';
                        }
                    }

                    // 3. Update cancel reason section
                    const reasonContainer = document.getElementById(`cancel-reason-container-${b.id}`);
                    const reasonText = document.getElementById(`cancel-reason-text-${b.id}`);
                    if (reasonContainer && reasonText) {
                        if ((b.status === 'CANCELLED' || b.status === 'CANCEL_REQUESTED') && b.cancel_reason) {
                            reasonText.innerText = b.cancel_reason;
                            reasonContainer.classList.remove('d-none');
                        } else {
                            reasonContainer.classList.add('d-none');
                        }
                    }
                });
            })
            .catch(err => console.log("Error polling status:", err));
    }

    // Auto start polling if query exists
    if (activeSearchQuery) {
        startStatusPolling(activeSearchQuery);
    }
</script>

<!-- Custom Cancellation Modal -->
<div class="modal fade" id="cancelModal" tabindex="-1" aria-labelledby="cancelModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content bg-dark text-light border border-secondary border-opacity-20 shadow-lg" style="background-color: #121212 !important; border-radius: 12px; overflow: hidden;">
            <div class="modal-header border-bottom border-secondary border-opacity-20 pb-3" style="background-color: #181818;">
                <div class="d-flex align-items-center gap-2">
                    <span class="material-symbols-outlined text-danger fs-4">event_busy</span>
                    <h5 class="modal-title font-anton text-uppercase text-warning tracking-wide mb-0" id="cancelModalLabel">
                        <?php echo t("Request Cancellation", "ยืนยันการขอยกเลิกการจองโต๊ะ"); ?>
                    </h5>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            
            <div class="modal-body p-4">
                <p class="text-secondary small mb-3">
                    <?php echo t("Please enter your registered phone number to confirm your identity, along with the reason for cancellation.", "กรุณากรอกเบอร์โทรศัพท์ที่ใช้จองเพื่อยืนยันตัวตน และระบุเหตุผลการขอยกเลิกการจอง"); ?>
                </p>

                <!-- Target Booking Ref Badge -->
                <div class="bg-black p-2.5 rounded border border-secondary border-opacity-20 mb-3 d-flex align-items-center justify-content-between">
                    <span class="text-secondary small font-mono"><?php echo t("Booking Ref:", "หมายเลขการจอง:"); ?></span>
                    <span id="cancel-modal-ref" class="font-anton text-warning tracking-wider fs-6"></span>
                </div>

                <!-- Error Notification Banner -->
                <div id="cancel-modal-alert" class="alert alert-danger d-none py-2 px-3 small font-sans mb-3" role="alert"></div>

                <form id="cancel-modal-form" onsubmit="event.preventDefault(); submitCancelBooking();">
                    <input type="hidden" id="cancel-modal-booking-id">
                    <input type="hidden" id="cancel-modal-correct-phone">

                    <div class="mb-3">
                        <label for="cancel-modal-phone-input" class="form-label text-warning small font-mono text-uppercase tracking-wide mb-1">
                            <span class="material-symbols-outlined fs-6 align-middle me-1">call</span>
                            <?php echo t("Registered Phone Number", "เบอร์โทรศัพท์ที่ใช้จอง (เพื่อยืนยันตัวตน)"); ?> *
                        </label>
                        <input type="tel" id="cancel-modal-phone-input" class="form-control bg-black text-light border-secondary border-opacity-30 font-sans shadow-none" placeholder="0800711996" required autocomplete="off">
                    </div>

                    <div class="mb-2">
                        <label for="cancel-modal-reason-input" class="form-label text-warning small font-mono text-uppercase tracking-wide mb-1">
                            <span class="material-symbols-outlined fs-6 align-middle me-1">edit_note</span>
                            <?php echo t("Cancellation Reason", "เหตุผลในการขอยกเลิก"); ?> *
                        </label>
                        <textarea id="cancel-modal-reason-input" class="form-control bg-black text-light border-secondary border-opacity-30 font-sans shadow-none" rows="3" placeholder="<?php echo t("e.g. Change of plans, emergency schedule...", "เช่น ติดภารกิจด่วน, เลื่อนวันเดินทาง ฯลฯ"); ?>" style="resize: none;" required></textarea>
                    </div>
                </form>
            </div>

            <div class="modal-footer border-top border-secondary border-opacity-20 p-3" style="background-color: #181818;">
                <button type="button" class="btn btn-outline-secondary btn-sm px-3 font-sans" data-bs-dismiss="modal">
                    <?php echo t("Cancel", "ยกเลิก"); ?>
                </button>
                <button type="button" class="btn btn-danger btn-sm px-3 font-sans font-bold d-flex align-items-center gap-1" onclick="submitCancelBooking()">
                    <span class="material-symbols-outlined fs-6">check_circle</span>
                    <span><?php echo t("Confirm Cancellation", "ยืนยันส่งคำขอยกเลิก"); ?></span>
                </button>
            </div>
        </div>
    </div>
</div>

<?php require_once 'footer.php'; ?>
