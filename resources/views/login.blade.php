<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Masuk — Sistem Komunikasi Internal Kampus</title>

    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,400,0,0" rel="stylesheet" />

    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body {
            font-family: 'Inter', ui-sans-serif, system-ui, sans-serif;
        }
    </style>
</head>
<body class="flex min-h-screen flex-col items-center justify-center bg-slate-50 px-4 py-8 text-slate-800 antialiased selection:bg-blue-100 selection:text-blue-900">
    <main class="w-full max-w-md">
        <!-- Kartu Utama Login -->
        <div class="rounded-2xl border border-slate-200/80 bg-white p-6 shadow-sm sm:p-8">
            <!-- Identitas Aplikasi -->
            <div class="flex flex-col items-center text-center">
                <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-blue-600 text-white shadow-sm">
                    <span class="material-symbols-outlined text-[26px]">forum</span>
                </div>
                <h1 class="mt-4 text-xl font-semibold tracking-tight text-slate-900">
                    Sistem Komunikasi Internal Kampus
                </h1>
                <p class="mt-1 text-sm text-slate-500">
                    Masuk menggunakan identitas resmi sivitas akademika
                </p>
            </div>

            <!-- Pesan Galat Autentikasi -->
            @if ($errors->any())
                <div class="mt-5 flex items-start gap-2 rounded-xl border border-red-200 bg-red-50 px-3.5 py-2.5 text-xs text-red-700" role="alert">
                    <span class="material-symbols-outlined mt-0.5 text-[16px] flex-shrink-0">error</span>
                    <span>{{ $errors->first() }}</span>
                </div>
            @endif

            <!-- Form Autentikasi -->
            <form id="login-form" action="{{ route('login.submit') }}" method="POST" class="mt-6 space-y-4">
                @csrf

                <div>
                    <label for="identity-input" class="mb-1.5 block text-xs font-medium text-slate-700">
                        NIM / NIDN / Kode Unit
                    </label>
                    <div class="relative">
                        <span class="material-symbols-outlined pointer-events-none absolute top-1/2 left-3.5 -translate-y-1/2 text-[19px] text-slate-400">badge</span>
                        <input
                            id="identity-input"
                            type="text"
                            name="username"
                            value="{{ old('username') }}"
                            required
                            autofocus
                            autocomplete="username"
                            placeholder="Contoh: 2421400177 atau 0711028801"
                            class="w-full rounded-xl border border-slate-200 bg-slate-50 py-2.5 pr-3.5 pl-10 text-sm text-slate-800 placeholder:text-slate-400 outline-none transition focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-500/15"
                        />
                    </div>
                </div>

                <div>
                    <label for="password-input" class="mb-1.5 block text-xs font-medium text-slate-700">
                        Kata Sandi
                    </label>
                    <div class="relative">
                        <span class="material-symbols-outlined pointer-events-none absolute top-1/2 left-3.5 -translate-y-1/2 text-[19px] text-slate-400">lock</span>
                        <input
                            id="password-input"
                            type="password"
                            name="password"
                            required
                            autocomplete="current-password"
                            placeholder="Masukkan kata sandi akun"
                            class="w-full rounded-xl border border-slate-200 bg-slate-50 py-2.5 pr-10 pl-10 text-sm text-slate-800 placeholder:text-slate-400 outline-none transition focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-500/15"
                        />
                        <button
                            id="toggle-password-btn"
                            type="button"
                            aria-label="Tampilkan atau sembunyikan kata sandi"
                            class="absolute top-1/2 right-2.5 flex h-7 w-7 -translate-y-1/2 items-center justify-center rounded-lg text-slate-400 transition-colors hover:bg-slate-100 hover:text-slate-600"
                        >
                            <span id="password-eye-icon" class="material-symbols-outlined text-[19px]">visibility</span>
                        </button>
                    </div>
                </div>

                <div class="flex items-center justify-between pt-0.5">
                    <label class="inline-flex cursor-pointer items-center gap-2 select-none">
                        <input
                            type="checkbox"
                            name="remember"
                            class="h-4 w-4 cursor-pointer rounded border-slate-300 text-blue-600 accent-blue-600 focus:ring-0"
                        />
                        <span class="text-xs text-slate-600">Ingat sesi saya</span>
                    </label>

                    <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-2.5 py-0.5 text-[11px] font-medium text-emerald-700">
                        <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                        <span>SSO Aktif</span>
                    </span>
                </div>

                <button
                    id="login-submit-btn"
                    type="submit"
                    class="flex w-full items-center justify-center gap-2 rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-medium text-white shadow-sm transition hover:bg-blue-700 active:scale-[0.99] disabled:cursor-not-allowed disabled:opacity-60"
                >
                    <span>Masuk ke Portal</span>
                    <span class="material-symbols-outlined text-[18px]">arrow_forward</span>
                </button>
            </form>

            <!-- Akun Uji Coba Cepat -->
            <div class="mt-6 border-t border-slate-100 pt-5">
                <div class="mb-2.5 flex items-center justify-between">
                    <span class="text-xs font-medium text-slate-500">Akun Uji Coba (Klik untuk isi cepat)</span>
                    <span id="demo-fill-badge" class="hidden rounded-full bg-blue-50 px-2 py-0.5 text-[11px] font-medium text-blue-700"></span>
                </div>

                <div class="grid grid-cols-2 gap-2">
                    <button
                        type="button"
                        data-demo-user="2421400177"
                        data-demo-role="Mahasiswa"
                        class="flex items-center justify-between rounded-xl border border-slate-200/80 bg-slate-50/70 px-3 py-2 text-left transition hover:border-blue-200 hover:bg-blue-50/60"
                    >
                        <div class="min-w-0">
                            <div class="text-xs font-medium text-slate-800">Mahasiswa</div>
                            <div class="truncate font-mono text-[11px] text-slate-400">2421400177</div>
                        </div>
                        <span class="h-2 w-2 flex-shrink-0 rounded-full bg-emerald-500"></span>
                    </button>

                    <button
                        type="button"
                        data-demo-user="0711028801"
                        data-demo-role="Dosen"
                        class="flex items-center justify-between rounded-xl border border-slate-200/80 bg-slate-50/70 px-3 py-2 text-left transition hover:border-blue-200 hover:bg-blue-50/60"
                    >
                        <div class="min-w-0">
                            <div class="text-xs font-medium text-slate-800">Dosen</div>
                            <div class="truncate font-mono text-[11px] text-slate-400">0711028801</div>
                        </div>
                        <span class="h-2 w-2 flex-shrink-0 rounded-full bg-blue-500"></span>
                    </button>

                    <button
                        type="button"
                        data-demo-user="unit_baak"
                        data-demo-role="Unit Layanan"
                        class="flex items-center justify-between rounded-xl border border-slate-200/80 bg-slate-50/70 px-3 py-2 text-left transition hover:border-blue-200 hover:bg-blue-50/60"
                    >
                        <div class="min-w-0">
                            <div class="text-xs font-medium text-slate-800">Unit BAAK</div>
                            <div class="truncate font-mono text-[11px] text-slate-400">unit_baak</div>
                        </div>
                        <span class="h-2 w-2 flex-shrink-0 rounded-full bg-amber-500"></span>
                    </button>

                    <button
                        type="button"
                        data-demo-user="admin"
                        data-demo-role="Admin"
                        class="flex items-center justify-between rounded-xl border border-slate-200/80 bg-slate-50/70 px-3 py-2 text-left transition hover:border-blue-200 hover:bg-blue-50/60"
                    >
                        <div class="min-w-0">
                            <div class="text-xs font-medium text-slate-800">Admin</div>
                            <div class="truncate font-mono text-[11px] text-slate-400">admin</div>
                        </div>
                        <span class="h-2 w-2 flex-shrink-0 rounded-full bg-indigo-500"></span>
                    </button>
                </div>
            </div>
        </div>

        <!-- Catatan Kaki Minimalis -->
        <p class="mt-5 flex items-center justify-center gap-1.5 text-center text-xs text-slate-400">
            <span class="material-symbols-outlined text-[14px] text-emerald-600">lock</span>
            <span>Jaringan Internal Sivitas Akademika • SKIK</span>
        </p>
    </main>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const form = document.getElementById('login-form');
            const submitBtn = document.getElementById('login-submit-btn');
            const identityInput = document.getElementById('identity-input');
            const passwordInput = document.getElementById('password-input');
            const togglePasswordBtn = document.getElementById('toggle-password-btn');
            const passwordEyeIcon = document.getElementById('password-eye-icon');
            const demoFillBadge = document.getElementById('demo-fill-badge');

            togglePasswordBtn?.addEventListener('click', () => {
                const isPassword = passwordInput.type === 'password';
                passwordInput.type = isPassword ? 'text' : 'password';
                passwordEyeIcon.textContent = isPassword ? 'visibility_off' : 'visibility';
            });

            document.querySelectorAll('[data-demo-user]').forEach((btn) => {
                btn.addEventListener('click', () => {
                    identityInput.value = btn.dataset.demoUser || '';
                    passwordInput.value = 'password';
                    identityInput.focus();

                    if (demoFillBadge) {
                        demoFillBadge.textContent = `${btn.dataset.demoRole} terisi`;
                        demoFillBadge.classList.remove('hidden');
                    }
                });
            });

            form?.addEventListener('submit', () => {
                if (submitBtn) {
                    submitBtn.disabled = true;
                }
            });
        });
    </script>
</body>
</html>
