<?php
session_start();

if (!empty($_SESSION['id'])) {
    header('Location: pages/dashboard.php');
    exit;
}

date_default_timezone_set('Asia/Bangkok');

require_once __DIR__ . '/config/db.php';

$basePath = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/staff-main/index.php')), '/');
$basePath = $basePath === '' ? '' : $basePath;
$today = date('Y-m-d');
$monthStart = date('Y-m-01');
$monthEnd = date('Y-m-t');

function homepage_query_value(PDO $conn, string $sql, array $params = [], $fallback = 0)
{
    try {
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        $value = $stmt->fetchColumn();
        return $value === false ? $fallback : $value;
    } catch (Throwable $e) {
        return $fallback;
    }
}

function homepage_thai_date(string $date): string
{
    $months = [
        1 => 'มกราคม',
        2 => 'กุมภาพันธ์',
        3 => 'มีนาคม',
        4 => 'เมษายน',
        5 => 'พฤษภาคม',
        6 => 'มิถุนายน',
        7 => 'กรกฎาคม',
        8 => 'สิงหาคม',
        9 => 'กันยายน',
        10 => 'ตุลาคม',
        11 => 'พฤศจิกายน',
        12 => 'ธันวาคม',
    ];

    $timestamp = strtotime($date);
    return (int) date('j', $timestamp) . ' ' . $months[(int) date('n', $timestamp)] . ' ' . ((int) date('Y', $timestamp) + 543);
}

$todayShiftCount = (int) homepage_query_value($conn, 'SELECT COUNT(*) FROM time_logs WHERE work_date = ?', [$today]);
$todayStaffCount = (int) homepage_query_value($conn, 'SELECT COUNT(DISTINCT user_id) FROM time_logs WHERE work_date = ?', [$today]);
$pendingReviewCount = (int) homepage_query_value($conn, "SELECT COUNT(*) FROM time_logs WHERE checked_at IS NULL AND (status IS NULL OR status <> 'rejected')");
$monthHours = (float) homepage_query_value($conn, 'SELECT COALESCE(SUM(work_hours), 0) FROM time_logs WHERE work_date BETWEEN ? AND ?', [$monthStart, $monthEnd], 0);
$activeStaffCount = (int) homepage_query_value($conn, 'SELECT COUNT(*) FROM users WHERE COALESCE(is_active, 1) = 1');
$departmentCount = (int) homepage_query_value($conn, 'SELECT COUNT(*) FROM departments');
$coveragePercent = $activeStaffCount > 0 ? min(100, (int) round(($todayStaffCount / $activeStaffCount) * 100)) : 0;

$overviewCards = [
    ['label' => 'เวรวันนี้', 'value' => number_format($todayShiftCount), 'note' => 'รายการประจำวันที่บันทึกไว้', 'icon' => 'bi-calendar2-check'],
    ['label' => 'เจ้าหน้าที่วันนี้', 'value' => number_format($todayStaffCount), 'note' => 'คนที่มีเวรในวันนี้', 'icon' => 'bi-people'],
    ['label' => 'รอตรวจสอบ', 'value' => number_format($pendingReviewCount), 'note' => 'รายการที่ยังไม่ผ่านการตรวจ', 'icon' => 'bi-patch-question'],
    ['label' => 'ชั่วโมงสะสมเดือนนี้', 'value' => number_format($monthHours, 2), 'note' => 'ชั่วโมงรวมของเดือนปัจจุบัน', 'icon' => 'bi-activity'],
];

$featureCards = [
    ['title' => 'ลงเวลาเวร', 'text' => 'บันทึกเข้าออกเวร', 'icon' => 'bi-clock-history'],
    ['title' => 'ตรวจสอบรายการ', 'text' => 'จัดการคิวรอตรวจ', 'icon' => 'bi-shield-check'],
    ['title' => 'รายงานและส่งออก', 'text' => 'พิมพ์และส่งออกเอกสาร', 'icon' => 'bi-file-earmark-arrow-down'],
    ['title' => 'สิทธิ์ตามบทบาท', 'text' => 'แสดงข้อมูลตามหน้าที่', 'icon' => 'bi-person-lock'],
];

