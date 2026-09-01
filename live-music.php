<?php
require_once 'db.php';

// Fetch all live music schedules
try {
    $stmt = $pdo->query("SELECT * FROM music ORDER BY 
        CASE show_day
            WHEN 'Mon' THEN 1
            WHEN 'Tue' THEN 2
            WHEN 'Wed' THEN 3
            WHEN 'Thu' THEN 4
            WHEN 'Fri' THEN 5
            WHEN 'Sat' THEN 6
            WHEN 'Sun' THEN 7
        END, show_time");
    $music_events = $stmt->fetchAll();
} catch (Exception $e) {
    $music_events = [];
}

// Group events by day for easy front-end selection
$events_by_day = [
    'Mon' => [], 'Tue' => [], 'Wed' => [], 'Thu' => [], 'Fri' => [], 'Sat' => [], 'Sun' => []
];
foreach ($music_events as $event) {
    $events_by_day[$event['show_day']][] = $event;
}

// Fetch dynamic gallery photos directly from /images/live-music/
$gallery_dir = __DIR__ . '/images/live-music';
$gallery_images = [];

if (is_dir($gallery_dir)) {
    $files = scandir($gallery_dir);
    foreach ($files as $file) {
        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
            $gallery_images[] = [
                'src' => 'images/live-music/' . $file
            ];
        }
    }
}

// Days helper for tabs
$days_list = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
$current_day = date('D'); // e.g. Mon, Tue, etc.
if (!in_array($current_day, $days_list)) {
    $current_day = 'Fri'; // Fallback default selected tab
}

// AJAX Request to fetch live music timetable HTML snippet
if (isset($_GET['action']) && $_GET['action'] === 'get_music_html') {
    foreach ($days_list as $d) {
        ?>
        <div id="schedule-day-<?php echo $d; ?>" class="schedule-tab-content" style="display: none;">
            <?php if (empty($events_by_day[$d])): ?>
                <div class="text-center font-mono py-5 text-secondary border border-dashed border-secondary border-opacity-25 rounded">
                    <?php echo t("No performances scheduled for this day.", "ไม่มีวงดนตรีสดขึ้นแสดงในวันนี้"); ?>
                </div>
            <?php else: ?>
                <div class="d-flex flex-column gap-3">
                    <?php foreach ($events_by_day[$d] as $event): ?>
                        <div class="d-flex align-items-center justify-content-between p-3 bg-black bg-opacity-40 border border-secondary border-opacity-10 rounded">
                            <div class="d-flex flex-column">
                                <div class="d-flex align-items-center gap-2 mb-1">
                                    <span class="font-anton text-warning text-uppercase fs-5"><?php echo htmlspecialchars($event['artist']); ?></span>
                                </div>
                                <span class="text-secondary small font-sans mt-0.5"><?php echo htmlspecialchars($event['description']); ?></span>
                            </div>
                            <span class="font-anton text-light fs-5 tracking-wide bg-dark bg-opacity-70 border border-secondary border-opacity-20 px-3 py-1.5" style="border-radius: 4px;">
                                <?php echo htmlspecialchars($event['show_time']); ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }
    exit;
}

require_once 'header.php';
?>

