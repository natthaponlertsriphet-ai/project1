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
    
    // Run migration/seeding ONLY if running this script directly from CLI
    if (php_sapi_name() === 'cli' && isset($_SERVER['SCRIPT_FILENAME']) && realpath(__FILE__) === realpath($_SERVER['SCRIPT_FILENAME'])) {
        run_mysql_migration($pdo);
    }
} catch (\PDOException $e) {
    // 2. Fallback to embedded SQLite database if MySQL daemon is unavailable (e.g. cloud container without MySQL)
    try {
        $sqlite_file = __DIR__ . '/chithole_db.sqlite';
        $is_new_sqlite = !file_exists($sqlite_file);
        $pdo = new PDO("sqlite:" . $sqlite_file, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);

        if ($is_new_sqlite || (php_sapi_name() === 'cli' && isset($_SERVER['SCRIPT_FILENAME']) && realpath(__FILE__) === realpath($_SERVER['SCRIPT_FILENAME']))) {
            init_sqlite_db($pdo);
        }
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

    $pdo->exec("CREATE TABLE IF NOT EXISTS \"table\" (
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
    }

    // Seed tables
    $tbl_table = $is_sqlite ? '"table"' : '`table`';
    $table_count = $pdo->query("SELECT COUNT(*) FROM $tbl_table")->fetchColumn();
    if ($table_count == 0) {
        $tables = [
            ['d-1', 'D1', 'OUTDOOR', 2], ['d-2', 'D2', 'OUTDOOR', 2],
            ['w-1', '01', 'INDOOR_WINDOW', 8], ['w-2', '02', 'INDOOR_WINDOW', 8],
            ['c-3', '03', 'INDOOR_CENTER', 4], ['c-4', '04', 'INDOOR_CENTER', 4],
            ['c-5', '05', 'INDOOR_CENTER', 4], ['c-6', '06', 'INDOOR_CENTER', 4],
            ['c-7', '07', 'INDOOR_CENTER', 4], ['c-10', '10', 'INDOOR_CENTER', 4],
            ['c-11', '11', 'INDOOR_CENTER', 4], ['c-13', '13', 'INDOOR_CENTER', 4],
            ['s-8', '08', 'STAGE', 4], ['s-9', '09', 'STAGE', 4],
            ['s-12', '12', 'STAGE', 4], ['s-14', '14', 'STAGE', 4],
            ['b-16', '16', 'BAR', 4],
            ['k-17', '17', 'WALKWAY', 3], ['k-18', '18', 'WALKWAY', 3],
        ];
        $stmt = $pdo->prepare("INSERT INTO $tbl_table (table_id, table_number, zone, capacity) VALUES (?, ?, ?, ?)");
        foreach ($tables as $t) {
            $stmt->execute($t);
        }
    }

    // Seed promotions
    $promo_count = $pdo->query("SELECT COUNT(*) FROM promotion")->fetchColumn();
    if ($promo_count == 0) {
        $promotions = [
            ['p-1', 'Happy Hour: Buy 1 Get 1', 'Double the impact. Buy any pint from our selected industrial tap list and receive a second on the house. Fuel the evening shift.', 'BUY 1 GET 1', 'Daily • 5PM - 7PM', 'images/promotions/751026922_122278510508129427_3687616286560289044_n.jpg', 1],
            ['p-2', 'Craft Night', 'A gathering for the enthusiasts. Flash your brewing guild card or demonstrate your palate to receive 15% off all tasting flights.', '15% OFF', 'Every Wednesday', 'images/home-booking/735563412_122276495840129427_6246480903433139955_n.jpg', 1],
        ];
        $stmt = $pdo->prepare("INSERT INTO promotion (promo_id, promo_title, description, offer, promo_period, image_path, is_active) VALUES (?, ?, ?, ?, ?, ?, ?)");
        foreach ($promotions as $p) {
            $stmt->execute($p);
        }
    }

    // Seed music
    $music_count = $pdo->query("SELECT COUNT(*) FROM music")->fetchColumn();
    if ($music_count == 0) {
        $music = [
            ['m-1', 'Mon', '19:30 - 20:30', 'วง NULL', 'Acoustic indie rock session.'],
            ['m-2', 'Mon', '21:00 - 22:00', 'วง Black Devil', 'Heavy rock and alternative hits.'],
            ['m-3', 'Tue', '19:30 - 20:30', 'วง Poppular', 'Popular pop/rock acoustic sets.'],
            ['m-4', 'Tue', '21:30 - 22:30', 'วง ตูมตาม', 'Upbeat local rock covers.'],
            ['m-5', 'Wed', '19:45 - 22:00', 'วง Rhapsody', 'Classic progressive rock session.'],
            ['m-6', 'Thu', '19:00 - 21:15', 'วง Tewly', 'Smooth acoustic pop & rock.'],
            ['m-7', 'Thu', '21:30 - 22:30', 'วง Chilling Groove', 'Funky grooves and soul.'],
            ['m-8', 'Fri', '18:45 - 20:45', 'วง Tewly', 'Popular hit songs and request sets.'],
            ['m-9', 'Fri', '21:00 - 22:00', 'วง Karuna', 'Grunge and alternative rock.'],
            ['m-10', 'Fri', '22:30 - 24:00', 'วง Judy', 'Late night energetic party pop.'],
            ['m-11', 'Sat', '19:00 - 20:00', 'วง NULL', 'Alternative rock & pop.'],
            ['m-12', 'Sat', '21:00 - 22:00', 'วง ....', 'Special guest band session.'],
            ['m-13', 'Sat', '22:30 - 23:30', 'วง Karuna', 'High-octane hard rock show.'],
            ['m-14', 'Sun', '19:30 - 20:30', 'วง Black Devil', 'Heavy rock classic sets.'],
            ['m-15', 'Sun', '21:30 - 22:30', 'วง ตูมตาม', 'Closing party rock set.'],
        ];
        $stmt = $pdo->prepare("INSERT INTO music (music_id, show_day, show_time, artist, description) VALUES (?, ?, ?, ?, ?)");
        foreach ($music as $m) {
            $stmt->execute($m);
        }
    }

    // Seed beers
    $menu_count = $pdo->query("SELECT COUNT(*) FROM menu")->fetchColumn();
    if ($menu_count == 0) {
        $beers = [
            ['b-1', '01', 'DDH OLD SCHOOL IPA', 'RERNGPOY X MUANJAI', '6.6%'],
            ['b-2', '02', 'PASSION MANGO & STRAWBERRY', 'PHING DOI', '5.0%'],
            ['b-3', '03', 'FOUR TEEN AGAIN SESSION IPA', 'CHIT BEER', '4.9%'],
            ['b-4', '04', 'NEW ZEALAND PALE ALE', 'CHIT BEER', '5.6%'],
            ['b-5', '05', 'CHITHOLE LAGER', 'CHIT HOLE', '5.0%'],
            ['b-6', '06', 'IMPERIAL STOUT', 'WISET', '9.0%'],
            ['b-7', '07', 'FOREVER WEIZEN', 'CHIT HOLE', '5.0%'],
            ['b-8', '08', 'WITTY WITBIER', 'MICKLEHEIM', '6.3%'],
            ['b-9', '09', 'TRIPLE IPA', 'WISET', '11.0%'],
            ['b-10', '10', 'HILLBERRY STRAWBERRY CIDER', 'CHIANGMAI', '5.0%'],
            ['b-11', '11', 'RED TRUCK ALE', 'CHIANGMAI', '5.0%'],
            ['b-12', '12', 'GUAVA ALE', 'KHOY BREWING', '5.0%'],
            ['b-13', '13', 'TIDLOM SESSION IPA', 'SUNTREE', '4.4%'],
            ['b-14', '14', 'SIMBUS PALE ALE', 'MICKLEHEIM', '5.7%'],
            ['b-15', '15', 'ROSE', 'TAWANDANG', '4.0%'],
            ['b-16', '16', 'GERMAN LAGER', 'TAWANDANG', '4.9%'],
        ];
        $stmt = $pdo->prepare("INSERT INTO menu (menu_id, tap_number, menu_name, beer_type, abv) VALUES (?, ?, ?, ?, ?)");
        foreach ($beers as $b) {
            $stmt->execute($b);
        }
    }
}
