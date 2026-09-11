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
    .live-status-pill {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 5px 14px;
        background: rgba(24, 20, 16, 0.85);
        border: 1px solid rgba(255, 215, 130, 0.35);
        border-radius: 30px;
        backdrop-filter: blur(12px);
        -webkit-backdrop-filter: blur(12px);
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.6), 0 0 15px rgba(255, 215, 130, 0.12);
        margin-bottom: 1rem;
    }
    .live-dot-wrapper {
        position: relative;
        width: 10px;
        height: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    .live-dot-core {
        width: 7px;
        height: 7px;
        background-color: #10b981;
        border-radius: 50%;
        box-shadow: 0 0 8px #10b981;
        position: relative;
        z-index: 2;
    }
    .live-dot-ring {
        position: absolute;
        width: 100%;
        height: 100%;
        border: 2px solid #34d399;
        border-radius: 50%;
        animation: liveRingPulse 2s cubic-bezier(0.215, 0.61, 0.355, 1) infinite;
        z-index: 1;
    }
    @keyframes liveRingPulse {
        0% { transform: scale(0.8); opacity: 0.9; }
        60%, 100% { transform: scale(2.6); opacity: 0; }
    }
    .live-status-text {
        font-family: 'Rockwell', 'Pridi', 'Arvo', serif;
        font-size: 11px;
        font-weight: 700;
        letter-spacing: 0.08em;
        color: #ffd782;
        text-transform: uppercase;
    }

    /* 3D Multi-Dimensional Tap Board Styling */
    .tap-board-header-bar {
        background: rgba(18, 18, 26, 0.65);
        border: 1px solid rgba(255, 215, 130, 0.2);
        border-radius: 12px;
        padding: 10px 20px;
        color: rgba(255, 215, 130, 0.85);
        font-family: 'Rockwell', 'Pridi', serif;
        font-size: 11px;
        font-weight: 700;
        letter-spacing: 0.12em;
        text-transform: uppercase;
        backdrop-filter: blur(8px);
        -webkit-backdrop-filter: blur(8px);
        box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.05);
    }
    .tap-board-card {
        background: linear-gradient(135deg, rgba(28, 28, 36, 0.95) 0%, rgba(16, 16, 22, 0.98) 100%);
        border: 1.5px solid rgba(255, 215, 130, 0.22);
        border-radius: 14px;
        padding: 14px 20px;
        transition: all 0.35s cubic-bezier(0.16, 1, 0.3, 1);
        position: relative;
        overflow: hidden;
        box-shadow: 0 8px 25px rgba(0, 0, 0, 0.55), inset 0 1px 0 rgba(255, 255, 255, 0.1), 0 0 15px rgba(255, 215, 130, 0.04);
    }
    .tap-board-card::before {
        content: '';
        position: absolute;
        left: 0;
        top: 0;
        bottom: 0;
        width: 4px;
        background: linear-gradient(180deg, #ffd782, #f59e0b);
        border-radius: 4px 0 0 4px;
        opacity: 0.8;
        transition: all 0.3s ease;
    }
    .tap-board-card:hover {
        transform: translateY(-4px) scale(1.01);
        border-color: #ffd782 !important;
        box-shadow: 0 14px 35px rgba(0, 0, 0, 0.75), 0 0 20px rgba(255, 215, 130, 0.25) !important;
        background: linear-gradient(135deg, rgba(34, 34, 44, 0.98) 0%, rgba(20, 20, 28, 1) 100%);
    }
    .tap-board-card:hover::before {
        opacity: 1;
        width: 5px;
        box-shadow: 0 0 12px #ffd782;
    }
    .tap-board-card.sold-out {
        opacity: 0.55;
        border-color: rgba(239, 68, 68, 0.3);
        background: linear-gradient(135deg, rgba(24, 16, 16, 0.9) 0%, rgba(14, 10, 10, 0.95) 100%);
    }
    .tap-board-card.sold-out::before {
        background: linear-gradient(180deg, #ef4444, #dc2626);
    }

    /* 3D Metallic Tap Number Badge */
    .tap-number-badge {
        width: 44px;
        height: 44px;
        border-radius: 12px;
        background: linear-gradient(145deg, #2a2a34 0%, #14141a 100%);
        border: 1.5px solid rgba(255, 215, 130, 0.4);
        color: #ffd782;
        font-family: 'Anton', 'Rockwell', sans-serif;
        font-size: 18px;
        display: flex;
        align-items: center;
        justify-content: center;
        box-shadow: 0 4px 10px rgba(0, 0, 0, 0.6), inset 0 1px 2px rgba(255, 215, 130, 0.3);
        flex-shrink: 0;
    }
    .sold-out .tap-number-badge {
        border-color: rgba(239, 68, 68, 0.4);
        color: #f87171;
        box-shadow: 0 4px 10px rgba(0, 0, 0, 0.6);
    }

    /* 3D Glass Pill Badges & ABV Tag */
    .beer-brand-badge {
        background: rgba(255, 215, 130, 0.12);
        border: 1px solid rgba(255, 215, 130, 0.35);
        color: #ffd782;
        font-family: 'Rockwell', 'Pridi', serif;
        font-size: 11px;
        font-weight: 700;
        letter-spacing: 0.05em;
        border-radius: 20px;
        padding: 3px 10px;
        display: inline-block;
    }
    .abv-pill-badge {
        background: linear-gradient(135deg, rgba(20, 20, 26, 0.9) 0%, rgba(10, 10, 14, 0.95) 100%);
        border: 1.5px solid rgba(255, 215, 130, 0.35);
        border-radius: 20px;
        padding: 4px 12px;
        color: #ffffff;
        font-family: 'Anton', sans-serif;
        font-size: 15px;
        letter-spacing: 0.04em;
        box-shadow: 0 3px 10px rgba(0, 0, 0, 0.5), inset 0 1px 0 rgba(255, 255, 255, 0.1);
        display: inline-flex;
        align-items: center;
        gap: 5px;
        flex-shrink: 0;
    }
    .soldout-pill-badge {
        background: linear-gradient(135deg, #dc2626 0%, #991b1b 100%);
        border: 1.5px solid #ef4444;
        color: #ffffff;
        font-family: 'Anton', sans-serif;
        font-size: 10px;
        letter-spacing: 0.08em;
        padding: 2px 8px;
        border-radius: 20px;
        box-shadow: 0 0 12px rgba(239, 68, 68, 0.5);
    }
</style>

<!-- Cover Banner Section -->
<section class="taplist-hero">
    <div class="taplist-hero-bg"></div>
    <div class="taplist-hero-overlay"></div>
    
    <div class="container px-4 px-lg-5 relative z-3" style="position: relative; z-index: 2;">
        <div class="live-status-pill">
            <div class="live-dot-wrapper">
                <div class="live-dot-core"></div>
                <div class="live-dot-ring"></div>
            </div>
            <span class="live-status-text"><?php echo t("Live Draft Menu", "รายการเบียร์สดบนบอร์ด"); ?></span>
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
                <div class="glass-card overflow-hidden position-relative border border-warning border-opacity-30 rounded-4 shadow-lg" style="height: 250px;">
                    <div class="position-absolute top-0 start-0 end-0" style="height: 3px; background: linear-gradient(90deg, #f59e0b, #ffd782, #f59e0b); z-index: 3;"></div>
                    <div class="absolute inset-0 bg-dark opacity-75" style="position: absolute; inset:0; background-image: url('images/beer-menu/481664700_122207974712129427_4846131329867806613_n.jpg'); background-size:cover; background-position: center; mix-blend-mode: luminosity; opacity:0.25; z-index:0;"></div>
                    <div class="h-100 d-flex flex-column justify-content-end p-4 relative" style="position: relative; z-index: 2;">
                        <div class="d-flex gap-4">
                            <div class="d-flex flex-column">
                                <span id="active-taps-count" class="font-anton text-warning display-3 lh-1" style="text-shadow: 0 0 20px rgba(255, 215, 130, 0.4);">
                                    <?php echo sprintf("%02d", $active_taps_count); ?>
                                </span>
                                <span class="text-uppercase font-mono tracking-wider mt-1" style="font-size: 18px; font-weight: bold; color: #ffffff;">
                                    <?php echo t("Active Taps", "แท็ปที่พร้อมบริการ"); ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ABV Info Image Card -->
                <div class="glass-card overflow-hidden p-0 border border-warning border-opacity-30 rounded-4 shadow-lg">
                    <img src="images/beer-menu/763647029_122254452440266045_1313488753492914884_n.jpg" alt="ABV Guide" class="img-fluid w-100" style="display: block;">
                </div>

            </div>
        </div>

        <!-- Right Side: Beers List Cards -->
        <div class="col-lg-8">
            <div class="glass-card p-4 p-md-5 border border-warning border-opacity-30 position-relative overflow-hidden shadow-lg">
                <div class="position-absolute top-0 start-0 end-0" style="height: 3px; background: linear-gradient(90deg, #f59e0b, #ffd782, #f59e0b);"></div>
                <div class="d-flex justify-content-between align-items-center mb-4 pb-3 border-bottom border-secondary border-opacity-25">
                    <h3 class="font-anton text-warning text-uppercase tracking-wider m-0 d-flex align-items-center gap-2 fs-3">
                        <span class="material-symbols-outlined text-warning fs-3">sports_bar</span>
                        <span><?php echo t("Live Tap Board", "กระดานเบียร์สดส่งตรงในร้าน"); ?></span>
                    </h3>
                    <span class="badge bg-black bg-opacity-60 border border-warning border-opacity-30 text-warning font-sans px-3 py-1.5 rounded-pill small fw-bold">
                        <?php echo count($beers); ?> <?php echo t("Taps Total", "แท็ปทั้งหมด"); ?>
                    </span>
                </div>

                <!-- 3D Board Column Headers: NO | BRAND | BEERS | ABV -->
                <div class="tap-board-header-bar d-none d-md-flex align-items-center gap-3 mb-3">
                    <div style="width: 54px; flex-shrink: 0; text-align: center;"><?php echo t("NO", "NO"); ?></div>
                    <div style="width: 130px; flex-shrink: 0;"><?php echo t("BRAND", "BRAND"); ?></div>
                    <div class="flex-grow-1"><?php echo t("BEERS", "BEERS"); ?></div>
                    <div style="width: 110px; flex-shrink: 0; text-align: right;"><?php echo t("ABV", "ABV"); ?></div>
                </div>

                <div id="beer-cards-container" class="d-flex flex-column gap-3">
                    <?php if (empty($beers)): ?>
                        <div class="text-center font-mono py-5 text-secondary border border-dashed border-secondary border-opacity-25 rounded-4">
                            <?php echo t("No active beers on tap right now.", "ขณะนี้ไม่มีเบียร์เปิดบริการบนแท็ปบอร์ด"); ?>
                        </div>
                    <?php else: ?>
                        <?php foreach ($beers as $b): ?>
                            <div id="beer-card-<?php echo $b['menu_id']; ?>" class="tap-board-card <?php echo !$b['is_active'] ? 'sold-out' : ''; ?> d-flex align-items-center flex-wrap flex-md-nowrap gap-3">
                                <!-- 1. NO -->
                                <div class="d-flex align-items-center justify-content-center" style="width: 54px; flex-shrink: 0;">
                                    <div class="tap-number-badge">
                                        <?php echo sprintf("%02d", $b['tap_number']); ?>
                                    </div>
                                </div>
                                <!-- 2. BRAND -->
                                <div class="d-flex align-items-center gap-1 flex-wrap" style="width: 130px; flex-shrink: 0;">
                                    <span class="beer-brand-badge"><?php echo htmlspecialchars($b['beer_type']); ?></span>
                                    <?php if (!$b['is_active']): ?>
                                        <span class="soldout-pill-badge mt-1"><?php echo t("SOLD OUT", "หมดแล้ว"); ?></span>
                                    <?php endif; ?>
                                </div>
                                <!-- 3. BEERS -->
                                <div class="flex-grow-1 min-w-0">
                                    <h4 class="font-anton text-light text-uppercase tracking-wide fs-5 m-0 text-truncate"><?php echo htmlspecialchars($b['menu_name']); ?></h4>
                                </div>
                                <!-- 4. ABV -->
                                <div class="d-flex align-items-center justify-content-end ms-auto ms-md-0" style="width: 110px; flex-shrink: 0;">
                                    <div class="abv-pill-badge">
                                        <span class="text-secondary small font-sans me-1">ABV</span>
                                        <span class="text-warning"><?php echo htmlspecialchars($b['abv']); ?></span>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
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

                const cardsContainer = document.getElementById('beer-cards-container');
                const countBadge = document.getElementById('active-taps-count');
                if (!cardsContainer) return;

                let activeTapsCount = 0;
                let html = '';

                if (beers.length === 0) {
                    html = `
                        <div class="text-center font-mono py-5 text-secondary border border-dashed border-secondary border-opacity-25 rounded-4">
                            <?php echo t("No active beers on tap right now.", "ขณะนี้ไม่มีเบียร์เปิดบริการบนแท็ปบอร์ด"); ?>
                        </div>`;
                } else {
                    beers.forEach(b => {
                        const isActive = parseInt(b.is_active) === 1;
                        if (isActive) activeTapsCount++;

                        const formattedTap = String(b.tap_number).padStart(2, '0');
                        const cardClass = isActive ? '' : 'sold-out';
                        const soldoutBadge = isActive ? '' : `<span class="soldout-pill-badge mt-1"><?php echo t("SOLD OUT", "หมดแล้ว"); ?></span>`;

                        html += `
                            <div id="beer-card-${b.menu_id}" class="tap-board-card ${cardClass} d-flex align-items-center flex-wrap flex-md-nowrap gap-3">
                                <div class="d-flex align-items-center justify-content-center" style="width: 54px; flex-shrink: 0;">
                                    <div class="tap-number-badge">
                                        ${formattedTap}
                                    </div>
                                </div>
                                <div class="d-flex align-items-center gap-1 flex-wrap" style="width: 130px; flex-shrink: 0;">
                                    <span class="beer-brand-badge">${escapeHtml(b.beer_type)}</span>
                                    ${soldoutBadge}
                                </div>
                                <div class="flex-grow-1 min-w-0">
                                    <h4 class="font-anton text-light text-uppercase tracking-wide fs-5 m-0 text-truncate">${escapeHtml(b.menu_name)}</h4>
                                </div>
                                <div class="d-flex align-items-center justify-content-end ms-auto ms-md-0" style="width: 110px; flex-shrink: 0;">
                                    <div class="abv-pill-badge">
                                        <span class="text-secondary small font-sans me-1">ABV</span>
                                        <span class="text-warning">${escapeHtml(b.abv)}</span>
                                    </div>
                                </div>
                            </div>`;
                    });
                }              </div>
                                <div class="d-flex align-items-center justify-content-end ms-auto ms-md-0" style="width: 100px; flex-shrink: 0;">
                                    <div class="abv-pill-badge">
                                        <span class="text-secondary small font-sans me-1">ABV</span>
                                        <span class="text-warning">${escapeHtml(b.abv)}</span>
                                    </div>
                                </div>
                            </div>`;
                    });
                }

                cardsContainer.innerHTML = html;

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
