<?php
session_start();

if (!empty($_SESSION['id'])) {
    header('Location: pages/dashboard.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>ระบบลงเวลาเวร | โรงพยาบาลหนองพอก</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@400;500;600;700&family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="/staff-main/assets/css/landing-page.css">
</head>
<body class="landing-page">
<main class="landing-shell">
    <header class="landing-topbar">
        <div class="landing-brand">
            <div class="landing-brand-badge">
                <img src="/staff-main/LOGO/nongphok_logo.png" alt="โลโก้โรงพยาบาลหนองพอก">
            </div>
            <div class="landing-brand-copy">
                <span class="landing-brand-title">ระบบลงเวลาเวรและรายงาน</span>
                <span class="landing-brand-subtitle">โรงพยาบาลหนองพอก</span>
            </div>
        </div>

        <div class="landing-top-actions">
            <a class="btn landing-btn landing-btn-soft" href="/staff-main/auth/register.php">สมัครใช้งาน</a>
            <a class="btn landing-btn landing-btn-primary" href="/staff-main/auth/login.php">เข้าสู่ระบบ</a>
        </div>
    </header>

    <section class="landing-hero">
        <div class="landing-hero-copy">
            <span class="landing-eyebrow">
                <i class="bi bi-heart-pulse"></i>
                ระบบงานเวรสำหรับบุคลากรโรงพยาบาล
            </span>
            <h1>บันทึกเวลา ตรวจสอบรายการ และออกรายงานในที่เดียว</h1>
            <p>ออกแบบให้เริ่มงานได้เร็ว ดูข้อมูลชัด และใช้งานจริงได้ทุกวัน</p>

            <div class="landing-hero-actions">
                <a class="btn landing-btn landing-btn-primary" href="/staff-main/auth/login.php">เข้าสู่ระบบ</a>
                <a class="btn landing-btn landing-btn-soft" href="#landing-features">ดูคุณสมบัติระบบ</a>
            </div>

            <div class="landing-hero-tags">
                <span>ลงเวลาเวร</span>
                <span>ตรวจสอบรายการ</span>
                <span>รายงานพร้อมพิมพ์</span>
            </div>
        </div>

        <aside class="landing-hero-panel">
            <div class="landing-panel-card landing-panel-card-highlight">
                <span class="landing-panel-label">เริ่มต้นได้ทันที</span>
                <h2>งานหลักอยู่ในโครงที่เข้าใจง่าย</h2>
                <ul class="landing-panel-list">
                    <li><strong>ลงเวลาเวร</strong><span>เปิดงานประจำวันได้ทันที</span></li>
                    <li><strong>ตรวจสอบ</strong><span>ดูคิวอนุมัติได้ชัด</span></li>
                    <li><strong>รายงาน</strong><span>พร้อมพิมพ์และส่งออก</span></li>
                </ul>
            </div>

            <div class="landing-panel-grid">
                <div class="landing-panel-card">
                    <span class="landing-mini-label">รองรับงานจริง</span>
                    <strong>งานเวรประจำวัน</strong>
                </div>
                <div class="landing-panel-card">
                    <span class="landing-mini-label">สิทธิ์แยกชัด</span>
                    <strong>ตามบทบาทผู้ใช้</strong>
                </div>
            </div>
        </aside>
    </section>

    <section class="landing-section" id="landing-features">
        <div class="landing-section-head">
            <span class="landing-section-kicker">คุณสมบัติหลัก</span>
            <h2>สั้น ชัด และพร้อมใช้งาน</h2>
            <p>จัดโครงให้เห็นงานสำคัญเร็วขึ้นโดยไม่ต้องอ่านยาว</p>
        </div>

        <div class="landing-feature-grid">
            <article class="landing-feature-card">
                <div class="landing-feature-icon"><i class="bi bi-clock-history"></i></div>
                <h3>ลงเวลาเวร</h3>
                <p>บันทึกเวลาเข้าออกได้รวดเร็วจากหน้าที่คุ้นเคย</p>
            </article>

            <article class="landing-feature-card">
                <div class="landing-feature-icon"><i class="bi bi-patch-check"></i></div>
                <h3>ตรวจสอบรายการ</h3>
                <p>เห็นคิวตรวจสอบและยืนยันรายการได้เป็นขั้นตอน</p>
            </article>

            <article class="landing-feature-card">
                <div class="landing-feature-icon"><i class="bi bi-file-earmark-arrow-down"></i></div>
                <h3>รายงานและส่งออก</h3>
                <p>เปิด พิมพ์ และส่งออกเอกสารจากข้อมูลชุดเดียวกัน</p>
            </article>

            <article class="landing-feature-card">
                <div class="landing-feature-icon"><i class="bi bi-shield-check"></i></div>
                <h3>สิทธิ์ตามบทบาท</h3>
                <p>กำหนดการเข้าถึงให้เหมาะกับหน้าที่ของแต่ละคน</p>
            </article>
        </div>
    </section>

    <section class="landing-section">
        <div class="landing-strengths">
            <div class="landing-strengths-main">
                <span class="landing-section-kicker">ภาพรวมระบบ</span>
                <h2>หน้าตาเบา แต่รองรับงานโรงพยาบาลจริง</h2>
                <p>ลดความหนาแน่นของข้อมูลบนหน้าแรก เพื่อให้เริ่มงานต่อได้เร็วขึ้น</p>
            </div>

            <div class="landing-strength-list">
                <div class="landing-strength-item">
                    <strong>ใช้งานง่าย</strong>
                    <span>เห็นปุ่มเริ่มต้นและงานสำคัญทันที</span>
                </div>
                <div class="landing-strength-item">
                    <strong>พร้อมพิมพ์รายงาน</strong>
                    <span>รองรับเอกสารที่ใช้งานต่อได้จริง</span>
                </div>
                <div class="landing-strength-item">
                    <strong>เชื่อถือได้</strong>
                    <span>เหมาะกับระบบงานของบุคลากรในโรงพยาบาล</span>
                </div>
            </div>
        </div>
    </section>

    <section class="landing-final-cta">
        <div>
            <span class="landing-section-kicker">เริ่มใช้งาน</span>
            <h2>พร้อมเริ่มใช้งานระบบแล้ว</h2>
            <p>เข้าสู่ระบบเพื่อบันทึกเวลา ตรวจสอบรายการ และดูรายงานจากศูนย์กลางเดียว</p>
        </div>

        <div class="landing-top-actions">
            <a class="btn landing-btn landing-btn-soft landing-btn-soft-inverse" href="/staff-main/auth/register.php">สมัครใช้งาน</a>
            <a class="btn landing-btn landing-btn-light" href="/staff-main/auth/login.php">เข้าสู่ระบบ</a>
        </div>
    </section>
</main>
</body>
</html>
