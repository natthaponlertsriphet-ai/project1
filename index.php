<?php
require_once 'db.php';
require_once 'header.php';

// Fetch highlight data from database
try {
    // Top active beers for taproom showcase
    $stmt = $pdo->query("SELECT * FROM menu WHERE is_active = 1 ORDER BY CAST(tap_number AS UNSIGNED) ASC, tap_number ASC LIMIT 8");
    $beers = $stmt->fetchAll();

    // Active promotions
    $stmt = $pdo->query("SELECT * FROM promotion WHERE is_active = 1 ORDER BY promo_id DESC LIMIT 3");
    $promotions = $stmt->fetchAll();

    // Live music schedule
    $stmt = $pdo->query("SELECT * FROM music ORDER BY CASE show_day WHEN 'Mon' THEN 1 WHEN 'Tue' THEN 2 WHEN 'Wed' THEN 3 WHEN 'Thu' THEN 4 WHEN 'Fri' THEN 5 WHEN 'Sat' THEN 6 WHEN 'Sun' THEN 7 ELSE 8 END, show_time LIMIT 7");
    $music_schedule = $stmt->fetchAll();
} catch (Exception $e) {
    $beers = [];
    $promotions = [];
    $music_schedule = [];
}

// Collect atmosphere gallery images
$atmosphere_dir = __DIR__ . '/images/atmosphere';
$gallery_images = [];
if (is_dir($atmosphere_dir)) {
    $files = scandir($atmosphere_dir);
    foreach ($files as $file) {
        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
            $gallery_images[] = 'images/atmosphere/' . $file;
        }
    }
}
?>

<style>
/* Custom Animations & Premium Styling for Modern Homepage */
@keyframes pulseGlow {
    0%, 100% { box-shadow: 0 0 20px rgba(245, 158, 11, 0.3); }
    50% { box-shadow: 0 0 35px rgba(245, 158, 11, 0.6); }
}

.hero-banner-container {
    position: relative;
    min-height: 88vh;
    background: radial-gradient(circle at 50% 30%, rgba(245, 158, 11, 0.15) 0%, rgba(19, 19, 19, 1) 85%),
                linear-gradient(to bottom, rgba(19, 19, 19, 0.5), rgba(19, 19, 19, 1));
    overflow: hidden;
}

.hero-carousel-item {
    height: 88vh;
    min-height: 580px;
}

.hero-carousel-item img {
    object-fit: cover;
    height: 100%;
    width: 100%;
    filter: brightness(0.42) contrast(1.1);
}

.hero-overlay-content {
    position: absolute;
    inset: 0;
    z-index: 10;
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
    text-align: center;
    background: linear-gradient(180deg, rgba(19, 19, 19, 0.4) 0%, rgba(19, 19, 19, 0.85) 100%);
}

.glow-title {
    color: #ffd782;
    text-shadow: 0 0 25px rgba(255, 215, 130, 0.45), 0 0 50px rgba(245, 158, 11, 0.25);
    letter-spacing: 0.08em;
}

.glass-card-hover {
    background: rgba(30, 29, 29, 0.75);
    backdrop-filter: blur(16px);
    -webkit-backdrop-filter: blur(16px);
    border: 1px solid rgba(255, 255, 255, 0.08);
    transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1);
}

.glass-card-hover:hover {
    transform: translateY(-6px);
    border-color: rgba(255, 215, 130, 0.4);
    box-shadow: 0 15px 35px rgba(0, 0, 0, 0.7), 0 0 25px rgba(255, 215, 130, 0.15);
}

