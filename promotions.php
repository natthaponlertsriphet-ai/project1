<?php
require_once 'db.php';

// Fetch active promotions
try {
    $stmt = $pdo->query("SELECT * FROM promotions WHERE active = 1");
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
    .promos-bento-grid {
        position: relative;
        z-index: 2;
    }
</style>

<!-- Hero / Header Section -->
<section class="promos-hero mb-5">
    <div class="promos-hero-bg"></div>
    <div class="promos-hero-overlay"></div>
    
    <div class="container px-4 px-lg-5" style="position: relative; z-index: 2;">
        <span class="font-mono text-warning mb-2 d-block tracking-widest text-uppercase" style="font-size: 11px; font-weight: bold;">
            <?php echo t("Chit Hole Experiences", "ชิตโฮล ประสบการณ์พิเศษ"); ?>
        </span>
        <h1 class="font-anton text-light text-uppercase display-3 leading-none m-0">
            <?php echo t("Promotions", "โปรโมชัน"); ?>
        </h1>
        <p class="mt-4 text-secondary fs-5 m-0" style="max-width: 600px;">
            <?php echo t(
              "Stay hydrated, stay energized. From our signature Lady Night to fitness rewards, discover what's pouring this week at Chiang Mai's premier craft taproom.",
              "เติมพลังและความสดชื่น พบข้อเสนอพิเศษในร้านตั้งแต่แคมเปญเลดี้ไนท์ไปจนถึงรางวัลสำหรับสายเฮลตี้ และตารางดนตรีสดสุดมันส์ประจำสัปดาห์นี้"
            ); ?>
        </p>
    </div>
</section>

<!-- Featured Promotions Bento Grid -->
<div class="container px-4 px-lg-5 pb-5 promos-bento-grid">
    <?php if (empty($promotions)): ?>
        <div class="text-center font-mono py-5 text-secondary border border-dashed border-secondary border-opacity-25 rounded w-100">
            <?php echo t("No promotions active at the moment.", "ไม่มีโปรโมชันเปิดใช้งานในขณะนี้"); ?>
        </div>
    <?php else: ?>
        <!-- Dynamic promotions rendering -->
        <div class="row g-4">
            <?php foreach ($promotions as $index => $promo): ?>
                <?php $is_large = ($index % 2 === 0); ?>
                <?php if ($is_large): ?>
                    <!-- 7-Column Horizontal Card -->
                    <div class="col-xl-7">
                        <div class="glass-card overflow-hidden h-100 position-relative border-0 shadow-lg">
                            <div class="row g-0 h-100">
                                <div class="col-md-5 position-relative overflow-hidden" style="min-height: 250px;">
                                    <div class="h-100 w-100" style="background-image: url('<?php echo htmlspecialchars($promo['image']); ?>'); background-size: cover; background-position: center; position:absolute;"></div>
                                    <div class="h-100 w-100" style="position:absolute; background: linear-gradient(to right, transparent, #201f1f); opacity: 1;"></div>
                                </div>
                                <div class="col-md-7 p-4 p-md-5 d-flex flex-column justify-content-center bg-dark bg-opacity-10">
                                    <span class="badge bg-warning bg-opacity-10 border border-warning border-opacity-25 text-warning font-mono py-1.5 px-3 self-start mb-3" style="width: fit-content; font-size: 10px; font-weight: bold;"><?php echo htmlspecialchars($promo['period']); ?></span>
                                    <h2 class="font-anton text-uppercase text-light display-6 mb-3 lh-1"><?php echo htmlspecialchars($promo['title']); ?></h2>
                                    <p class="text-secondary small mb-4"><?php echo nl2br(htmlspecialchars($promo['description'])); ?></p>
                                    <a href="reservation.php" class="btn btn-custom-gold py-2.5 px-4 font-anton text-uppercase" style="width: fit-content; display: inline-flex; align-items: center; gap: 8px;">
                                        <span class="material-symbols-outlined fs-6">local_bar</span>
                                        <?php echo t("Book a Table", "จองโต๊ะ"); ?>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <!-- 5-Column Vertical Card -->
                    <div class="col-xl-5">
                        <div class="glass-card overflow-hidden h-100 position-relative border-0 shadow-lg d-flex flex-column">
                            <div class="position-relative overflow-hidden" style="height: 200px;">
                                <div class="h-100 w-100" style="background-image: url('<?php echo htmlspecialchars($promo['image']); ?>'); background-size: cover; background-position: center; position:absolute;"></div>
                                <div class="h-100 w-100" style="position:absolute; background: linear-gradient(to bottom, transparent, #201f1f); opacity: 1;"></div>
                            </div>
                            <div class="p-4 p-md-5 flex-grow-1 d-flex flex-column bg-dark bg-opacity-10" style="margin-top: -35px; position:relative; z-index: 2;">
                                <span class="text-warning font-mono text-uppercase tracking-wider d-block mb-1" style="font-size: 10px; font-weight: bold;"><?php echo htmlspecialchars($promo['period']); ?></span>
                                <h2 class="font-anton text-uppercase text-light fs-3 mb-3"><?php echo htmlspecialchars($promo['title']); ?></h2>
                                <p class="text-secondary small mb-4"><?php echo nl2br(htmlspecialchars($promo['description'])); ?></p>
                                <div class="mt-auto">
                                    <a href="reservation.php" class="btn btn-custom-gold py-2.5 px-4 font-anton text-uppercase" style="width: fit-content; display: inline-flex; align-items: center; gap: 8px;">
                                        <span class="material-symbols-outlined fs-6">local_bar</span>
                                        <?php echo t("Book a Table", "จองโต๊ะ"); ?>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php require_once 'footer.php'; ?>
