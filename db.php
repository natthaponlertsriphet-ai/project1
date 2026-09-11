<?php
date_default_timezone_set('Asia/Bangkok');

$host = getenv('DB_HOST') ?: '127.0.0.1';
$port = getenv('DB_PORT') ?: '3306';
$db   = getenv('DB_NAME') ?: 'chithole_db';
$user = getenv('DB_USER') ?: 'root';
$pass = getenv('DB_PASS') ?: '';
$charset = 'utf8mb4';

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

$pdo = null;

// 1. Try Primary MySQL Connection (chithole_db on phpMyAdmin)
try {
    $hosts = [$host, '127.0.0.1', 'localhost'];
    $hosts = array_unique(array_filter($hosts));
    $connected = false;

    // First attempt: Connect directly with dbname=chithole_db
    foreach ($hosts as $h) {
        try {
            $dsn = "mysql:host=$h;port=$port;dbname=$db;charset=$charset";
            $pdo = new PDO($dsn, $user, $pass, $options);
            $connected = true;
            break;
        } catch (\PDOException $ex) {
            continue;
        }
    }

    // Try XAMPP unix socket with dbname=chithole_db
    if (!$connected) {
        $xampp_socket = '/Applications/XAMPP/xamppfiles/var/mysql/mysql.sock';
        if (file_exists($xampp_socket)) {
            try {
                $dsn = "mysql:unix_socket=$xampp_socket;dbname=$db;charset=$charset";
                $pdo = new PDO($dsn, $user, $pass, $options);
                $connected = true;
            } catch (\PDOException $ex) {}
        }
    }

    // Second attempt: If database does not exist yet, connect without dbname to create it
    if (!$connected) {
        foreach ($hosts as $h) {
            try {
                $dsn = "mysql:host=$h;port=$port;charset=$charset";
                $pdo = new PDO($dsn, $user, $pass, $options);
                $pdo->exec("CREATE DATABASE IF NOT EXISTS `$db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                $pdo->exec("USE `$db`");
                $connected = true;
                break;
            } catch (\PDOException $ex) {
                continue;
            }
        }
    }

    if (!$connected || !$pdo) {
        throw new \PDOException("Unable to establish MySQL connection to chithole_db.");
    }
    
    // Auto-run migration if MySQL tables do not exist yet
    try {
        $checkTable = $pdo->query("SHOW TABLES LIKE 'admin'")->fetch();
        if (!$checkTable || (php_sapi_name() === 'cli' && isset($_SERVER['SCRIPT_FILENAME']) && realpath(__FILE__) === realpath($_SERVER['SCRIPT_FILENAME']))) {
            run_mysql_migration($pdo);
        }
    } catch (\Exception $migEx) {}
} catch (\PDOException $e) {
    // 2. Fallback to embedded SQLite database if MySQL daemon is unavailable (e.g. cloud container without MySQL)
    try {
        $sqlite_file = __DIR__ . '/chithole_db.sqlite';
        $is_new_sqlite = !file_exists($sqlite_file);
        $pdo = new PDO("sqlite:" . $sqlite_file, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);

        try {
            $checkSqliteTable = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='admin'")->fetch();
            if (!$checkSqliteTable || $is_new_sqlite || (php_sapi_name() === 'cli' && isset($_SERVER['SCRIPT_FILENAME']) && realpath(__FILE__) === realpath($_SERVER['SCRIPT_FILENAME']))) {
                init_sqlite_db($pdo);
            }
        } catch (\Exception $sqEx) {}
    } catch (\PDOException $sqlite_ex) {
        die("Database Connection / Setup Failed: " . $e->getMessage());
    }
}

function run_mysql_migration($pdo) {
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");

    $pdo->exec("CREATE TABLE IF NOT EXISTS admin (
        admin_id VARCHAR(50) PRIMARY KEY,
        admin_email VARCHAR(255) UNIQUE NOT NULL,
        admin_password_hash VARCHAR(255) NOT NULL,
        admin_name VARCHAR(255) NOT NULL,
        role VARCHAR(50) DEFAULT 'ADMIN'
    ) ENGINE=InnoDB;");

    $pdo->exec("CREATE TABLE IF NOT EXISTS staff (
        staff_id VARCHAR(50) PRIMARY KEY,
        staff_email VARCHAR(255) UNIQUE NOT NULL,
        staff_password_hash VARCHAR(255) NOT NULL,
        staff_name VARCHAR(255) NOT NULL,
        role VARCHAR(50) DEFAULT 'STAFF'
    ) ENGINE=InnoDB;");
    
    $pdo->exec("CREATE TABLE IF NOT EXISTS `table` (
        table_id VARCHAR(50) PRIMARY KEY,
        table_number VARCHAR(50) UNIQUE NOT NULL,
        zone VARCHAR(50) NOT NULL,
        capacity INT NOT NULL,
        table_status VARCHAR(50) DEFAULT 'AVAILABLE',
        image VARCHAR(255) NULL
    ) ENGINE=InnoDB;");
    
    $pdo->exec("CREATE TABLE IF NOT EXISTS menu (
        menu_id VARCHAR(50) PRIMARY KEY,
        tap_number VARCHAR(50) UNIQUE NOT NULL,
        menu_name VARCHAR(255) NOT NULL,
        beer_type VARCHAR(255) NOT NULL,
        abv VARCHAR(50) NOT NULL,
        is_active BOOLEAN DEFAULT TRUE
    ) ENGINE=InnoDB;");
    
    $pdo->exec("CREATE TABLE IF NOT EXISTS promotion (
        promo_id VARCHAR(50) PRIMARY KEY,
        promo_title VARCHAR(255) NOT NULL,
        description TEXT NOT NULL,
        offer VARCHAR(255) NOT NULL,
        promo_period VARCHAR(255) NOT NULL,
        image_path VARCHAR(255) NOT NULL,
        is_active BOOLEAN DEFAULT TRUE
    ) ENGINE=InnoDB;");
    
    $pdo->exec("CREATE TABLE IF NOT EXISTS music (
        music_id VARCHAR(50) PRIMARY KEY,
        show_day VARCHAR(50) NOT NULL,
        show_time VARCHAR(50) NOT NULL,
        artist VARCHAR(255) NOT NULL,
        description TEXT NOT NULL
    ) ENGINE=InnoDB;");
    
    $pdo->exec("CREATE TABLE IF NOT EXISTS reservation (
        reservation_id VARCHAR(50) PRIMARY KEY,
        customer_name VARCHAR(255) NOT NULL,
        customer_phone VARCHAR(50) NOT NULL,
        reservation_date VARCHAR(20) NOT NULL,
        reservation_time VARCHAR(20) NOT NULL,
        guest_count INT NOT NULL,
        table_id VARCHAR(50) NULL,
        reservation_status VARCHAR(50) DEFAULT 'PENDING',
        cancel_reason TEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (table_id) REFERENCES `table`(table_id) ON DELETE SET NULL
    ) ENGINE=InnoDB;");

    seed_database_records($pdo);
    echo "MySQL Database migrated and seeded successfully!\n";
}

function init_sqlite_db($pdo) {
    $pdo->exec("CREATE TABLE IF NOT EXISTS admin (
        admin_id TEXT PRIMARY KEY,
        admin_email TEXT UNIQUE NOT NULL,
        admin_password_hash TEXT NOT NULL,
        admin_name TEXT NOT NULL,
        role TEXT DEFAULT 'ADMIN'
    );");

    $pdo->exec("CREATE TABLE IF NOT EXISTS staff (
        staff_id TEXT PRIMARY KEY,
        staff_email TEXT UNIQUE NOT NULL,
        staff_password_hash TEXT NOT NULL,
        staff_name TEXT NOT NULL,
        role TEXT DEFAULT 'STAFF'
    );");

    $pdo->exec("CREATE TABLE IF NOT EXISTS `table` (
        table_id TEXT PRIMARY KEY,
        table_number TEXT UNIQUE NOT NULL,
        zone TEXT NOT NULL,
        capacity INTEGER NOT NULL,
        table_status TEXT DEFAULT 'AVAILABLE',
        image TEXT NULL
    );");

    $pdo->exec("CREATE TABLE IF NOT EXISTS menu (
        menu_id TEXT PRIMARY KEY,
        tap_number TEXT UNIQUE NOT NULL,
        menu_name TEXT NOT NULL,
        beer_type TEXT NOT NULL,
        abv TEXT NOT NULL,
        is_active INTEGER DEFAULT 1
    );");

    $pdo->exec("CREATE TABLE IF NOT EXISTS promotion (
        promo_id TEXT PRIMARY KEY,
        promo_title TEXT NOT NULL,
        description TEXT NOT NULL,
        offer TEXT NOT NULL,
        promo_period TEXT NOT NULL,
        image_path TEXT NOT NULL,
        is_active INTEGER DEFAULT 1
    );");

    $pdo->exec("CREATE TABLE IF NOT EXISTS music (
        music_id TEXT PRIMARY KEY,
        show_day TEXT NOT NULL,
        show_time TEXT NOT NULL,
        artist TEXT NOT NULL,
        description TEXT NOT NULL
    );");

    $pdo->exec("CREATE TABLE IF NOT EXISTS reservation (
        reservation_id TEXT PRIMARY KEY,
        customer_name TEXT NOT NULL,
        customer_phone TEXT NOT NULL,
        reservation_date TEXT NOT NULL,
        reservation_time TEXT NOT NULL,
        guest_count INTEGER NOT NULL,
        table_id TEXT NULL,
        reservation_status TEXT DEFAULT 'PENDING',
        cancel_reason TEXT NULL,
        created_at TEXT DEFAULT CURRENT_TIMESTAMP,
        updated_at TEXT DEFAULT CURRENT_TIMESTAMP
    );");

    seed_database_records($pdo, true);
}

function seed_database_records($pdo, $is_sqlite = false) {
    // Seed users
    $admin_count = $pdo->query("SELECT COUNT(*) FROM admin")->fetchColumn();
    if ($admin_count == 0) {
        $admin_pw = password_hash('admin123', PASSWORD_DEFAULT);
        $staff_pw = password_hash('staff123', PASSWORD_DEFAULT);
        
        $stmt = $pdo->prepare("INSERT INTO admin (admin_id, admin_email, admin_password_hash, admin_name, role) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute(['admin-1', 'admin@chithole.com', $admin_pw, 'Admin Boss', 'ADMIN']);

        $stmt = $pdo->prepare("INSERT INTO staff (staff_id, staff_email, staff_password_hash, staff_name, role) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute(['staff-1', 'staff@chithole.com', $staff_pw, 'Staff Member', 'STAFF']);
        $stmt->execute(['admin_6a97a8352d2ac', 'nook@chithole.com', '$2y$10$M0Mun8JZS7t6s6YdGcLaTuNQzTkJM4ShdpZbqxZ.Jy3ZHrdmqI3gm', 'natthapon', 'STAFF']);
    }

    // Seed tables (20 Tables with image paths)
    $tbl_table = $is_sqlite ? '"table"' : '`table`';
    $table_count = $pdo->query("SELECT COUNT(*) FROM $tbl_table")->fetchColumn();
    if ($table_count == 0) {
        $tables = [
            ['w-1', '01', 'INDOOR_WINDOW', 4, 'AVAILABLE', 'images/tables/uploaded_1788352492_IMG_0131.jpg'],
            ['w-2', '02', 'INDOOR_WINDOW', 2, 'AVAILABLE', 'images/tables/uploaded_1788352727_IMG_0132.jpg'],
            ['c-3', '03', 'INDOOR_WINDOW', 4, 'AVAILABLE', 'images/tables/uploaded_1788352906_IMG_0133.jpg'],
            ['c-4', '04', 'INDOOR_CENTER', 4, 'AVAILABLE', 'images/tables/uploaded_1788353487_IMG_0134.jpg'],
            ['c-5', '05', 'INDOOR_CENTER', 4, 'AVAILABLE', 'images/tables/uploaded_1788358948_IMG_0135.jpg'],
            ['c-6', '06', 'INDOOR_CENTER', 4, 'AVAILABLE', 'images/tables/uploaded_1788358965_IMG_0136.jpg'],
            ['c-7', '07', 'INDOOR_CENTER', 2, 'AVAILABLE', 'images/tables/uploaded_1788359057_IMG_0137.jpg'],
            ['s-8', '08', 'INDOOR_CENTER', 4, 'AVAILABLE', 'images/tables/uploaded_1788359035_IMG_0138.jpg'],
            ['s-9', '09', 'STAGE', 4, 'AVAILABLE', 'images/tables/uploaded_1788359088_IMG_0139.jpg'],
            ['c-10', '10', 'STAGE', 4, 'AVAILABLE', 'images/tables/uploaded_1788359111_IMG_0140.jpg'],
            ['c-11', '11', 'INDOOR_CENTER', 6, 'AVAILABLE', 'images/tables/uploaded_1788359700_IMG_0149.jpg'],
            ['s-12', '12', 'INDOOR_CENTER', 4, 'AVAILABLE', 'images/tables/uploaded_1788359727_IMG_0141.jpg'],
            ['c-13', '13', 'STAGE', 4, 'AVAILABLE', 'images/tables/uploaded_1788359743_IMG_0142.jpg'],
            ['s-14', '14', 'STAGE', 6, 'AVAILABLE', 'images/tables/uploaded_1788359763_IMG_0143.jpg'],
            ['tbl_6a9834810a37f', '15', 'BAR', 2, 'AVAILABLE', 'images/tables/uploaded_1788359808_IMG_0146.jpg'],
            ['b-16', '16', 'BAR', 2, 'AVAILABLE', 'images/tables/uploaded_1788359791_IMG_0145.jpg'],
            ['k-17', '17', 'WALKWAY', 6, 'AVAILABLE', 'images/tables/uploaded_1788359833_IMG_0144.jpg'],
            ['k-18', '18', 'WALKWAY', 6, 'AVAILABLE', 'images/tables/uploaded_1788359847_IMG_0150.jpg'],
            ['d-1', 'D1', 'OUTDOOR', 2, 'AVAILABLE', 'images/tables/uploaded_1788260700_IMG_9774.jpg'],
            ['d-2', 'D2', 'OUTDOOR', 2, 'AVAILABLE', 'images/tables/uploaded_1788260742_IMG_9774.jpg']
        ];
        $stmt = $pdo->prepare("INSERT INTO $tbl_table (table_id, table_number, zone, capacity, table_status, image) VALUES (?, ?, ?, ?, ?, ?)");
        foreach ($tables as $t) {
            $stmt->execute($t);
        }
    }

    // Seed promotions (3 Active Promotions)
    $promo_count = $pdo->query("SELECT COUNT(*) FROM promotion")->fetchColumn();
    if ($promo_count == 0) {
        $promotions = [
            [
                'promo_6aa12cd375577',
                'PROMOTION HAPPY HOUR (ลดราคาตะวันแดง)',
                "คำอธิบายเงื่อนไข\n\nรายการราคาพิเศษช่วง Happy Hour:\n\nแก้วเล็ก (350 ML): 99 บาท (จากปกติ 149 บาท)\n\nเหยือก (1000 ML): 289 บาท (จากปกติ 399 บาท)\n\nทาวเวอร์ (3000 ML): 859 บาท (จากปกติ 999 บาท)\n\nสิทธิ์นี้ใช้ได้เฉพาะช่วงเวลา HAPPY HOUR 17:00 – 21:00 น. เท่านั้น\n\nไม่สามารถใช้ร่วมกับโปรโมชันอื่นได้",
                '',
                '17:00 – 21:00 น.',
                'images/promotions/uploaded_1788947667_IMG_0181.JPG',
                1
            ],
            [
                'promo_6a9835f77c650',
                'เดือนนี้เดือนเกิด... ต้องจัดที่ CHIT HOLE! 🎂🍻',
                'วันเกิดปีนี้ พาเพื่อนมาเลี้ยงฉลองกันให้เต็มที่! รับส่วนลดค่าอาหาร 10% ทันที เมื่อมาฉลองวันเกิดที่ CHIT HOLE เชียงใหม่ สันทราย 📍 เฉพาะสาขา CHIT HOLE เชียงใหม่ สันทราย 🎁 สิทธิพิเศษ: ลดค่าอาหาร 10% 📝 แสดงบัตรประชาชนก่อนใช้สิทธิ',
                '',
                'ตลอดเดือนเกิด',
                'images/promotions/uploaded_1788360183_756444299_122279188670129427_5185743561094400021_n.jpg',
                1
            ],
            [
                'promo_6a98354bc5a10',
                '3 สาว ก่อน 3 ทุ่ม ฟรี 1 เหยือก',
                'สาว ๆ มา 3 คน ก่อน 21:00 น. รับฟรี ตะวันแดง 1 Pitcher! (มูลค่า 399.-) เฉพาะวันจันทร์และอังคารเท่านั้น! คราฟต์เบียร์เย็นๆ บรรยากาศดี ดนตรีเพราะ... จะรออะไร!',
                '',
                'ทุกวันจันทร์และอังคาร ก่อนเวลา 21:00 น.',
                'images/promotions/uploaded_1788360011_791383960_122283290132129427_324725330320101261_n.jpg',
                1
            ]
        ];
        $stmt = $pdo->prepare("INSERT INTO promotion (promo_id, promo_title, description, offer, promo_period, image_path, is_active) VALUES (?, ?, ?, ?, ?, ?, ?)");
        foreach ($promotions as $p) {
            $stmt->execute($p);
        }
    }

    // Seed music (17 Live Music Sessions)
    $music_count = $pdo->query("SELECT COUNT(*) FROM music")->fetchColumn();
    if ($music_count == 0) {
        $music = [
            ['m-8', 'Fri', '18:45 - 20:45', 'วง Tewly', 'Popular hit songs and request sets.'],
            ['m-9', 'Fri', '21:00 - 22:00', 'วง Karuna', 'Grunge and alternative rock.'],
            ['m-10', 'Fri', '22:30 - 24:00', 'วง Judy', 'Late night energetic party pop.'],
            ['m-1', 'Mon', '19:30 - 20:30', 'วง NULL', 'Acoustic indie rock session.'],
            ['m-2', 'Mon', '21:00 - 22:00', 'วง Black Devil', 'Heavy rock and alternative hits.'],
            ['m-11', 'Sat', '19:00 - 20:00', 'วง NULL', 'Alternative rock & pop.'],
            ['m-12', 'Sat', '21:00 - 22:00', 'วง Sunday Evening', 'Special guest band session.'],
            ['m-13', 'Sat', '22:30 - 23:30', 'วง ดอกเหมย', 'High-octane hard rock show.'],
            ['m-14', 'Sun', '19:30 - 20:30', 'วง Black Devil', 'Heavy rock classic sets.'],
            ['m-15', 'Sun', '21:30 - 22:30', 'วง ตูมตาม', 'Closing party rock set.'],
            ['m-6', 'Thu', '19:00 - 21:15', 'วง Tewly', 'Smooth acoustic pop & rock.'],
            ['m-7', 'Thu', '21:30 - 22:30', 'วง Chilling Groove', 'Funky grooves and soul.'],
            ['music_6aa12dc4e6c35', 'Thu', '22:45 - 23:45', 'วง Black Devil', 'pop. 80 rock 70'],
            ['m-3', 'Tue', '19:30 - 20:30', 'วง Poppular', 'Popular pop/rock acoustic sets.'],
            ['m-4', 'Tue', '21:30 - 22:30', 'วง ตูมตาม', 'Upbeat local rock covers.'],
            ['m-5', 'Wed', '19:45 - 22:00', 'วง Rhapsody', 'Classic progressive rock session.'],
            ['music_6a9aa53928dd2', 'Wed', '22:15 - 23:15', 'วง Tewly', '80s-90s']
        ];
        $stmt = $pdo->prepare("INSERT INTO music (music_id, show_day, show_time, artist, description) VALUES (?, ?, ?, ?, ?)");
        foreach ($music as $m) {
            $stmt->execute($m);
        }
    }

    // Seed beers (16 Taps)
    $menu_count = $pdo->query("SELECT COUNT(*) FROM menu")->fetchColumn();
    if ($menu_count == 0) {
        $beers = [
            ['b-1', '01', 'BLOSSOM WEIZEN', 'CHIANGMAI', '5.0%', 1],
            ['b-2', '02', 'MEE CHAI IPA', 'MUAY THAI', '5.0%', 1],
            ['b-3', '03', 'IRISH OYSTER EXTRA STOUT', 'UNDERDOG', '5.8%', 1],
            ['b-4', '04', 'HIPSTER IPA', 'CHIT BEER', '7.0%', 1],
            ['b-5', '05', 'FOREVER WEIZEN 🥈', 'CHIT BEER', '5.0%', 1],
            ['b-6', '06', 'YOGURT CIDER', 'CHIT HOLE', '5.0%', 1],
            ['b-7', '07', 'FOREVER WEIZEN', 'CHIT HOLE', '5.0%', 1],
            ['b-8', '08', 'WITTY WITBIER', 'MICKLEHEIM', '6.3%', 1],
            ['b-9', '09', 'TRIPLE IPA', 'WISET', '11.0%', 1],
            ['b-10', '10', 'HILLBERRY STRAWBERRY CIDER', 'CHIANGMAI', '5.0%', 1],
            ['b-11', '11', 'RED TRUCK ALE', 'CHIANGMAI', '5.0%', 1],
            ['b-12', '12', 'GUAVA ALE', 'KHOY BREWING', '5.0%', 1],
            ['b-13', '13', 'TIDLOM SESSION IPA', 'SUNTREE', '4.4%', 1],
            ['b-14', '14', 'SIMBUS PALE ALE', 'MICKLEHEIM', '5.7%', 1],
            ['b-15', '15', 'ROSE', 'TAWANDANG', '4.0%', 1],
            ['b-16', '16', 'GERMAN LAGER', 'TAWANDANG', '4.9%', 1]
        ];
        $stmt = $pdo->prepare("INSERT INTO menu (menu_id, tap_number, menu_name, beer_type, abv, is_active) VALUES (?, ?, ?, ?, ?, ?)");
        foreach ($beers as $b) {
            $stmt->execute($b);
        }
    }

    // Seed reservations (22 Booking Records)
    $res_count = $pdo->query("SELECT COUNT(*) FROM reservation")->fetchColumn();
    if ($res_count == 0) {
        $reservations = [
            ['CHITHOLECNX_6a95d203845f1', 'นุ๊ก', '0800711996', '2026-09-01', '19:00', 2, 'c-3', 'CANCELLED', 'ร้านปิด', '2026-09-01 02:12:03', '2026-09-01 02:47:31'],
            ['CHITHOLECNX_6a95da1d0148b', 'joy', '0800711996', '2026-09-01', '19:00', 2, 'c-4', 'CANCELLED', 'ยกเลิก', '2026-09-01 02:46:37', '2026-09-01 02:47:34'],
            ['CHITHOLECNX_6a96f8efe1e17', 'นุ๊ก', '0800711996', '2026-09-01', '19:00', 2, 'c-3', 'COMPLETED', NULL, '2026-09-01 23:10:23', '2026-09-02 01:43:08'],
            ['CHITHOLECNX_6a971ce8d6b50', 'เจม', '0800711996', '2026-09-01', '19:00', 2, 'c-4', 'COMPLETED', NULL, '2026-09-02 01:43:52', '2026-09-02 01:54:29'],
            ['CHITHOLECNX_6a971d0e57cb4', 'เจม', '0800711996', '2026-09-02', '19:00', 2, 's-14', 'COMPLETED', NULL, '2026-09-02 01:44:30', '2026-09-02 01:49:53'],
            ['CHITHOLECNX_6a97268bcebdb', 'นุ๊ก', '0931804838', '2026-09-02', '19:00', 2, 'c-3', 'COMPLETED', 'ยกเลิก', '2026-09-02 02:24:59', '2026-09-02 03:09:56'],
            ['CHITHOLECNX_6a972859d29ed', 'นุ๊ก', '0800711996', '2026-09-02', '19:00', 2, 'b-16', 'COMPLETED', NULL, '2026-09-02 02:32:41', '2026-09-02 02:37:48'],
            ['CHITHOLECNX_6a9729a1e04fc', 'นุ๊ก', '0800711996', '2026-09-02', '19:00', 2, 'd-2', 'COMPLETED', 'ยกเลิก', '2026-09-02 02:38:09', '2026-09-02 03:10:01'],
            ['CHITHOLECNX_6a972e08b9fc7', 'เจม', '0800711996', '2026-09-02', '19:00', 2, 'd-2', 'COMPLETED', 'ยกเลิกลูกค้าไม่มาตามเวลาที่ลูกค้าจอง', '2026-09-02 02:56:56', '2026-09-02 03:09:43'],
            ['CHITHOLECNX_6a9839e86a1c4', 'เจม', '0800711996', '2026-09-02', '19:00', 2, 'd-1', 'CANCELLED', 'ยกเลิก', '2026-09-02 21:59:52', '2026-09-02 22:00:54'],
            ['CHITHOLECNX_6a983ae838ccc', 'เจม', '0800711996', '2026-09-02', '19:00', 2, 'd-1', 'COMPLETED', NULL, '2026-09-02 22:04:08', '2026-09-02 22:04:55'],
            ['CHITHOLECNX_6a9bd67bb5a6f', 'ออน', '0919856264', '2026-09-05', '19:30', 3, 'w-1', 'CANCELLED', 'ลูกค้าไม่มาตามเล่น ทำการยกเลิก', '2026-09-05 15:44:43', '2026-09-05 15:47:54'],
            ['CHITHOLECNX_6aa1c23d010f3', 'ออน', '0919856264', '2026-09-10', '19:00', 2, 'd-2', 'CANCELLED', 'ร้านปิด', '2026-09-10 03:31:57', '2026-09-10 03:35:00'],
            ['CHITHOLECNX_6aa2695bd3879', 'นุ๊ก', '0800711996', '2026-09-10', '19:00', 2, 's-12', 'COMPLETED', NULL, '2026-09-10 15:24:59', '2026-09-10 15:26:24'],
            ['CHITHOLECNX_6aa26b262739a', 'ออน', '0919856264', '2026-09-10', '19:00', 2, 'k-17', 'CANCELLED', 'ยกเลิก', '2026-09-10 15:32:38', '2026-09-10 15:33:22'],
            ['CHITHOLECNX_6aa26b5c79a76', 'ออน', '0919856264', '2026-09-10', '19:00', 2, 'd-2', 'CANCELLED', 'ยกเลิก', '2026-09-10 15:33:32', '2026-09-10 15:34:36'],
            ['CHITHOLECNX_6aa2c4e834b08', 'เจม', '0800711996', '2026-09-10', '19:00', 2, 'd-2', 'COMPLETED', NULL, '2026-09-10 21:55:36', '2026-09-10 22:04:08'],
            ['CHITHOLECNX_6aa2f886566f8', 'เจม', '0800711996', '2026-09-11', '19:00', 2, 'k-18', 'COMPLETED', NULL, '2026-09-11 01:35:50', '2026-09-11 01:47:31'],
            ['CHITHOLECNX_6aa2f944b7f07', 'เจม', '0800711996', '2026-09-11', '19:00', 2, 'k-17', 'CANCELLED', 'ยกเลิก', '2026-09-11 01:39:00', '2026-09-11 01:47:06'],
            ['CHITHOLECNX_6aa2f9d558b3c', 'เจเจ', '0800711996', '2026-09-11', '19:00', 2, 'c-11', 'COMPLETED', NULL, '2026-09-11 01:41:25', '2026-09-11 01:47:28'],
            ['CHITHOLECNX_6aa2fa8c527dd', 'ต้น', '0800711996', '2026-09-11', '19:00', 2, 's-12', 'CANCELLED', 'ยกเลิก', '2026-09-11 01:44:28', '2026-09-11 01:47:02'],
            ['CHITHOLECNX_6aa2fad220e2c', 'เจเจ', '0800711996', '2026-09-11', '19:00', 2, 'c-6', 'COMPLETED', NULL, '2026-09-11 01:45:38', '2026-09-11 01:47:23']
        ];
        $stmt = $pdo->prepare("INSERT INTO reservation (reservation_id, customer_name, customer_phone, reservation_date, reservation_time, guest_count, table_id, reservation_status, cancel_reason, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        foreach ($reservations as $r) {
            $stmt->execute($r);
        }
    }
}


