<!DOCTYPE html>

<html lang="id"><head><meta charset="utf-8"/><meta content="width=device-width, initial-scale=1.0" name="viewport"/><meta content="web_standard" name="shell-type"/><link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@600;700&amp;family=Inter:wght@400;600;700&amp;display=swap" rel="stylesheet"/><link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" rel="stylesheet"/>
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap" rel="stylesheet"/><style>@layer base{html,body{margin:0;padding:0;}body{overscroll-behavior:none;}main>:first-child{margin-top:0!important;}main>:last-child{margin-bottom:0!important;}}::-webkit-scrollbar{display:none;}</style><script src="https://cdn.tailwindcss.com"></script><script id="tailwind-config">tailwind.config={darkMode:"class",theme:{extend:{colors:{"surface-container-lowest":"#ffffff","surface-container-highest":"#d3e4fe","secondary-fixed":"#85f8c4","surface-container-low":"#eff4ff","tertiary-container":"#0042b2","secondary-container":"#82f5c1","surface-dim":"#cbdbf5","surface-bright":"#f8f9ff","surface-variant":"#d3e4fe","surface-container-high":"#dce9ff","primary-fixed-dim":"#b8c4ff","on-surface":"#0b1c30","primary-container":"#1e40af","on-tertiary-container":"#a4b9ff","on-secondary":"#ffffff","on-surface-variant":"#444653","on-primary-fixed-variant":"#173bab","on-tertiary-fixed-variant":"#003ea8","surface":"#f8f9ff","background":"#f8f9ff","on-tertiary-fixed":"#00174b","on-secondary-fixed":"#002114","on-primary-fixed":"#001453","tertiary-fixed":"#dbe1ff","primary":"#00288e","error-container":"#ffdad6","tertiary":"#002e81","surface-container":"#e5eeff","on-error-container":"#93000a","outline":"#757684","on-primary":"#ffffff","on-secondary-fixed-variant":"#005137","surface-tint":"#3755c3","error":"#ba1a1a","on-background":"#0b1c30","on-primary-container":"#a8b8ff","tertiary-fixed-dim":"#b4c5ff","secondary-fixed-dim":"#68dba9","on-secondary-container":"#00714e","on-tertiary":"#ffffff","inverse-surface":"#213145","inverse-primary":"#b8c4ff","secondary":"#006c4a","primary-fixed":"#dde1ff","outline-variant":"#c4c5d5","inverse-on-surface":"#eaf1ff","on-error":"#ffffff"},borderRadius:{DEFAULT:"0.25rem",lg:"0.5rem",xl:"0.75rem",full:"9999px"},spacing:{"space-xl":"2rem","gutter-lg":"2rem",gutter:"1.5rem","margin-sm":"1rem","space-md":"1rem","gutter-sm":"1rem","space-xs":"0.25rem","space-lg":"1.5rem",margin:"2rem","margin-lg":"3rem","space-sm":"0.5rem"},fontFamily:{"headline-xl":["Plus Jakarta Sans"],"headline-xl-mobile":["Plus Jakarta Sans"],"label-md":["Inter"],"label-sm":["Inter"],"headline-md":["Plus Jakarta Sans"],"body-lg":["Inter"],"body-sm":["Inter"],"headline-sm":["Plus Jakarta Sans"],"body-md":["Inter"],"label-lg":["Inter"],"headline-lg":["Plus Jakarta Sans"],"display-lg":["Plus Jakarta Sans"],"display-lg-mobile":["Plus Jakarta Sans"]},fontSize:{"headline-xl":["32px",{lineHeight:"40px",letterSpacing:"-0.02em",fontWeight:"700"}],"headline-xl-mobile":["24px",{lineHeight:"32px",letterSpacing:"-0.015em",fontWeight:"600"}],"label-md":["12px",{lineHeight:"16px",letterSpacing:"0.02em",fontWeight:"600"}],"label-sm":["10px",{lineHeight:"14px",letterSpacing:"0.04em",fontWeight:"700"}],"headline-md":["20px",{lineHeight:"28px",letterSpacing:"-0.01em",fontWeight:"600"}],"body-lg":["16px",{lineHeight:"26px",letterSpacing:"-0.005em",fontWeight:"400"}],"body-sm":["12px",{lineHeight:"18px",letterSpacing:"0.005em",fontWeight:"400"}],"headline-sm":["16px",{lineHeight:"24px",letterSpacing:"-0.005em",fontWeight:"600"}],"body-md":["14px",{lineHeight:"22px",letterSpacing:"0em",fontWeight:"400"}],"label-lg":["14px",{lineHeight:"20px",letterSpacing:"0.01em",fontWeight:"600"}],"headline-lg":["24px",{lineHeight:"32px",letterSpacing:"-0.015em",fontWeight:"600"}],"display-lg":["40px",{lineHeight:"48px",letterSpacing:"-0.025em",fontWeight:"700"}],"display-lg-mobile":["30px",{lineHeight:"38px",letterSpacing:"-0.02em",fontWeight:"700"}]}}}};</script></head><body class="bg-background font-body-md text-body-md text-on-surface min-h-screen flex flex-col justify-between"><header class="fixed top-0 w-full z-50 bg-surface/80 backdrop-blur-xl shadow-[0_1px_8px_rgba(0,0,0,0.04)]"><div class="h-16 max-w-[1440px] mx-auto px-margin flex items-center justify-between"><div class="flex items-center gap-space-md"><img alt="Logo Sistem Komunikasi Internal Kampus" class="h-8 w-auto object-contain" src="{{ asset('images/logo-skik.svg') }}"/><div class="flex flex-col"><span class="font-headline-sm text-headline-sm text-primary tracking-tight">SKIK Portal</span><span class="font-label-sm text-label-sm text-on-surface-variant uppercase tracking-wider">Layanan Komunikasi Akademik</span></div></div><nav class="hidden md:flex items-center gap-space-lg" data-active-classes="bg-primary-container text-on-primary font-label-lg rounded-lg"><a aria-current="page" class="transition-colors bg-primary-container text-on-primary font-label-lg rounded-lg" data-path="portal-autentikasi" href="#">Masuk Portal</a><a class="text-on-surface-variant hover:text-on-surface transition-colors font-label-lg text-label-lg" data-path="panduan-sistem" href="#">Panduan SSO</a><a class="text-on-surface-variant hover:text-on-surface transition-colors font-label-lg text-label-lg" data-path="bantuan-layanan" href="#">Pusat Bantuan</a><a class="text-on-surface-variant hover:text-on-surface transition-colors font-label-lg text-label-lg" data-path="status-server" href="#">Status Jaringan</a></nav><div class="flex items-center gap-space-md"><div class="hidden sm:flex items-center px-space-sm py-space-xs bg-surface-container rounded-full"><span class="w-2 h-2 rounded-full bg-secondary mr-space-xs"></span><span class="font-label-sm text-label-sm text-on-surface-variant">SSO Aktif</span></div><div class="w-8 h-8 rounded-full bg-primary flex items-center justify-center"><span class="material-symbols-outlined text-on-primary text-[18px]">person</span></div></div></div></header><main class="w-full pt-16 flex-1 bg-surface"><div class="flex flex-col w-full">
<div class="relative w-full py-space-xl px-margin-sm md:px-margin flex items-center justify-center overflow-hidden">
<!-- Ambient Atmospheric Gradients (Strictly bounded, no border) -->
<div class="absolute -top-32 -left-20 w-96 h-96 rounded-full bg-primary/5 blur-3xl pointer-events-none"></div>
<div class="absolute top-1/2 -right-32 w-80 h-80 rounded-full bg-secondary-container/40 blur-3xl pointer-events-none"></div>
<div class="absolute -bottom-24 left-1/3 w-72 h-72 rounded-full bg-surface-container-highest/60 blur-3xl pointer-events-none"></div>
<div class="relative w-full max-w-lg flex flex-col items-center">
<!-- Top Decorative Micro-Ticker -->
<div class="flex items-center gap-space-xs px-space-md py-space-xs rounded-full bg-surface-container mb-space-md shadow-sm">
<span class="relative flex h-2 w-2">
<span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-secondary opacity-75"></span>
<span class="relative inline-flex rounded-full h-2 w-2 bg-secondary"></span>
</span>
<span class="font-label-sm text-label-sm uppercase tracking-wider text-on-surface-variant font-bold">Koneksi Terenkripsi TLS 1.3 • SSO Kampus Aktif</span>
</div>
<!-- Main Login Card (Level 1 Elevation, Crisp White Canvas) -->
<div class="w-full bg-surface-container-lowest rounded-2xl shadow-xl shadow-on-surface/5 p-space-md sm:p-space-xl flex flex-col">
<!-- Header Emblem & Institutional Identity -->
<div class="flex flex-col items-center text-center">
<div class="relative flex items-center justify-center w-20 h-20 rounded-2xl bg-surface-container-low mb-space-md shadow-sm">
<svg class="w-14 h-14" fill="none" viewbox="0 0 100 100" xmlns="http://www.w3.org/2000/svg">
<path class="fill-primary-container" d="M50 15L15 35L50 55L85 35L50 15Z"></path>
<path class="fill-primary" d="M30 45V65C30 75 40 82 50 82C60 82 70 75 70 65V45L50 57L30 45Z"></path>
<path class="text-surface-container-lowest" d="M50 53L20 36.5V58" stroke="currentColor" stroke-linecap="round" stroke-width="3"></path>
<circle class="fill-secondary" cx="72" cy="42" r="18"></circle>
<path d="M64 47C68 44 76 44 80 47" stroke="white" stroke-linecap="round" stroke-width="2.5"></path>
<path d="M66 42C69 39.5 75 39.5 78 42" stroke="white" stroke-linecap="round" stroke-width="2.5"></path>
<path d="M68 37C70.5 35 73.5 35 76 37" stroke="white" stroke-linecap="round" stroke-width="2.5"></path>
</svg>
<div class="absolute -bottom-1 -right-1 w-6 h-6 rounded-full bg-secondary-fixed flex items-center justify-center shadow-sm">
<span class="material-symbols-outlined text-secondary text-[14px]" style="font-variation-settings: 'FILL' 1;">verified_user</span>
</div>
</div>
<h1 class="font-headline-lg text-headline-lg text-on-surface tracking-tight">Sistem Komunikasi Internal Kampus</h1>
<p class="font-body-md text-body-md text-on-surface-variant mt-space-xs max-w-sm">
            Portal Layanan &amp; Komunikasi Terpadu Civitas Academica
          </p>