<style>
    .music-hero {
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
    .music-hero-bg {
        position: absolute;
        inset: 0;
        opacity: 0.4;
        background-image: url('images/live-music/750355503_122278340186129427_706892172589075451_n.jpg');
        background-size: cover;
        background-position: center;
        mix-blend-mode: luminosity;
        z-index: 0;
    }
    .music-hero-overlay {
        position: absolute;
        inset: 0;
        background: linear-gradient(to top, #131313, rgba(19, 19, 19, 0.6) 50%, transparent);
        z-index: 1;
    }
    .day-tab-btn {
        font-family: 'Rockwell', 'Pridi', 'Arvo', serif;
        text-transform: uppercase;
        font-size: 14px;
        letter-spacing: 0.05em;
        border-radius: 4px;
        transition: all 0.2s ease-in-out;
        border: 1px solid rgba(255, 255, 255, 0.05);
        background-color: #1b1b1b;
        color: #e5e2e1;
    }
    .day-tab-btn.active {
        background-color: #ffd782;
        color: #3f2e00;
        border-color: #ffd782;
        box-shadow: 0 0 12px rgba(255, 215, 130, 0.3);
    }
    .gallery-img-container {
        position: relative;
        aspect-ratio: 16 / 9;
        overflow: hidden;
        border: 1px solid rgba(255, 255, 255, 0.08);
        background-color: #121414;
        border-radius: 12px;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
        transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1);
        cursor: pointer;
    }
    .gallery-img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        transition: transform 0.8s cubic-bezier(0.16, 1, 0.3, 1), filter 0.5s ease;
        filter: brightness(0.8) contrast(1.05);
    }
    .gallery-img-container:hover {
        border-color: rgba(255, 215, 130, 0.35);
        box-shadow: 0 8px 25px rgba(255, 215, 130, 0.12);
        transform: translateY(-4px);
    }
    .gallery-img-container:hover .gallery-img {
        transform: scale(1.04);
        filter: brightness(1) contrast(1.05);
    }
    
    /* Lightbox Modal */
    .lightbox-modal {
        display: none;
        position: fixed;
        z-index: 9999;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0, 0, 0, 0.92);
        backdrop-filter: blur(10px);
        align-items: center;
        justify-content: center;
        opacity: 0;
        transition: opacity 0.3s ease;
    }
    .lightbox-modal.show {
        opacity: 1;
    }
    .lightbox-content {
        max-width: 90%;
        max-height: 85vh;
        border-radius: 12px;
        box-shadow: 0 0 40px rgba(0, 0, 0, 0.9), 0 0 1px rgba(255, 255, 255, 0.2);
        transform: scale(0.95);
        transition: transform 0.3s cubic-bezier(0.16, 1, 0.3, 1);
    }
    .lightbox-modal.show .lightbox-content {
        transform: scale(1);
    }
    .lightbox-close {
        position: absolute;
        top: 25px;
        right: 35px;
        color: rgba(255, 255, 255, 0.6);
        font-size: 40px;
        font-weight: 300;
        transition: all 0.2s ease;
        cursor: pointer;
        z-index: 10000;
        line-height: 1;
    }
    .lightbox-close:hover {
        color: #ffd782;
        transform: scale(1.1);
    }
</style>

<!-- Cover Banner Section -->
<section class="music-hero">
    <div class="music-hero-bg"></div>
    <div class="music-hero-overlay"></div>
    
    <div class="container px-4 px-lg-5" style="position: relative; z-index: 2;">
        <div class="d-flex align-items-center gap-2 mb-3">
            <span class="badge bg-warning rounded-circle animate-pulse" style="width: 10px; height: 10px; padding:0; display:inline-block; box-shadow: 0 0 10px rgba(255,215,130,0.8);"></span>
            <span class="font-mono text-warning text-uppercase tracking-widest" style="font-size: 11px;">
                <?php echo t("LIVE SESSIONS TIMETABLE", "ตารางการแสดงดนตรีสด"); ?>
            </span>
        </div>
        <h1 class="font-anton text-light text-uppercase tracking-wide display-4 mb-3 lh-1">
            <?php echo t("Stage Lineup", "ตารางดนตรีสด"); ?>
        </h1>
        <p class="text-secondary fs-5 max-width-md m-0">
            <?php echo t(
              "Immerse yourself in our eclectic live music lineup. Handpicked every night, from chill acoustic sets to explosive rock performances ready to blow the roof off.",
              "ปล่อยอารมณ์ไปกับไลน์อัปดนตรีสดหลากสไตล์ คืนนี้เราคัดมาให้เน้นๆ ตั้งแต่อคูสติกสุดชิลล์ ไปจนถึงวงร็อกที่พร้อมจะระเบิดความมันส์ให้สุดเหวี่ยง"
            ); ?>
        </p>
    </div>
</section>

