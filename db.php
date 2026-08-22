<?php
date_default_timezone_set('Asia/Bangkok');
$host = '127.0.0.1';
$db   = 'chithole_db';
$user = 'root';
$pass = '';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
    
    // 1. Create database if it does not exist
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE `$db`");
    
    // 2. Create tables
    
    // Users table
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id VARCHAR(50) PRIMARY KEY,
        email VARCHAR(255) UNIQUE NOT NULL,
        password_hash VARCHAR(255) NOT NULL,
        name VARCHAR(255) NOT NULL,
        role VARCHAR(50) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB;");
    
    // Tables table
    $pdo->exec("CREATE TABLE IF NOT EXISTS tables (
        id VARCHAR(50) PRIMARY KEY,
        number VARCHAR(50) UNIQUE NOT NULL,
        zone VARCHAR(50) NOT NULL,
        capacity INT NOT NULL,
        status VARCHAR(50) DEFAULT 'AVAILABLE',
        image VARCHAR(255) NULL
    ) ENGINE=InnoDB;");
    
    // Add image column to tables table if it doesn't exist
    try {
        $pdo->exec("ALTER TABLE tables ADD COLUMN image VARCHAR(255) NULL;");
    } catch (Exception $e) {
        // Column may already exist
    }
    
    // Bookings table
    $pdo->exec("CREATE TABLE IF NOT EXISTS bookings (
        id VARCHAR(50) PRIMARY KEY,
        customer_name VARCHAR(255) NOT NULL,
        customer_phone VARCHAR(50) NOT NULL,
        date VARCHAR(20) NOT NULL,
        time_slot VARCHAR(20) NOT NULL,
        pax INT NOT NULL,
        table_id VARCHAR(50) NULL,
        status VARCHAR(50) DEFAULT 'PENDING',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (table_id) REFERENCES tables(id) ON DELETE SET NULL
    ) ENGINE=InnoDB;");
    
    // Promotions table
    $pdo->exec("CREATE TABLE IF NOT EXISTS promotions (
        id VARCHAR(50) PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        description TEXT NOT NULL,
        offer VARCHAR(255) NOT NULL,
        period VARCHAR(255) NOT NULL,
        image VARCHAR(255) NOT NULL,
        active BOOLEAN DEFAULT TRUE
    ) ENGINE=InnoDB;");
    
    // Live Music table
    $pdo->exec("CREATE TABLE IF NOT EXISTS live_music (
        id VARCHAR(50) PRIMARY KEY,
        day VARCHAR(50) NOT NULL,
        time VARCHAR(50) NOT NULL,
        artist VARCHAR(255) NOT NULL,
        description TEXT NOT NULL,
        status VARCHAR(50) NOT NULL
    ) ENGINE=InnoDB;");
    
    // Beers table
    $pdo->exec("CREATE TABLE IF NOT EXISTS beers (
        id VARCHAR(50) PRIMARY KEY,
        tap_number VARCHAR(50) UNIQUE NOT NULL,
        name VARCHAR(255) NOT NULL,
        type VARCHAR(255) NOT NULL,
        abv VARCHAR(50) NOT NULL,
        ibu VARCHAR(50) NOT NULL,
        description TEXT NOT NULL,
        price DECIMAL(10,2) DEFAULT 0.00,
        active BOOLEAN DEFAULT TRUE
    ) ENGINE=InnoDB;");

    // 3. Seed Database if empty
    
    // Check if the database has already been initialized (if users table has entries)
    $stmt = $pdo->query("SELECT COUNT(*) FROM users");
    $db_needs_seeding = ($stmt->fetchColumn() == 0);
    
    if ($db_needs_seeding) {
        // Seed users
        $admin_pw = password_hash('admin123', PASSWORD_DEFAULT);
        $staff_pw = password_hash('staff123', PASSWORD_DEFAULT);
        
        $stmtInsert = $pdo->prepare("INSERT INTO users (id, email, password_hash, name, role) VALUES (?, ?, ?, ?, ?)");
        $stmtInsert->execute(['admin-1', 'admin@chithole.com', $admin_pw, 'Admin Boss', 'ADMIN']);
        $stmtInsert->execute(['staff-1', 'staff@chithole.com', $staff_pw, 'Staff Member', 'STAFF']);
        
        // Seed tables
        $tables = [
            // ด้านนอก (OUTDOOR)
            ['d-1', 'D1', 'OUTDOOR', 2], ['d-2', 'D2', 'OUTDOOR', 2],
            // ติดกระจก (INDOOR_WINDOW)
            ['w-1', '01', 'INDOOR_WINDOW', 8], ['w-2', '02', 'INDOOR_WINDOW', 8],
            // ตรงกลาง (INDOOR_CENTER)
            ['c-3', '03', 'INDOOR_CENTER', 4], ['c-4', '04', 'INDOOR_CENTER', 4],
            ['c-5', '05', 'INDOOR_CENTER', 4], ['c-6', '06', 'INDOOR_CENTER', 4],
            ['c-7', '07', 'INDOOR_CENTER', 4], ['c-10', '10', 'INDOOR_CENTER', 4],
            ['c-11', '11', 'INDOOR_CENTER', 4], ['c-13', '13', 'INDOOR_CENTER', 4],
            // หน้าเวที (STAGE)
            ['s-8', '08', 'STAGE', 4], ['s-9', '09', 'STAGE', 4],
            ['s-12', '12', 'STAGE', 4], ['s-14', '14', 'STAGE', 4],
            // หน้าบาร์ (BAR)
            ['b-16', '16', 'BAR', 4],
            // โซนทางเดิน (WALKWAY)
            ['k-17', '17', 'WALKWAY', 3], ['k-18', '18', 'WALKWAY', 3],
        ];
        $stmtInsert = $pdo->prepare("INSERT INTO tables (id, number, zone, capacity) VALUES (?, ?, ?, ?)");
        foreach ($tables as $t) {
            $stmtInsert->execute($t);
        }

        // Seed promotions
        $promotions = [
            ['p-1', 'Happy Hour: Buy 1 Get 1', 'Double the impact. Buy any pint from our selected industrial tap list and receive a second on the house. Fuel the evening shift.', 'BUY 1 GET 1', 'Daily • 5PM - 7PM', 'images/promotions/751026922_122278510508129427_3687616286560289044_n.jpg', 1],
            ['p-2', 'Craft Night', 'A gathering for the enthusiasts. Flash your brewing guild card or demonstrate your palate to receive 15% off all tasting flights.', '15% OFF', 'Every Wednesday', 'images/home-booking/735563412_122276495840129427_6246480903433139955_n.jpg', 1],
        ];
        $stmtInsert = $pdo->prepare("INSERT INTO promotions (id, title, description, offer, period, image, active) VALUES (?, ?, ?, ?, ?, ?, ?)");
        foreach ($promotions as $p) {
            $stmtInsert->execute($p);
        }

        // Seed live music
        $music = [
            ['m-1', 'Mon', '19:30 - 20:30', 'วง NULL', 'Acoustic indie rock session.', 'REGULAR'],
            ['m-2', 'Mon', '21:00 - 22:00', 'วง Black Devil', 'Heavy rock and alternative hits.', 'HOT'],
            ['m-3', 'Tue', '19:30 - 20:30', 'วง Poppular', 'Popular pop/rock acoustic sets.', 'REGULAR'],
            ['m-4', 'Tue', '21:30 - 22:30', 'วง ตูมตาม', 'Upbeat local rock covers.', 'REGULAR'],
            ['m-5', 'Wed', '19:45 - 22:00', 'วง Rhapsody', 'Classic progressive rock session.', 'HOT'],
            ['m-6', 'Thu', '19:00 - 21:15', 'วง Tewly', 'Smooth acoustic pop & rock.', 'REGULAR'],
            ['m-7', 'Thu', '21:30 - 22:30', 'วง Chilling Groove', 'Funky grooves and soul.', 'REGULAR'],
            ['m-8', 'Fri', '18:45 - 20:45', 'วง Tewly', 'Popular hit songs and request sets.', 'REGULAR'],
            ['m-9', 'Fri', '21:00 - 22:00', 'วง Karuna', 'Grunge and alternative rock.', 'HOT'],
            ['m-10', 'Fri', '22:30 - 24:00', 'วง Judy', 'Late night energetic party pop.', 'HOT'],
            ['m-11', 'Sat', '19:00 - 20:00', 'วง NULL', 'Alternative rock & pop.', 'REGULAR'],
            ['m-12', 'Sat', '21:00 - 22:00', 'วง ....', 'Special guest band session.', 'REGULAR'],
            ['m-13', 'Sat', '22:30 - 23:30', 'วง Karuna', 'High-octane hard rock show.', 'HOT'],
            ['m-14', 'Sun', '19:30 - 20:30', 'วง Black Devil', 'Heavy rock classic sets.', 'REGULAR'],
            ['m-15', 'Sun', '21:30 - 22:30', 'วง ตูมตาม', 'Closing party rock set.', 'REGULAR'],
        ];
        $stmtInsert = $pdo->prepare("INSERT INTO live_music (id, day, time, artist, description, status) VALUES (?, ?, ?, ?, ?, ?)");
        foreach ($music as $m) {
            $stmtInsert->execute($m);
        }

        // Seed beers
        $beers = [
            ['b-1', '01', 'DDH OLD SCHOOL IPA', 'RERNGPOY X MUANJAI', '6.6%', 'Hoppy', 'Double Dry Hopped classic Old School IPA. Bitter, citrusy, and resinous.', 220.0],
            ['b-2', '02', 'PASSION MANGO & STRAWBERRY', 'PHING DOI', '5.0%', 'Fruity', 'Sweet and tart fruit beer.', 210.0],
            ['b-3', '03', 'FOUR TEEN AGAIN SESSION IPA', 'CHIT BEER', '4.9%', 'Hoppy', 'Light, refreshing session IPA. Low ABV, high hop aroma, citrus, tropical notes.', 180.0],
            ['b-4', '04', 'NEW ZEALAND PALE ALE', 'CHIT BEER', '5.6%', 'Hoppy', 'Brewed with NZ hops. Lime peel, gooseberry, tropical fruit aroma, clean bitterness.', 190.0],
            ['b-5', '05', 'CHITHOLE LAGER', 'CHIT HOLE', '5.0%', 'Crisp', 'Our signature clean craft lager. Bready, crisp, refreshing body, perfect pour.', 180.0],
            ['b-6', '06', 'IMPERIAL STOUT', 'WISET', '9.0%', 'Dark', 'Big, bold stout. Heavy roasted malt, coffee, dark chocolate, full-bodied warmth.', 260.0],
            ['b-7', '07', 'FOREVER WEIZEN', 'CHIT HOLE', '5.0%', 'Fruity', 'Classic wheat beer. Hazy gold, banana esters, clove aroma, smooth mouthfeel.', 190.0],
            ['b-8', '08', 'WITTY WITBIER', 'MICKLEHEIM', '6.3%', 'Fruity', 'Belgian-style wheat beer. Orange peel, coriander, spicy citrus notes, soft texture.', 200.0],
            ['b-9', '09', 'TRIPLE IPA', 'WISET', '11.0%', 'Hoppy', 'Extremely strong IPA. Massive hop intensity, heavy pine, grapefruit, sweet warming alcohol finish.', 280.0],
            ['b-10', '10', 'HILLBERRY STRAWBERRY CIDER', 'CHIANGMAI', '5.0%', 'Fruity', 'Crisp apple cider infused with mountain strawberries. Sweet, refreshing, bubbly.', 210.0],
            ['b-11', '11', 'RED TRUCK ALE', 'CHIANGMAI', '5.0%', 'Crisp', 'Amber ale named after Chiang Mai\'s Red Truck. Caramel malts, subtle herbal hops.', 190.0],
            ['b-12', '12', 'GUAVA ALE', 'KHOY BREWING', '5.0%', 'Fruity', 'Unique tropical ale brewed with local pink guavas. Highly aromatic, exotic, refreshing.', 200.0],
            ['b-13', '13', 'TIDLOM SESSION IPA', 'SUNTREE', '4.4%', 'Hoppy', 'Crushable low-alcohol session IPA. Floral hops, light body, highly refreshing.', 180.0],
            ['b-14', '14', 'SIMBUS PALE ALE', 'MICKLEHEIM', '5.7%', 'Hoppy', 'Clean pale ale. Grapefruit, pine needle, toasted cracker malt body, balanced finish.', 190.0],
            ['b-15', '15', 'ROSE', 'TAWANDANG', '4.0%', 'Fruity', 'Blush rose wheat ale. Floral hibiscus notes, sweet berry aroma, light pink head.', 180.0],
            ['b-16', '16', 'GERMAN LAGER', 'TAWANDANG', '4.9%', 'Crisp', 'Traditional German-style Helles lager. Noble Hallertau hops, clean pilsner malt sweetness.', 180.0],
        ];
        $stmtInsert = $pdo->prepare("INSERT INTO beers (id, tap_number, name, type, abv, ibu, description, price) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        foreach ($beers as $b) {
            $stmtInsert->execute($b);
        }
    } else {
        // Run migration checks / data updates even if database is already populated
        
        // 1. Check duplicate or old beers list refresh (e.g. if 'Lanna IPA' is found)
        $stmt = $pdo->query("SELECT COUNT(*) FROM beers WHERE name = 'Lanna IPA'");
        if ($stmt->fetchColumn() > 0) {
            $pdo->exec("DELETE FROM beers");
            $beers = [
                ['b-1', '01', 'DDH OLD SCHOOL IPA', 'RERNGPOY X MUANJAI', '6.6%', 'Hoppy', 'Double Dry Hopped classic Old School IPA. Bitter, citrusy, and resinous.', 220.0],
                ['b-2', '02', 'PASSION MANGO & STRAWBERRY', 'PHING DOI', '5.0%', 'Fruity', 'Sweet and tart fruit beer, rich passion fruit, mango, and fresh strawberry notes.', 210.0],
                ['b-3', '03', 'FOUR TEEN AGAIN SESSION IPA', 'CHIT BEER', '4.9%', 'Hoppy', 'Light, refreshing session IPA. Low ABV, high hop aroma, citrus, tropical notes.', 180.0],
                ['b-4', '04', 'NEW ZEALAND PALE ALE', 'CHIT BEER', '5.6%', 'Hoppy', 'Brewed with NZ hops. Lime peel, gooseberry, tropical fruit aroma, clean bitterness.', 190.0],
                ['b-5', '05', 'CHITHOLE LAGER', 'CHIT HOLE', '5.0%', 'Crisp', 'Our signature clean craft lager. Bready, crisp, refreshing body, perfect pour.', 180.0],
                ['b-6', '06', 'IMPERIAL STOUT', 'WISET', '9.0%', 'Dark', 'Big, bold stout. Heavy roasted malt, coffee, dark chocolate, full-bodied warmth.', 260.0],
                ['b-7', '07', 'FOREVER WEIZEN', 'CHIT HOLE', '5.0%', 'Fruity', 'Classic wheat beer. Hazy gold, banana esters, clove aroma, smooth mouthfeel.', 190.0],
                ['b-8', '08', 'WITTY WITBIER', 'MICKLEHEIM', '6.3%', 'Fruity', 'Belgian-style wheat beer. Orange peel, coriander, spicy citrus notes, soft texture.', 200.0],
                ['b-9', '09', 'TRIPLE IPA', 'WISET', '11.0%', 'Hoppy', 'Extremely strong IPA. Massive hop intensity, heavy pine, grapefruit, sweet warming alcohol finish.', 280.0],
                ['b-10', '10', 'HILLBERRY STRAWBERRY CIDER', 'CHIANGMAI', '5.0%', 'Fruity', 'Crisp apple cider infused with mountain strawberries. Sweet, refreshing, bubbly.', 210.0],
                ['b-11', '11', 'RED TRUCK ALE', 'CHIANGMAI', '5.0%', 'Crisp', 'Amber ale named after Chiang Mai\'s Red Truck. Caramel malts, subtle herbal hops.', 190.0],
                ['b-12', '12', 'GUAVA ALE', 'KHOY BREWING', '5.0%', 'Fruity', 'Unique tropical ale brewed with local pink guavas. Highly aromatic, exotic, refreshing.', 200.0],
                ['b-13', '13', 'TIDLOM SESSION IPA', 'SUNTREE', '4.4%', 'Hoppy', 'Crushable low-alcohol session IPA. Floral hops, light body, highly refreshing.', 180.0],
                ['b-14', '14', 'SIMBUS PALE ALE', 'MICKLEHEIM', '5.7%', 'Hoppy', 'Clean pale ale. Grapefruit, pine needle, toasted cracker malt body, balanced finish.', 190.0],
                ['b-15', '15', 'ROSE', 'TAWANDANG', '4.0%', 'Fruity', 'Blush rose wheat ale. Floral hibiscus notes, sweet berry aroma, light pink head.', 180.0],
                ['b-16', '16', 'GERMAN LAGER', 'TAWANDANG', '4.9%', 'Crisp', 'Traditional German-style Helles lager. Noble Hallertau hops, clean pilsner malt sweetness.', 180.0],
            ];
            $stmtInsert = $pdo->prepare("INSERT INTO beers (id, tap_number, name, type, abv, ibu, description, price) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            foreach ($beers as $b) {
                $stmtInsert->execute($b);
            }
        }

        // 2. Auto-correct old promotion image paths if they exist
        $pdo->exec("UPDATE promotions SET image = 'images/promotions/751026922_122278510508129427_3687616286560289044_n.jpg' WHERE image LIKE '%623883591273152516%' OR image = '/images/promotions/623883591273152516.jpg'");
        $pdo->exec("UPDATE promotions SET image = 'images/home-booking/735563412_122276495840129427_6246480903433139955_n.jpg' WHERE image LIKE '%736367357%' OR image = '/images/promotions/736367357_122276503490129427_3242995819338038000_n.jpg'");
    }
} catch (\PDOException $e) {
    die("Database Connection / Setup Failed: " . $e->getMessage());
}

