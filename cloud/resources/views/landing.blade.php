@php
    use App\Support\Persian;
    $ic = [
        'bolt' => '<path d="M13 2 4 14h7l-1 8 9-12h-7z"/>',
        'map' => '<path d="M9 4 3 6v14l6-2 6 2 6-2V4l-6 2z"/><path d="M9 4v14M15 6v14"/>',
        'qr' => '<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><path d="M14 14h3v3h-3zM20 14v.01M14 20h.01M17 20h4v-3"/>',
        'plug' => '<path d="M9 2v6M15 2v6M6 8h12v4a6 6 0 0 1-12 0zM12 18v4"/>',
        'check' => '<path d="M20 6 9 17l-5-5"/>',
        'shield' => '<path d="M12 2l8 4v6c0 5-3.5 8-8 10-4.5-2-8-5-8-10V6z"/><path d="M9 12l2 2 4-4"/>',
        'clock' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
        'wallet' => '<path d="M3 7a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><path d="M16 12h.01M3 9h18"/>',
        'arrow' => '<path d="M19 12H5M11 6l-6 6 6 6"/>',
        'chart' => '<path d="M3 3v18h18"/><path d="M7 15l4-4 3 3 5-6"/>',
        'coins' => '<circle cx="8" cy="8" r="6"/><path d="M18.09 10.37A6 6 0 1 1 10.34 18M7 6h1v4M16.71 13.88l.7.71-2.82 2.82"/>',
        'cpu' => '<rect x="5" y="5" width="14" height="14" rx="2"/><path d="M9 9h6v6H9zM9 2v3M15 2v3M9 19v3M15 19v3M2 9h3M2 15h3M19 9h3M19 15h3"/>',
        'wifi' => '<path d="M5 12.5a10 10 0 0 1 14 0M8.5 16a5 5 0 0 1 7 0M2 9a15 15 0 0 1 20 0"/><path d="M12 19.5h.01"/>',
        'menu' => '<path d="M4 7h16M4 12h16M4 17h16"/>',
        'phone' => '<rect x="6" y="2" width="12" height="20" rx="3"/><path d="M11 18h2"/>',
    ];
    $svg = fn ($k) => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'.$ic[$k].'</svg>';
    $n = fn ($v) => Persian::digits(number_format((int) $v));
@endphp
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>QUAD EV — شارژ هوشمند خودروی برقی</title>
    <meta name="description" content="QUAD EV؛ نقشهٔ ایستگاه‌های شارژ خودروی برقی، شروع شارژ با اسکن QR، پرداخت با کیف پول و مسیریاب سفر برقی. ایستگاه شارژ خود را به شبکهٔ QUAD متصل کنید.">
    <meta name="theme-color" content="#061629">
    <meta property="og:title" content="QUAD EV — شارژ هوشمند خودروی برقی">
    <meta property="og:description" content="نزدیک‌ترین شارژر آزاد را پیدا کنید، با یک اسکن شارژ را شروع کنید و آنلاین پرداخت کنید.">
    <meta property="og:image" content="{{ url('/assets/landing/hero.webp') }}">
    <meta property="og:type" content="website">
    <link rel="icon" type="image/png" href="/assets/brand/quad-icon-256.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="preload" as="image" href="/assets/landing/hero.webp" imagesrcset="/assets/landing/hero-sm.webp 900w, /assets/landing/hero.webp 1920w" imagesizes="100vw">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin="">
    <link rel="stylesheet" href="@v('/assets/landing/landing.css')">
</head>
<body>

<header class="nav" id="nav">
    <div class="wrap">
        <a href="/" class="nav-logo"><img src="/assets/brand/quad-logo-white.png" alt="QUAD EV"></a>
        <nav class="nav-links">
            <a href="#how">چطور کار می‌کند</a>
            <a href="#features">امکانات</a>
            <a href="#app">اپلیکیشن</a>
            <a href="#network">نقشهٔ شبکه</a>
            <a href="#business">کسب‌وکارها</a>
            <a href="#faq">سؤالات</a>
        </nav>
        <div class="nav-cta">
            <a href="/partner/login" class="btn btn-glass">پنل شرکا</a>
            <a href="/app/login" class="btn btn-primary">{!! $svg('bolt') !!} ورود به اپ</a>
        </div>
    </div>
</header>