$miniCards = [
    ['label' => 'บุคลากรที่ใช้งาน', 'value' => number_format($activeStaffCount), 'note' => 'บัญชีพร้อมใช้'],
    ['label' => 'แผนกในระบบ', 'value' => number_format($departmentCount), 'note' => 'หน่วยงานที่บันทึก'],
    ['label' => 'ครอบคลุมวันนี้', 'value' => number_format($coveragePercent) . '%', 'note' => 'เทียบกับบุคลากรทั้งหมด'],
];

?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>ระบบลงเวลาเวร | โรงพยาบาลหนองพอก</title>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@400;500;600;700&family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="<?= htmlspecialchars($basePath) ?>/assets/css/index-tailwind.css">
</head>
<body class="home-shell">
    <main class="relative isolate min-h-screen py-4 sm:py-5">
        <div class="ambient-orb left-[-10rem] top-[-10rem] h-[30rem] w-[30rem] bg-hospital-aqua/40"></div>
        <div class="ambient-orb right-[-9rem] top-0 h-[34rem] w-[34rem] bg-hospital-mint/80"></div>
        <div class="ambient-orb bottom-[-11rem] left-1/2 h-[30rem] w-[30rem] -translate-x-1/2 bg-cyan-100/75"></div>

        <header class="home-frame relative z-20">
            <div class="glass-nav flex items-center justify-between gap-3 px-4 py-3">
                <a href="<?= htmlspecialchars($basePath) ?>/index.php" class="group flex min-w-0 items-center gap-3 text-hospital-ink no-underline">
                    <span class="grid h-11 w-11 shrink-0 place-items-center rounded-2xl bg-white p-2 shadow-soft transition group-hover:-translate-y-0.5">
                        <img src="<?= htmlspecialchars($basePath) ?>/LOGO/nongphok_logo.png" alt="โลโก้โรงพยาบาลหนองพอก" class="h-full w-full object-contain">
                    </span>
                    <span class="grid leading-tight">
                        <span class="font-prompt text-base font-bold">StaffMain</span>
                        <span class="hidden text-xs font-semibold text-hospital-muted sm:block">โรงพยาบาลหนองพอก</span>
                    </span>
                </a>

                <nav class="home-nav-menu" aria-label="เมนูหน้าแรก">
                    <a href="#overview-canvas" class="home-nav-link is-active">ภาพรวม</a>
                    <a href="#workflow-board" class="home-nav-link">งานหลัก</a>
                    <a href="#start-now" class="home-nav-link">เริ่มใช้งาน</a>
                </nav>

                <div class="flex shrink-0 items-center gap-2">
                    <a class="nav-action nav-action-outline" href="<?= htmlspecialchars($basePath) ?>/auth/register.php">
                        <i class="bi bi-person-plus"></i>
                        สมัครใช้งาน
                    </a>
                    <a class="nav-action nav-action-primary" href="<?= htmlspecialchars($basePath) ?>/auth/login.php">
                        <i class="bi bi-box-arrow-in-right"></i>
                        เข้าสู่ระบบ
                    </a>
                </div>
            </div>
        </header>

        <div class="home-frame relative z-10 mt-6 sm:mt-7">
            <section id="overview-canvas" class="dashboard-canvas entrance-soft" aria-label="ภาพรวมระบบวันนี้">
                <article class="dashboard-card intro-action-card lg:col-span-4">
                    <div>
                        <span class="hero-kicker">
                            <span class="live-dot" aria-hidden="true"></span>
                            ภาพรวมการใช้งาน
                        </span>
                        <h1 class="mt-4 max-w-sm font-prompt text-2xl font-bold leading-tight tracking-[-0.025em] text-hospital-ink sm:text-3xl">
                            ภาพรวมระบบวันนี้
                        </h1>
                        <p class="mt-3 max-w-md text-sm leading-6 text-hospital-muted">
                            ดูสถานะงานเวร เข้าสู่ระบบ และเริ่มงานประจำวันจากหน้าเดียว
                        </p>
                    </div>

                    <div class="mt-6 grid gap-4">
                        <div class="flex flex-col gap-3 sm:flex-row lg:flex-col xl:flex-row">
                            <a class="overview-cta-primary flex-1" href="<?= htmlspecialchars($basePath) ?>/auth/login.php">
                                เข้าสู่ระบบ
                                <i class="bi bi-arrow-right"></i>
                            </a>
                            <a class="overview-cta-secondary flex-1" href="<?= htmlspecialchars($basePath) ?>/auth/register.php">
                                สมัครใช้งาน
                                <i class="bi bi-person-plus"></i>
                            </a>
                        </div>

                        <div class="flex flex-wrap gap-2">
                            <span class="intro-chip">ลงเวลาเวร</span>
                            <span class="intro-chip">ตรวจสอบรายการ</span>
                            <span class="intro-chip">รายงานพร้อมพิมพ์</span>
                        </div>

                        <div class="rounded-[1.5rem] border border-hospital-teal/10 bg-hospital-mint/70 p-4">
                            <div class="flex items-center justify-between gap-3">
                                <div>
                                    <p class="text-xs font-bold text-hospital-teal">สถานะระบบ</p>
                                    <p class="mt-1 font-prompt text-lg font-bold text-hospital-navy">พร้อมเริ่มงาน</p>
                                </div>
                                <span class="grid h-11 w-11 place-items-center rounded-2xl bg-white text-lg text-hospital-teal shadow-soft">
                                    <i class="bi bi-check2-circle"></i>
                                </span>
                            </div>
                        </div>
                    </div>
                </article>

                <article class="dashboard-card live-overview-card lg:col-span-5" aria-label="เวลาและอุณหภูมิ">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="text-xs font-bold uppercase tracking-[0.18em] text-white/60">Nong Phok Hospital</p>
                            <h2 class="mt-1 font-prompt text-2xl font-bold text-white">ภาพรวมวันนี้</h2>
                        </div>
                        <span class="inline-flex items-center gap-2 rounded-full bg-white/10 px-3 py-1.5 text-xs font-bold text-white/80">
                            <span class="live-dot" aria-hidden="true"></span>
                            Asia/Bangkok
                        </span>
                    </div>

                    <div class="mt-5 grid gap-4 sm:grid-cols-[1.1fr_0.9fr]">
                        <div class="live-sub-card">
                            <div class="flex items-center gap-2 text-sm font-semibold text-white/70">
                                <i class="bi bi-clock clock-icon"></i>
                                เวลาปัจจุบัน
                            </div>
                            <div id="bangkokTime" class="mt-2 font-prompt text-5xl font-bold tracking-[-0.06em] text-white sm:text-6xl">--:--</div>
                            <div id="bangkokDate" class="mt-1 text-sm font-semibold text-white/60"><?= htmlspecialchars(homepage_thai_date($today)) ?></div>
                        </div>

                        <div class="weather-sub-card">
                            <div class="flex items-center gap-2 text-sm font-bold text-hospital-muted">
                                <i id="weatherIcon" class="bi bi-cloud-sun weather-icon text-hospital-teal"></i>
                                อุณหภูมิ
                            </div>
                            <div class="mt-3 flex items-end gap-1 font-prompt text-5xl font-bold text-hospital-ink">
                                <span id="weatherTemp">--</span>
                                <span class="pb-2 text-base text-hospital-muted">°C</span>
                            </div>
                            <div id="weatherStatus" class="mt-1 text-xs font-semibold text-hospital-muted">กำลังโหลดสภาพอากาศ</div>
                        </div>
                    </div>

                    <div class="mt-5 rounded-[1.5rem] border border-white/10 bg-white/10 p-4">
                        <p class="text-xs font-bold uppercase tracking-[0.16em] text-white/50">Location</p>
                        <p class="mt-1 text-sm font-semibold leading-6 text-white/80">โรงพยาบาลหนองพอก อำเภอหนองพอก จังหวัดร้อยเอ็ด ประเทศไทย</p>
                    </div>
                </article>

                <aside class="grid gap-3 lg:col-span-3 lg:grid-rows-3" aria-label="สรุปข้อมูลสนับสนุน">
                    <?php $miniIcons = ['bi-people', 'bi-buildings', 'bi-pie-chart']; ?>
                    <?php foreach ($miniCards as $index => $card): ?>
                        <article class="mini-stat-card">
                            <span class="stat-icon-badge">
                                <i class="bi <?= htmlspecialchars($miniIcons[$index] ?? 'bi-activity') ?>"></i>
                            </span>
                            <div class="min-w-0">
                                <p class="home-label"><?= htmlspecialchars($card['label']) ?></p>
                                <div class="home-value mt-1"><?= htmlspecialchars($card['value']) ?></div>
                                <p class="text-xs font-semibold text-hospital-muted"><?= htmlspecialchars($card['note']) ?></p>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </aside>
            </section>

            <section class="dashboard-subgrid mt-5" aria-label="ตัวชี้วัดภาพรวม">
                <?php foreach ($overviewCards as $card): ?>
                    <article class="metric-tile lg:col-span-3">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <p class="home-label"><?= htmlspecialchars($card['label']) ?></p>
                                <div class="home-value mt-3"><?= htmlspecialchars($card['value']) ?></div>
                            </div>
                            <span class="icon-badge">
                                <i class="bi <?= htmlspecialchars($card['icon']) ?>"></i>
                            </span>
                        </div>
                        <div class="mt-4 flex items-center justify-between gap-4">
                            <p class="text-sm text-hospital-muted"><?= htmlspecialchars($card['note']) ?></p>
                            <span class="tile-chevron" aria-hidden="true"><i class="bi bi-chevron-right"></i></span>
                        </div>
                    </article>
                <?php endforeach; ?>
            </section>

            <section id="workflow-board" class="section-board mt-5 grid gap-4 lg:grid-cols-12" aria-label="งานหลักในระบบ">
                <article class="workflow-intro-card lg:col-span-4">
                    <p class="text-xs font-bold uppercase tracking-[0.18em] text-hospital-teal">Core workflow</p>
                    <h2 class="mt-3 font-prompt text-2xl font-bold leading-tight text-hospital-ink">งานหลักในระบบ</h2>
                    <p class="mt-3 max-w-sm text-sm leading-6 text-hospital-muted">เข้าสู่ระบบแล้วเริ่มงานประจำวันได้ทันทีตามสิทธิ์ของหน่วยงาน</p>
                    <div class="mt-5 grid gap-2">
                        <div class="workflow-check"><i class="bi bi-check2"></i><span>ลงเวลาและตรวจรายการในระบบเดียว</span></div>
                        <div class="workflow-check"><i class="bi bi-check2"></i><span>รายงานพร้อมพิมพ์และส่งออก</span></div>
                        <div class="workflow-check"><i class="bi bi-check2"></i><span>แยกข้อมูลตามบทบาทผู้ใช้</span></div>
                    </div>
                </article>

                <div class="grid gap-4 sm:grid-cols-2 lg:col-span-8">
                    <?php foreach ($featureCards as $index => $feature): ?>
                        <article class="workflow-card">
                            <div class="flex h-full items-start gap-3">
                                <span class="workflow-icon-badge">
                                    <i class="bi <?= htmlspecialchars($feature['icon']) ?>"></i>
                                </span>
                                <div>
                                    <div class="text-[0.68rem] font-bold uppercase tracking-[0.16em] text-hospital-teal">0<?= $index + 1 ?></div>
                                    <h3 class="mt-1 font-prompt text-lg font-bold text-hospital-ink"><?= htmlspecialchars($feature['title']) ?></h3>
                                    <p class="mt-1 text-sm text-hospital-muted"><?= htmlspecialchars($feature['text']) ?></p>
                                </div>
                                <span class="workflow-chevron" aria-hidden="true"><i class="bi bi-chevron-right"></i></span>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>

            <section id="start-now" class="compact-cta-board mt-5">
                <div class="cta-copy-block">
                    <span class="cta-icon-badge" aria-hidden="true"><i class="bi bi-rocket-takeoff"></i></span>
                    <div>
                        <p class="text-xs font-bold uppercase tracking-[0.18em] text-hospital-teal">Next step</p>
                        <h2 class="mt-1 font-prompt text-2xl font-bold text-white">พร้อมเริ่มใช้งานระบบ</h2>
                        <p class="mt-1 text-sm text-white/70">เข้าสู่ระบบหรือสมัครบัญชีใหม่เพื่อใช้งานตามสิทธิ์ของหน่วยงาน</p>
                    </div>
                </div>
                <div class="flex flex-col gap-3 sm:flex-row">
                    <a class="cta-button cta-button-light" href="<?= htmlspecialchars($basePath) ?>/auth/login.php">เข้าสู่ระบบ <i class="bi bi-arrow-right"></i></a>
                    <a class="cta-button cta-button-teal" href="<?= htmlspecialchars($basePath) ?>/auth/register.php">สมัครใช้งาน <i class="bi bi-arrow-right"></i></a>
                </div>
            </section>
        </div>
    </main>

    <script>
        const timeNode = document.getElementById('bangkokTime');
        const dateNode = document.getElementById('bangkokDate');
        const tempNode = document.getElementById('weatherTemp');
        const weatherStatusNode = document.getElementById('weatherStatus');
        const weatherIconNode = document.getElementById('weatherIcon');

        function updateBangkokTime() {
            const now = new Date();
            if (timeNode) {
                timeNode.textContent = new Intl.DateTimeFormat('th-TH', {
                    timeZone: 'Asia/Bangkok',
                    hour: '2-digit',
                    minute: '2-digit',
                    second: '2-digit',
                    hour12: false
                }).format(now);
            }

            if (dateNode) {
                dateNode.textContent = new Intl.DateTimeFormat('th-TH', {
                    timeZone: 'Asia/Bangkok',
                    day: 'numeric',
                    month: 'long',
                    year: 'numeric'
                }).format(now);
            }
        }

        function updateWeatherIcon(temp) {
            if (!weatherIconNode) {
                return;
            }
            weatherIconNode.className = 'bi weather-icon text-hospital-teal ' + (temp >= 34 ? 'bi-sun' : temp >= 27 ? 'bi-cloud-sun' : 'bi-cloud');
        }

        async function loadWeather() {
            if (!tempNode || !weatherStatusNode || !weatherIconNode) {
                return;
            }

            try {
                weatherStatusNode.textContent = 'กำลังอัปเดตอุณหภูมิ';
                const response = await fetch('https://api.open-meteo.com/v1/forecast?latitude=16.316&longitude=104.209&current=temperature_2m&timezone=Asia%2FBangkok', {
                    headers: { 'Accept': 'application/json' }
                });
                if (!response.ok) {
                    throw new Error('Weather request failed');
                }
                const data = await response.json();
                const temp = data && data.current ? data.current.temperature_2m : null;
                if (typeof temp !== 'number') {
                    throw new Error('Temperature missing');
                }
                tempNode.textContent = Math.round(temp).toString();
                updateWeatherIcon(temp);
                weatherStatusNode.textContent = 'อำเภอหนองพอก จ.ร้อยเอ็ด';
            } catch (error) {
                tempNode.textContent = '--';
                weatherIconNode.className = 'bi bi-cloud-slash weather-icon text-hospital-teal';
                weatherStatusNode.textContent = 'ยังไม่สามารถดึงอุณหภูมิได้';
            }
        }

        updateBangkokTime();
        setInterval(updateBangkokTime, 1000);
        loadWeather();
        setInterval(loadWeather, 600000);
    </script>
</body>
</html>
