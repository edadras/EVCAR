@php
    $isPrivacy = $page === 'privacy';
    $phone = \App\Models\SystemSetting::text('app', 'support_phone', '1570');
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>QUAD EV — {{ $isPrivacy ? 'Privacy Policy' : 'Support' }}</title>
<link rel="icon" href="{{ asset('assets/landing/app-icon.png') }}">
<style>
body{margin:0;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Vazirmatn,Tahoma,sans-serif;background:#07131F;color:#E6EEF6;line-height:1.75}
.wrap{max-width:820px;margin:0 auto;padding:32px 20px 60px}
header{display:flex;align-items:center;gap:14px;margin-bottom:28px}
header img{width:56px;height:56px;border-radius:14px}
header a{color:inherit;text-decoration:none;font-weight:800;font-size:22px}
h1{font-size:28px;margin:0 0 6px}h2{font-size:18px;margin:26px 0 8px;color:#22D3EE}
.card{background:#0E2133;border:1px solid #1B3550;border-radius:18px;padding:22px 24px;margin-bottom:22px}
.fa{direction:rtl;text-align:right}
.muted{color:#93A9BE;font-size:14px}
a{color:#22D3EE}ul{padding-inline-start:22px}
</style>
</head>
<body>
<div class="wrap">
<header><img src="{{ asset('assets/landing/app-icon.png') }}" alt=""><a href="{{ route('welcome') }}">QUAD EV</a></header>

@if($isPrivacy)
<div class="card">
<h1>Privacy Policy</h1>
<p class="muted">Last updated: {{ \Carbon\Carbon::parse('2026-11-06')->format('F j, Y') }}</p>
<p>QUAD EV ("we") operates the QUAD EV electric-vehicle charging app and the website ev.edadras.com. This policy explains what we collect and why.</p>
<h2>Data we collect</h2>
<ul>
<li><b>Account data:</b> name, mobile phone number and optional national ID, used to create your account and sign you in with one-time SMS codes.</li>
<li><b>Vehicle data:</b> vehicle model and licence plate, used to show compatible chargers and to start charging automatically when your plate is recognised at a station.</li>
<li><b>Location:</b> your approximate or precise location, only while the app is in use, to show nearby charging stations and distances. Location is not tracked in the background.</li>
<li><b>Camera:</b> used only to scan the QR code on a charger. Images are not stored or uploaded.</li>
<li><b>Charging and payment history:</b> sessions, energy, cost, wallet top-ups and receipts, needed to bill you and show your history.</li>
<li><b>Device token:</b> used to send charging status and account notifications.</li>
</ul>
<h2>How we use data</h2>
<p>Data is used only to provide the charging service: finding stations, starting and stopping sessions, billing, support and service notifications. We do not sell personal data and we do not use it for third-party advertising or cross-app tracking.</p>
<h2>Sharing</h2>
<p>We share data only with the charging-station operator where you charge (to run your session) and with payment providers that process wallet top-ups, or when required by law.</p>
<h2>Retention and deletion</h2>
<p>You can delete your account at any time in the app under <b>Account → Delete account</b>. Your personal details, plates and RFID cards are removed or deactivated immediately; anonymised billing records are kept only as required for accounting.</p>
<h2>Contact</h2>
<p>Questions about privacy: call {{ $phone }} or use in-app support chat.</p>
</div>
<div class="card fa">
<h1>سیاست حفظ حریم خصوصی</h1>
<p>QUAD EV فقط اطلاعات لازم برای ارائهٔ خدمات شارژ را جمع‌آوری می‌کند: نام و شماره موبایل برای ورود، مشخصات و پلاک خودرو برای شارژ خودکار، موقعیت مکانی فقط هنگام استفاده برای نمایش ایستگاه‌های نزدیک، دوربین فقط برای اسکن QR شارژر، و سوابق شارژ و پرداخت برای صورتحساب.</p>
<p>اطلاعات شما فروخته نمی‌شود و برای تبلیغات شخص ثالث استفاده نمی‌شود. فقط با اپراتور ایستگاهی که در آن شارژ می‌کنید و درگاه پرداخت به اشتراک گذاشته می‌شود.</p>
<p>از مسیر «حساب کاربری ← حذف حساب کاربری» در اپ می‌توانید هر زمان حساب خود را حذف کنید. پشتیبانی: {{ $phone }}</p>
</div>
@else
<div class="card">
<h1>Support</h1>
<p>Need help with charging, payments or your account? We are here to help.</p>
<h2>Contact us</h2>
<ul>
<li>Phone: <a href="tel:{{ $phone }}">{{ $phone }}</a></li>
<li>In-app: open <b>Account → Support &amp; contact</b> for live chat, the AI assistant and support tickets.</li>
</ul>
<h2>Common questions</h2>
<p><b>How do I start charging?</b> Find a station on the map or scan the QR code on the charger, choose a connector and tap Start. You can also use an RFID card or automatic plate recognition.</p>
<p><b>How do I top up my wallet?</b> Open the Wallet tab and tap Top up.</p>
<p><b>How do I delete my account?</b> Account → Delete account. See our <a href="{{ route('privacy.public') }}">Privacy Policy</a>.</p>
</div>
<div class="card fa">
<h1>پشتیبانی</h1>
<p>برای راهنمایی در شارژ، پرداخت یا حساب کاربری با شماره <a href="tel:{{ $phone }}">{{ $phone }}</a> تماس بگیرید یا از بخش «حساب کاربری ← پشتیبانی و تماس با ما» در اپ با پشتیبانی گفتگو کنید.</p>
</div>
@endif
</div>
</body>
</html>
