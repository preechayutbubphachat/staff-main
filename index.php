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
    <title>Staff Time Attendance</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@400;500;600;700&family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --ink: #10243b;
            --teal: #1e6f67;
            --gold: #d0a343;
        }

        body {
            margin: 0;
            min-height: 100vh;
            color: var(--ink);
            font-family: 'Sarabun', sans-serif;
            background:
                radial-gradient(circle at top left, rgba(30, 111, 103, 0.14), transparent 24%),
                radial-gradient(circle at bottom right, rgba(208, 163, 67, 0.12), transparent 28%),
                linear-gradient(180deg, #f7fbfd, #eef4f8);
        }

        .hero {
            min-height: 100vh;
            display: grid;
            place-items: center;
            padding: 32px;
        }

        .page-back {
            display: none;
        }

        .btn-back {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 16px;
            border-radius: 999px;
            border: 1px solid rgba(16,36,59,.12);
            background: rgba(255,255,255,.92);
            color: var(--ink);
            font-weight: 700;
            box-shadow: 0 12px 26px rgba(16,36,59,.08);
        }

        .hero-shell {
            width: min(1100px, 100%);
            display: grid;
            grid-template-columns: 1.1fr .9fr;
            gap: 28px;
            align-items: stretch;
        }

        .hero-copy,
        .hero-panel {
            border-radius: 32px;
            background: rgba(255,255,255,.88);
            border: 1px solid rgba(16,36,59,.08);
            box-shadow: 0 24px 56px rgba(16,36,59,.10);
        }

        .hero-copy {
            padding: 44px;
        }

        .hero-panel {
            padding: 34px;
            background: linear-gradient(160deg, rgba(16,36,59,.98), rgba(30,111,103,.92));
            color: #f7fbff;
        }

        .eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 8px 14px;
            border-radius: 999px;
            background: rgba(16,36,59,.06);
            color: var(--teal);
            font-size: .84rem;
            font-weight: 700;
            letter-spacing: .04em;
        }

        h1 {
            margin: 18px 0 14px;
            font-family: 'Prompt', sans-serif;
            font-size: clamp(2.2rem, 4vw, 4.2rem);
            line-height: 1.02;
        }

        .lead {
            max-width: 640px;
            color: #5d6d80;
            font-size: 1.05rem;
            line-height: 1.8;
        }

        .hero-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 14px;
            margin-top: 28px;
        }

        .btn-hero {
            border-radius: 999px;
            padding: 14px 22px;
            font-weight: 700;
        }

        .hero-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 14px;
            margin-top: 32px;
        }

        .hero-stat {
            border-radius: 22px;
            padding: 18px;
            background: #f7fafc;
            border: 1px solid rgba(16,36,59,.06);
        }

        .hero-stat strong {
            display: block;
            font-family: 'Prompt', sans-serif;
            font-size: 1.5rem;
            margin-bottom: 4px;
        }

        .panel-title {
            font-family: 'Prompt', sans-serif;
            font-size: 1.6rem;
            margin-bottom: 14px;
        }

        .feature-list {
            display: grid;
            gap: 14px;
        }

        .feature-list div {
            padding: 14px 16px;
            border-radius: 20px;
            background: rgba(255,255,255,.08);
            border: 1px solid rgba(255,255,255,.10);
        }

        .feature-list strong {
            display: block;
            margin-bottom: 4px;
        }

        .panel-note {
            margin-top: 18px;
            color: rgba(247,251,255,.78);
            line-height: 1.7;
        }

        @media (max-width: 960px) {
            .hero-shell {
                grid-template-columns: 1fr;
            }

            .hero-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
<div class="page-back">
    <button type="button" class="btn btn-back" data-simple-back data-fallback-href="/staff-main/">
        <i class="bi bi-arrow-left"></i>ย้อนกลับ
    </button>
</div>

<main class="hero">
    <section class="hero-shell">
        <div class="hero-copy">
            <div class="eyebrow">โรงพยาบาลหนองพอก</div>
            <h1>ระบบลงเวลาเวรและรายงานสำหรับบุคลากรโรงพยาบาล</h1>
            <p class="lead">บันทึกเวลาเวร ดูรายงานย้อนหลัง ตรวจสอบรายการ และส่งออกเอกสารได้ในระบบเดียว โดยแยกสิทธิ์ตามบทบาทของเจ้าหน้าที่อย่างชัดเจน</p>

            <div class="hero-actions">
                <a class="btn btn-dark btn-hero" href="/staff-main/auth/login.php">เข้าสู่ระบบ</a>
                <a class="btn btn-outline-dark btn-hero" href="/staff-main/auth/register.php">สมัครใช้งาน</a>
            </div>

            <div class="hero-grid">
                <div class="hero-stat">
                    <strong>ลงเวลา</strong>
                    <span>เลือกเวรและบันทึกเวลาเข้าออกได้รวดเร็ว</span>
                </div>
                <div class="hero-stat">
                    <strong>รายงาน</strong>
                    <span>ดูแบบรายบุคคล รายวัน และรายแผนกได้</span>
                </div>
                <div class="hero-stat">
                    <strong>ตรวจสอบ</strong>
                    <span>อนุมัติรายการและส่งออกเอกสารได้ทันที</span>
                </div>
            </div>
        </div>

        <aside class="hero-panel">
            <div class="panel-title">จุดเด่นของระบบ</div>
            <div class="feature-list">
                <div>
                    <strong>สิทธิ์ตามบทบาท</strong>
                    <span>แยกเจ้าหน้าที่ทั่วไป เจ้าหน้าที่การเงิน และผู้ตรวจสอบอย่างเป็นระบบ</span>
                </div>
                <div>
                    <strong>รองรับงานเวรจริง</strong>
                    <span>มี preset เวรเช้า เวรบ่าย เวรดึก พร้อมตรวจสอบเวลาชนกันอัตโนมัติ</span>
                </div>
                <div>
                    <strong>พร้อมพิมพ์และส่งออก</strong>
                    <span>รายงานสามารถพิมพ์ ดาวน์โหลด PDF และ CSV ได้จากหน้ารายงานโดยตรง</span>
                </div>
            </div>
            <p class="panel-note">หน้าแรกนี้ถูกทำไว้เป็นทางเข้ากลางของโปรเจกต์ เมื่อเข้าสู่ระบบแล้ว ระบบจะพาไปหน้า dashboard อัตโนมัติ</p>
        </aside>
    </section>
</main>
<script>
document.querySelectorAll('[data-simple-back]').forEach(function (button) {
    button.addEventListener('click', function () {
        const fallbackHref = button.getAttribute('data-fallback-href') || '/staff-main/';
        const hasHistory = window.history.length > 1 && document.referrer;

        if (hasHistory) {
            window.history.back();
            return;
        }

        window.location.href = fallbackHref;
    });
});
</script>
</body>
</html>