<section class="hero" id="top">
    <div class="hero-bg" id="heroBg"><img src="/assets/landing/hero.webp" srcset="/assets/landing/hero-sm.webp 900w, /assets/landing/hero.webp 1920w" sizes="100vw" alt="ایستگاه شارژ خودروی برقی QUAD EV" fetchpriority="high"></div>
    <div class="hero-grid"></div>
    <div id="sparks"></div>

    <div class="float-card fc-free"><i>{!! $svg('plug') !!}</i><div><b>۲ کانکتور آزاد</b><small>ایستگاه میدان آزادی · ۱٫۲ کیلومتر</small></div></div>
    <div class="float-card fc-pay"><i>{!! $svg('wallet') !!}</i><div><b>پرداخت شد</b><small>۴۲٫۵ kWh · کیف پول</small></div></div>
    <div class="float-card fc-charge"><div class="fc-ring" id="fcRing"><span id="fcPct">۷۲٪</span></div><div><b>در حال شارژ…</b><small>۱۲۰ kW · DC سریع</small></div></div>

    <div class="wrap hero-inner">
        <div class="hero-copy">
            <span class="eyebrow"><span class="live"></span> شبکهٔ هوشمند شارژ خودروی برقی</span>
            <h1>شارژ خودروی برقی،<br><span class="grad">ساده‌تر از همیشه</span></h1>
            <p class="lead">نزدیک‌ترین شارژر آزاد را روی نقشه پیدا کنید، با اسکن QR شارژ را شروع کنید، لحظه‌به‌لحظه پیشرفت را ببینید و با کیف پول پرداخت کنید. بدون کارت، بدون صف، بدون دردسر.</p>
            <div class="hero-ctas">
                <a href="/app/login" class="btn btn-primary">{!! $svg('bolt') !!} همین حالا شروع کنید</a>
                <a href="#network" class="btn btn-glass">{!! $svg('map') !!} مشاهدهٔ نقشهٔ ایستگاه‌ها</a>
            </div>
            <div class="hero-trust">
                <span>{!! $svg('shield') !!} پرداخت امن</span>
                <span>{!! $svg('clock') !!} پشتیبانی ۲۴ ساعته</span>
                <span>{!! $svg('wifi') !!} کار حتی بدون اینترنت</span>
            </div>
        </div>
    </div>
    <a href="#how" class="scroll-hint" aria-label="ادامه"></a>
</section>

<div class="stats">
    <div class="wrap">
        <div class="stats-grid rv">
            <div class="stat"><b class="tnum"><span data-count="{{ $stats['map'] }}">{{ $n($stats['map']) }}</span><em>+</em></b><span>ایستگاه روی نقشه</span></div>
            <div class="stat"><b class="tnum"><span data-count="{{ $stats['operators'] }}">{{ $n($stats['operators']) }}</span></b><span>اپراتور در یک اپ</span></div>
            <div class="stat"><b class="tnum"><span data-count="{{ $stats['connectors'] }}">{{ $n($stats['connectors']) }}</span></b><span>کانکتور شبکهٔ QUAD</span></div>
            <div class="stat"><b class="tnum">۲۴<em>/</em>۷</b><span>در دسترس، هر روز هفته</span></div>
        </div>
    </div>
</div>

<section class="sec" id="how">
    <div class="wrap">
        <div class="sec-head rv">
            <span class="kicker">در سه قدم</span>
            <h2>از پیدا کردن شارژر تا پرداخت، کمتر از یک دقیقه</h2>
            <p>هر آنچه برای شارژ لازم دارید در گوشی شماست؛ نیازی به نصب از استور نیست.</p>
        </div>
        <div class="steps" id="steps">
            <span class="flow"></span>
            <div class="step rv" style="--d:.05s"><div class="step-ic"><span class="n">۱</span>{!! $svg('map') !!}</div><h3>پیدا کنید</h3><p>ایستگاه‌های آزاد اطراف، نوع کانکتور، توان و قیمت را روی نقشه ببینید.</p></div>
            <div class="step rv" style="--d:.2s"><div class="step-ic"><span class="n">۲</span>{!! $svg('qr') !!}</div><h3>اسکن کنید</h3><p>QR روی شارژر را اسکن کنید یا بگذارید دوربین پلاک شما را تشخیص دهد.</p></div>
            <div class="step rv" style="--d:.35s"><div class="step-ic"><span class="n">۳</span>{!! $svg('bolt') !!}</div><h3>شارژ کنید</h3><p>توان، انرژی و هزینه را زنده دنبال کنید؛ در پایان خودکار از کیف پول کسر می‌شود.</p></div>
        </div>
    </div>
