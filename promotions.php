<?php
require_once 'db.php';

// Live Real-Time AJAX Sync Endpoint
if (isset($_GET['action']) && $_GET['action'] === 'get_live_promotions') {
    header('Content-Type: application/json');
    try {
        $stmt = $pdo->query("SELECT promo_id AS id, promo_title AS title, description, offer, promo_period AS period, image_path AS image FROM promotion WHERE is_active = 1 ORDER BY promo_id DESC");
        $live_promos = $stmt->fetchAll();
        echo json_encode(['success' => true, 'data' => $live_promos]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// Fetch active promotions initially
try {
    $stmt = $pdo->query("SELECT promo_id AS id, promo_title AS title, description, offer, promo_period AS period, image_path AS image FROM promotion WHERE is_active = 1 ORDER BY promo_id DESC");
    $promotions = $stmt->fetchAll();
} catch (Exception $e) {
    $promotions = [];
}

require_once 'header.php';
?>

<style>
    .promos-hero {
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
    .promos-hero-bg {
        position: absolute;
        inset: 0;
        opacity: 0.4;
        background-image: url('images/promotions/725574172_122249018876266045_3156720140671520867_n (1).jpg');
        background-size: cover;
        background-position: center;
        mix-blend-mode: luminosity;
        z-index: 0;
    }
    .promos-hero-overlay {
        position: absolute;
        inset: 0;
        background: linear-gradient(to top, #131313, rgba(19, 19, 19, 0.6) 50%, transparent);
        z-index: 1;
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
</style>

<!-- Hero / Header Section -->
<section class="promos-hero mb-5">
    <div class="promos-hero-bg"></div>
    <div class="promos-hero-overlay"></div>
    
    <div class="container px-4 px-lg-5" style="position: relative; z-index: 2;">
        <div class="live-status-pill">
            <div class="live-dot-wrapper">
                <div class="live-dot-core"></div>
                <div class="live-dot-ring"></div>
            </div>
            <span class="live-status-text"><?php echo t("Promotions List", "รายการโปรโมชัน"); ?></span>
        </div>
        <span class="font-mono text-warning mb-2 d-block tracking-widest text-uppercase" style="font-size: 11px; font-weight: bold;">
            <?php echo t("Chit Hole Experiences", "ชิตโฮล ประสบการณ์พิเศษ"); ?>
        </span>
        <h1 class="font-anton text-light text-uppercase display-3 leading-none m-0">
            <?php echo t("Promotions", "รายการโปรโมชัน"); ?>
        </h1>
        <p class="mt-4 text-secondary fs-5 m-0" style="max-width: 600px;">
            <?php echo t(
              "Taste fresh craft flavors at even better value. Explore our latest special privileges and promotions listed below.",
              "ลิ้มลองรสชาติสดใหม่ในราคาที่คุ้มค่ากว่า เลือกดูสิทธิพิเศษและโปรโมชั่นล่าสุดได้ที่รายการด้านล่าง"
            ); ?>
        </p>
    </div>
</section>

<!-- Featured Promotions Bento Grid -->
<div class="container px-4 px-lg-5 pb-5 promos-bento-grid" id="promotions-container">
    <?php if (empty($promotions)): ?>
        <div class="text-center font-mono py-5 text-secondary border border-dashed border-secondary border-opacity-25 rounded w-100">
            <?php echo t("No promotions active at the moment.", "ไม่มีโปรโมชั่นเปิดใช้งานในขณะนี้"); ?>
        </div>
    <?php else: ?>
        <!-- Dynamic promotions rendering -->
        <div class="row g-4">
            <?php foreach ($promotions as $index => $promo): ?>
                <?php 
                $is_large = ($index % 2 === 0);
                $raw_img = !empty($promo['image']) ? preg_replace('#^(\.\./|/)+#', '', $promo['image']) : '';
                $promo_img = !empty($raw_img) ? $raw_img : 'images/promotions/uploaded_1788947667_IMG_0181.JPG';
                ?>
                <?php if ($is_large): ?>
                    <!-- 7-Column Horizontal Card -->
                    <div class="col-xl-7">
                        <div class="glass-card overflow-hidden h-100 position-relative border-0 shadow-lg">
                            <div class="row g-0 h-100">
                                <div class="col-md-5 position-relative overflow-hidden" style="min-height: 250px;">
                                    <img src="<?php echo htmlspecialchars($promo_img); ?>" alt="<?php echo htmlspecialchars($promo['title']); ?>" class="w-100 h-100 position-absolute top-0 start-0" style="object-fit: cover; width: 100%; height: 100%; z-index: 0;" onerror="this.onerror=null; this.src='images/promotions/uploaded_1788947667_IMG_0181.JPG';">
                                    <div class="h-100 w-100 position-absolute top-0 start-0" style="background: linear-gradient(to right, rgba(20,20,20,0.1), #201f1f); z-index: 1; pointer-events: none;"></div>
                                </div>
                                <div class="col-md-7 p-4 p-md-5 d-flex flex-column justify-content-center bg-dark bg-opacity-10 relative z-2">
                                    <span class="badge bg-warning bg-opacity-10 border border-warning border-opacity-25 text-warning font-mono py-1.5 px-3 self-start mb-3" style="width: fit-content; font-size: 10px; font-weight: bold;"><?php echo htmlspecialchars($promo['period']); ?></span>
                                    <h2 class="font-anton text-uppercase text-light display-6 mb-3 lh-1"><?php echo htmlspecialchars($promo['title']); ?></h2>
                                    <p class="text-secondary small mb-0"><?php echo nl2br(htmlspecialchars($promo['description'])); ?></p>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <!-- 5-Column Vertical Card -->
                    <div class="col-xl-5">
                        <div class="glass-card overflow-hidden h-100 position-relative border-0 shadow-lg d-flex flex-column">
                            <div class="position-relative overflow-hidden" style="height: 220px;">
                                <img src="<?php echo htmlspecialchars($promo_img); ?>" alt="<?php echo htmlspecialchars($promo['title']); ?>" class="w-100 h-100 position-absolute top-0 start-0" style="object-fit: cover; width: 100%; height: 100%; z-index: 0;" onerror="this.onerror=null; this.src='images/promotions/uploaded_1788947667_IMG_0181.JPG';">
                                <div class="h-100 w-100 position-absolute top-0 start-0" style="background: linear-gradient(to bottom, rgba(20,20,20,0.1), #201f1f); z-index: 1; pointer-events: none;"></div>
                            </div>
                            <div class="p-4 p-md-5 flex-grow-1 d-flex flex-column bg-dark bg-opacity-10" style="margin-top: -35px; position:relative; z-index: 2;">
                                <span class="text-warning font-mono text-uppercase tracking-wider d-block mb-1" style="font-size: 10px; font-weight: bold;"><?php echo htmlspecialchars($promo['period']); ?></span>
                                <h2 class="font-anton text-uppercase text-light fs-3 mb-3"><?php echo htmlspecialchars($promo['title']); ?></h2>
                                <p class="text-secondary small mb-0"><?php echo nl2br(htmlspecialchars($promo['description'])); ?></p>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<script>
    let currentPromoHash = JSON.stringify(<?php echo json_encode($promotions); ?>);

    function escapeHtml(str) {
        if (!str) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function renderLivePromotions(promos) {
        const container = document.getElementById('promotions-container');
        if (!container) return;

        if (!promos || promos.length === 0) {
            container.innerHTML = `
                <div class="text-center font-mono py-5 text-secondary border border-dashed border-secondary border-opacity-25 rounded w-100">
                    <?php echo t("No promotions active at the moment.", "ไม่มีโปรโมชั่นเปิดใช้งานในขณะนี้"); ?>
                </div>
            `;
            return;
        }

        let html = '<div class="row g-4">';
        promos.forEach((promo, index) => {
            const isLarge = (index % 2 === 0);
            const title = escapeHtml(promo.title);
            const period = escapeHtml(promo.period);
            const desc = escapeHtml(promo.description).replace(/\n/g, '<br>');
            let rawImg = promo.image ? String(promo.image).replace(/^(\.\.\/|\/)+/, '') : '';
            const image = rawImg ? escapeHtml(rawImg) : 'images/promotions/uploaded_1788947667_IMG_0181.JPG';

            if (isLarge) {
                html += `
                    <div class="col-xl-7">
                        <div class="glass-card overflow-hidden h-100 position-relative border-0 shadow-lg">
                            <div class="row g-0 h-100">
                                <div class="col-md-5 position-relative overflow-hidden" style="min-height: 250px;">
                                    <img src="${image}" alt="${title}" class="w-100 h-100 position-absolute top-0 start-0" style="object-fit: cover; width: 100%; height: 100%; z-index: 0;" onerror="this.onerror=null; this.src='images/promotions/uploaded_1788947667_IMG_0181.JPG';">
                                    <div class="h-100 w-100 position-absolute top-0 start-0" style="background: linear-gradient(to right, rgba(20,20,20,0.1), #201f1f); z-index: 1; pointer-events: none;"></div>
                                </div>
                                <div class="col-md-7 p-4 p-md-5 d-flex flex-column justify-content-center bg-dark bg-opacity-10 relative z-2">
                                    <span class="badge bg-warning bg-opacity-10 border border-warning border-opacity-25 text-warning font-mono py-1.5 px-3 self-start mb-3" style="width: fit-content; font-size: 10px; font-weight: bold;">${period}</span>
                                    <h2 class="font-anton text-uppercase text-light display-6 mb-3 lh-1">${title}</h2>
                                     <p class="text-secondary small mb-0">${desc}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
            } else {
                html += `
                    <div class="col-xl-5">
                        <div class="glass-card overflow-hidden h-100 position-relative border-0 shadow-lg d-flex flex-column">
                            <div class="position-relative overflow-hidden" style="height: 220px;">
                                <img src="${image}" alt="${title}" class="w-100 h-100 position-absolute top-0 start-0" style="object-fit: cover; width: 100%; height: 100%; z-index: 0;" onerror="this.onerror=null; this.src='images/promotions/uploaded_1788947667_IMG_0181.JPG';">
                                <div class="h-100 w-100 position-absolute top-0 start-0" style="background: linear-gradient(to bottom, rgba(20,20,20,0.1), #201f1f); z-index: 1; pointer-events: none;"></div>
                            </div>
                            <div class="p-4 p-md-5 flex-grow-1 d-flex flex-column bg-dark bg-opacity-10" style="margin-top: -35px; position:relative; z-index: 2;">
                                <span class="text-warning font-mono text-uppercase tracking-wider d-block mb-1" style="font-size: 10px; font-weight: bold;">${period}</span>
                                <h2 class="font-anton text-uppercase text-light fs-3 mb-3">${title}</h2>
                                 <p class="text-secondary small mb-0">${desc}</p>
                            </div>
                        </div>
                    </div>
                `;
            }
        });
        html += '</div>';
        container.innerHTML = html;
    }

    function checkLivePromotions() {
        fetch('promotions.php?action=get_live_promotions')
            .then(res => res.json())
            .then(res => {
                if (res.success && res.data) {
                    const newHash = JSON.stringify(res.data);
                    if (newHash !== currentPromoHash) {
                        currentPromoHash = newHash;
                        renderLivePromotions(res.data);
                    }
                }
            })
            .catch(err => console.log('Live sync error:', err));
    }

    // Auto sync every 3.5 seconds
    setInterval(checkLivePromotions, 3500);
</script>

<?php require_once 'footer.php'; ?>
