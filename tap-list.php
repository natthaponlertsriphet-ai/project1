<?php
require_once 'db.php';

// AJAX Request to fetch full live beer list for real-time board updates
if (isset($_GET['action']) && $_GET['action'] === 'get_beers_status') {
    header('Content-Type: application/json');
    try {
        $stmt = $pdo->query("SELECT menu_id, tap_number, menu_name, beer_type, abv, is_active FROM menu ORDER BY CAST(tap_number AS UNSIGNED)");
        $beers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode([
            'success' => true,
            'beers' => $beers
        ]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'beers' => []]);
    }
    exit;
}

// Fetch all beers (including sold out ones)
try {
    $stmt = $pdo->query("SELECT * FROM menu ORDER BY CAST(tap_number AS UNSIGNED)");
    $beers = $stmt->fetchAll();
} catch (Exception $e) {
    $beers = [];
}

// Calculate active taps count
$active_taps_count = 0;
foreach ($beers as $b) {
    if ($b['is_active']) $active_taps_count++;
}

// Categories list
$categories = ["All", "Hoppy", "Crisp", "Dark", "Fruity"];

require_once 'header.php';
?>

<style>
    .taplist-hero {
        position: relative;
        min-height: 45vh;
        width: 100%;
        display: flex;
        flex-direction: column;
        justify-content: flex-end;
        padding-bottom: 3rem;
        padding-top: 4rem;
        background-color: #131313;
        overflow: hidden;
    }
    .taplist-hero-bg {
        position: absolute;
        inset: 0;
        opacity: 0.35;
        background-image: url('images/beer-menu/IMG_9625.jpg');
        background-size: cover;
        background-position: center;
        mix-blend-mode: luminosity;
        z-index: 0;
    }
    .taplist-hero-overlay {
        position: absolute;
        inset: 0;
        background: linear-gradient(to top, #131313, rgba(19, 19, 19, 0.6) 50%, transparent);
        z-index: 1;
    }
    .tap-row {
        transition: background-color 0.2s ease;
    }
    .tap-row:hover {
        background-color: rgba(255, 215, 130, 0.05) !important;
    }
    .btn-style-filter {
        border-radius: 4px;
        font-family: 'Rockwell', 'Pridi', 'Arvo', serif;
        font-size: 13px;
        text-transform: uppercase;
        border: none;
        transition: all 0.2s;
    }
</style>

<!-- Cover Banner Section -->
<section class="taplist-hero">
    <div class="taplist-hero-bg"></div>
    <div class="taplist-hero-overlay"></div>
    
    <div class="container px-4 px-lg-5 relative z-3" style="position: relative; z-index: 2;">
        <div class="d-flex align-items-center gap-2 mb-3">
            <span class="badge bg-warning rounded-circle animate-pulse" style="width: 10px; height: 10px; padding:0; display:inline-block; box-shadow: 0 0 10px rgba(255,215,130,0.8);"></span>
            <span class="font-mono text-warning text-uppercase tracking-widest" style="font-size: 11px;">
                <?php echo t("Live Draft Menu", "รายการเบียร์สดบนบอร์ด"); ?>
            </span>
        </div>
        <h1 class="font-anton text-light text-uppercase tracking-wide display-4 mb-3 lh-1">
            CHIT HOLE CNX <br>
            <span class="text-warning"><?php echo t("Branch Pours", "แท็ปสดส่งตรงในร้าน"); ?></span>
        </h1>
        <p class="text-secondary fs-5 max-width-md m-0">
            <?php echo t("16 taps of the freshest, most uncompromised craft brews. The board updates dynamically as kegs blow.", "คราฟต์เบียร์รสชาติสดใหม่กว่า 16 แท็ปที่คัดสรรมาอย่างไร้ที่ติ บอร์ดบนร้านจะทำการอัปเดตแบบเรียลไทม์เมื่อหัวถังถูกสลับ"); ?>
        </p>
    </div>
</section>

<!-- Main Board Grid -->
<div class="container px-4 px-lg-5 py-5">
    <div class="row g-4 lg:g-5">
        
        <!-- Left Sidebar: Style Filter & Status -->
        <div class="col-lg-4">
            <div class="d-flex flex-column gap-4">
                


                <!-- Live Stat Box with Background Image -->
                <div class="glass-card overflow-hidden position-relative" style="height: 250px;">
                    <div class="absolute inset-0 bg-dark opacity-75" style="position: absolute; inset:0; background-image: url('images/beer-menu/481664700_122207974712129427_4846131329867806613_n.jpg'); background-size:cover; background-position: center; mix-blend-mode: luminosity; opacity:0.2; z-index:0;"></div>
                    <div class="h-100 d-flex flex-column justify-content-end p-4 relative" style="position: relative; z-index: 2;">
                        <div class="d-flex gap-4">
                            <div class="d-flex flex-column">
                                <span id="active-taps-count" class="font-anton text-warning display-4 lh-1">
                                    <?php echo sprintf("%02d", $active_taps_count); ?>
                                </span>
                                <span class="text-uppercase font-mono" style="font-size: 18px; font-weight: bold; color: #ffffff;">
                                    <?php echo t("Active Taps", "แท็ปที่พร้อมบริการ"); ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ABV Info Image Card -->
                <div class="glass-card overflow-hidden p-0">
                    <img src="images/beer-menu/763647029_122254452440266045_1313488753492914884_n.jpg" alt="ABV Guide" class="img-fluid w-100" style="display: block;">
                </div>

            </div>
        </div>

        <!-- Right Side: Beers List Table -->
        <div class="col-lg-8">
            <div class="glass-card p-4 p-md-5">
                <div class="table-responsive">
                    <table class="table table-dark table-striped align-middle border-0 m-0 font-mono text-xs">
                        <thead>
                            <tr class="text-secondary border-bottom border-secondary border-opacity-25 uppercase small">
                                <th class="py-3 px-3" style="width: 10%;"><?php echo t("No", "ลำดับ"); ?></th>
                                <th class="py-3 px-3" style="width: 25%;"><?php echo t("Brand", "แบรนด์"); ?></th>
                                <th class="py-3 px-3" style="width: 50%;"><?php echo t("Beers", "เบียร์"); ?></th>
                                <th class="py-3 px-3" style="width: 15%;"><?php echo t("ABV", "แอลกอฮอล์"); ?></th>
                            </tr>
                        </thead>
                        <tbody id="beer-table-body">
                            <?php if (empty($beers)): ?>
                                <tr>
                                    <td colspan="4" class="text-center py-5 text-secondary">
                                        <?php echo t("No active beers on tap right now.", "ขณะนี้ไม่มีเบียร์เปิดบริการบนแท็ปบอร์ด"); ?>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($beers as $b): ?>
                                    <tr id="beer-row-<?php echo $b['menu_id']; ?>" class="tap-row border-bottom border-secondary border-opacity-10 <?php echo !$b['is_active'] ? 'opacity-40 select-none' : ''; ?>">
                                        <!-- No (Tap Number) -->
                                        <td class="py-4 px-3 font-anton text-warning fs-5">
                                            <?php echo sprintf("%02d", $b['tap_number']); ?>
                                        </td>
                                        <!-- Brand -->
                                        <td class="py-4 px-3 text-secondary font-sans font-bold fs-6 text-uppercase">
                                            <?php echo htmlspecialchars($b['beer_type']); ?>
                                        </td>
                                        <!-- Beers (Name and description) -->
                                        <td class="py-4 px-3">
                                            <div class="d-flex flex-column">
                                                <div class="d-flex align-items-center gap-2 beer-name-container">
                                                    <span class="text-light font-anton text-uppercase fs-6 tracking-wide"><?php echo htmlspecialchars($b['menu_name']); ?></span>
                                                    <?php if (!$b['is_active']): ?>
                                                        <span class="badge bg-danger text-light font-anton text-uppercase px-2 py-0.5 tracking-wider soldout-badge" style="font-size: 8.5px;"><?php echo t("SOLD OUT", "หมดแล้ว"); ?></span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </td>
                                        <!-- ABV -->
                                        <td class="py-4 px-3 font-anton text-light fs-5">
                                            <?php echo htmlspecialchars($b['abv']); ?>
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
</div>

<script>
    // Real-Time Draft Beer Menu Synchronization Engine (1.5s live polling)
    let previousBeersHash = '';

    function syncLiveBeerList() {
        fetch('tap-list.php?action=get_beers_status')
            .then(res => res.json())
            .then(data => {
                if (!data.success || !Array.isArray(data.beers)) return;
                
                const beers = data.beers;
                const currentHash = JSON.stringify(beers);
                
                // Skip DOM update if data has not changed
                if (currentHash === previousBeersHash) return;
                previousBeersHash = currentHash;

                const tbody = document.getElementById('beer-table-body');
                const countBadge = document.getElementById('active-taps-count');
                if (!tbody) return;

                let activeTapsCount = 0;
                let html = '';

                if (beers.length === 0) {
                    html = `
                        <tr>
                            <td colspan="4" class="text-center py-5 text-secondary">
                                <?php echo t("No active beers on tap right now.", "ขณะนี้ไม่มีเบียร์เปิดบริการบนแท็ปบอร์ด"); ?>
                            </td>
                        </tr>`;
                } else {
                    beers.forEach(b => {
                        const isActive = parseInt(b.is_active) === 1;
                        if (isActive) activeTapsCount++;

                        const formattedTap = String(b.tap_number).padStart(2, '0');
                        const rowClass = isActive ? '' : 'opacity-40 select-none';
                        const soldoutBadge = isActive ? '' : `<span class="badge bg-danger text-light font-anton text-uppercase px-2 py-0.5 tracking-wider soldout-badge" style="font-size: 8.5px;"><?php echo t("SOLD OUT", "หมดแล้ว"); ?></span>`;

                        html += `
                            <tr id="beer-row-${b.menu_id}" class="tap-row border-bottom border-secondary border-opacity-10 ${rowClass}">
                                <td class="py-4 px-3 font-anton text-warning fs-5">
                                    ${formattedTap}
                                </td>
                                <td class="py-4 px-3 text-secondary font-sans font-bold fs-6 text-uppercase">
                                    ${escapeHtml(b.beer_type)}
                                </td>
                                <td class="py-4 px-3">
                                    <div class="d-flex flex-column">
                                        <div class="d-flex align-items-center gap-2 beer-name-container">
                                            <span class="text-light font-anton text-uppercase fs-6 tracking-wide">${escapeHtml(b.menu_name)}</span>
                                            ${soldoutBadge}
                                        </div>
                                    </div>
                                </td>
                                <td class="py-4 px-3 font-anton text-light fs-5">
                                    ${escapeHtml(b.abv)}
                                </td>
                            </tr>`;
                    });
                }

                tbody.innerHTML = html;

                if (countBadge) {
                    countBadge.innerText = String(activeTapsCount).padStart(2, '0');
                }
            })
            .catch(err => console.error("Error syncing live beer list:", err));
    }

    function escapeHtml(text) {
        if (!text) return '';
        return String(text)
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }

    // Start 1.5s real-time sync loop
    window.addEventListener('load', () => {
        syncLiveBeerList();
        setInterval(syncLiveBeerList, 1500);
    });
</script>

<?php require_once 'footer.php'; ?>