<!-- Main Schedule Selector Grid -->
<div class="container px-4 px-lg-5 py-5">
    <div class="row g-5">
        
        <!-- Interactive Timetable Day Tabs -->
        <div class="col-lg-12">
            <div class="glass-card p-4 p-md-5">
                <h3 class="font-anton text-warning text-uppercase tracking-wider mb-4"><?php echo t("Weekly Gigs Timetable", "ตารางแสดงรอบดนตรีสด"); ?></h3>
                
                <!-- Day Select buttons -->
                <div class="d-flex flex-wrap gap-2 mb-4">
                    <?php foreach ($days_list as $d): ?>
                        <button 
                            id="btn-day-<?php echo $d; ?>"
                            class="btn day-tab-btn px-3 py-2 <?php echo $d === $current_day ? 'active' : ''; ?>"
                            onclick="selectActiveDay('<?php echo $d; ?>')"
                        >
                            <?php 
                            if ($d === 'Mon') echo t("MON", "วันจันทร์");
                            elseif ($d === 'Tue') echo t("TUE", "วันอังคาร");
                            elseif ($d === 'Wed') echo t("WED", "วันพุธ");
                            elseif ($d === 'Thu') echo t("THU", "วันพฤหัสบดี");
                            elseif ($d === 'Fri') echo t("FRI", "วันศุกร์");
                            elseif ($d === 'Sat') echo t("SAT", "วันเสาร์");
                            else echo t("SUN", "วันอาทิตย์");
                            ?>
                        </button>
                    <?php endforeach; ?>
                </div>

                <!-- Timetable listings for each day -->
                <div class="position-relative mt-2" id="timetable-container">
                    <?php foreach ($days_list as $d): ?>
                        <div id="schedule-day-<?php echo $d; ?>" class="schedule-tab-content" style="display: <?php echo $d === $current_day ? 'block' : 'none'; ?>;">
                            <?php if (empty($events_by_day[$d])): ?>
                                <div class="text-center font-mono py-5 text-secondary border border-dashed border-secondary border-opacity-25 rounded">
                                    <?php echo t("No performances scheduled for this day.", "ไม่มีวงดนตรีสดขึ้นแสดงในวันนี้"); ?>
                                </div>
                            <?php else: ?>
                                <div class="d-flex flex-column gap-3">
                                    <?php foreach ($events_by_day[$d] as $event): ?>
                                        <div class="d-flex align-items-center justify-content-between p-3 bg-black bg-opacity-40 border border-secondary border-opacity-10 rounded">
                                            <div class="d-flex flex-column">
                                                <div class="d-flex align-items-center gap-2 mb-1">
                                                    <span class="font-anton text-warning text-uppercase fs-5"><?php echo htmlspecialchars($event['artist']); ?></span>
                                                </div>
                                                <span class="text-secondary small font-sans mt-0.5"><?php echo htmlspecialchars($event['description']); ?></span>
                                            </div>
                                            <span class="font-anton text-light fs-5 tracking-wide bg-dark bg-opacity-70 border border-secondary border-opacity-20 px-3 py-1.5" style="border-radius: 4px;">
                                                <?php echo htmlspecialchars($event['show_time']); ?>
                                            </span>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="mt-4 pt-3 border-top border-secondary border-opacity-10 d-flex justify-content-between align-items-center">
                    <span class="text-secondary small"><?php echo t("Want to request a table for tonight?", "ต้องการจองพื้นที่ที่นั่งในค่ำคืนนี้?"); ?></span>
                    <a href="reservation.php" class="btn btn-custom-gold py-2 px-3 font-anton text-uppercase" style="font-size: 12px;"><?php echo t("Book Table Now", "จองโต๊ะเลย"); ?></a>
                </div>
            </div>
        </div>

        <!-- Stage Atmosphere Gallery (Full Width Below) -->
        <div class="col-lg-12">
            <div class="glass-card p-4 p-md-5">
                <h3 class="font-anton text-warning text-uppercase tracking-wider mb-4"><?php echo t("Stage Atmosphere", "ภาพบรรยากาศเวที"); ?></h3>
                
                <div class="row g-4">
                    <?php if (empty($gallery_images)): ?>
                        <div class="col-12 text-center text-secondary small font-mono py-4">
                            <?php echo t("No photos in atmosphere folder.", "ยังไม่มีภาพบรรยากาศอัปโหลดอยู่ในระบบ"); ?>
                        </div>
                    <?php else: ?>
                        <?php foreach ($gallery_images as $img): ?>
                            <div class="col-6 col-md-4 col-lg-3">
                                <div class="gallery-img-container" onclick="openLightbox('<?php echo htmlspecialchars($img['src']); ?>')">
                                    <img src="<?php echo htmlspecialchars($img['src']); ?>" alt="Atmosphere" class="gallery-img">
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

    </div>