</section>

<section class="sec feat-bg" id="features">
    <div class="wrap">
        <div class="sec-head rv">
            <span class="kicker">امکانات</span>
            <h2>همهٔ ابزارهای یک رانندهٔ برقی، در یک اپ</h2>
            <p>طراحی‌شده برای جاده‌ها و شهرهای ایران؛ سریع، فارسی و قابل اعتماد.</p>
        </div>
        <div class="feats">
            <div class="feat wide rv" style="--glow:rgba(14,107,168,.2)">
                <div class="fw-body">
                    <h3>نقشهٔ یکپارچهٔ همهٔ اپراتورها</h3>
                    <p>ایستگاه‌های QUAD و {{ Persian::digits(max(0, $stats['operators'] - 1)) }} اپراتور دیگر کنار هم؛ با وضعیت لحظه‌ای کانکتورها، ساعات کاری، امکانات رفاهی و تعرفه.</p>
                    <div class="tag-row"><span>وضعیت زنده</span><span>فیلتر شارژ سریع</span><span>مسیریابی</span><span>اعلان آزاد شدن</span></div>
                </div>
                <div class="fw-img"><img src="/assets/landing/hub.webp" alt="ایستگاه شارژ با سقف خورشیدی" loading="lazy"></div>
            </div>
            <div class="feat rv" style="--d:.1s;--glow:rgba(56,189,248,.25)">
                <h3>شارژ با QR یا پلاک</h3><p>اسکن کنید و شروع کنید؛ در ایستگاه‌های مجهز، پلاک‌خوان شما را می‌شناسد.</p>
                <div class="feat-art"><img src="/assets/app-img/scan.webp" alt="" loading="lazy"></div>
            </div>
            <div class="feat rv" style="--glow:rgba(249,115,22,.22)">
                <h3>کیف پول و پاداش</h3><p>شارژ آنلاین کیف پول، تاریخچهٔ تراکنش‌ها و اعتبار هدیهٔ باشگاه مشتریان.</p>
                <div class="feat-art"><img src="/assets/app-img/wallet.webp" alt="" loading="lazy"></div>
            </div>
            <div class="feat rv" style="--d:.1s;--glow:rgba(34,197,94,.22)">
                <h3>پایش زندهٔ شارژ</h3><p>درصد باتری، توان لحظه‌ای، انرژی و هزینه؛ و اعلان پایان شارژ.</p>
                <div class="feat-art"><img src="/assets/app-img/battery.webp" alt="" loading="lazy"></div>
            </div>
            <div class="feat rv" style="--d:.2s;--glow:rgba(139,92,246,.22)">
                <h3>دستیار هوشمند</h3><p>«نزدیک‌ترین شارژر آزاد کجاست؟» — هر سؤالی دارید، همان لحظه جواب بگیرید.</p>
                <div class="feat-art"><img src="/assets/app-img/assistant.webp" alt="" loading="lazy"></div>
            </div>
            <div class="feat wide rv" style="--glow:rgba(249,115,22,.2)">
                <div class="fw-body">
                    <h3>مسیریاب سفر برقی</h3>
                    <p>مبدأ و مقصد را بدهید؛ مصرف انرژی، توقف‌های شارژ پیشنهادی و زمان رسیدن را پیش از حرکت ببینید.</p>
                    <div class="tag-row"><span>برآورد مصرف</span><span>توقف‌های بهینه</span><span>کاتالوگ خودروها</span></div>
                </div>
                <div class="fw-img"><img src="/assets/app-img/navigator.webp" alt="مسیر سفر برقی در جادهٔ کوهستانی" loading="lazy"></div>
            </div>
        </div>
    </div>
</section>