<!-- Campus Indicator Dots -->
<div class="flex items-center gap-1.5 mt-space-sm">
<span class="w-2 h-2 rounded-full bg-primary-fixed-dim"></span>
<span class="w-2.5 h-2.5 rounded-full bg-secondary"></span>
<span class="w-2 h-2 rounded-full bg-primary-fixed-dim"></span>
</div>
</div>
<!-- Authentication Form -->
<form action="{{ route('login.submit') }}" class="mt-space-lg flex flex-col gap-space-md" id="loginForm" method="POST">
@csrf
<!-- Username / Identifier Input -->
<div class="flex flex-col gap-space-xs">
<label class="font-label-lg text-label-lg text-on-surface flex items-center justify-between" for="identityInput">
<span>NIM / NIDN / Kode Unit</span>
<span class="font-label-sm text-label-sm text-on-surface-variant font-normal">Identitas Resmi</span>
</label>
<div class="relative flex items-center">
<span class="material-symbols-outlined absolute left-3.5 text-on-surface-variant text-[20px] pointer-events-none">badge</span>
<input autocomplete="username" class="w-full pl-11 pr-4 py-2.5 bg-surface-container-lowest text-on-surface font-body-md text-body-md rounded-lg shadow-sm placeholder:text-outline-variant outline-none focus:bg-surface-bright focus:shadow-[0_0_0_3px_rgba(30,64,175,0.18)] transition-all" id="identityInput" name="username" placeholder="Contoh: 2421400177 atau 0711028801" required="" type="text" value="{{ old('username') }}"/>
</div>
</div>
<!-- Password Input -->
<div class="flex flex-col gap-space-xs">
<div class="flex items-center justify-between">
<label class="font-label-lg text-label-lg text-on-surface" for="passwordInput">Kata Sandi (Password)</label>
<a class="font-label-sm text-label-sm text-primary hover:text-primary-container font-semibold transition-colors" href="#">Lupa Password?</a>
</div>
<div class="relative flex items-center">
<span class="material-symbols-outlined absolute left-3.5 text-on-surface-variant text-[20px] pointer-events-none">lock</span>
<input autocomplete="current-password" class="w-full pl-11 pr-11 py-2.5 bg-surface-container-lowest text-on-surface font-body-md text-body-md rounded-lg shadow-sm placeholder:text-outline-variant outline-none focus:bg-surface-bright focus:shadow-[0_0_0_3px_rgba(30,64,175,0.18)] transition-all" id="passwordInput" name="password" placeholder="Masukkan kata sandi akun" required="" type="password"/>
<button aria-label="Tampilkan atau sembunyikan kata sandi" class="absolute right-3.5 text-on-surface-variant hover:text-on-surface p-1 rounded transition-colors" id="togglePasswordBtn" onclick="togglePasswordVisibility()" type="button">
<span class="material-symbols-outlined text-[20px]" id="passwordEyeIcon">visibility</span>
</button>
</div>
</div>
<!-- Remember Me & Policy -->
<div class="flex items-center justify-between mt-space-xs">
<label class="flex items-center gap-2 cursor-pointer select-none">
<input class="w-4 h-4 rounded text-primary-container focus:ring-0 focus:outline-none accent-primary-container cursor-pointer" id="rememberMe" name="remember" type="checkbox"/>
<span class="font-body-sm text-body-sm text-on-surface-variant">Ingat sesi di perangkat ini</span>
</label>
<span class="font-label-sm text-label-sm text-on-surface-variant hidden sm:inline-block">Radius Kampus</span>
</div>
<!-- Primary Submit Action -->
<button class="mt-space-xs w-full py-3 px-space-md rounded-lg bg-primary-container hover:bg-tertiary-container active:bg-primary text-on-primary font-headline-sm text-headline-sm tracking-wide shadow-md shadow-primary-container/25 flex items-center justify-center gap-space-sm transition-all duration-200 group" id="submitBtn" type="submit">
<span>Masuk ke Portal</span>
<span class="material-symbols-outlined text-[20px] transition-transform duration-200 group-hover:translate-x-1">arrow_forward</span>
</button>
</form>
<!-- Dynamic Feedback Message Container -->
<div class="hidden mt-space-md p-space-sm rounded-lg text-body-sm font-body-sm flex items-center gap-space-sm" id="authAlert">
<span class="material-symbols-outlined text-[20px]" id="authAlertIcon"></span>
<span id="authAlertText"></span>
</div>
<!-- Demo Accounts Quick-Fill Section -->
<div class="mt-space-lg pt-space-md bg-surface-container-low rounded-xl p-space-md shadow-sm">
<div class="flex items-center justify-between mb-space-sm">
<div class="flex items-center gap-space-xs">
<span class="material-symbols-outlined text-primary text-[20px]" style="font-variation-settings: 'FILL' 1;">dataset</span>
<span class="font-headline-sm text-headline-sm text-on-surface">Akun Uji Coba (Demo Access)</span>
</div>
<span class="font-label-sm text-label-sm px-2 py-0.5 rounded-full bg-secondary-fixed-dim text-on-secondary-container uppercase tracking-wider font-bold">Instan Fill</span>
</div>
<p class="font-body-sm text-body-sm text-on-surface-variant mb-space-md">
            Pilih salah satu peran di bawah ini untuk mengisi kredensial verifikasi secara otomatis:
          </p>