</div>

<!-- Lightbox Modal -->
<div id="gallery-lightbox" class="lightbox-modal" onclick="closeLightbox(event)">
    <span class="lightbox-close" onclick="closeLightbox(event)">&times;</span>
    <img class="lightbox-content" id="lightbox-img" alt="Enlarged Atmosphere">
</div>

<script>
    // Selected Timetable Day Handler
    function selectActiveDay(day) {
        // Toggle Day Selector Buttons Active Style
        const buttons = document.querySelectorAll('.day-tab-btn');
        buttons.forEach(btn => {
            if (btn.id === `btn-day-${day}`) {
                btn.classList.add('active');
            } else {
                btn.classList.remove('active');
            }
        });

        // Hide/Show Day Timetable content blocks
        const dayContents = document.querySelectorAll('.schedule-tab-content');
        dayContents.forEach(content => {
            if (content.id === `schedule-day-${day}`) {
                content.style.display = 'block';
            } else {
                content.style.display = 'none';
            }
        });
    }

    // Lightbox modal logic
    function openLightbox(src) {
        const lightbox = document.getElementById('gallery-lightbox');
        const img = document.getElementById('lightbox-img');
        if (lightbox && img) {
            img.src = src;
            lightbox.style.display = 'flex';
            // Trigger reflow to apply CSS transitions
            lightbox.offsetHeight;
            lightbox.classList.add('show');
            document.body.style.overflow = 'hidden'; // Disable background scrolling
        }
    }

    // Close lightbox
    function closeLightbox(event) {
        if (event.target.id === 'gallery-lightbox' || event.target.classList.contains('lightbox-close')) {
            const lightbox = document.getElementById('gallery-lightbox');
            if (lightbox) {
                lightbox.classList.remove('show');
                setTimeout(() => {
                    lightbox.style.display = 'none';
                    document.body.style.overflow = ''; // Restore scrolling
                }, 300);
            }
        }
    }

    // Close on Escape key press
    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape') {
            const lightbox = document.getElementById('gallery-lightbox');
            if (lightbox && lightbox.classList.contains('show')) {
                lightbox.classList.remove('show');
                setTimeout(() => {
                    lightbox.style.display = 'none';
                    document.body.style.overflow = '';
                }, 300);
            }
        }
    });

    // Live Gigs Timetable Polling (every 5 seconds)
    function updateLiveTimetable() {
        const activeDayBtn = document.querySelector('.day-tab-btn.active');
        if (!activeDayBtn) return;
        const activeDay = activeDayBtn.id.replace('btn-day-', '');

        fetch('live-music.php?action=get_music_html')
            .then(res => res.text())
            .then(html => {
                const container = document.getElementById('timetable-container');
                if (container) {
                    const normalizedHtml = html.trim().replace(/\s+/g, ' ');
                    const currentNormalizedHtml = container.innerHTML.trim().replace(/\s+/g, ' ');
                    if (normalizedHtml !== currentNormalizedHtml) {
                        container.innerHTML = html;
                    }
                    
                    // Restore visibility of active day
                    const dayContents = container.querySelectorAll('.schedule-tab-content');
                    dayContents.forEach(content => {
                        const day = content.id.replace('schedule-day-', '');
                        if (day === activeDay) {
                            content.style.display = 'block';
                        } else {
                            content.style.display = 'none';
                        }
                    });
                }
            })
            .catch(err => console.error("Error polling live timetable:", err));
    }

    window.addEventListener('load', () => {
        setInterval(updateLiveTimetable, 5000);
    });
</script>

<?php require_once 'footer.php'; ?>