<section class="sec showcase" id="app">
    <div class="wrap split">
        <div class="rv rv-r">
            <span class="kicker">وب‌اپ QUAD EV</span>
            <h2>اپی که نیاز به نصب ندارد</h2>
            <p class="lead">در مرورگر گوشی باز کنید و به صفحهٔ اصلی اضافه کنید؛ مثل یک اپ واقعی، با اعلان‌ها و حالت آفلاین.</p>
            <ul class="checks">
                <li><i>{!! $svg('check') !!}</i><div>ورود با شمارهٔ موبایل<small>کد تأیید یا رمز عبور؛ در کمتر از ۳۰ ثانیه.</small></div></li>
                <li><i>{!! $svg('check') !!}</i><div>رزرو شارژر پیش از رسیدن<small>جای خود را نگه دارید و بی‌دغدغه برانید.</small></div></li>
                <li><i>{!! $svg('check') !!}</i><div>رسید و سوابق کامل<small>هر جلسهٔ شارژ با جزئیات انرژی و هزینه.</small></div></li>
            </ul>
            <div class="store-row">
                <a href="/app/login" class="btn btn-primary">{!! $svg('phone') !!} باز کردن وب‌اپ</a>
                <a href="#faq" class="btn btn-glass">نحوهٔ نصب روی گوشی</a>
            </div>
        </div>
        <div class="phones rv rv-z" id="phones">
            <div class="halo"></div>
            <div class="phone p3"><img src="/assets/landing/app-wallet.webp" alt="کیف پول در اپ" loading="lazy"></div>
            <div class="phone p2"><img src="/assets/landing/app-charger.webp" alt="صفحهٔ ایستگاه در اپ" loading="lazy"></div>
            <div class="phone p1"><img src="/assets/landing/app-home.webp" alt="پیشخان اپ QUAD EV" loading="lazy"></div>
        </div>
    </div>
</section>

<section class="sec" id="network">
    <div class="wrap">
        <div class="sec-head rv">
            <span class="kicker">نقشهٔ زنده</span>
            <h2>شبکه‌ای که هر روز بزرگ‌تر می‌شود</h2>
            <p>{{ $n($stats['map']) }} ایستگاه از {{ Persian::digits($stats['operators']) }} اپراتور، در یک نقشه. پین نارنجی، ایستگاه‌های QUAD است.</p>
        </div>
        <div class="map-card rv rv-z">
            <div id="lmap" aria-label="نقشهٔ ایستگاه‌های شارژ"></div>
            <div class="map-overlay">
                <b>ایستگاه‌های شارژ</b>
                <div class="legend" id="legend"><span class="q"><i></i>QUAD EV ({{ Persian::digits($stats['own']) }})</span></div>
            </div>
        </div>
    </div>
</section>

<section class="sec biz" id="business">
    <div class="wrap split">
        <div class="biz-media rv rv-l">
            <div class="main"><img src="/assets/landing/partner.webp" alt="مدیریت ایستگاه شارژ" loading="lazy"></div>
            <div class="sub"><img src="/assets/landing/hub.webp" alt="" loading="lazy"></div>
            <div class="biz-badge"><i>{!! $svg('chart') !!}</i><div><b>تسویهٔ خودکار</b><small>درآمد هر ایستگاه، شفاف و لحظه‌ای</small></div></div>
        </div>
        <div class="rv rv-r">
            <span class="kicker">برای کسب‌وکارها و مالکان شارژر</span>
            <h2>ایستگاه شارژ خود را به شبکهٔ QUAD وصل کنید</h2>
            <p class="lead">پارکینگ، هتل، مجتمع تجاری یا جایگاه سوخت دارید؟ شارژرهایتان را با QUAD مدیریت کنید و از اولین روز درآمد داشته باشید.</p>
            <div class="biz-list">
                <div class="biz-item"><i>{!! $svg('chart') !!}</i><div><b>پایش زنده</b><small>وضعیت، خطاها و توان هر شارژر در یک داشبورد.</small></div></div>
                <div class="biz-item"><i>{!! $svg('coins') !!}</i><div><b>تعرفهٔ انعطاف‌پذیر</b><small>قیمت پیک و کم‌باری، هزینهٔ اشغال و تخفیف.</small></div></div>
                <div class="biz-item"><i>{!! $svg('cpu') !!}</i><div><b>سازگار با OCPP</b><small>اتصال به اکثر برندهای شارژر AC و DC.</small></div></div>
                <div class="biz-item"><i>{!! $svg('wifi') !!}</i><div><b>کار در حالت آفلاین</b><small>ایستگاه بدون اینترنت هم شارژ می‌دهد و بعداً همگام می‌شود.</small></div></div>
            </div>
            <div class="store-row">
                <a href="/partner/login" class="btn btn-dark">ورود به پنل شرکا {!! $svg('arrow') !!}</a>
                <a href="#faq" class="btn btn-line">سؤالات متداول</a>
            </div>
        </div>
    </div>
</section>