<div class="grid grid-cols-1 sm:grid-cols-3 gap-2">
<!-- Quick Fill: Mahasiswa -->
<button class="flex flex-col items-start p-2.5 rounded-lg bg-surface-container-lowest hover:bg-surface-bright transition-all text-left shadow-sm group" onclick="fillDemo('2421400177', 'password', 'Mahasiswa')" type="button">
<div class="flex items-center justify-between w-full">
<span class="font-label-sm text-label-sm px-1.5 py-0.5 rounded bg-surface-container text-primary font-bold">Mahasiswa</span>
<span class="material-symbols-outlined text-outline-variant group-hover:text-primary text-[16px] transition-colors">touch_app</span>
</div>
<span class="font-label-md text-label-md text-on-surface mt-1.5 font-bold">2421400177</span>
<span class="font-label-sm text-label-sm text-on-surface-variant font-mono">pass: password</span>
</button>
<!-- Quick Fill: Dosen -->
<button class="flex flex-col items-start p-2.5 rounded-lg bg-surface-container-lowest hover:bg-surface-bright transition-all text-left shadow-sm group" onclick="fillDemo('0711028801', 'password', 'Dosen')" type="button">
<div class="flex items-center justify-between w-full">
<span class="font-label-sm text-label-sm px-1.5 py-0.5 rounded bg-secondary-fixed text-on-secondary-fixed-variant font-bold">Dosen</span>
<span class="material-symbols-outlined text-outline-variant group-hover:text-secondary text-[16px] transition-colors">touch_app</span>
</div>
<span class="font-label-md text-label-md text-on-surface mt-1.5 font-bold">0711028801</span>
<span class="font-label-sm text-label-sm text-on-surface-variant font-mono">pass: password</span>
</button>
<!-- Quick Fill: Admin -->
<button class="flex flex-col items-start p-2.5 rounded-lg bg-surface-container-lowest hover:bg-surface-bright transition-all text-left shadow-sm group" onclick="fillDemo('admin', 'password', 'Admin')" type="button">
<div class="flex items-center justify-between w-full">
<span class="font-label-sm text-label-sm px-1.5 py-0.5 rounded bg-surface-container-high text-on-surface font-bold">Admin</span>
<span class="material-symbols-outlined text-outline-variant group-hover:text-on-surface text-[16px] transition-colors">touch_app</span>
</div>
<span class="font-label-md text-label-md text-on-surface mt-1.5 font-bold">admin</span>
<span class="font-label-sm text-label-sm text-on-surface-variant font-mono">pass: password</span>
</button>
</div>
</div>
<!-- Academic Network Trust Footer Inside Card -->
<div class="mt-space-md pt-space-sm flex items-center justify-between font-label-sm text-label-sm text-on-surface-variant">
<div class="flex items-center gap-1">
<span class="material-symbols-outlined text-[15px] text-secondary">security</span>
<span>Autentikasi Terintegrasi SAML 2.0</span>
</div>
<span class="font-mono text-outline">v2.4 LTS</span>
</div>
</div>
<!-- External Quick Links & Help Desk -->
<div class="mt-space-lg flex flex-wrap items-center justify-center gap-x-space-lg gap-y-space-xs text-center font-body-sm text-body-sm text-on-surface-variant">
<a class="hover:text-primary transition-colors flex items-center gap-1" href="#">
<span class="material-symbols-outlined text-[16px]">contact_support</span>
<span>Bantuan Helpdesk IT (Ext. 1104)</span>
</a>
<span class="text-outline-variant">•</span>
<a class="hover:text-primary transition-colors flex items-center gap-1" href="#">
<span class="material-symbols-outlined text-[16px]">policy</span>
<span>Panduan Penggunaan Sandi</span>
</a>
<span class="text-outline-variant">•</span>
<a class="hover:text-primary transition-colors flex items-center gap-1" href="#">
<span class="material-symbols-outlined text-[16px]">wifi_protected_setup</span>
<span>Aktivasi Akun Baru</span>
</a>
</div>
</div>
</div>
</div>
<script>
  function togglePasswordVisibility() {
    const passwordInput = document.getElementById('passwordInput');
    const eyeIcon = document.getElementById('passwordEyeIcon');
    if (passwordInput.type === 'password') {
      passwordInput.type = 'text';
      eyeIcon.textContent = 'visibility_off';
    } else {
      passwordInput.type = 'password';
      eyeIcon.textContent = 'visibility';
    }
  }

  function fillDemo(username, password, roleLabel) {
    const userInput = document.getElementById('identityInput');
    const passInput = document.getElementById('passwordInput');
    const alertBox = document.getElementById('authAlert');
    const alertText = document.getElementById('authAlertText');
    const alertIcon = document.getElementById('authAlertIcon');

    userInput.value = username;
    passInput.value = password;

    alertBox.className = 'mt-space-md p-space-sm rounded-lg text-body-sm font-body-sm flex items-center gap-space-sm bg-surface-container text-primary transition-all';
    alertIcon.textContent = 'check_circle';
    alertText.textContent = 'Kredensial ' + roleLabel + ' (' + username + ') berhasil dimuat!';
    alertBox.classList.remove('hidden');

    userInput.focus();
    setTimeout(() => {
      alertBox.classList.add('hidden');
    }, 4000);
  }

  function handleAuthSubmit() {
    const userInput = document.getElementById('identityInput');
    const passInput = document.getElementById('passwordInput');
    const alertBox = document.getElementById('authAlert');
    const alertText = document.getElementById('authAlertText');
    const alertIcon = document.getElementById('authAlertIcon');
    const submitBtn = document.getElementById('submitBtn');

    if (!userInput.value || !passInput.value) {
      alertBox.className = 'mt-space-md p-space-sm rounded-lg text-body-sm font-body-sm flex items-center gap-space-sm bg-error-container text-on-error-container';
      alertIcon.textContent = 'error';
      alertText.textContent = 'Harap isi semua kolom identitas dan kata sandi.';
      alertBox.classList.remove('hidden');
      return;
    }

    const originalText = submitBtn.innerHTML;
    submitBtn.innerHTML = '<span class="material-symbols-outlined animate-spin text-[20px]">sync</span> Memverifikasi...';
    submitBtn.disabled = true;

    setTimeout(() => {
      submitBtn.innerHTML = originalText;
      submitBtn.disabled = false;
      alertBox.className = 'mt-space-md p-space-sm rounded-lg text-body-sm font-body-sm flex items-center gap-space-sm bg-secondary-container text-on-secondary-container';
      alertIcon.textContent = 'verified';
      alertText.textContent = 'Otentikasi berhasil! Mengalihkan ke dashboard SKIK...';
      alertBox.classList.remove('hidden');
    }, 900);
  }