.btn-cta-gold {
    background: linear-gradient(135deg, #ffd782 0%, #f59e0b 100%);
    color: #111113;
    font-weight: 700;
    border: none;
    padding: 14px 32px;
    border-radius: 50px;
    letter-spacing: 0.05em;
    box-shadow: 0 8px 25px rgba(245, 158, 11, 0.35);
    transition: all 0.35s ease;
}

.btn-cta-gold:hover {
    background: linear-gradient(135deg, #fff2d1 0%, #fbbf24 100%);
    color: #000000;
    transform: translateY(-2px) scale(1.03);
    box-shadow: 0 12px 35px rgba(245, 158, 11, 0.55);
}

.btn-cta-outline {
    background: rgba(19, 19, 19, 0.6);
    border: 2px solid rgba(255, 215, 130, 0.6);
    color: #ffd782;
    padding: 13px 30px;
    border-radius: 50px;
    backdrop-filter: blur(10px);
    transition: all 0.35s ease;
}

.btn-cta-outline:hover {
    background: rgba(255, 215, 130, 0.15);
    border-color: #ffd782;
    color: #ffffff;
    transform: translateY(-2px);
    box-shadow: 0 0 20px rgba(255, 215, 130, 0.3);
}

.gallery-grid-img {
    aspect-ratio: 1 / 1;
    object-fit: cover;
    transition: transform 0.5s cubic-bezier(0.16, 1, 0.3, 1), filter 0.3s;
    cursor: pointer;
}

.gallery-grid-item {
    overflow: hidden;
    position: relative;
    border-radius: 12px;
    border: 1px solid rgba(255, 255, 255, 0.08);
}

.gallery-grid-item:hover .gallery-grid-img {
    transform: scale(1.08);
    filter: brightness(1.1);
}

.feature-icon-badge {
    width: 64px;
    height: 64px;
    border-radius: 18px;
    background: rgba(255, 215, 130, 0.1);
    border: 1px solid rgba(255, 215, 130, 0.3);
    display: flex;
    align-items: center;
    justify-content: center;
    color: #ffd782;
    margin-bottom: 20px;
}
</style>

<!-- 1. HERO BANNER SECTION (แบนเนอร์ภาพสไลด์ตระการตา) -->
<section class="hero-banner-container position-relative">
    <div id="heroCarousel" class="carousel slide carousel-fade h-100" data-bs-ride="carousel" data-bs-interval="4500">
        <div class="carousel-indicators mb-4 z-20">
            <button type="button" data-bs-target="#heroCarousel" data-bs-slide-to="0" class="active" aria-current="true"></button>
            <button type="button" data-bs-target="#heroCarousel" data-bs-slide-to="1"></button>
            <button type="button" data-bs-target="#heroCarousel" data-bs-slide-to="2"></button>
            <button type="button" data-bs-target="#heroCarousel" data-bs-slide-to="3"></button>
            <button type="button" data-bs-target="#heroCarousel" data-bs-slide-to="4"></button>
            <button type="button" data-bs-target="#heroCarousel" data-bs-slide-to="5"></button>
            <button type="button" data-bs-target="#heroCarousel" data-bs-slide-to="6"></button>
        </div>
        
        <div class="carousel-inner h-100">
            <!-- Slide 1 (First Featured Image) -->
            <div class="carousel-item hero-carousel-item active">
                <img src="images/hero/735563412_122276495840129427_6246480903433139955_n.jpg" alt="CHIT HOLE CNX Featured 1">
            </div>
            <!-- Slide 2 -->
            <div class="carousel-item hero-carousel-item">
                <img src="images/hero/777851750_122281871930129427_4833326693886290042_n.jpg" alt="CHIT HOLE CNX Hero 2">
            </div>
            <!-- Slide 3 -->
            <div class="carousel-item hero-carousel-item">
                <img src="images/hero/752741095_122278340024129427_3159467724997469637_n.jpg" alt="CHIT HOLE CNX Hero 3">
            </div>
            <!-- Slide 4 -->
            <div class="carousel-item hero-carousel-item">
                <img src="images/hero/749356007_122278339724129427_2108767678100899836_n.jpg" alt="CHIT HOLE CNX Hero 4">
            </div>
            <!-- Slide 5 -->
            <div class="carousel-item hero-carousel-item">
                <img src="images/hero/743496966_122278339736129427_6962547489215919661_n.jpg" alt="CHIT HOLE CNX Hero 5">
            </div>
            <!-- Slide 6 -->
            <div class="carousel-item hero-carousel-item">
                <img src="images/hero/481219434_122206980932129427_2589329957957079331_n.jpg" alt="CHIT HOLE CNX Hero 6">
            </div>
            <!-- Slide 7 -->
            <div class="carousel-item hero-carousel-item">
                <img src="images/hero/473542511_122198553512129427_8339791701034145855_n.jpg" alt="CHIT HOLE CNX Hero 7">
            </div>
        </div>

        <!-- Hero Content Overlay -->
        <div class="hero-overlay-content px-3 px-md-5">
            <div class="container">
                <div class="row justify-content-center">
                    <div class="col-lg-10 col-xl-9">


                        <!-- Main Title -->
                        <h1 class="display-3 font-anton glow-title text-uppercase mb-3 leading-tight">
                            CHIT HOLE CNX
                        </h1>

                        <!-- Subtitle -->
                        <p class="fs-5 text-light text-opacity-90 font-sans max-w-2xl mx-auto mb-4 font-normal" style="line-height: 1.7;">
                            <?php echo t(
                                "Experience Chiang Mai's premier craft beer destination. Enjoy fresh draft taps, live acoustic lineup every night, and non-stop vibrant taproom energy under the nocturnal lights.", 
                                "สัมผัสวัฒนธรรมคราฟต์เบียร์ไทยที่ไม่ยอมประนีประนอม ณ โรงเบียร์ชิตโฮลเชียงใหม่ ดนตรีสดฟังเพลิน บรรยากาศสุดคึกคัก และแท็ปเบียร์สดหมุนเวียนที่ดีที่สุดในสันทราย"
                            ); ?>
                        </p>

                        <!-- CTA Action Buttons -->
                        <div class="d-flex flex-wrap justify-content-center gap-3 mt-2">
                            <a href="reservation" class="btn btn-cta-gold font-anton text-uppercase d-inline-flex align-items-center gap-2">
                                <span class="material-symbols-outlined fs-5">calendar_month</span>
                                <span><?php echo t("Book A Table Now", "จองโต๊ะล่วงหน้า"); ?></span>
                            </a>
                            <a href="tap-list" class="btn btn-cta-outline font-anton text-uppercase d-inline-flex align-items-center gap-2">
                                <span class="material-symbols-outlined fs-5">sports_bar</span>
                                <span><?php echo t("Explore Tap List", "ดูรายการเครื่องดื่ม"); ?></span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- 2. FEATURES & HIGHLIGHTS SECTION (จุดเด่น 4 ประการ) -->
<section class="py-5 bg-black position-relative border-bottom border-secondary border-opacity-10">
    <div class="container px-4 px-lg-5 py-4">
        <div class="row g-4 justify-content-center">
            <!-- Feature 1 -->
            <div class="col-md-4">
                <div class="glass-card-hover p-4 rounded-4 h-100 d-flex flex-column align-items-center text-center">
                    <div class="feature-icon-badge">
                        <span class="material-symbols-outlined fs-2">local_bar</span>
                    </div>
                    <h4 class="font-anton text-warning text-uppercase mb-2 fs-5"><?php echo t("Fresh Draft Taps", "แท็ปเบียร์สดหมุนเวียน"); ?></h4>
                    <p class="text-secondary small font-sans m-0">
                        <?php echo t("Fresh craft draft beers on tap, rotated weekly with unique local Thai recipes.", "เสิร์ฟคราฟต์เบียร์สดใหม่จากแท็ป หมุนเวียนสูตรและรสชาติใหม่ๆ ให้ลิ้มลองสัปดาห์ต่อสัปดาห์"); ?>
                    </p>
                </div>
            </div>

            <!-- Feature 2 -->
            <div class="col-md-4">
                <div class="glass-card-hover p-4 rounded-4 h-100 d-flex flex-column align-items-center text-center">
                    <div class="feature-icon-badge">
                        <span class="material-symbols-outlined fs-2">music_note</span>
                    </div>
                    <h4 class="font-anton text-warning text-uppercase mb-2 fs-5"><?php echo t("Live Music Nightly", "ดนตรีสดบรรยากาศดีทุกวัน"); ?></h4>
                    <p class="text-secondary small font-sans m-0">
                        <?php echo t("Acoustic bands & live vocalists performing every evening to amp up your night.", "เพลิดเพลินกับวงดนตรีสดและนักร้องคุณภาพ เคล้าบรรยากาศยามราตรีทุกค่ำคืน"); ?>
                    </p>
                </div>
            </div>

            <!-- Feature 3 -->
            <div class="col-md-4">
                <div class="glass-card-hover p-4 rounded-4 h-100 d-flex flex-column align-items-center text-center">
                    <div class="feature-icon-badge">
                        <span class="material-symbols-outlined fs-2">chair</span>
                    </div>
                    <h4 class="font-anton text-warning text-uppercase mb-2 fs-5"><?php echo t("Indoor & Outdoor Zones", "โซนแอร์ & โซนรับลมชิล"); ?></h4>
                    <p class="text-secondary small font-sans m-0">
                        <?php echo t("Choose indoor AC tables, window sides, front stage, or open-air garden breeze.", "เลือกที่นั่งได้ตามสไตล์ ทั้งห้องแอร์เย็นฉ่ำ หน้าบาร์ หน้าเวที หรือโซนรับลมธรรมชาติ"); ?>
                    </p>
                </div>
            </div>
        </div>
    </div>
</section>





<!-- 5. ATMOSPHERE & GALLERY SHOWCASE (ภาพบรรยากาศร้านสุดชิล - รวมรูปถ่ายทั้งหมดที่มี!) -->
<?php if (!empty($gallery_images)): ?>
<section class="py-5 position-relative" style="background-color: #141313;">
    <div class="container px-4 px-lg-5 py-4">
        <div class="text-center mb-5">
            <span class="text-warning font-mono text-uppercase tracking-widest small fw-bold">[ GALLERY & VIBES ]</span>
            <h2 class="font-anton text-uppercase text-light display-5 m-0 mt-1"><?php echo t("Live Music & Customer Moments", "ภาพบรรยากาศการแสดงดนตรีสด ณ ชิตโฮลเชียงใหม่"); ?></h2>
            <p class="text-secondary small max-w-xl mx-auto mt-2">
                <?php echo t("Explore the energetic night vibe, acoustic music moments, and welcoming spaces at CHIT HOLE CNX.", "รวมภาพถ่ายบรรยากาศ วงดนตรีสด และความสนุกสนานของลูกค้าที่มาเยือนโรงเบียร์ชิตโฮลเชียงใหม่"); ?>
            </p>
        </div>

        <div class="row g-3">
            <?php 
            // Display top 12 curated atmosphere photos in grid
            $display_photos = array_slice($gallery_images, 0, 12);
            foreach ($display_photos as $index => $photo_path): 
            ?>
                <div class="col-6 col-md-4 col-lg-3">
                    <div class="gallery-grid-item shadow-lg" onclick="openPhotoModal('<?php echo htmlspecialchars($photo_path); ?>')">
                        <img src="<?php echo htmlspecialchars($photo_path); ?>" alt="CHIT HOLE Atmosphere photo" class="w-100 gallery-grid-img">
                        <div class="position-absolute bottom-0 inset-x-0 p-2 text-center bg-black bg-opacity-60 backdrop-blur-sm opacity-0 hover-opacity-100 transition-all">
                            <span class="text-warning font-mono small d-flex align-items-center justify-content-center gap-1">
                                <span class="material-symbols-outlined fs-6">zoom_in</span>
                                <span><?php echo t("View Photo", "ขยายรูปภาพ"); ?></span>
                            </span>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>



<!-- 7. CALL TO ACTION & LOCATION BANNER SECTION (แผนที่และข้อมูลติดต่อสาขาสันทราย) -->
<section class="py-5 position-relative" style="background: radial-gradient(circle at 50% 50%, rgba(245, 158, 11, 0.12) 0%, rgba(10, 10, 10, 1) 90%);">
    <div class="container px-4 px-lg-5 py-5 text-center">
        <div class="max-w-3xl mx-auto">
            <div class="rounded-circle overflow-hidden border border-warning shadow-lg bg-dark mx-auto mb-4 d-flex align-items-center justify-content-center" style="width: 80px; height: 80px; box-shadow: 0 0 30px rgba(255, 215, 130, 0.3);">
                <img src="images/logo/755221157_122278964708129427_8713818424547983601_n.jpg" alt="CHIT logo" style="width: 100%; height: 100%; object-fit: cover;">
            </div>
            
            <h2 class="display-5 font-anton text-warning text-uppercase mb-3"><?php echo t("Ready to Reserve Your Spot?", "พร้อมมาสัมผัสบรรยากาศชิตโฮลแล้วหรือยัง?"); ?></h2>
            <p class="fs-6 text-secondary font-sans mb-4 max-w-xl mx-auto">
                <?php echo t("Join us tonight for craft beers, live acoustic tunes, and great conversations with friends.", "สำรองที่นั่งล่วงหน้าได้ง่ายๆ ผ่านระบบจองโต๊ะออนไลน์ เลือกระบุโซนที่ต้องการได้ทันที"); ?>
            </p>
            
            <div class="d-flex flex-wrap justify-content-center gap-3">
                <a href="reservation" class="btn btn-cta-gold font-anton text-uppercase px-5 py-3 fs-6">
                    <?php echo t("Reserve A Table Online", "จองโต๊ะออนไลน์ทันที"); ?>
                </a>
                <a href="https://maps.app.goo.gl/UKdfCxycHEVRGcST6" target="_blank" class="btn btn-cta-outline font-anton text-uppercase px-4 py-3 fs-6">
                    <span class="material-symbols-outlined fs-5 align-middle me-1">location_on</span>
                    <span><?php echo t("Get Directions (Google Maps)", "ดูแผนที่สาขาสันทราย"); ?></span>
                </a>
            </div>
        </div>
    </div>
</section>

<!-- Photo Lightbox Modal -->
<div class="modal fade" id="photoModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content bg-dark border-secondary border-opacity-50">
            <div class="modal-body p-1 text-center position-relative">
                <button type="button" class="btn-close btn-close-white position-absolute top-0 end-0 m-3 z-3 bg-black rounded-circle p-2" data-bs-dismiss="modal" aria-label="Close"></button>
                <img id="modalImageDisplay" src="" alt="Full Resolution Atmosphere Photo" class="w-100 rounded" style="max-height: 80vh; object-fit: contain;">
            </div>
        </div>
    </div>
</div>

<script>
function openPhotoModal(imgUrl) {
    document.getElementById('modalImageDisplay').src = imgUrl;
    var photoModal = new bootstrap.Modal(document.getElementById('photoModal'));
    photoModal.show();
}
</script>

<?php require_once 'footer.php'; ?>