<section class="sec" id="faq">
    <div class="wrap">
        <div class="sec-head rv">
            <span class="kicker">سؤالات متداول</span>
            <h2>پاسخ سؤال‌های رایج</h2>
        </div>
        <div class="faq">
            <details class="rv" open><summary>برای استفاده باید اپ نصب کنم؟</summary><div class="ans">خیر. وب‌اپ QUAD EV در مرورگر گوشی کار می‌کند. برای تجربهٔ بهتر، در کروم (اندروید) گزینهٔ «افزودن به صفحهٔ اصلی» و در سافاری (آیفون) دکمهٔ اشتراک‌گذاری و سپس «Add to Home Screen» را بزنید.</div></details>
            <details class="rv"><summary>هزینهٔ شارژ چطور محاسبه و پرداخت می‌شود؟</summary><div class="ans">بر اساس تعرفهٔ هر ایستگاه (معمولاً به ازای هر کیلووات‌ساعت) که پیش از شروع در اپ نمایش داده می‌شود. پس از پایان شارژ، مبلغ به‌صورت خودکار از کیف پول شما کسر و رسید آن در سوابق ثبت می‌شود.</div></details>
            <details class="rv"><summary>ایستگاه‌های سایر اپراتورها را هم می‌بینم؟</summary><div class="ans">بله. نقشهٔ QUAD ایستگاه‌های اپراتورهای دیگر را هم با مشخصات و راه ارتباطی نشان می‌دهد تا همیشه نزدیک‌ترین گزینه را داشته باشید. شروع شارژ و پرداخت درون‌اپ برای ایستگاه‌های شبکهٔ QUAD فعال است.</div></details>
            <details class="rv"><summary>اگر اینترنت ایستگاه قطع باشد چه می‌شود؟</summary><div class="ans">ایستگاه‌های QUAD سرور محلی دارند؛ با اتصال به WiFi ایستگاه، شارژ بدون اینترنت هم شروع می‌شود و اطلاعات بعداً با مرکز همگام می‌شود.</div></details>
            <details class="rv"><summary>چطور ایستگاه شارژ خودم را به QUAD اضافه کنم؟</summary><div class="ans">از طریق پنل شرکا وارد شوید یا با پشتیبانی تماس بگیرید. شارژرهای سازگار با OCPP در کوتاه‌ترین زمان به شبکه متصل و روی نقشه نمایش داده می‌شوند.</div></details>
        </div>
    </div>
</section>

<section class="cta">
    <div class="wrap">
        <div class="cta-box rv rv-z">
            <div>
                <h2>آمادهٔ اولین شارژ هستید؟</h2>
                <p>با شمارهٔ موبایل وارد شوید و نزدیک‌ترین ایستگاه را همین حالا پیدا کنید.</p>
                <div class="store-row">
                    <a href="/app/login" class="btn btn-primary">{!! $svg('bolt') !!} شروع رایگان</a>
                    <a href="/partner/login" class="btn btn-glass">همکاری با QUAD</a>
                </div>
            </div>
            <div class="cta-art"><img src="/assets/app-img/battery.webp" alt="" loading="lazy"></div>
        </div>
    </div>
</section>

<footer>
    <div class="wrap">
        <div class="foot">
            <div>
                <img src="/assets/brand/quad-logo-white.png" alt="QUAD EV">
                <p style="margin:0;max-width:340px">QUAD EV، پلتفرم هوشمند شارژ خودروی برقی؛ از پیدا کردن ایستگاه تا پرداخت، و ابزار کامل مدیریت برای مالکان شارژر.</p>
            </div>
            <div><h4>رانندگان</h4><ul><li><a href="/app/login">ورود به وب‌اپ</a></li><li><a href="#network">نقشهٔ ایستگاه‌ها</a></li><li><a href="#features">امکانات</a></li></ul></div>
            <div><h4>کسب‌وکارها</h4><ul><li><a href="/partner/login">پنل شرکا</a></li><li><a href="#business">اتصال ایستگاه</a></li><li><a href="/admin/login">پنل مدیریت</a></li></ul></div>
            <div><h4>راهنما</h4><ul><li><a href="#how">چطور کار می‌کند</a></li><li><a href="#faq">سؤالات متداول</a></li><li><a href="#app">نصب وب‌اپ</a></li></ul></div>
        </div>
        <div class="foot-bot"><span>© {{ Persian::dateTime(now(), 'yyyy') }} QUAD EV — همهٔ حقوق محفوظ است.</span><span>ساخته‌شده برای آیندهٔ برقی ایران ⚡</span></div>
    </div>
</footer>

<script>window.LANDING_MAP=@json($map);</script>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin=""></script>
<script src="@v('/assets/landing/landing.js')"></script>
</body>
</html>