@if ($errors->any())
  document.addEventListener('DOMContentLoaded', () => {
    const alertBox = document.getElementById('authAlert');
    const alertText = document.getElementById('authAlertText');
    const alertIcon = document.getElementById('authAlertIcon');
    alertBox.className = 'mt-space-md p-space-sm rounded-lg text-body-sm font-body-sm flex items-center gap-space-sm bg-error-container text-on-error-container';
    alertIcon.textContent = 'error';
    alertText.textContent = @json($errors->first());
    alertBox.classList.remove('hidden');
  });
@endif
</script></main><footer class="w-full bg-surface-container-low py-space-xl"><div class="max-w-[1440px] mx-auto px-margin flex flex-col md:flex-row items-center justify-between gap-space-md"><div class="flex flex-col items-center md:items-start gap-space-xs"><span class="font-headline-sm text-headline-sm text-on-surface">Sistem Komunikasi Internal Kampus</span><span class="font-body-sm text-body-sm text-on-surface-variant">© 2024 Direktorat Sistem Informasi &amp; Komunikasi Kampus. Hak Cipta Dilindungi.</span></div><div class="flex items-center gap-space-lg"><a class="font-label-md text-label-md text-on-surface-variant hover:text-primary transition-colors" href="#">Kebijakan Keamanan SSO</a><a class="font-label-md text-label-md text-on-surface-variant hover:text-primary transition-colors" href="#">Tata Tertib Digital</a><a class="font-label-md text-label-md text-on-surface-variant hover:text-primary transition-colors" href="#">Kontak Helpdesk</a></div></div></footer></body></html>